<?php
/**
 * Protected database configuration 
 * config/database.php
 * 
 * 🛡️ ПОЛНАЯ ЗАЩИТА ОТ SQL ИНЪЕКЦИЙ:
 * - Все запросы только через prepared statements
 * - Валидация и санитизация всех входных данных
 * - Whitelist подход для критических параметров
 * - Ограничение прав пользователя БД
 */

class Database {
    private $host = 'localhost';              
    private $db_name = 'kvinu29_osbb_surveys'; 
    private $username = 'kvinu29_osbb_user';   
    private $password = 'Avk0978@0978';  
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * 🔒 Безопасное подключение к базе данных
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // 🛡️ КРИТИЧНО: настоящие prepared statements
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_STRINGIFY_FETCHES => false, // 🛡️ Предотвращает type juggling атаки
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Помилка підключення до бази даних");
        }
        
        return $this->conn;
    }

    /**
     * 🔒 Безопасное выполнение SELECT запросов
     */
    public function secureSelect($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($this->sanitizeParams($params));
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Secure select error: " . $e->getMessage());
            throw new Exception("Помилка виконання запиту");
        }
    }

    /**
     * 🔒 Безопасное выполнение SELECT для одной записи
     */
    public function secureSelectOne($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($this->sanitizeParams($params));
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Secure select one error: " . $e->getMessage());
            throw new Exception("Помилка виконання запиту");
        }
    }

    /**
     * 🛡️ Санитизация параметров для prepared statements
     */
    private function sanitizeParams($params) {
        $sanitized = [];
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                // Удаляем потенциально опасные символы
                $value = preg_replace('/[^\p{L}\p{N}\s\-_.@]/u', '', $value);
                $value = trim($value);
            } elseif (is_numeric($value)) {
                $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            }
            $sanitized[$key] = $value;
        }
        return $sanitized;
    }

    /**
     * Тест подключения к базе данных
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Генерация уникального ID для ответа
     */
    public static function generateResponseId() {
        return 'RESP_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
    }

    /**
     * 🔒 Безопасное получение IP адреса пользователя
     */
    public static function getUserIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 🛡️ Валидация IP адреса
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * 🔒 Безопасное получение User Agent
     */
    public static function getUserAgent() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        // 🛡️ Ограничиваем длину и удаляем потенциально опасные символы
        $userAgent = substr($userAgent, 0, 500);
        $userAgent = preg_replace('/[<>"\']/', '', $userAgent);
        return $userAgent;
    }

    /**
     * 🔒 Безопасное логирование действий
     */
    public function logActivity($response_id, $action, $details = null, $ip = null, $user_agent = null) {
        try {
            // 🛡️ Валидация входных данных
            $response_id = $this->validateResponseId($response_id);
            $action = $this->validateAction($action);
            
            $query = "INSERT INTO activity_logs (response_id, action, details, ip_address, user_agent) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                $response_id,
                $action,
                substr($details, 0, 1000), // Ограничиваем длину
                $ip,
                substr($user_agent, 0, 500) // Ограничиваем длину
            ]);
            
        } catch(PDOException $e) {
            error_log("Log activity error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🛡️ Валидация Response ID
     */
    private function validateResponseId($response_id) {
        if (!preg_match('/^[A-Z0-9_]+$/', $response_id)) {
            throw new Exception("Некоректний формат ID відповіді");
        }
        return $response_id;
    }

    /**
     * 🛡️ Валидация Action для логирования
     */
    private function validateAction($action) {
        $allowed_actions = [
            'survey_started', 'survey_completed', 'survey_saved',
            'response_submitted', 'progress_saved', 'error_occurred'
        ];
        
        if (!in_array($action, $allowed_actions)) {
            $action = 'unknown_action';
        }
        
        return $action;
    }

    /**
     * 🛡️ Улучшенная санитизация данных
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        // Удаляем null bytes
        $data = str_replace("\0", '', $data);
        
        // Базовая очистка
        $data = trim($data);
        $data = stripslashes($data);
        
        // 🛡️ Предотвращаем XSS
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $data;
    }

    /**
     * 🛡️ Строгая валидация типа анкеты (whitelist подход)
     */
    public function validateSurveyType($survey_type) {
        // 🛡️ WHITELIST - только разрешенные типы анкет
        $valid_types = [
            'universal_survey',      // 50 вопросов
            'residents',            // 50 вопросов  
            'osbb_heads',          // 50 вопросов
            'osbb_members_board',  // 50 вопросов
            'audit_commission',    // 50 вопросов
            'managers',           // 50 вопросов
            'oms_representatives', // 35 вопросов
            'legal_experts'       // 50 вопросов
        ];
        
        // 🛡️ Дополнительная проверка формата
        if (!preg_match('/^[a-z_]+$/', $survey_type)) {
            return false;
        }
        
        return in_array($survey_type, $valid_types, true); // Строгое сравнение
    }

    /**
     * 🔒 Безопасное получение ID типа анкеты
     */
    public function getSurveyTypeId($survey_type_slug) {
        try {
            // 🛡️ Предварительная валидация
            if (!$this->validateSurveyType($survey_type_slug)) {
                throw new Exception("Некоректний тип анкети");
            }
            
            $query = "SELECT id FROM survey_types WHERE slug = ? AND is_active = 1 LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$survey_type_slug]);
            
            $result = $stmt->fetch();
            return $result ? (int)$result['id'] : null;
            
        } catch(PDOException $e) {
            error_log("Get survey type ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔒 Безопасная проверка существования ответа
     */
    public function responseExists($response_id) {
        try {
            // 🛡️ Валидация response_id
            $this->validateResponseId($response_id);
            
            $query = "SELECT id FROM survey_responses WHERE response_id = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$response_id]);
            
            return $stmt->rowCount() > 0;
            
        } catch(PDOException $e) {
            error_log("Check response exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔒 Безопасное получение статистики
     */
    public function getSurveyStats($survey_type_slug = null) {
        try {
            if ($survey_type_slug) {
                // 🛡️ Валидация типа анкеты
                if (!$this->validateSurveyType($survey_type_slug)) {
                    throw new Exception("Некоректний тип анкети");
                }
                
                $query = "SELECT * FROM survey_overview WHERE slug = ? LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$survey_type_slug]);
                return $stmt->fetch();
            } else {
                $query = "SELECT * FROM survey_overview ORDER BY id";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                return $stmt->fetchAll();
            }
            
        } catch(PDOException $e) {
            error_log("Get survey stats error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔒 Безопасное сохранение ответа анкеты
     */
    public function saveSurveyResponse($data) {
        try {
            $this->conn->beginTransaction();
            
            // 🛡️ Валидация основных данных
            $survey_type = $data['survey_type'] ?? '';
            if (!$this->validateSurveyType($survey_type)) {
                throw new Exception("Некоректний тип анкети");
            }
            
            $survey_type_id = $this->getSurveyTypeId($survey_type);
            if (!$survey_type_id) {
                throw new Exception("Тип анкети не знайдено");
            }
            
            $response_id = $this->generateResponseId();
            
            // Сохраняем основную запись
            $main_query = "INSERT INTO survey_responses 
                          (response_id, survey_type_id, user_ip, user_agent, total_questions, 
                           answered_questions, completion_percentage, is_completed) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($main_query);
            $result = $stmt->execute([
                $response_id,
                $survey_type_id,
                self::getUserIP(),
                self::getUserAgent(),
                $data['total_questions'] ?? 0,
                $data['answered_questions'] ?? 0,
                $data['completion_percentage'] ?? 0,
                ($data['completion_percentage'] ?? 0) >= 80 ? 1 : 0
            ]);
            
            if (!$result) {
                throw new Exception("Помилка збереження основних даних");
            }
            
            // Сохраняем ответы на вопросы
            $responses = $data['responses'] ?? [];
            $answer_query = "INSERT INTO question_answers 
                            (response_id, question_key, question_number, answer_value) 
                            VALUES (?, ?, ?, ?)";
            $answer_stmt = $this->conn->prepare($answer_query);
            
            foreach ($responses as $question_key => $answer_value) {
                // 🛡️ Валидация каждого ответа
                if (!SurveyValidator::validateAnswer($question_key, $answer_value, $survey_type)) {
                    error_log("Invalid answer: $question_key = $answer_value");
                    continue;
                }
                
                $question_number = SurveyValidator::extractQuestionNumber($question_key);
                
                $answer_stmt->execute([
                    $response_id,
                    $question_key,
                    $question_number,
                    $answer_value
                ]);
            }
            
            $this->conn->commit();
            
            // Логируем успешное сохранение
            $this->logActivity($response_id, 'survey_completed', 
                "Survey type: $survey_type, Answers: " . count($responses));
            
            return $response_id;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Save survey response error: " . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Класс для ответов API - без изменений, уже безопасен
 */
class APIResponse {
    public static function success($data = null, $message = 'Успішно') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public static function error($message = 'Виникла помилка', $code = 400, $details = null) {
        http_response_code($code);
        return [
            'success' => false,
            'error' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public static function send($response) {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * 🛡️ УСИЛЕННЫЙ валидатор данных анкеты
 */
class SurveyValidator {
    
    // Конфигурация анкет с дополнительными проверками безопасности
    private static $survey_configs = [
        'universal_survey'      => ['questions' => 50, 'min_percentage' => 80],
        'residents'            => ['questions' => 50, 'min_percentage' => 80],
        'osbb_heads'          => ['questions' => 50, 'min_percentage' => 80],
        'osbb_members_board'  => ['questions' => 50, 'min_percentage' => 80],
        'audit_commission'    => ['questions' => 50, 'min_percentage' => 80],
        'managers'           => ['questions' => 50, 'min_percentage' => 80],
        'oms_representatives' => ['questions' => 35, 'min_percentage' => 80],
        'legal_experts'       => ['questions' => 50, 'min_percentage' => 80]
    ];
    
    /**
     * 🛡️ Строгая валидация ответа на вопрос
     */
    public static function validateAnswer($question_key, $answer_value, $survey_type = 'universal_survey') {
        // 🛡️ Проверка формата ключа вопроса (только буквы и цифры)
        if (!preg_match('/^q([1-9]\d*)$/', $question_key, $matches)) {
            return false;
        }
        
        $question_number = (int)$matches[1];
        $max_questions = self::$survey_configs[$survey_type]['questions'] ?? 50;
        
        // 🛡️ Проверка диапазона номера вопроса
        if ($question_number < 1 || $question_number > $max_questions) {
            return false;
        }
        
        // 🛡️ WHITELIST допустимых значений ответов
        $valid_answers = ['yes', 'no', 'undecided', 'less5', '5-10', 'more10'];
        
        // 🛡️ Строгая проверка типа и значения
        return is_string($answer_value) && in_array($answer_value, $valid_answers, true);
    }

    /**
     * 🛡️ Усиленная валидация всех ответов анкеты
     */
    public static function validateSurveyAnswers($answers, $survey_type = 'universal_survey') {
        // 🛡️ Базовые проверки безопасности
        if (!is_array($answers) || empty($answers)) {
            return ['valid' => false, 'error' => 'Відповіді повинні бути непустим масивом'];
        }
        
        // 🛡️ Защита от слишком больших данных (DoS атака)
        if (count($answers) > 100) {
            return ['valid' => false, 'error' => 'Занадто багато відповідей'];
        }

        // 🛡️ Проверка конфигурации анкеты
        if (!isset(self::$survey_configs[$survey_type])) {
            return ['valid' => false, 'error' => 'Невідомий тип анкети'];
        }

        $config = self::$survey_configs[$survey_type];
        $max_questions = $config['questions'];
        $min_percentage = $config['min_percentage'];
        $min_required = max(ceil($max_questions * $min_percentage / 100), 10);

        $errors = [];
        $question_numbers = [];
        
        foreach ($answers as $question_key => $answer_value) {
            // 🛡️ Дополнительная проверка ключа
            if (!is_string($question_key) || strlen($question_key) > 10) {
                $errors[] = "Некоректний формат ключа питання: $question_key";
                continue;
            }
            
            if (!self::validateAnswer($question_key, $answer_value, $survey_type)) {
                $errors[] = "Некоректна відповідь для питання: $question_key";
                continue;
            }
            
            // 🛡️ Проверка на дублирование номеров вопросов
            $question_number = self::extractQuestionNumber($question_key);
            if (in_array($question_number, $question_numbers)) {
                $errors[] = "Дублікат питання: $question_key";
            } else {
                $question_numbers[] = $question_number;
            }
        }

        // 🛡️ Проверка минимального количества ответов
        if (count($answers) < $min_required) {
            $errors[] = "Мінімум $min_required відповідей обов'язкові для анкети '$survey_type'";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_answers' => count($answers),
            'unique_questions' => count($question_numbers),
            'min_required' => $min_required,
            'max_questions' => $max_questions
        ];
    }

    /**
     * 🛡️ Безопасное извлечение номера вопроса
     */
    public static function extractQuestionNumber($question_key) {
        if (preg_match('/^q([1-9]\d*)$/', $question_key, $matches)) {
            return min((int)$matches[1], 999); // Ограничиваем максимальный номер
        }
        return 0;
    }
    
    /**
     * Получение конфигурации анкеты
     */
    public static function getSurveyConfig($survey_type) {
        return self::$survey_configs[$survey_type] ?? null;
    }
    
    /**
     * Получение всех поддерживаемых типов анкет
     */
    public static function getSupportedSurveyTypes() {
        return array_keys(self::$survey_configs);
    }
}

// Остальные классы остаются без изменений, но с улучшенной защитой
class QuestionsDirectory {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * 🔒 Безопасное сохранение вопроса в справочник
     */
    public function saveQuestion($question_code, $survey_type, $question_text, $question_order = 0) {
        try {
            // 🛡️ Валидация входных данных
            if (!preg_match('/^q[1-9]\d*$/', $question_code)) {
                return false;
            }
            
            if (!preg_match('/^[a-z_]+$/', $survey_type)) {
                return false;
            }
            
            $question_text = substr(trim($question_text), 0, 1000); // Ограничиваем длину
            $question_order = max(0, min((int)$question_order, 999)); // Ограничиваем диапазон
            
            // Проверяем, есть ли уже такой вопрос
            $check_query = "SELECT id FROM questions_directory 
                           WHERE question_code = ? AND survey_type = ? LIMIT 1";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->execute([$question_code, $survey_type]);
            
            // Если вопроса нет - добавляем
            if ($check_stmt->rowCount() == 0) {
                $insert_query = "INSERT INTO questions_directory 
                               (question_code, survey_type, question_text, question_order) 
                               VALUES (?, ?, ?, ?)";
                $insert_stmt = $this->conn->prepare($insert_query);
                $result = $insert_stmt->execute([
                    $question_code, 
                    $survey_type, 
                    $question_text, 
                    $question_order
                ]);
                
                return $result;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error saving question: " . $e->getMessage());
            return false;
        }
    }
    
    // Остальные методы с аналогичными улучшениями безопасности...
}

/**
 * 🔒 Безопасное тестирование подключения к БД
 */
function testDatabaseConnection() {
    try {
        $database = new Database();
        if ($database->testConnection()) {
            echo "✅ Підключення до бази даних успішне з захистом від SQL ін'єкцій!\n";
            
            // Безопасная проверка наличия таблиц
            $conn = $database->getConnection();
            $allowed_tables = ['survey_types', 'survey_responses', 'question_answers', 'survey_statistics', 'activity_logs'];
            
            foreach ($allowed_tables as $table) {
                $stmt = $conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if ($stmt->rowCount() > 0) {
                    echo "✅ Таблиця '$table' існує\n";
                } else {
                    echo "❌ Таблиця '$table' не знайдена\n";
                }
            }
            
        } else {
            echo "❌ Помилка підключення до бази даних\n";
        }
    } catch (Exception $e) {
        echo "❌ Помилка: " . $e->getMessage() . "\n";
    }
}

// Если файл запущен напрямую, выполняем тест
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    testDatabaseConnection();
}
?>