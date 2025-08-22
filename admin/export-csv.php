<?php
/**
 * Separate file for exporting CSV reports
 * admin/export-csv.php
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Доступ заборонено');
}

require_once '../config/database.php';

while (ob_get_level()) {
    ob_end_clean();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
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
    die('Помилка отримання даних: ' . $e->getMessage());
}

$filename = 'osbb_questions_report_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: application/force-download');
header('Content-Type: application/octet-stream');
header('Content-Type: application/download');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// UTF-8 
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

$headers = [
    'Назва питання',
    'Назва анкети',
    'Всього відповідей - Так',
    'Всього відповідей - Ні',
    'Всього відповідей - Не визначився',
    'Загальна кількість відповідей'
];

fputcsv($output, $headers, ',', '"');

foreach ($export_data as $row) {
    $csv_row = [
        $row['question_name'] ?: 'Текст питання не вказано',
        $row['survey_name'] ?: 'Невідома анкета',
        $row['total_yes'] ?: 0,
        $row['total_no'] ?: 0,
        $row['total_undecided'] ?: 0,
        $row['total_responses'] ?: 0
    ];
    
    fputcsv($output, $csv_row, ',', '"');
}

fclose($output);
exit();
?>