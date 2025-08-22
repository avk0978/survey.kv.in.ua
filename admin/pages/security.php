<?php
/**
 * Security management page
 * admin/pages/security.php
 */

if (!isset($authManager)) {
    die('Прямий доступ заборонено');
}

$securityInfo = $authManager->getSecurityInfo();

if (isset($_POST['clear_blocks'])) {
    $authManager->clearAllBlocks();
    $successMessage = "Всі блокування IP очищено";
}

$securityLogs = [];
$logFile = __DIR__ . '/../logs/security.log';
if (file_exists($logFile)) {
    $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $securityLogs = array_slice($lines, 0, 50); 
}

$blockedIps = [];
$lockFile = __DIR__ . '/../auth/login_attempts.json';
if (file_exists($lockFile)) {
    $data = json_decode(file_get_contents($lockFile), true);
    if ($data) {
        foreach ($data as $ip => $info) {
            if ($info['count'] >= 5 && (time() - $info['last_attempt']) < 900) {
                $blockedIps[$ip] = $info;
            }
        }
    }
}
?>

<div class="page-title">🔒 Управління безпекою</div>

<?php if (isset($successMessage)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $securityInfo['session_timeout'] / 60 ?></div>
        <div class="stat-label">Хвилин до автовиходу</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?= $securityInfo['max_login_attempts'] ?></div>
        <div class="stat-label">Максимум спроб входу</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?= $securityInfo['lockout_time'] / 60 ?></div>
        <div class="stat-label">Хвилин блокування</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?= count($blockedIps) ?></div>
        <div class="stat-label">Заблокованих IP</div>
    </div>
</div>

<div class="card">
    <div class="card-header">🛡️ Статус системи безпеки</div>
    <div class="card-body">
        <table class="data-table">
            <tr>
                <td><strong>Файл паролю:</strong></td>
                <td>
                    <?php if ($securityInfo['password_file_exists']): ?>
                        <span class="badge badge-success">✅ Існує і захищений</span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ Не знайдено</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Останнє оновлення паролю:</strong></td>
                <td><?= htmlspecialchars($securityInfo['password_last_changed'] ?? 'Невідомо') ?></td>
            </tr>
            <tr>
                <td><strong>Поточний IP:</strong></td>
                <td><code><?= htmlspecialchars($securityInfo['current_ip']) ?></code></td>
            </tr>
            <tr>
                <td><strong>Сесія активна:</strong></td>
                <td>
                    <?php if ($securityInfo['session_active']): ?>
                        <span class="badge badge-success">✅ Так</span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ Ні</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Алгоритм хешування:</strong></td>
                <td><code>Argon2ID</code> <span class="badge badge-success">Надійний</span></td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">🔑 Управління паролем</div>
    <div class="card-body">
        <p>Для зміни пароля адміністратора використовуйте форму нижче. Пароль повинен містити мінімум 8 символів.</p>
        <button onclick="showChangePasswordForm()" class="btn btn-warning">
            🔄 Змінити пароль
        </button>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #025b79;">
            <strong>💡 Рекомендації для надійного пароля:</strong><br>
            • Мінімум 8 символів<br>
            • Комбінація великих та малих літер<br>
            • Цифри та спеціальні символи<br>
            • Не використовуйте словникові слова
        </div>
    </div>
</div>

<?php if (!empty($blockedIps)): ?>
<div class="card">
    <div class="card-header">🚫 Заблоковані IP адреси</div>
    <div class="card-body">
        <div style="margin-bottom: 15px;">
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear_blocks" class="btn btn-danger btn-sm" 
                        onclick="return confirm('Ви впевнені, що хочете очистити всі блокування?')">
                    🗑️ Очистити всі блокування
                </button>
            </form>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>IP адреса</th>
                    <th>Кількість спроб</th>
                    <th>Перша спроба</th>
                    <th>Остання спроба</th>
                    <th>Залишилось до розблокування</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blockedIps as $ip => $info): 
                    $timeLeft = 900 - (time() - $info['last_attempt']);
                    $minutesLeft = max(0, ceil($timeLeft / 60));
                ?>
                    <tr>
                        <td><code><?= htmlspecialchars($ip) ?></code></td>
                        <td><span class="badge badge-danger"><?= $info['count'] ?></span></td>
                        <td><?= date('d.m.Y H:i:s', $info['first_attempt']) ?></td>
                        <td><?= date('d.m.Y H:i:s', $info['last_attempt']) ?></td>
                        <td>
                            <?php if ($minutesLeft > 0): ?>
                                <span class="badge badge-warning"><?= $minutesLeft ?> хв</span>
                            <?php else: ?>
                                <span class="badge badge-success">Розблоковано</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">✅ Заблоковані IP адреси</div>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">🛡️</div>
            <h3>Немає заблокованих IP</h3>
            <p>Всі IP адреси мають доступ до системи входу</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">📜 Журнал подій безпеки (останні 50 записів)</div>
    <div class="card-body">
        <?php if (!empty($securityLogs)): ?>
            <div style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px;">
                <?php foreach ($securityLogs as $logLine): 
                    // Парсим строку лога для выделения важной информации
                    $isError = strpos($logLine, 'Неудачная') !== false || strpos($logLine, 'Неверный') !== false;
                    $isSuccess = strpos($logLine, 'Успешный') !== false;
                    $isBlock = strpos($logLine, 'заблокированного') !== false;
                    
                    $class = '';
                    $icon = '📝';
                    if ($isError) {
                        $class = 'color: #dc3545;';
                        $icon = '🚨';
                    } elseif ($isSuccess) {
                        $class = 'color: #28a745;';
                        $icon = '✅';
                    } elseif ($isBlock) {
                        $class = 'color: #ffc107;';
                        $icon = '🚫';
                    }
                ?>
                    <div style="margin-bottom: 5px; <?= $class ?>">
                        <?= $icon ?> <?= htmlspecialchars($logLine) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>Журнал порожній</h3>
                <p>Поки немає записів у журналі безпеки</p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 15px; padding: 12px; background: #e3f2fd; border-radius: 6px; font-size: 14px;">
            <strong>💡 Про журнал:</strong><br>
            Тут відображаються всі події, пов'язані з безпекою системи: успішні та неуспішні спроби входу, 
            блокування IP, зміни пароля та інші важливі події.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">💡 Рекомендації з безпеки</div>
    <div class="card-body">
        <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div style="padding: 15px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #025b79;">
                <h4 style="color: #025b79; margin-bottom: 10px;">🔐 Пароль</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Регулярно змінюйте пароль (раз на 3-6 місяців)</li>
                    <li>Використовуйте унікальний складний пароль</li>
                    <li>Не передавайте пароль іншим особам</li>
                </ul>
            </div>
            
            <div style="padding: 15px; background: #f8fff9; border-radius: 8px; border-left: 4px solid #28a745;">
                <h4 style="color: #28a745; margin-bottom: 10px;">🌐 Доступ</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Завжди виходьте з системи після роботи</li>
                    <li>Не залишайте браузер відкритим на чужих комп'ютерах</li>
                    <li>Використовуйте захищене з'єднання (HTTPS)</li>
                </ul>
            </div>
            
            <div style="padding: 15px; background: #fffbf0; border-radius: 8px; border-left: 4px solid #ffc107;">
                <h4 style="color: #f57f17; margin-bottom: 10px;">🔍 Моніторинг</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Регулярно перевіряйте журнал подій</li>
                    <li>Звертайте увагу на підозрілі IP адреси</li>
                    <li>Налаштуйте резервне копіювання</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">🔧 Технічна інформація</div>
    <div class="card-body">
        <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <h5 style="color: #025b79;">Файли безпеки:</h5>
                <ul style="font-family: monospace; font-size: 13px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <li>admin/auth/admin_password.txt</li>
                    <li>admin/auth/login_attempts.json</li>
                    <li>admin/logs/security.log</li>
                </ul>
            </div>
            
            <div>
                <h5 style="color: #025b79;">Права доступу:</h5>
                <ul style="font-family: monospace; font-size: 13px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <li>admin_password.txt: 0600</li>
                    <li>login_attempts.json: 0644</li>
                    <li>security.log: 0644</li>
                </ul>
            </div>
            
            <div>
                <h5 style="color: #025b79;">Алгоритми:</h5>
                <ul style="font-family: monospace; font-size: 13px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                    <li>Хешування: Argon2ID</li>
                    <li>Memory cost: 65536</li>
                    <li>Time cost: 4</li>
                    <li>Threads: 3</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff5f5; border-radius: 8px; border: 1px solid #fed7d7;">
            <h5 style="color: #dc3545; margin-bottom: 10px;">⚠️ Важливо!</h5>
            <p style="margin: 0; color: #721c24;">
                Ніколи не надавайте доступ до файлів з паролями стороннім особам. 
                Регулярно створюйте резервні копії системи та перевіряйте журнали безпеки.
            </p>
        </div>
    </div>
</div>

<script>
setInterval(() => {
    if (!document.getElementById('modal').style.display || document.getElementById('modal').style.display === 'none') {
        location.reload();
    }
}, 30000);
</script>