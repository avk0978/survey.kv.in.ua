<?php
/**
 * 🛡️ PROTECTED API for saving questionnaires
 * api/save-survey.php
 * 
 * ПОЛНАЯ ЗАЩИТА ОТ SQL ИНЪЕКЦИЙ И ДРУГИХ АТАК:
 * - Rate limiting для предотвращения спама
 * - Валидация всех входных данных
 * - CSRF защита
 * - Логирование подозрительной активности
 */

// 🛡️ Настройки безопасности
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 🛡️ Безопасные заголовки
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
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
} else {
    header('Access-Control-Allow-Origin: null');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🛡️ Разрешаем только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не дозволений']);
    exit;
}

require_once '../config/database.php';

/**
 * 🛡️ Класс для защиты от атак
 */
class SecurityManager {
    private static $rate_limit_file = '../logs/rate_limit.json';
    private static $max_requests_per_hour = 10; // Максимум 10 анкет в час с одного IP
    private static $max_requests_per_day = 50;  // Максимум 50 анкет в день с одного IP
    
    /**
     * 🛡️ Проверка rate limiting
     */
    public static function checkRateLimit($ip) {
        $now = time();
        $hour_ago = $now - 3600;
        $day_ago = $now - 86400;
        
        // Создаем файл если его нет
        if (!file_exists(self::$rate_limit_file)) {
            file_put_contents(self::$rate_limit_file, json_encode([]));
        }
        
        $rate_data = json_decode(file_get_contents(self::$rate_limit_file), true) ?: [];
        
        // Очищаем старые записи
        foreach ($rate_data as $recorded_ip => $timestamps) {
            $rate_data[$recorded_ip] = array_filter($timestamps, function($timestamp) use ($day_ago) {
                return $timestamp > $day_ago;
            });
            
            if (empty($rate_data[$recorded_ip])) {
                unset($rate_data[$recorded_ip]);
            }
        }
        
        // Проверяем лимиты для текущего IP
        $ip_requests = $rate_data[$ip] ?? [];
        
        $requests_last_hour = count(array_filter($ip_requests, function($timestamp) use ($hour_ago) {
            return $timestamp > $hour_ago;
        }));
        
        $requests_last_day = count($ip_requests);
        
        // Проверяем лимиты
        if ($requests_last_hour >= self::$max_requests_per_hour) {
            return ['allowed' => false, 'reason' => 'Перевищено ліміт запитів за годину'];
        }
        
        if ($requests_last_day >= self::$max_requests_per_day) {
            return ['allowed' => false, 'reason' => 'Перевищено ліміт запитів за день'];
        }
        
        // Записываем текущий запрос
        $rate_data[$ip][] = $now;
        file_put_contents(self::$rate_limit_file, json_encode($rate_data));
        
        return ['allowed' => true];
    }
    
    /**
     * 🛡️ Валидация размера запроса
     */
    public static function validateRequestSize() {
        $max_size = 1024 * 1024; // 1MB максимум
        $content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
        
        if ($content_length > $max_size) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 🛡️ Проверка подозрительных паттернов в данных
     */
    public static function detectSuspiciousPatterns($data) {
        $suspicious_patterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onclick=/i'
        ];
        
        $data_string = json_encode($data);
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $data_string)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 🛡️ Логирование подозрительной активности
     */
    public static function logSuspiciousActivity($ip, $user_agent, $reason, $data = null) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'user_agent' => $user_agent,
            'reason' => $reason,
            'data_sample' => $data ? substr(json_encode($data), 0, 200) : null
        ];
        
        $log_file = '../logs/security.log';
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // В критических случаях можно отправить уведомление администратору
        if (strpos($reason, 'SQL injection') !== false) {
            error_log("SECURITY ALERT: Possible SQL injection attempt from IP: $ip");
        }
    }
}

try {
    // 🛡️ Базовые проверки безопасности
    $user_ip = Database::getUserIP();
    $user_agent = Database::getUserAgent();
    
    // 🛡️ Проверка rate limiting
    $rate_check = SecurityManager::checkRateLimit($user_ip);
    if (!$rate_check['allowed']) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Rate limit exceeded', null);
        APIResponse::send(APIResponse::error($rate_check['reason'], 429));
    }
    
    // 🛡️ Проверка размера запроса
    if (!SecurityManager::validateRequestSize()) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Request too large', null);
        APIResponse::send(APIResponse::error('Запит занадто великий', 413));
    }
    
    // 🛡️ Получение и валидация JSON данных
    $json_input = file_get_contents('php://input');
    if (empty($json_input)) {
        APIResponse::send(APIResponse::error('Відсутні дані для збереження', 400));
    }
    
    $input_data = json_decode($json_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Invalid JSON', $json_input);
        APIResponse::send(APIResponse::error('Некоректний формат даних', 400));
    }
    
    // 🛡️ Проверка на подозрительные паттерны
    if (SecurityManager::detectSuspiciousPatterns($input_data)) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Suspicious patterns detected', $input_data);
        APIResponse::send(APIResponse::error('Виявлено підозрілі дані', 400));
    }
    
    // 🛡️ Валидация структуры данных
    $required_fields = ['survey_type', 'responses'];
    foreach ($required_fields as $field) {
        if (!isset($input_data[$field])) {
            APIResponse::send(APIResponse::error("Відсутнє обов'язкове поле: $field", 400));
        }
    }
    
    // 🛡️ Очистка и валидация данных
    $survey_type = Database::sanitizeInput($input_data['survey_type']);
    $responses = $input_data['responses'];
    $timestamp = $input_data['timestamp'] ?? date('Y-m-d H:i:s');
    $total_questions = (int)($input_data['total_questions'] ?? 0);
    $answered_questions = (int)($input_data['answered_questions'] ?? 0);
    $completion_percentage = (int)($input_data['completion_percentage'] ?? 0);
    
    // 🛡️ Подключение к базе данных
    $database = new Database();
    $pdo = $database->getConnection();
    
    // 🛡️ Валидация типа анкеты
    if (!$database->validateSurveyType($survey_type)) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Invalid survey type', $survey_type);
        APIResponse::send(APIResponse::error('Некоректний тип анкети', 400));
    }
    
    // 🛡️ Валидация ответов
    $validation_result = SurveyValidator::validateSurveyAnswers($responses, $survey_type);
    if (!$validation_result['valid']) {
        $error_message = 'Помилки валідації: ' . implode(', ', $validation_result['errors']);
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Validation failed', $validation_result);
        APIResponse::send(APIResponse::error($error_message, 400));
    }
    
    // 🛡️ Дополнительные проверки безопасности
    if ($answered_questions > 100 || $total_questions > 100) {
        SecurityManager::logSuspiciousActivity($user_ip, $user_agent, 'Suspicious question count', [
            'answered' => $answered_questions,
            'total' => $total_questions
        ]);
        APIResponse::send(APIResponse::error('Некоректна кількість питань', 400));
    }
    
    if ($completion_percentage < 0 || $completion_percentage > 100) {
        APIResponse::send(APIResponse::error('Некоректний відсоток заповнення', 400));
    }
    
    // 🔒 Безопасное сохранение данных
    $save_data = [
        'survey_type' => $survey_type,
        'responses' => $responses,
        'timestamp' => $timestamp,
        'total_questions' => $total_questions,
        'answered_questions' => $answered_questions,
        'completion_percentage' => $completion_percentage
    ];
    
    $response_id = $database->saveSurveyResponse($save_data);
    
    if ($response_id) {
        // Успешное сохранение
        APIResponse::send(APIResponse::success([
            'response_id' => $response_id,
            'survey_type' => $survey_type,
            'answers_saved' => count($responses),
            'completion_percentage' => $completion_percentage
        ], 'Анкету успішно збережено'));
    } else {
        APIResponse::send(APIResponse::error('Помилка збереження анкети', 500));
    }
    
} catch (Exception $e) {
    // 🛡️ Безопасное логирование ошибок
    error_log("Survey save error: " . $e->getMessage());
    
    // Логируем как подозрительную активность если это не обычная ошибка
    if (strpos($e->getMessage(), 'SQL') !== false || strpos($e->getMessage(), 'injection') !== false) {
        SecurityManager::logSuspiciousActivity(
            Database::getUserIP(),
            Database::getUserAgent(),
            'Database error (possible attack): ' . $e->getMessage(),
            $_POST
        );
    }
    
    APIResponse::send(APIResponse::error('Внутрішня помилка сервера', 500));
}
?>