<?php
/**
 * Class for managing administrator authentication
 * admin/auth/auth_manager.php
 */

class AuthManager {
    private $passwordFile;
    private $sessionTimeout;
    private $maxLoginAttempts;
    private $lockoutTime;
    
    public function __construct() {
        $this->passwordFile = __DIR__ . '/admin_password.txt';
        $this->sessionTimeout = 3600; // 1 час
        $this->maxLoginAttempts = 5;
        $this->lockoutTime = 900; // 15 минут
        $this->initPasswordFile();
    }
    
    private function initPasswordFile() {
        if (!file_exists($this->passwordFile)) {
            $defaultPassword = "Avk09780978"; 
            $hashedPassword = password_hash($defaultPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $data = [
                'password_hash' => $hashedPassword,
                'created_at' => date('Y-m-d H:i:s'),
                'last_changed' => date('Y-m-d H:i:s'),
                'version' => '1.0'
            ];
            
            file_put_contents($this->passwordFile, json_encode($data, JSON_PRETTY_PRINT));
            chmod($this->passwordFile, 0600); 
        }
    }
    
    private function getPasswordHash() {
        if (!file_exists($this->passwordFile)) {
            throw new Exception('Файл паролю не знайдено');
        }
        
        $data = json_decode(file_get_contents($this->passwordFile), true);
        if (!$data || !isset($data['password_hash'])) {
            throw new Exception('Невірний формат файлу паролю');
        }
        
        return $data['password_hash'];
    }
    
    private function isIpBlocked($ip) {
        $lockFile = __DIR__ . '/login_attempts.json';
        if (!file_exists($lockFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($lockFile), true);
        if (!$data || !isset($data[$ip])) {
            return false;
        }
        
        $attempts = $data[$ip];
        
        if ($attempts['count'] >= $this->maxLoginAttempts && 
            (time() - $attempts['last_attempt']) < $this->lockoutTime) {
            return true;
        }
        
        return false;
    }
    
    private function logFailedAttempt($ip) {
        $lockFile = __DIR__ . '/login_attempts.json';
        $data = [];
        
        if (file_exists($lockFile)) {
            $data = json_decode(file_get_contents($lockFile), true) ?: [];
        }
        
        if (!isset($data[$ip])) {
            $data[$ip] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data[$ip]['count']++;
        $data[$ip]['last_attempt'] = time();
        
        foreach ($data as $recorded_ip => $info) {
            if ((time() - $info['first_attempt']) > 86400) {
                unset($data[$recorded_ip]);
            }
        }
        
        file_put_contents($lockFile, json_encode($data, JSON_PRETTY_PRINT));
        
        $this->logSecurityEvent("Неудачная попытка входа с IP: $ip. Попытка " . $data[$ip]['count'] . "/" . $this->maxLoginAttempts);
    }
    
    private function clearFailedAttempts($ip) {
        $lockFile = __DIR__ . '/login_attempts.json';
        if (!file_exists($lockFile)) {
            return;
        }
        
        $data = json_decode(file_get_contents($lockFile), true);
        if ($data && isset($data[$ip])) {
            unset($data[$ip]);
            file_put_contents($lockFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
    
    private function logSecurityEvent($message) {
        $logFile = dirname(__DIR__) . '/logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logMessage = "[{$timestamp}] IP: {$ip} | {$message} | User-Agent: {$userAgent}" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function login($password, $ip) {
        // Проверить, заблокирован ли IP
        if ($this->isIpBlocked($ip)) {
            $this->logSecurityEvent("Попытка входа с заблокированного IP: $ip");
            return [
                'success' => false,
                'message' => 'IP адресу заблоковано через перевищення кількості спроб входу. Спробуйте через 15 хвилин.'
            ];
        }
        
        try {
            $storedHash = $this->getPasswordHash();
            
            if (password_verify($password, $storedHash)) {
                // Успешный вход
                $this->clearFailedAttempts($ip);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['user_ip'] = $ip;
                $_SESSION['session_id'] = bin2hex(random_bytes(32));
                
                $this->logSecurityEvent("Успешный вход администратора с IP: $ip");
                
                return [
                    'success' => true,
                    'message' => 'Успішний вхід'
                ];
            } else {
                // Неверный пароль
                $this->logFailedAttempt($ip);
                $this->logSecurityEvent("Неверный пароль для IP: $ip");
                
                return [
                    'success' => false,
                    'message' => 'Невірний пароль'
                ];
            }
        } catch (Exception $e) {
            $this->logSecurityEvent("Ошибка при попытке входа: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Помилка автентифікації'
            ];
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            return false;
        }
        
        if (isset($_SESSION['login_time']) && 
            (time() - $_SESSION['login_time']) > $this->sessionTimeout) {
            $this->logout();
            return false;
        }
        
        if (isset($_SESSION['user_ip']) && 
            $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            $this->logSecurityEvent("Попытка использования сессии с другого IP");
            $this->logout();
            return false;
        }
        
        $_SESSION['login_time'] = time();
        
        return true;
    }
    
    public function logout() {
        if (isset($_SESSION['user_ip'])) {
            $this->logSecurityEvent("Выход из системы IP: " . $_SESSION['user_ip']);
        }
        
        session_destroy();
        session_start();
    }
    
    public function changePassword($currentPassword, $newPassword) {
        try {
            $storedHash = $this->getPasswordHash();
            
            if (!password_verify($currentPassword, $storedHash)) {
                return [
                    'success' => false,
                    'message' => 'Невірний поточний пароль'
                ];
            }
            
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Новий пароль повинен містити не менше 8 символів'
                ];
            }
            
            $newHash = password_hash($newPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $data = [
                'password_hash' => $newHash,
                'created_at' => json_decode(file_get_contents($this->passwordFile), true)['created_at'] ?? date('Y-m-d H:i:s'),
                'last_changed' => date('Y-m-d H:i:s'),
                'version' => '1.0'
            ];
            
            file_put_contents($this->passwordFile, json_encode($data, JSON_PRETTY_PRINT));
            chmod($this->passwordFile, 0600);
            
            $this->logSecurityEvent("Пароль администратора изменен");
            
            return [
                'success' => true,
                'message' => 'Пароль успішно змінено'
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent("Ошибка при смене пароля: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Помилка при зміні паролю'
            ];
        }
    }
    
    public function getSecurityInfo() {
        $info = [
            'session_timeout' => $this->sessionTimeout,
            'max_login_attempts' => $this->maxLoginAttempts,
            'lockout_time' => $this->lockoutTime,
            'password_file_exists' => file_exists($this->passwordFile),
            'current_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'session_active' => $this->isLoggedIn()
        ];
        
        if (file_exists($this->passwordFile)) {
            $data = json_decode(file_get_contents($this->passwordFile), true);
            $info['password_last_changed'] = $data['last_changed'] ?? 'unknown';
        }
        
        return $info;
    }
    
    public function clearAllBlocks() {
        $lockFile = __DIR__ . '/login_attempts.json';
        if (file_exists($lockFile)) {
            unlink($lockFile);
            $this->logSecurityEvent("Все блокировки IP очищены администратором");
            return true;
        }
        return false;
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Генератор хеша пароля для админ-панели ОСББ\n";
    echo "=====================================\n";
    
    if ($argc < 2) {
        echo "Использование: php auth_manager.php 'ваш_новый_пароль'\n";
        exit(1);
    }
    
    $password = $argv[1];
    
    if (strlen($password) < 8) {
        echo "Ошибка: Пароль должен содержать не менее 8 символов\n";
        exit(1);
    }
    
    $hash = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
    
    $data = [
        'password_hash' => $hash,
        'created_at' => date('Y-m-d H:i:s'),
        'last_changed' => date('Y-m-d H:i:s'),
        'version' => '1.0'
    ];
    
    $passwordFile = __DIR__ . '/admin_password.txt';
    file_put_contents($passwordFile, json_encode($data, JSON_PRETTY_PRINT));
    chmod($passwordFile, 0600);
    
    echo "Хеш пароля создан и сохранен в файл: $passwordFile\n";
    echo "Пароль: " . str_repeat('*', strlen($password)) . "\n";
    echo "Длина: " . strlen($password) . " символов\n";
    echo "Алгоритм: Argon2ID\n";
    echo "Файл защищен правами доступа: 0600\n";
}
?>