<?php
/**
 * 🛡️ Protected API for public statistics
 * api/public-stats.php
 * 
 * ЗАЩИТА ОТ SQL ИНЪЕКЦИЙ И ДРУГИХ АТАК:
 * - Whitelist подход для всех параметров
 * - Кеширование для защиты от DoS атак
 * - Валидация всех входных данных
 * - Rate limiting для API вызовов
 */

// 🛡️ Настройки безопасности
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 🛡️ Безопасные заголовки
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// 🛡️ CORS с ограничениями
$allowed_origins = [
    'http://survey.kv.in.ua',
    'https://survey.kv.in.ua',
    'http://localhost'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 3600');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🛡️ Разрешаем только GET запросы
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не дозволений']);
    exit;
}

require_once '../config/database.php';

/**
 * 🛡️ Менеджер кеша для защиты от частых запросов
 */
class CacheManager {
    private static $cache_dir = '../cache/';
    private static $cache_ttl = 60; // 🔧 УМЕНЬШЕНО: 1 минута вместо 5 минут
    
    /**
     * 🛡️ Получение данных из кеша
     */
    public static function get($key) {
        $cache_file = self::$cache_dir . md5($key) . '.json';
        
        if (!file_exists($cache_file)) {
            return null;
        }
        
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        if (!$cache_data || !isset($cache_data['expires']) || time() > $cache_data['expires']) {
            @unlink($cache_file);
            return null;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * 🛡️ Сохранение данных в кеш
     */
    public static function set($key, $data) {
        if (!is_dir(self::$cache_dir)) {
            @mkdir(self::$cache_dir, 0755, true);
        }
        
        $cache_file = self::$cache_dir . md5($key) . '.json';
        $cache_data = [
            'data' => $data,
            'expires' => time() + self::$cache_ttl,
            'created' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($cache_file, json_encode($cache_data), LOCK_EX);
    }
    
    /**
     * 🛡️ Очистка старого кеша
     */
    public static function cleanup() {
        if (!is_dir(self::$cache_dir)) {
            return;
        }
        
        $files = glob(self::$cache_dir . '*.json');
        $now = time();
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - self::$cache_ttl * 2)) {
                @unlink($file);
            }
        }
    }
}

/**
 * 🛡️ Защищенный класс для статистики
 */
class SecureStatsAPI {
    private $database;
    private $pdo;
    
    // 🛡️ WHITELIST разрешенных действий
    private $allowed_actions = [
        'overview',
        'survey_types',
        'detailed'
    ];
    
    // 🛡️ WHITELIST разрешенных типов анкет
    private $allowed_survey_types = [
        'universal_survey',
        'residents',
        'osbb_heads',
        'osbb_members_board',
        'audit_commission',
        'managers',
        'oms_representatives',
        'legal_experts'
    ];
    
    public function __construct() {
        $this->database = new Database();
        $this->pdo = $this->database->getConnection();
    }
    
    /**
     * 🛡️ Валидация действия
     */
    private function validateAction($action) {
        return in_array($action, $this->allowed_actions, true);
    }
    
    /**
     * 🛡️ Валидация типа анкеты
     */
    private function validateSurveyType($survey_type) {
        return in_array($survey_type, $this->allowed_survey_types, true);
    }
    
    /**
     * 🔒 Безопасное получение общей статистики
     */
    public function getOverview() {
        $cache_key = 'stats_overview';
        $cached = CacheManager::get($cache_key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            // 🛡️ Безопасные запросы с prepared statements
            
            // Общая статистика - ТОЧНО как в админ-панели
            // Считаем только ответы, связанные с существующими анкетами
            $overview_query = "
                SELECT 
                    COUNT(qa.id) as total_responses,
                    COUNT(DISTINCT qa.response_id) as total_surveys,
                    COUNT(DISTINCT CASE WHEN sr.is_completed = 1 THEN sr.id END) as completed_responses,
                    ROUND(AVG(sr.completion_percentage), 2) as avg_completion,
                    MIN(sr.created_at) as first_response,
                    MAX(sr.created_at) as last_response,
                    COUNT(DISTINCT DATE(sr.created_at)) as active_days
                FROM question_answers qa
                INNER JOIN survey_responses sr ON qa.response_id = sr.response_id
                WHERE sr.response_id IS NOT NULL
            ";
            
            $stmt = $this->pdo->prepare($overview_query);
            $stmt->execute();
            $overview = $stmt->fetch();
            
            // Активность по дням - точно как в админ-панели
            $daily_query = "
                SELECT 
                    DATE(sr.created_at) as response_date,
                    COUNT(qa.id) as daily_count
                FROM question_answers qa
                INNER JOIN survey_responses sr ON qa.response_id = sr.response_id
                WHERE sr.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  AND sr.response_id IS NOT NULL
                GROUP BY DATE(sr.created_at)
                ORDER BY response_date DESC
                LIMIT 14
            ";
            
            $stmt = $this->pdo->prepare($daily_query);
            $stmt->execute();
            $daily_activity = $stmt->fetchAll();
            
            $result = [
                'overview' => $overview,
                'daily_activity' => array_reverse($daily_activity), // От старых к новым
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            CacheManager::set($cache_key, $result);
            return $result;
            
        } catch (PDOException $e) {
            error_log("Stats overview error: " . $e->getMessage());
            throw new Exception("Помилка отримання загальної статистики");
        }
    }
    
    /**
     * 🔒 Безопасное получение статистики по типам анкет
     */
    public function getSurveyTypes() {
        $cache_key = 'stats_survey_types';
        $cached = CacheManager::get($cache_key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $query = "
                SELECT 
                    st.id,
                    st.name,
                    st.slug,
                    st.total_questions,
                    COUNT(sr.id) as total_responses,
                    COUNT(CASE WHEN sr.is_completed = 1 THEN 1 END) as completed_responses,
                    ROUND(AVG(sr.completion_percentage), 2) as avg_completion,
                    MAX(sr.created_at) as last_response,
                    MIN(sr.created_at) as first_response
                FROM survey_types st
                LEFT JOIN survey_responses sr ON st.id = sr.survey_type_id
                WHERE st.is_active = 1
                GROUP BY st.id, st.name, st.slug, st.total_questions
                ORDER BY total_responses DESC, st.name
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll();
            
            CacheManager::set($cache_key, $result);
            return $result;
            
        } catch (PDOException $e) {
            error_log("Stats survey types error: " . $e->getMessage());
            throw new Exception("Помилка отримання статистики по типах анкет");
        }
    }
    
    /**
     * 🔒 Безопасное получение детальной статистики
     */
    public function getDetailed($survey_type) {
        // 🛡️ Валидация типа анкеты
        if (!$this->validateSurveyType($survey_type)) {
            throw new Exception("Некоректний тип анкети");
        }
        
        $cache_key = "stats_detailed_{$survey_type}";
        $cached = CacheManager::get($cache_key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            // Получаем ID типа анкеты
            $type_query = "SELECT id, name FROM survey_types WHERE slug = ? AND is_active = 1 LIMIT 1";
            $stmt = $this->pdo->prepare($type_query);
            $stmt->execute([$survey_type]);
            $survey_info = $stmt->fetch();
            
            if (!$survey_info) {
                throw new Exception("Тип анкети не знайдено");
            }
            
            // Детальная статистика по вопросам
            $detailed_query = "
                SELECT 
                    qa.question_key,
                    qa.answer_value,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (
                        SELECT COUNT(DISTINCT qa2.response_id) 
                        FROM question_answers qa2 
                        JOIN survey_responses sr2 ON qa2.response_id = sr2.response_id 
                        WHERE sr2.survey_type_id = ?
                    )), 2) as percentage
                FROM question_answers qa
                JOIN survey_responses sr ON qa.response_id = sr.response_id
                WHERE sr.survey_type_id = ?
                GROUP BY qa.question_key, qa.answer_value
                ORDER BY qa.question_number, qa.answer_value
            ";
            
            $stmt = $this->pdo->prepare($detailed_query);
            $stmt->execute([$survey_info['id'], $survey_info['id']]);
            $detailed_stats = $stmt->fetchAll();
            
            $result = [
                'survey_info' => $survey_info,
                'detailed_stats' => $detailed_stats,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            CacheManager::set($cache_key, $result);
            return $result;
            
        } catch (PDOException $e) {
            error_log("Stats detailed error: " . $e->getMessage());
            throw new Exception("Помилка отримання детальної статистики");
        }
    }
}

// 🛡️ Основная логика обработки запроса
try {
    // 🛡️ Получение и валидация параметров
    $action = $_GET['action'] ?? '';
    $survey_type = $_GET['survey_type'] ?? null;
    $force_refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1'; // 🔧 НОВЫЙ: принудительное обновление
    
    // 🛡️ Очистка параметров
    $action = preg_replace('/[^a-z_]/', '', strtolower($action));
    if ($survey_type) {
        $survey_type = preg_replace('/[^a-z_]/', '', strtolower($survey_type));
    }
    
    // 🔧 НОВЫЙ: Принудительная очистка кеша при необходимости
    if ($force_refresh) {
        $cache_keys = ['stats_overview', 'stats_survey_types'];
        foreach ($cache_keys as $key) {
            $cache_file = '../cache/' . md5($key) . '.json';
            if (file_exists($cache_file)) {
                @unlink($cache_file);
            }
        }
    }
    
    // 🛡️ Проверка на слишком частые запросы (простая защита)
    $user_ip = Database::getUserIP();
    $rate_limit_file = '../cache/rate_limit_stats.json';
    
    if (file_exists($rate_limit_file)) {
        $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?: [];
        $now = time();
        
        // Очищаем старые записи (старше 1 минуты)
        $rate_data = array_filter($rate_data, function($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });
        
        // Проверяем количество запросов за последнюю минуту
        $recent_requests = array_filter($rate_data, function($entry) use ($user_ip, $now) {
            return isset($entry['ip']) && $entry['ip'] === $user_ip && ($now - $entry['time']) < 60;
        });
        
        if (count($recent_requests) > 30) { // Максимум 30 запросов в минуту
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Занадто багато запитів']);
            exit;
        }
        
        // Добавляем текущий запрос
        $rate_data[] = ['ip' => $user_ip, 'time' => $now];
        file_put_contents($rate_limit_file, json_encode($rate_data));
    }
    
    // Очистка старого кеша
    CacheManager::cleanup();
    
    // Создание API объекта
    $stats_api = new SecureStatsAPI();
    
    // 🛡️ Обработка запроса по действию
    switch ($action) {
        case 'overview':
            $data = $stats_api->getOverview();
            APIResponse::send(APIResponse::success($data, 'Загальна статистика отримана'));
            break;
            
        case 'survey_types':
            $data = $stats_api->getSurveyTypes();
            APIResponse::send(APIResponse::success($data, 'Статистика по типах анкет отримана'));
            break;
            
        case 'detailed':
            if (!$survey_type) {
                APIResponse::send(APIResponse::error('Не вказано тип анкети', 400));
            }
            
            $data = $stats_api->getDetailed($survey_type);
            APIResponse::send(APIResponse::success($data, 'Детальна статистика отримана'));
            break;
            
        default:
            APIResponse::send(APIResponse::error('Некоректна дія. Доступні: overview, survey_types, detailed', 400));
    }
    
} catch (Exception $e) {
    error_log("Public stats API error: " . $e->getMessage());
    APIResponse::send(APIResponse::error($e->getMessage(), 500));
}
?>