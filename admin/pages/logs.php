<?php
$logs_query = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 100";
$stmt = $conn->prepare($logs_query);
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<div class="page-title">📜 Журнал подій</div>
<div class="card">
    <div class="card-body">
        <table>
            <tr><th>Дата</th><th>Дія</th><th>Деталі</th><th>IP</th></tr>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['details']) ?></td>
                <td><?= htmlspecialchars($log['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>