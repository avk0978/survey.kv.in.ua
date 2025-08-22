<?php
// admin/api/populate-questions.php

session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $questions_dir = new QuestionsDirectory($conn);
    
    $survey_files = [
        'universal_survey' => '../../form/universal_survey.html',
        'residents' => '../../form/residents.html',
        'osbb_heads' => '../../form/osbb_heads.html',
        'osbb_members_board' => '../../form/osbb_members_board.html',
        'audit_commission' => '../../form/audit_commission.html',
        'managers' => '../../form/managers.html',
        'oms_representatives' => '../../form/oms_representatives.html',
        'legal_experts' => '../../form/legal_experts.html'
    ];
    
    $total_saved = 0;
    $results = [];
    
    foreach ($survey_files as $survey_type => $file_path) {
        $result = [
            'survey_type' => $survey_type,
            'file_exists' => false,
            'questions_found' => 0,
            'questions_saved' => 0,
            'error' => null
        ];
        
        try {
            if (file_exists($file_path)) {
                $result['file_exists'] = true;
                $html_content = file_get_contents($file_path);
                
                if ($html_content) {
                    $questions = $questions_dir->parseQuestionsFromHTML($html_content, $survey_type);
                    $result['questions_found'] = count($questions);
                    
                    if (!empty($questions)) {
                        $saved = $questions_dir->saveQuestionsFromSurvey($survey_type, $questions);
                        $result['questions_saved'] = $saved;
                        $total_saved += $saved;
                    }
                } else {
                    $result['error'] = 'Cannot read file content';
                }
            } else {
                $result['error'] = 'File not found: ' . $file_path;
            