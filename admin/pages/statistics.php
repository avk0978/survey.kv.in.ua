<?php
/**
 * Statistics page for admin panel
 * admin/pages/statistics.php
 */


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit();
}

try {
    $survey_types_query = "SELECT slug, name FROM survey_types ORDER BY name";
    $survey_types_stmt = $conn->prepare($survey_types_query);
    $survey_types_stmt->execute();
    $survey_types = $survey_types_stmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_survey = $_GET['survey'] ?? 'universal_survey';
    
    $valid_types = array_column($survey_types, 'slug');
    if (!in_array($selected_survey, $valid_types)) {
        $selected_survey = 'universal_survey';
    }

    $stats_query = "SELECT * FROM question_statistics_aggregated 
                   WHERE survey_type = ? 
                   ORDER BY COALESCE(question_order, 999), question_code";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute([$selected_survey]);
    $statistics = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $survey_info_query = "
        SELECT 
            st.name as survey_name,
            COUNT(sr.id) as total_responses,
            AVG(sr.completion_percentage) as avg_completion
        FROM survey_types st
        LEFT JOIN survey_responses sr ON st.id = sr.survey_type_id
        WHERE st.slug = ?
        GROUP BY st.id, st.slug, st.name
    ";
    $survey_info_stmt = $conn->prepare($survey_info_query);
    $survey_info_stmt->execute([$selected_survey]);
    $survey_info = $survey_info_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Ошибка получения статистики: " . $e->getMessage();
}
?>

<div class="page-title">📈 Детальна статистика по питаннях</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Помилка!</strong> <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">🎯 Виберіть тип анкети</div>
    <div class="card-body">
        <form method="GET" class="filter-row">
            <input type="hidden" name="page" value="statistics">
            <select name="survey" class="form-control" onchange="this.form.submit()">
                <?php foreach ($survey_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['slug']) ?>" 
                            <?= $selected_survey === $type['slug'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" class="btn btn-success btn-sm" onclick="location.reload()">
                🔄 Оновити
            </button>
        </form>
    </div>
</div>

<?php if ($survey_info): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $survey_info['total_responses'] ?? 0 ?></div>
        <div class="stat-label">Всього відповідей</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= round($survey_info['avg_completion'] ?? 0, 1) ?>%</div>
        <div class="stat-label">Середня повнота заповнення</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= count($statistics) ?></div>
        <div class="stat-label">Питань з даними</div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($statistics)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px;">
            <div style="font-size: 64px; color: #e0e0e0; margin-bottom: 20px;">📭</div>
            <h3 style="color: #025b79; margin-bottom: 10px;">Немає даних для відображення</h3>
            <p>Поки що немає відповідей для цього типу анкети або питання не заповнені в довіднику.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($statistics as $index => $stat): ?>
        <div class="card" style="margin-bottom: 20px; border-left: 4px solid #025b79;">
            <div class="card-header" style="background: #f8f9fa; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="background: #025b79; color: white; padding: 8px 12px; border-radius: 6px; font-weight: bold; min-width: 50px; text-align: center;">
                        <?= htmlspecialchars($stat['question_code']) ?>
                    </span>
                    <div style="font-weight: 500; color: #212121; font-size: 16px; flex: 1;">
                        <?= !empty($stat['question_text']) 
                            ? htmlspecialchars($stat['question_text']) 
                            : '⚠️ Текст питання не знайдено в довіднику' ?>
                    </div>
                </div>
                <span style="background: #ff9800; color: white; padding: 6px 12px; border-radius: 15px; font-size: 14px; font-weight: 500;">
                    📊 Всього: <?= $stat['total_responses'] ?>
                </span>
            </div>
            
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <?php if ($stat['yes_count'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #e8f5e8, #f1f8e9); border: 2px solid #a5d6a7; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #2e7d32; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #2e7d32; border-radius: 50%; margin-right: 10px;"></span>
                                ✅ Так
                            </div>
                            <div style="text-align: right; color: #2e7d32;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['yes_count'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= $stat['yes_percentage'] ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?> 
                   
                    <?php if ($stat['no_count'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #ffebee, #fce4ec); border: 2px solid #ef9a9a; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #c62828; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #c62828; border-radius: 50%; margin-right: 10px;"></span>
                                ❌ Ні
                            </div>
                            <div style="text-align: right; color: #c62828;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['no_count'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= $stat['no_percentage'] ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stat['undecided_count'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #fff8e1, #fffbf0); border: 2px solid #ffcc02; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #f57f17; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #f57f17; border-radius: 50%; margin-right: 10px;"></span>
                                🤔 Не визначився
                            </div>
                            <div style="text-align: right; color: #f57f17;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['undecided_count'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= $stat['undecided_percentage'] ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stat['less5_count'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #f3e5f5, #fce4ec); border: 2px solid #ce93d8; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #7b1fa2; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #7b1fa2; border-radius: 50%; margin-right: 10px;"></span>
                                📅 Менше 5 років
                            </div>
                            <div style="text-align: right; color: #7b1fa2;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['less5_count'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= round(($stat['less5_count'] / $stat['total_responses']) * 100, 2) ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stat['count_5_10'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #e0f2f1, #e8f5e8); border: 2px solid #80cbc4; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #00695c; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #00695c; border-radius: 50%; margin-right: 10px;"></span>
                                📆 5-10 років
                            </div>
                            <div style="text-align: right; color: #00695c;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['count_5_10'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= round(($stat['count_5_10'] / $stat['total_responses']) * 100, 2) ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stat['more10_count'] > 0): ?>
                        <div style="padding: 15px; background: linear-gradient(135deg, #e3f2fd, #f0f8ff); border: 2px solid #81d4fa; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; color: #0277bd; font-weight: 600;">
                                <span style="width: 12px; height: 12px; background: #0277bd; border-radius: 50%; margin-right: 10px;"></span>
                                📈 Більше 10 років
                            </div>
                            <div style="text-align: right; color: #0277bd;">
                                <div style="font-weight: bold; font-size: 18px;"><?= $stat['more10_count'] ?></div>
                                <div style="font-size: 13px; opacity: 0.8;"><?= round(($stat['more10_count'] / $stat['total_responses']) * 100, 2) ?>%</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
/* Specific styles for statistics page */
.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.card-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    font-weight: bold;
    color: #025b79;
    font-size: 16px;
}

.card-body {
    padding: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.form-control {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 16px;
    min-width: 250px;
    background: white;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #025b79;
    box-shadow: 0 0 0 3px rgba(2, 91, 121, 0.1);
}

.btn {
    background: #025b79;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    background: #0a7ba3;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(2, 91, 121, 0.2);
    color: white;
    text-decoration: none;
}

.btn-success {
    background: #28a745;
}

.btn-success:hover {
    background: #218838;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    border: 1px solid #e0e0e0;
    position: relative;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #025b79;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #025b79;
    margin-bottom: 10px;
}

.stat-label {
    color: #666;
    font-size: 16px;
    font-weight: 500;
}

.text-center {
    text-align: center;
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background: #fff5f5;
    color: #721c24;
    border-color: #f5c6cb;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-control {
        min-width: auto;
        width: 100%;
    }
    
    .card-header {
        padding: 15px;
        flex-direction: column !important;
        gap: 10px !important;
        align-items: flex-start !important;
    }
    
    .card-header > div:first-child {
        flex-direction: column !important;
        gap: 10px !important;
        align-items: flex-start !important;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-number {
        font-size: 28px;
    }
    
    .card-body > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

.card {
    animation: fadeInUp 0.4s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Auto refresh every 30 seconds
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        console.log('🔄 Auto-refreshing statistics...');
        location.reload();
    }, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

startAutoRefresh();

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>