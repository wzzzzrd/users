<?php
// users.php – управление пользователями (список, добавление, редактирование, удаление)


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$current_user = $_SESSION['user_id'] ?? null;
$current_role = $_SESSION['role'] ?? null;
$message = '';
$messageType = '';

// Проверка роли: доступно admin и owner
if (!in_array($current_role, ['admin', 'owner'])) {
    die('Доступ запрещён.');
}

// Обработка удаления
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
    $userId = trim($_POST['user_id']);
    if ($userId === $current_user) {
        $message = 'Нельзя удалить самого себя.';
        $messageType = 'error';
    } elseif ($userId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            log_action($pdo, $current_user, 'delete_user', $userId, "Удалён пользователь $userId");
            $message = 'Пользователь удалён.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Ошибка при удалении: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Загрузка данных для редактирования
$editUserId = null;
$editUsername = '';
$editRole = '';
$editForceChange = false;

if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editUserId = trim($_GET['edit']);
    $stmt = $pdo->prepare("SELECT user_id, username, role, force_password_change FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $editUserId]);
    $userData = $stmt->fetch();
    if ($userData) {
        $editUsername = $userData['username'];
        $editRole = $userData['role'];
        $editForceChange = $userData['force_password_change'];
    } else {
        $message = 'Пользователь не найден.';
        $messageType = 'error';
        $editUserId = null;
    }
}

// Добавление / обновление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $user_id = trim($_POST['user_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $password = $_POST['password'] ?? '';
    $force_change = isset($_POST['force_change']) ? true : false;

    if (!$user_id || strlen($user_id) > 100) {
        $message = 'ID пользователя обязателен и не более 100 символов.';
        $messageType = 'error';
    } elseif (strlen($username) < 1 || strlen($username) > 100) {
        $message = 'Имя должно быть от 1 до 100 символов.';
        $messageType = 'error';
    } else {
        if ($editUserId) {
            // Обновление
            if ($current_role !== 'owner' && $role !== $_POST['old_role']) {
                $message = 'Только владелец может менять роли.';
                $messageType = 'error';
            } else {
                $sql = "UPDATE users SET username = :username, role = :role, force_password_change = :force_change WHERE user_id = :user_id";
                $params = [
                    ':username' => $username,
                    ':role' => $role,
                    ':force_change' => $force_change ? 'true' : 'false',
                    ':user_id' => $user_id,
                ];
                if (!empty($password)) {
                    $sql = "UPDATE users SET username = :username, role = :role, password_hash = :hash, force_password_change = :force_change, last_password_change = NOW() WHERE user_id = :user_id";
                    $params[':hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                log_action($pdo, $current_user, 'edit_user', $user_id, "Изменены данные пользователя $user_id");
                $message = 'Пользователь обновлён!';
                $messageType = 'success';
                // Сброс режима редактирования
                $editUserId = null;
                $editUsername = '';
                $editRole = '';
                $editForceChange = false;
                // Перенаправляем, чтобы избежать повторной отправки
                header('Location: ?users');
                exit;
            }
        } else {
            // Добавление нового
            $check = $pdo->prepare("SELECT 1 FROM users WHERE user_id = :user_id");
            $check->execute([':user_id' => $user_id]);
            if ($check->fetch()) {
                $message = 'Пользователь с таким ID уже существует.';
                $messageType = 'error';
            } else {
                if (empty($password)) {
                    $password = bin2hex(random_bytes(6));
                    $force_change = true;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (user_id, username, password_hash, role, force_password_change, created_at) VALUES (:user_id, :username, :hash, :role, :force_change, NOW())");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':username' => $username,
                    ':hash' => $hash,
                    ':role' => $role,
                    ':force_change' => $force_change ? 'true' : 'false',
                ]);
                log_action($pdo, $current_user, 'add_user', $user_id, "Добавлен пользователь $user_id");
                $message = "Пользователь добавлен! Пароль: $password";
                $messageType = 'success';
                // Перенаправление, чтобы очистить POST
                header('Location: ?users');
                exit;
            }
        }
    }
}

// Получение списка пользователей
$users = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, role, force_password_change, last_password_change FROM users ORDER BY user_id");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = 'Ошибка загрузки списка пользователей: ' . $e->getMessage();
    $messageType = 'error';
}

// Вывод HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .actions a, .actions button { margin-right: 5px; text-decoration: none; padding: 4px 8px; border: none; background: none; cursor: pointer; font-size: 18px; }
        .actions button { background: none; border: none; cursor: pointer; font-size: 18px; }
        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 10px 0; }
        .form-inline input, .form-inline select { padding: 6px; }
        .add-form { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-top: 10px; }
        .delete-form { display: inline; }
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; }
        .role-owner { background: #ffc107; color: #000; }
        .role-admin { background: #17a2b8; color: #fff; }
        .role-operator { background: #6c757d; color: #fff; }
        .status-ok { color: green; }
        .status-warn { color: orange; }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .user-info { font-size: 0.9em; }
        .logout { margin-left: 20px; }
        .tabs { margin: 20px 0; }
        .tabs a { margin-right: 15px; padding: 5px 10px; background: #eee; border-radius: 4px; text-decoration: none; }
        .tabs a.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h2>?? Управление пользователями</h2>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($current_role) ?>)</span>
            <a href="?logout=1" class="logout">Выйти</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?users" class="active">Пользователи</a>
        <?php if ($current_role === 'owner'): ?>
            <a href="?logs">Логи</a>
        <?php endif; ?>
        <?php if ($current_role === 'owner' || $current_role === 'admin'): ?>
            <a href="?action=change_password">Сменить пароль</a>
        <?php endif; ?>
    </div>

    <!-- Форма добавления/редактирования -->
    <div class="add-form">
        <h3><?= $editUserId ? '?? Редактировать пользователя' : '? Добавить пользователя' ?></h3>
        <form method="POST" action="?users">
            <input type="hidden" name="save_user" value="1">
            <?php if ($editUserId): ?>
                <input type="hidden" name="old_role" value="<?= htmlspecialchars($editRole) ?>">
            <?php endif; ?>
            <div class="form-inline">
                <label for="user_id">ID:</label>
                <input type="text" id="user_id" name="user_id" required
                       value="<?= htmlspecialchars($editUserId ?? '') ?>"
                       <?= $editUserId ? 'readonly' : '' ?>>
                <label for="username">Имя:</label>
                <input type="text" id="username" name="username" required maxlength="100"
                       value="<?= htmlspecialchars($editUsername) ?>">
                <?php if ($editUserId && $current_role === 'owner'): ?>
                    <label for="role">Роль:</label>
                    <select id="role" name="role">
                        <option value="owner" <?= $editRole === 'owner' ? 'selected' : '' ?>>Владелец</option>
                        <option value="admin" <?= $editRole === 'admin' ? 'selected' : '' ?>>Администратор</option>
                        <option value="operator" <?= $editRole === 'operator' ? 'selected' : '' ?>>Оператор</option>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="role" value="<?= $editRole ?: 'operator' ?>">
                <?php endif; ?>
                <?php if ($editUserId && $current_role === 'owner'): ?>
                    <label><input type="checkbox" name="force_change" <?= $editForceChange ? 'checked' : '' ?>> Принудительная смена пароля</label>
                <?php endif; ?>
                <?php if (!$editUserId): ?>
                    <label for="password">Пароль (оставьте пустым для генерации):</label>
                    <input type="text" id="password" name="password">
                <?php endif; ?>
                <button type="submit"><?= $editUserId ? 'Обновить' : 'Добавить' ?></button>
                <?php if ($editUserId): ?>
                    <a href="?users">Отмена</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Таблица пользователей -->
    <?php if (count($users) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Роль</th>
                    <th>Статус пароля</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td>
                            <span class="role-badge role-<?= $row['role'] ?>">
                                <?= htmlspecialchars($row['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['force_password_change']): ?>
                                <span class="status-warn">?? Требуется смена</span>
                            <?php else: ?>
                                <span class="status-ok">? Установлен</span>
                                <?php if ($row['last_password_change']): ?>
                                    <br><small>с <?= htmlspecialchars(date('d.m.Y', strtotime($row['last_password_change']))) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($current_role === 'admin' || $current_role === 'owner'): ?>
                                <a href="?edit=<?= urlencode($row['user_id']) ?>" title="Редактировать">??</a>
                                <?php if ($row['user_id'] !== $current_user): ?>
                                    <form action="?users" method="POST" class="delete-form" onsubmit="return confirm('Удалить пользователя?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['user_id']) ?>">
                                        <button type="submit" title="Удалить">???</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($current_role === 'owner' && $row['user_id'] !== $current_user): ?>
                                <a href="?force_change=<?= urlencode($row['user_id']) ?>" title="Принудительная смена пароля" onclick="return confirm('Установить принудительную смену пароля?')">??</a>
                                <a href="?action=change_password&user=<?= urlencode($row['user_id']) ?>" title="Сменить пароль за пользователя">??</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Пользователи не найдены.</p>
    <?php endif; ?>
</body>
</html>
