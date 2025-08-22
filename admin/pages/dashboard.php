<?php
/**
 * Dashboard - admin panel main page
 * admin/pages/dashboard.php
 */


$stats_query = "SELECT 
    (SELECT COUNT(*) FROM question_answers) as total_responses,
    COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed_responses,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_responses,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_responses,
    ROUND(AVG(CASE WHEN is_completed = 1 THEN completion_percentage END), 1) as avg_completion,
    (SELECT COUNT(DISTINCT qa.response_id) FROM question_answers qa) as unique_surveys
FROM survey_responses";

$stmt = $conn->prepare($stats_query);
$stmt->execute();
$general_stats = $stmt->fetch();
$survey_stats_query = "SELECT 
    st.name,
    st.slug,
    st.total_questions,
    COUNT(sr.id) as total_responses,
    COUNT(CASE WHEN sr.is_completed = 1 THEN 1 END) as completed_responses,
    ROUND(AVG(CASE WHEN sr.is_completed = 1 THEN sr.completion_percentage END), 1) as avg_completion,
    MAX(sr.created_at) as last_response
FROM survey_types st
LEFT JOIN survey_responses sr ON st.id = sr.survey_type_id
WHERE st.is_active = 1
GROUP BY st.id, st.name, st.slug, st.total_questions
ORDER BY completed_responses DESC";

$stmt = $conn->prepare($survey_stats_query);
$stmt->execute();
$survey_stats = $stmt->fetchAll();
$activity_query = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as responses,
    COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed
FROM survey_responses 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC";

$stmt = $conn->prepare($activity_query);
$stmt->execute();
$daily_activity = $stmt->fetchAll();
$recent_responses_query = "SELECT 
    sr.response_id,
    st.name as survey_name,
    sr.answered_questions,
    sr.total_questions,
    sr.completion_percentage,
    sr.is_completed,
    sr.created_at,
    sr.user_ip
FROM survey_responses sr
JOIN survey_types st ON sr.survey_type_id = st.id
ORDER BY sr.created_at DESC 
LIMIT 10";

$stmt = $conn->prepare($recent_responses_query);
$stmt->execute();
$recent_responses = $stmt->fetchAll();

?>

<div class="page-title">📊 Панель управління</div>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= number_format($general_stats['total_responses']) ?></div>
        <div class="stat-label">Всього відповідей</div>
        <small style="color: #666; margin-top: 5px; display: block;">на питання анкет</small>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($general_stats['completed_responses']) ?></div>
        <div class="stat-label">Завершених анкет</div>
        <small style="color: #666; margin-top: 5px; display: block;">з <?= number_format($general_stats['unique_surveys']) ?> всього</small>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($general_stats['today_responses']) ?></div>
        <div class="stat-label">Сьогодні</div>
        <small style="color: #666; margin-top: 5px; display: block;">анкет</small>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= number_format($general_stats['week_responses']) ?></div>
        <div class="stat-label">За тиждень</div>
        <small style="color: #666; margin-top: 5px; display: block;">анкет</small>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $general_stats['avg_completion'] ?? 0 ?>%</div>
        <div class="stat-label">Середнє заповнення</div>
        <small style="color: #666; margin-top: 5px; display: block;">завершених анкет</small>
    </div>
</div>

<div class="card">
    <div class="card-header">📋 Статистика по типах анкет</div>
    <div class="card-body">
        <table>
            <thead>
                <tr>
                    <th>Назва анкети</th>
                    <th>Питань</th>
                    <th>Всього відповідей</th>
                    <th>Завершено</th>
                    <th>Середнє заповнення</th>
                    <th>Остання відповідь</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($survey_stats as $survey): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($survey['name']) ?></strong><br>
                        <small><?= $survey['slug'] ?></small>
                    </td>
                    <td><?= $survey['total_questions'] ?></td>
                    <td>
                        <span class="badge badge-<?= $survey['total_responses'] > 0 ? 'success' : 'warning' ?>">
                            <?= $survey['total_responses'] ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $completion_rate = $survey['total_responses'] > 0 ? 
                            round(($survey['completed_responses'] / $survey['total_responses']) * 100, 1) : 0;
                        ?>
                        <?= $survey['completed_responses'] ?> (<?= $completion_rate ?>%)
                        <div class="progress-bar" style="margin-top: 5px;">
                            <div class="progress-fill" style="width: <?= $completion_rate ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?= ($survey['avg_completion'] ?? 0) >= 80 ? 'success' : 'warning' ?>">
                            <?= $survey['avg_completion'] ?? 0 ?>%
                        </span>
                    </td>
                    <td>
                        <?php if ($survey['last_response']): ?>
                            <?= date('d.m.Y H:i', strtotime($survey['last_response'])) ?>
                        <?php else: ?>
                            <span class="badge badge-warning">Немає даних</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=responses&survey=<?= $survey['slug'] ?>" class="btn btn-sm">Переглянути</a>
                        <a href="?page=statistics&survey=<?= $survey['slug'] ?>" class="btn btn-sm btn-success">Статистика</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Активность по дням -->
    <div class="card">
        <div class="card-header">📈 Активність за тиждень</div>
        <div class="card-body">
            <?php if (empty($daily_activity)): ?>
                <p>Немає даних за останні 7 днів</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Відповідей</th>
                            <th>Завершено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_activity as $day): ?>
                        <tr>
                            <td><?= date('d.m.Y', strtotime($day['date'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $day['responses'] > 0 ? 'success' : 'warning' ?>">
                                    <?= $day['responses'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $day['completed'] > 0 ? 'success' : 'warning' ?>">
                                    <?= $day['completed'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">🕒 Останні відповіді</div>
        <div class="card-body">
            <?php if (empty($recent_responses)): ?>
                <p>Немає відповідей</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Анкета</th>
                            <th>Заповнення</th>
                            <th>Час</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_responses as $response): ?>
                        <tr>
                            <td>
                                <a href="javascript:void(0)" onclick="viewResponse('<?= $response['response_id'] ?>')">
                                    <?= substr($response['response_id'], -8) ?>
                                </a>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($response['survey_name']) ?></small>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?= $response['answered_questions'] ?>/<?= $response['total_questions'] ?></span>
                                    <div class="progress-bar" style="width: 60px;">
                                        <div class="progress-fill" style="width: <?= $response['completion_percentage'] ?>%"></div>
                                    </div>
                                    <span class="badge badge-<?= $response['is_completed'] ? 'success' : 'warning' ?>">
                                        <?= round($response['completion_percentage']) ?>%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <small><?= date('d.m H:i', strtotime($response['created_at'])) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="?page=responses" class="btn">Переглянути всі відповіді</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">⚡ Швидкі дії</div>
    <div class="card-body">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?page=export" class="btn">💾 Експорт всіх даних</a>
            <a href="?page=statistics" class="btn btn-success">📊 Детальна статистика</a>
            <a href="../test-all-surveys.php" target="_blank" class="btn btn-warning">🧪 Тестування анкет</a>
            <a href="../index.html" target="_blank" class="btn">🏠 Головна сторінка</a>
        </div>
    </div>
</div>

<script>

setInterval(() => {
    const now = new Date().toLocaleString('uk-UA');
    document.querySelector('.header-right span').textContent = 'Останнє оновлення: ' + now;
}, 1000);


let autoRefresh = setInterval(() => {
    if (!document.getElementById('modal').style.display || document.getElementById('modal').style.display === 'none') {
        location.reload();
    }
}, 30000);

document.addEventListener('click', () => {
    clearInterval(autoRefresh);
    // Перезапуск через 5 минут бездействия
    setTimeout(() => {
        autoRefresh = setInterval(() => {
            if (!document.getElementById('modal').style.display || document.getElementById('modal').style.display === 'none') {
                location.reload();
            }
        }, 30000);
    }, 300000); // 5 минут
});
</script>