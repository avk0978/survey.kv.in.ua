<?php
/**
 * API for administration panel
 * api/admin-api.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_response':
            getResponseDetails($conn);
            break;
            
        case 'export':
            exportData($conn);
            break;
            
        case 'get_stats':
            getStatistics($conn);
            break;
            
        case 'delete_response':
            deleteResponse($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Невідома дія'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Помилка сервера: ' . $e->getMessage()
    ]);
}

function getResponseDetails($conn) {
    $response_id = $_GET['id'] ?? '';
    
    if (empty($response_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID відповіді не вказано'
        ]);
        return;
    }
    
    try {
        $response_query = "
            SELECT 
                sr.*, 
                st.name as survey_name,
                st.slug as survey_slug
            FROM survey_responses sr
            LEFT JOIN survey_types st ON sr.survey_type_id = st.id
            WHERE sr.response_id = ?
        ";
        $response_stmt = $conn->prepare($response_query);
        $response_stmt->execute([$response_id]);
        $response_data = $response_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$response_data) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Відповідь не знайдена'
            ]);
            return;
        }
        
        $answers_query = "
            SELECT 
                qa.question_key,
                qa.question_number,
                qa.answer_value,
                qd.question_text,
                qd.question_order
            FROM question_answers qa
            LEFT JOIN questions_directory qd 
                ON qa.question_key = qd.question_code 
                AND qd.survey_type = ?
            WHERE qa.response_id = ?
            ORDER BY 
                COALESCE(qd.question_order, qa.question_number, 999) ASC,
                qa.question_key ASC
        ";
        $answers_stmt = $conn->prepare($answers_query);
        $answers_stmt->execute([$response_data['survey_slug'], $response_id]);
        $answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_answers = [];
        foreach ($answers as $answer) {
            $formatted_answer = [
                'question_key' => $answer['question_key'],
                'question_number' => $answer['question_number'],
                'question_text' => $answer['question_text'] ?: 'Текст питання не знайдено',
                'answer_value' => $answer['answer_value'],
                'answer_text' => formatAnswerValue($answer['answer_value']),
                'question_order' => $answer['question_order']
            ];
            $formatted_answers[] = $formatted_answer;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'response' => $response_data,
                'answers' => $formatted_answers
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Помилка отримання деталей: ' . $e->getMessage()
        ]);
    }
}


function formatAnswerValue($value) {
    $answer_map = [
        'yes' => '✅ Так',
        'no' => '❌ Ні',
        'undecided' => '🤔 Не визначився',
        'less5' => '📅 Менше 5 років',
        '5-10' => '📆 5-10 років',
        'more10' => '📈 Більше 10 років'
    ];
    
    return $answer_map[$value] ?? $value;
}


function exportData($conn) {
    $format = $_GET['format'] ?? 'csv';
    $survey_type = $_GET['survey_type'] ?? '';
    
    try {
        $query = "
            SELECT 
                sr.response_id,
                sr.survey_type_id,
                st.slug as survey_type_slug,
                st.name as survey_name,
                sr.completion_percentage,
                sr.is_completed,
                sr.total_questions,
                sr.answered_questions,
                sr.started_at,
                sr.completed_at,
                sr.user_ip,
                sr.user_agent,
                sr.created_at
            FROM survey_responses sr
            LEFT JOIN survey_types st ON sr.survey_type_id = st.id
        ";
        
        $params = [];
        if (!empty($survey_type)) {
            $query .= " WHERE st.slug = ?";
            $params[] = $survey_type;
        }
        
        $query .= " ORDER BY sr.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        switch ($format) {
            case 'csv':
                exportCSV($responses);
                break;
            case 'json':
                exportJSON($responses);
                break;
            case 'excel':
                exportExcel($responses);
                break;
            default:
                throw new Exception('Непідтримуваний формат експорту');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Помилка експорту: ' . $e->getMessage()
        ]);
    }
}


function exportCSV($data) {
    $filename = 'osbb_survey_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $headers = [
        'ID Відповіді',
        'Тип Анкети (ID)',
        'Slug Анкети',
        'Назва Анкети',
        'Повнота (%)',
        'Завершено',
        'Всього питань',
        'Відповідано питань',
        'Початок',
        'Завершення',
        'IP Адреса',
        'User Agent',
        'Створено'
    ];
    
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['response_id'],
            $row['survey_type_id'],
            $row['survey_type_slug'],
            $row['survey_name'],
            $row['completion_percentage'] . '%',
            $row['is_completed'] ? 'Так' : 'Ні',
            $row['total_questions'],
            $row['answered_questions'],
            $row['started_at'],
            $row['completed_at'],
            $row['user_ip'],
            $row['user_agent'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
}


function exportJSON($data) {
    $filename = 'osbb_survey_export_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode([
        'export_date' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'data' => $data
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportExcel($data) {
    $filename = 'osbb_survey_export_' . date('Y-m-d_H-i-s') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';
    
    // Заголовки
    echo '<tr>';
    echo '<th>ID Відповіді</th>';
    echo '<th>Тип Анкети</th>';
    echo '<th>Назва Анкети</th>';
    echo '<th>Повнота (%)</th>';
    echo '<th>Завершено</th>';
    echo '<th>Всього питань</th>';
    echo '<th>Відповідано</th>';
    echo '<th>Створено</th>';
    echo '<th>IP Адреса</th>';
    echo '</tr>';
    

    foreach ($data as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['response_id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['survey_type_slug']) . '</td>';
        echo '<td>' . htmlspecialchars($row['survey_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['completion_percentage']) . '%</td>';
        echo '<td>' . ($row['is_completed'] ? 'Так' : 'Ні') . '</td>';
        echo '<td>' . htmlspecialchars($row['total_questions']) . '</td>';
        echo '<td>' . htmlspecialchars($row['answered_questions']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($row['user_ip']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
}


function getStatistics($conn) {
    try {
        $general_stats = [];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM survey_responses");
        $stmt->execute();
        $general_stats['total_responses'] = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as today FROM survey_responses WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $general_stats['today_responses'] = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("
            SELECT 
                st.slug, 
                st.name, 
                COUNT(sr.id) as responses,
                AVG(sr.completion_percentage) as avg_completion
            FROM survey_types st
            LEFT JOIN survey_responses sr ON st.id = sr.survey_type_id
            GROUP BY st.id, st.slug, st.name
            ORDER BY responses DESC
        ");
        $stmt->execute();
        $general_stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $general_stats
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Помилка отримання статистики: ' . $e->getMessage()
        ]);
    }
}

function deleteResponse($conn) {
    $response_id = $_GET['id'] ?? '';
    
    if (empty($response_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID відповіді не вказано'
        ]);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        // Удаляем детальные ответы
        $stmt = $conn->prepare("DELETE FROM question_answers WHERE response_id = ?");
        $stmt->execute([$response_id]);
        
        // Удаляем основную запись
        $stmt = $conn->prepare("DELETE FROM survey_responses WHERE response_id = ?");
        $stmt->execute([$response_id]);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Відповідь успішно видалена'
            ]);
        } else {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Відповідь не знайдена'
            ]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Помилка видалення: ' . $e->getMessage()
        ]);
    }
}
?>