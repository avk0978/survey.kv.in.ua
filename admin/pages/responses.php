<?php
/**
 * View all responses page
 * admin/pages/responses.php
 */

$survey_filter = $_GET['survey'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page_num = max(1, intval($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$surveys_query = "SELECT slug, name FROM survey_types WHERE is_active = 1 ORDER BY name";
$stmt = $conn->prepare($surveys_query);
$stmt->execute();
$surveys_list = $stmt->fetchAll();


$where_conditions = [];
$params = [];

if ($survey_filter) {
    $where_conditions[] = "st.slug = :survey_filter";
    $params[':survey_filter'] = $survey_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(sr.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(sr.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($status_filter === 'completed') {
    $where_conditions[] = "sr.is_completed = 1";
} elseif ($status_filter === 'incomplete') {
    $where_conditions[] = "sr.is_completed = 0";
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

$count_query = "SELECT COUNT(*) as total 
               FROM survey_responses sr 
               JOIN survey_types st ON sr.survey_type_id = st.id 
               $where_clause";

$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

$responses_query = "SELECT 
    sr.id,
    sr.response_id,
    st.name as survey_name,
    st.slug as survey_slug,
    sr.answered_questions,
    sr.total_questions,
    sr.completion_percentage,
    sr.is_completed,
    sr.created_at,
    sr.user_ip,
    sr.user_agent
FROM survey_responses sr
JOIN survey_types st ON sr.survey_type_id = st.id
$where_clause
ORDER BY sr.created_at DESC
LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($responses_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$responses = $stmt->fetchAll();

?>

<div class="page-title">📝 Всі відповіді</div>

<div class="filters">
    <form method="GET" class="filter-row">
        <input type="hidden" name="page" value="responses">
        
        <div>
            <label>Тип анкети:</label>
            <select name="survey">
                <option value="">Всі анкети</option>
                <?php foreach ($surveys_list as $survey): ?>
                    <option value="<?= $survey['slug'] ?>" <?= $survey_filter === $survey['slug'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($survey['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label>Від дати:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        
        <div>
            <label>До дати:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        
        <div>
            <label>Статус:</label>
            <select name="status">
                <option value="">Всі</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Завершені</option>
                <option value="incomplete" <?= $status_filter === 'incomplete' ? 'selected' : '' ?>>Незавершені</option>
            </select>
        </div>
        
        <button type="submit" class="btn">Фільтрувати</button>
        <a href="?page=responses" class="btn btn-warning">Скинути</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        📊 Результати фільтрації 
        <?php if ($survey_filter): ?>
            для анкети "<?= htmlspecialchars(array_column($surveys_list, 'name', 'slug')[$survey_filter] ?? $survey_filter) ?>"
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($total_count) ?></div>
                <div class="stat-label">Всього відповідей</div>
            </div>
            <?php
            
            $filter_stats_query = "SELECT 
                COUNT(CASE WHEN sr.is_completed = 1 THEN 1 END) as completed,
                COUNT(CASE WHEN sr.is_completed = 0 THEN 1 END) as incomplete,
                ROUND(AVG(CASE WHEN sr.is_completed = 1 THEN sr.completion_percentage END), 1) as avg_completion
            FROM survey_responses sr 
            JOIN survey_types st ON sr.survey_type_id = st.id 
            $where_clause";
            
            $stmt = $conn->prepare($filter_stats_query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $filter_stats = $stmt->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($filter_stats['completed']) ?></div>
                <div class="stat-label">Завершено</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($filter_stats['incomplete']) ?></div>
                <div class="stat-label">Незавершено</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $filter_stats['avg_completion'] ?? 0 ?>%</div>
                <div class="stat-label">Середнє заповнення</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        📋 Відповіді (сторінка <?= $page_num ?> з <?= $total_pages ?>)
        <div style="float: right;">
            <a href="javascript:void(0)" onclick="exportData('csv', '<?= $survey_filter ?>')" class="btn btn-sm btn-success">💾 Експорт CSV</a>
            <a href="javascript:void(0)" onclick="exportData('json', '<?= $survey_filter ?>')" class="btn btn-sm">💾 Експорт JSON</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($responses)): ?>
            <p>Немає відповідей, що відповідають критеріям фільтрування.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID відповіді</th>
                        <th>Тип анкети</th>
                        <th>Заповнення</th>
                        <th>Статус</th>
                        <th>Дата створення</th>
                        <th>IP адреса</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responses as $response): ?>
                    <tr>
                        <td>
                            <strong><?= substr($response['response_id'], -8) ?></strong><br>
                            <small><?= $response['response_id'] ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($response['survey_name']) ?></strong><br>
                            <small><?= $response['survey_slug'] ?></small>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span><?= $response['answered_questions'] ?>/<?= $response['total_questions'] ?></span>
                                <div class="progress-bar" style="width: 80px;">
                                    <div class="progress-fill" style="width: <?= $response['completion_percentage'] ?>%"></div>
                                </div>
                                <span><?= round($response['completion_percentage']) ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $response['is_completed'] ? 'success' : 'warning' ?>">
                                <?= $response['is_completed'] ? 'Завершено' : 'В процесі' ?>
                            </span>
                        </td>
                        <td>
                            <?= date('d.m.Y H:i:s', strtotime($response['created_at'])) ?>
                        </td>
                        <td>
                            <span title="<?= htmlspecialchars($response['user_agent']) ?>">
                                <?= htmlspecialchars($response['user_ip']) ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="viewResponse('<?= $response['response_id'] ?>')" class="btn btn-sm">👁️ Переглянути</button>
                            <?php if ($response['is_completed']): ?>
                                <a href="?page=statistics&response=<?= $response['response_id'] ?>" class="btn btn-sm btn-success">📊 Аналіз</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <?php
                    $base_url = "?page=responses&survey=" . urlencode($survey_filter) . 
                               "&from=" . urlencode($date_from) . 
                               "&to=" . urlencode($date_to) . 
                               "&status=" . urlencode($status_filter);
                    ?>
                    
                    <?php if ($page_num > 1): ?>
                        <a href="<?= $base_url ?>&p=<?= $page_num - 1 ?>" class="btn btn-sm">← Попередня</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                        <a href="<?= $base_url ?>&p=<?= $i ?>" 
                           class="btn btn-sm <?= $i === $page_num ? 'btn-success' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="<?= $base_url ?>&p=<?= $page_num + 1 ?>" class="btn btn-sm">Наступна →</a>
                    <?php endif; ?>
                    
                    <div style="margin-top: 10px; color: #666;">
                        Показано <?= ($offset + 1) ?>-<?= min($offset + $per_page, $total_count) ?> з <?= $total_count ?> відповідей
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function viewResponse(responseId) {
    try {
        const response = await fetch(`../api/admin-api.php?action=get_response&id=${responseId}`);
        const data = await response.json();
        
        if (data.success) {
            const resp = data.data.response;
            const answers = data.data.answers;
            
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>Інформація про відповідь</h3>
                    <p><strong>ID:</strong> ${resp.response_id}</p>
                    <p><strong>Тип анкети:</strong> ${resp.survey_name}</p>
                    <p><strong>Заповнення:</strong> ${resp.answered_questions}/${resp.total_questions} (${resp.completion_percentage}%)</p>
                    <p><strong>Створено:</strong> ${new Date(resp.created_at).toLocaleString('uk-UA')}</p>
                    <p><strong>IP:</strong> ${resp.user_ip}</p>
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <h3>Відповіді на питання</h3>
                    <table style="width: 100%;">
                        <tr><th>Питання</th><th>Відповідь</th></tr>
            `;
            
            answers.forEach(answer => {
                const answerText = answer.answer_value === 'yes' ? '✅ Так' : 
                                 answer.answer_value === 'no' ? '❌ Ні' : 
                                 answer.answer_value === 'undecided' ? '❔ Не визначився' :
                                 answer.answer_value === 'less5' ? '📅 Менше 5 років' :
                                 answer.answer_value === '5-10' ? '📅 5-10 років' :
                                 answer.answer_value === 'more10' ? '📅 Понад 10 років' :
                                 answer.answer_value;
                content += `<tr><td>${answer.question_key}</td><td>${answerText}</td></tr>`;
            });
            
            content += '</table></div>';
            showModal('Деталі відповіді', content);
        } else {
            alert('Помилка завантаження даних: ' + data.error);
        }
    } catch (error) {
        alert('Помилка: ' + error.message);
    }
}
</script>