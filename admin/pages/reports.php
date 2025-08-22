<?php
/**
 *  Reporting page for admin panel *
 *  admin/pages/reports.php
 */


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    
    try {
        $report_query = "
            SELECT 
                qd.question_text as question_name,
                st.name as survey_name,
                qd.question_code,
                qd.question_order,
                SUM(CASE WHEN ss.answer_value = 'yes' THEN ss.count ELSE 0 END) as total_yes,
                SUM(CASE WHEN ss.answer_value = 'no' THEN ss.count ELSE 0 END) as total_no,
                SUM(CASE WHEN ss.answer_value = 'undecided' THEN ss.count ELSE 0 END) as total_undecided,
                SUM(ss.count) as total_responses
            FROM questions_directory qd
            LEFT JOIN survey_types st ON qd.survey_type = st.slug
            LEFT JOIN survey_statistics ss ON qd.question_code = ss.question_key 
                AND st.id = ss.survey_type_id
            WHERE qd.question_text IS NOT NULL 
                AND qd.question_text != ''
            GROUP BY qd.question_text, st.name, qd.question_code, qd.question_order
            HAVING total_responses > 0
            ORDER BY qd.question_text, st.name
        ";
        
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->execute();
        $export_data = $report_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        die('Помилка отримання даних для експорту: ' . $e->getMessage());
    }
    
    $filename = 'osbb_questions_report_' . date('Y-m-d_H-i-s') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"><style>table{border-collapse:collapse;} th,td{border:1px solid #000;padding:8px;text-align:left;} th{background:#f0f0f0;font-weight:bold;}</style></head><body>';
    
    echo '<h2>Звіт по питаннях ОСББ</h2>';
    echo '<p>Дата створення: ' . date('d.m.Y H:i:s') . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<tr>';
    echo '<th>Назва питання</th>';
    echo '<th>Назва анкети</th>';
    echo '<th>Всього відповідей - Так</th>';
    echo '<th>Всього відповідей - Ні</th>';
    echo '<th>Всього відповідей - Не визначився</th>';
    echo '<th>Загальна кількість відповідей</th>';
    echo '</tr>';
    
    foreach ($export_data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['question_name'] ?: 'Текст питання не вказано') . '</td>';
        echo '<td>' . htmlspecialchars($row['survey_name'] ?: 'Невідома анкета') . '</td>';
        echo '<td style="text-align:center;">' . ($row['total_yes'] ?: 0) . '</td>';
        echo '<td style="text-align:center;">' . ($row['total_no'] ?: 0) . '</td>';
        echo '<td style="text-align:center;">' . ($row['total_undecided'] ?: 0) . '</td>';
        echo '<td style="text-align:center;font-weight:bold;">' . ($row['total_responses'] ?: 0) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

try {
    $report_query = "
        SELECT 
            qd.question_text as question_name,
            st.name as survey_name,
            qd.question_code,
            qd.question_order,
            SUM(CASE WHEN ss.answer_value = 'yes' THEN ss.count ELSE 0 END) as total_yes,
            SUM(CASE WHEN ss.answer_value = 'no' THEN ss.count ELSE 0 END) as total_no,
            SUM(CASE WHEN ss.answer_value = 'undecided' THEN ss.count ELSE 0 END) as total_undecided,
            SUM(ss.count) as total_responses
        FROM questions_directory qd
        LEFT JOIN survey_types st ON qd.survey_type = st.slug
        LEFT JOIN survey_statistics ss ON qd.question_code = ss.question_key 
            AND st.id = ss.survey_type_id
        WHERE qd.question_text IS NOT NULL 
            AND qd.question_text != ''
        GROUP BY qd.question_text, st.name, qd.question_code, qd.question_order
        HAVING total_responses > 0
        ORDER BY qd.question_text, st.name
    ";
    
    $report_stmt = $conn->prepare($report_query);
    $report_stmt->execute();
    $report_data = $report_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary_query = "
        SELECT 
            COUNT(DISTINCT qd.question_text) as unique_questions,
            COUNT(DISTINCT st.name) as unique_surveys,
            SUM(ss.count) as total_all_responses
        FROM questions_directory qd
        LEFT JOIN survey_types st ON qd.survey_type = st.slug
        LEFT JOIN survey_statistics ss ON qd.question_code = ss.question_key 
            AND st.id = ss.survey_type_id
        WHERE qd.question_text IS NOT NULL 
            AND qd.question_text != ''
            AND ss.count > 0
    ";
    
    $summary_stmt = $conn->prepare($summary_query);
    $summary_stmt->execute();
    $summary_data = $summary_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Помилка створення звіту: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Звіти по питаннях - ОСББ</title>
    <link rel="stylesheet" href="../css/reports-style.css">
</head>
<body>

<div class="page-title">📊 Звіти по питаннях</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Помилка!</strong> <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<?php if ($summary_data): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $summary_data['unique_questions'] ?? 0 ?></div>
        <div class="stat-label">Унікальних питань</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $summary_data['unique_surveys'] ?? 0 ?></div>
        <div class="stat-label">Типів анкет</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $summary_data['total_all_responses'] ?? 0 ?></div>
        <div class="stat-label">Всього відповідей</div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">💾 Експорт звіту</div>
    <div class="card-body">
        <div class="export-buttons">
            <a href="../admin/export-csv.php" class="btn btn-csv" target="_blank">
                📄 Завантажити CSV
            </a>
            <a href="?page=reports&export=excel" class="btn btn-excel">
                📊 Відкрити в Excel
            </a>
        </div>
        <div class="export-hint">
            💡 <strong>Структура звіту:</strong> Назва питання, Назва анкети, Кількість відповідей (Так/Ні/Не визначився)
            <br>📥 <strong>CSV</strong> - файл завантажиться автоматично | 🌐 <strong>Excel</strong> - відкриється в браузері
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        📋 Детальний звіт по питаннях
        <span class="record-count">
            Всього записів: <?= count($report_data) ?>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($report_data)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>Немає даних для звіту</h3>
                <p>Поки що немає відповідей на питання або не заповнений довідник питань.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Назва питання</th>
                            <th style="width: 20%;">Назва анкети</th>
                            <th style="width: 10%; text-align: center;">✅ Так</th>
                            <th style="width: 10%; text-align: center;">❌ Ні</th>
                            <th style="width: 10%; text-align: center;">🤔 Не визначився</th>
                            <th style="width: 10%; text-align: center;">📊 Всього</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $index => $row): ?>
                            <tr>
                                <td class="question-cell">
                                    <div class="question-title">
                                        <?= htmlspecialchars($row['question_name'] ?: 'Текст питання не вказано') ?>
                                    </div>
                                    <?php if ($row['question_code']): ?>
                                        <div class="question-code"><?= htmlspecialchars($row['question_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="survey-badge">
                                        <?= htmlspecialchars($row['survey_name'] ?: 'Невідома анкета') ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['total_yes'] > 0): ?>
                                        <span class="answer-yes"><?= $row['total_yes'] ?></span>
                                    <?php else: ?>
                                        <span class="answer-zero">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['total_no'] > 0): ?>
                                        <span class="answer-no"><?= $row['total_no'] ?></span>
                                    <?php else: ?>
                                        <span class="answer-zero">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['total_undecided'] > 0): ?>
                                        <span class="answer-undecided"><?= $row['total_undecided'] ?></span>
                                    <?php else: ?>
                                        <span class="answer-zero">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="answer-total"><?= $row['total_responses'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card, .stat-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>

</body>
</html>