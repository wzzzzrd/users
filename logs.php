<?php
// logs.php – просмотр журнала действий (только владелец)


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$current_role = $_SESSION['role'] ?? null;
if ($current_role !== 'owner') {
    die('Доступ запрещён.');
}

$logs = [];
try {
    $stmt = $pdo->query("SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.user_id ORDER BY l.created_at DESC LIMIT 200");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Журнал действий</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        a { text-decoration: none; color: #007bff; }
        .tabs { margin: 20px 0; }
        .tabs a { margin-right: 15px; padding: 5px 10px; background: #eee; border-radius: 4px; text-decoration: none; }
        .tabs a.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h2>?? Журнал действий</h2>
        <div>
            <span><?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</span>
            <a href="?logout=1" style="margin-left:20px;">Выйти</a>
        </div>
    </div>

    <div class="tabs">
        <a href="?users">Пользователи</a>
        <a href="?logs" class="active">Логи</a>
        <a href="?action=change_password">Сменить пароль</a>
    </div>

    <?php if (count($logs) > 0): ?>
        <table>
            <thead><tr><th>Дата/время</th><th>Пользователь</th><th>Действие</th><th>Объект</th><th>Подробности</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['username'] ?? $log['user_id']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['object']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Логов пока нет.</p>
    <?php endif; ?>
</body>
</html>
