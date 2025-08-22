<?php
/**
 * Administration panel of the survey system
 * admin/index.php
 */

session_start();
require_once '../config/database.php';
require_once 'auth/auth_manager.php';

$authManager = new AuthManager();

if (isset($_POST['change_password']) && $authManager->isLoggedIn()) {
    $result = $authManager->changePassword(
        $_POST['current_password'],
        $_POST['new_password']
    );
    $passwordChangeMessage = $result;
}

if (!$authManager->isLoggedIn()) {
    if (isset($_POST['password'])) {
        $result = $authManager->login($_POST['password'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$result['success']) {
            $error = $result['message'];
        }
    }
    
    if (!$authManager->isLoggedIn()) {
        ?>
        <!DOCTYPE html>
        <html lang="uk">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Вхід в адмін-панель ОСББ</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    background: linear-gradient(135deg, #025b79 0%, #0a7ba3 100%); 
                    margin: 0; 
                }
                .login-form { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
                    max-width: 400px; 
                    width: 100%; 
                    text-align: center;
                }
                .logo { 
                    font-size: 48px; 
                    margin-bottom: 10px; 
                }
                h1 { 
                    color: #025b79; 
                    margin-bottom: 30px; 
                    font-weight: 600;
                }
                .form-group {
                    margin-bottom: 20px;
                    text-align: left;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    color: #333;
                    font-weight: 500;
                }
                input[type="password"] { 
                    width: 100%; 
                    padding: 14px; 
                    border: 2px solid #e0e0e0; 
                    border-radius: 6px; 
                    font-size: 16px;
                    transition: border-color 0.3s;
                    box-sizing: border-box;
                }
                input[type="password"]:focus {
                    outline: none;
                    border-color: #025b79;
                }
                button { 
                    width: 100%; 
                    background: linear-gradient(135deg, #025b79 0%, #0a7ba3 100%); 
                    color: white; 
                    padding: 14px; 
                    border: none; 
                    border-radius: 6px; 
                    font-size: 16px; 
                    font-weight: 600;
                    cursor: pointer; 
                    transition: transform 0.2s;
                }
                button:hover { 
                    transform: translateY(-1px);
                }
                .error { 
                    color: #dc3545; 
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    padding: 10px;
                    border-radius: 6px;
                    text-align: left; 
                    margin-bottom: 20px; 
                }
                .security-info {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    text-align: left;
                    font-size: 14px;
                    color: #666;
                }
                .security-info strong {
                    color: #025b79;
                }
            </style>
        </head>
        <body>
            <div class="login-form">
                <div class="logo">🏠</div>
                <h1>Адмін-панель ОСББ</h1>
                <?php if (isset($error)): ?>
                    <div class="error">🚨 <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">Пароль адміністратора:</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Введіть пароль" autocomplete="current-password">
                    </div>
                    <button type="submit">Увійти</button>
                </form>
                
                <div class="security-info">
                    <strong>🔐 Система безпеки:</strong><br>
                    • Максимум 5 спроб входу<br>
                    • Блокування IP на 15 хвилин<br>
                    • Пароль зберігається у хешованому вигляді<br>
                    • Всі спроби логуються
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

if (isset($_GET['logout'])) {
    $authManager->logout();
    header('Location: index.php');
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die('Помилка підключення до бази даних: ' . $e->getMessage());
}

$current_page = $_GET['page'] ?? 'dashboard';
$survey_filter = $_GET['survey'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Адмін-панель опросів ОСББ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        
        .header { background: #025b79; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .logout-btn { color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 4px; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        
        .container { display: flex; min-height: calc(100vh - 70px); }
        
        .sidebar { width: 250px; background: white; border-right: 1px solid #e0e0e0; padding: 20px 0; }
        .nav-item { display: block; padding: 12px 20px; text-decoration: none; color: #333; border-left: 3px solid transparent; transition: all 0.3s; }
        .nav-item:hover, .nav-item.active { background: #f0f8ff; border-left-color: #025b79; color: #025b79; }
        
        .content { flex: 1; padding: 30px; }
        .page-title { font-size: 28px; color: #025b79; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 36px; font-weight: bold; color: #025b79; }
        .stat-label { color: #666; margin-top: 5px; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        .card-header { background: #f8f9fa; padding: 20px; border-bottom: 1px solid #e0e0e0; font-weight: bold; color: #025b79; }
        .card-body { padding: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; color: #025b79; }
        tr:hover { background: #f8f9fa; }
        
        .btn { background: #025b79; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
        .btn:hover { background: #0a7ba3; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #000; }
        
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-row select, .filter-row input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        
        .progress-bar { width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #28a745; transition: width 0.3s; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 80%; max-height: 80%; overflow-y: auto; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #999; }
        .close:hover { color: #000; }
        
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-row { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 Адміністративна панель ОСББ</h1>
        <div class="header-right">
            <span>Останнє оновлення: <?= date('d.m.Y H:i') ?></span>
            <a href="?logout=1" class="logout-btn">Вихід</a>
        </div>
    </div>
    
    <div class="container">
        <nav class="sidebar">
            <a href="?page=dashboard" class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">📊 Панель управління</a>
            <a href="?page=responses" class="nav-item <?= $current_page === 'responses' ? 'active' : '' ?>">📝 Всі відповіді</a>
            <a href="?page=statistics" class="nav-item <?= $current_page === 'statistics' ? 'active' : '' ?>">📈 Статистика</a>
            <a href="?page=reports" class="nav-item <?= $current_page === 'reports' ? 'active' : '' ?>">📊 Звіти</a>
            <a href="?page=export" class="nav-item <?= $current_page === 'export' ? 'active' : '' ?>">💾 Експорт даних</a>
            <a href="?page=logs" class="nav-item <?= $current_page === 'logs' ? 'active' : '' ?>">📜 Журнал подій</a>
            <a href="?page=security" class="nav-item <?= $current_page === 'security' ? 'active' : '' ?>">🔒 Безпека</a>
        </nav>        
        
        <main class="content">
            <?php
            if (isset($passwordChangeMessage)): ?>
                <div class="alert <?= $passwordChangeMessage['success'] ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($passwordChangeMessage['message']) ?>
                </div>
            <?php endif;
            
            switch ($current_page) {
                case 'security':
                    include 'pages/security.php';
                    break;
                case 'dashboard':
                    include 'pages/dashboard.php';
                    break;
                case 'responses':
                    include 'pages/responses.php';
                    break;
                case 'statistics':
                    include 'pages/statistics.php';
                    break;
                case 'reports':
                    include 'pages/reports.php';
                    break;
                case 'export':
                    include 'pages/export.php';
                    break;
                case 'logs':
                    include 'pages/logs.php';
                    break;
                default:
                    include 'pages/dashboard.php';
            }
            ?>
        </main>
    </div>
    
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <script>
        function showModal(title, content) {
            document.getElementById('modal-body').innerHTML = '<h2>' + title + '</h2>' + content;
            document.getElementById('modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        if (window.location.search.includes('page=dashboard') || window.location.search === '') {
            setInterval(() => {
                if (!document.getElementById('modal').style.display || document.getElementById('modal').style.display === 'none') {
                    location.reload();
                }
            }, 30000);
        }
        
        async function viewResponse(responseId) {
            try {
                const response = await fetch(`../api/admin-api.php?action=get_response&id=${responseId}`);
                const data = await response.json();
                
                if (data.success) {
                    const responseData = data.data.response;
                    const answers = data.data.answers;
                    
                    let content = '<div style="max-height: 500px; overflow-y: auto;">';
                    
                    
                    content += '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #025b79;">';
                    content += '<h4 style="color: #025b79; margin-bottom: 10px;">📋 Інформація про відповідь</h4>';
                    content += '<p><strong>ID:</strong> ' + responseData.response_id + '</p>';
                    content += '<p><strong>Тип анкети:</strong> ' + (responseData.survey_name || 'Невідомий тип') + '</p>';
                    content += '<p><strong>Повнота заповнення:</strong> ' + responseData.completion_percentage + '%</p>';
                    content += '<p><strong>Завершено:</strong> ' + (responseData.is_completed ? 'Так' : 'Ні') + '</p>';
                    content += '<p><strong>Питань всього:</strong> ' + responseData.total_questions + '</p>';
                    content += '<p><strong>Відповідано:</strong> ' + responseData.answered_questions + '</p>';
                    content += '<p><strong>Початок:</strong> ' + (responseData.started_at ? new Date(responseData.started_at).toLocaleString('uk-UA') : 'Н/Д') + '</p>';
                    content += '<p><strong>Завершення:</strong> ' + (responseData.completed_at ? new Date(responseData.completed_at).toLocaleString('uk-UA') : 'Н/Д') + '</p>';
                    content += '<p><strong>IP адреса:</strong> ' + (responseData.user_ip || 'Невідома') + '</p>';
                    content += '</div>';
                    
                    
                    content += '<h4 style="color: #025b79; margin-bottom: 15px;">📝 Детальні відповіді</h4>';
                    content += '<table style="width: 100%; border-collapse: collapse; border: 1px solid #e0e0e0;">';
                    content += '<thead>';
                    content += '<tr style="background: #025b79; color: white;">';
                    content += '<th style="padding: 12px; text-align: left; border: 1px solid #e0e0e0; width: 80px;">Код</th>';
                    content += '<th style="padding: 12px; text-align: left; border: 1px solid #e0e0e0;">Питання</th>';
                    content += '<th style="padding: 12px; text-align: left; border: 1px solid #e0e0e0; width: 150px;">Відповідь</th>';
                    content += '</tr>';
                    content += '</thead>';
                    content += '<tbody>';
                    
                    answers.forEach((answer, index) => {
                        const bgColor = index % 2 === 0 ? '#f8f9fa' : '#ffffff';
                        content += '<tr style="background: ' + bgColor + ';">';
                        
                        
                        content += '<td style="padding: 10px; border: 1px solid #e0e0e0; font-weight: bold; color: #025b79; font-family: monospace;">' + 
                                answer.question_key + '</td>';
                        
                        
                        content += '<td style="padding: 10px; border: 1px solid #e0e0e0; line-height: 1.4;">';
                        if (answer.question_text && answer.question_text !== 'Текст питання не знайдено') {
                            content += answer.question_text;
                        } else {
                            content += '<span style="color: #999; font-style: italic;">⚠️ Текст питання не знайдено в довіднику</span>';
                        }
                        content += '</td>';
                        
                        
                        content += '<td style="padding: 10px; border: 1px solid #e0e0e0; font-weight: 500;">';
                        content += '<span style="background: #e3f2fd; padding: 4px 8px; border-radius: 4px; display: inline-block;">';
                        content += answer.answer_text;
                        content += '</span></td>';
                        
                        content += '</tr>';
                    });
                    
                    content += '</tbody>';
                    content += '</table>';
                    
                   
                    content += '<div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px; border: 1px solid #b3d9ff;">';
                    content += '<h5 style="color: #025b79; margin-bottom: 10px;">📊 Статистика відповіді</h5>';
                    content += '<p><strong>Всього питань:</strong> ' + answers.length + '</p>';
                    
                    const yesCount = answers.filter(a => a.answer_value === 'yes').length;
                    const noCount = answers.filter(a => a.answer_value === 'no').length;
                    const undecidedCount = answers.filter(a => a.answer_value === 'undecided').length;
                    const otherCount = answers.filter(a => !['yes', 'no', 'undecided'].includes(a.answer_value)).length;
                    
                    if (yesCount > 0) content += '<p><strong>Відповіді "Так":</strong> ' + yesCount + '</p>';
                    if (noCount > 0) content += '<p><strong>Відповіді "Ні":</strong> ' + noCount + '</p>';
                    if (undecidedCount > 0) content += '<p><strong>Відповіді "Не визначився":</strong> ' + undecidedCount + '</p>';
                    if (otherCount > 0) content += '<p><strong>Інші відповіді:</strong> ' + otherCount + '</p>';
                    
                    
                    if (answers.length > 0) {
                        content += '<hr style="margin: 10px 0;">';
                        content += '<p style="font-size: 14px; color: #666;">';
                        if (yesCount > 0) content += '✅ ' + Math.round((yesCount / answers.length) * 100) + '% Так | ';
                        if (noCount > 0) content += '❌ ' + Math.round((noCount / answers.length) * 100) + '% Ні | ';
                        if (undecidedCount > 0) content += '🤔 ' + Math.round((undecidedCount / answers.length) * 100) + '% Не визначився';
                        content = content.replace(/ \| $/, ''); // Убираем последний разделитель
                        content += '</p>';
                    }
                    
                    content += '</div>';
                    content += '</div>';
                    
                    showModal('Деталі відповіді: ' + responseId, content);
                } else {
                    alert('Помилка завантаження даних: ' + (data.message || 'Невідома помилка'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Помилка: ' + error.message);
            }
        }        

       
        function exportData(format, surveyType = '') {
            const url = `../api/admin-api.php?action=export&format=${format}&survey_type=${surveyType}`;
            window.open(url, '_blank');
        }
        
        
        function confirmDelete(message, url) {
            if (confirm(message)) {
                window.location.href = url;
            }
        }
        
        
        function showChangePasswordForm() {
            const content = `
                <form method="POST" style="max-width: 400px;">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Поточний пароль:</label>
                        <input type="password" name="current_password" required 
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Новий пароль:</label>
                        <input type="password" name="new_password" required minlength="8"
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px;">
                        <small style="color: #666;">Мінімум 8 символів</small>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="change_password" 
                                style="background: #025b79; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                            Змінити пароль
                        </button>
                        <button type="button" onclick="closeModal()" 
                                style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                            Скасувати
                        </button>
                    </div>
                </form>
            `;
            showModal('🔒 Зміна пароля адміністратора', content);
        }
    </script>
</body>
</html>