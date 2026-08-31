<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

$dsn = "pgsql:host=$host;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$message = '';
$messageType = '';
$editUserId = null;
$editUsername = '';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

//УДАЛЕНИЕ
if ($action === 'delete' && isset($_POST['user_id'])) {
    $userId = trim($_POST['user_id']);
    if ($userId !== '') {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $message = 'Пользователь удалён.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Ошибка при удалении: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// РЕДАКТИРОВАНИЕ
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editUserId = trim($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $editUserId]);
        $userData = $stmt->fetch();
        if ($userData) {
            $editUsername = $userData['username'];
        } else {
            $message = 'Пользователь не найден.';
            $messageType = 'error';
            $editUserId = null;
        }
    } catch (PDOException $e) {
        $message = 'Ошибка загрузки данных: ' . $e->getMessage();
        $messageType = 'error';
        $editUserId = null;
    }
}

// ДОБАВЛЕНИЕ И ОБНОВЛЕНИЕ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_DEFAULT);
    $username = trim((string)($_POST['username'] ?? ''));

    // Валидация
    if ($user_id === null || $user_id === '' || strlen($user_id) > 100) {
        $message = 'ID пользователя должен быть указан и не превышать 100 символов.';
        $messageType = 'error';
    } elseif (strlen($username) === 0 || strlen($username) > 100) {
        $message = 'Имя пользователя должно быть от 1 до 100 символов.';
        $messageType = 'error';
    } else {
        try {
            if ($action === 'edit') {
                // Обновление
                $sql = "UPDATE users SET username = :username WHERE user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':username' => $username,
                ]);
                $message = 'Пользователь обновлён!';
                $messageType = 'success';
                // Сбрасываем режим редактирования
                $editUserId = null;
                $editUsername = '';
            } else {
                // Добавление
                $sql = "INSERT INTO users (user_id, username) VALUES (:user_id, :username)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':username' => $username,
                ]);
                $message = 'Пользователь успешно добавлен!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;
            if ($errorCode === 23505) {
                $message = 'Ошибка: пользователь с таким ID уже существует.';
            } else {
                $message = 'Произошла ошибка при сохранении. Попробуйте позже.';
            }
            $messageType = 'error';
        }
    }
}

// СПИСОК ВСЕХ ПОЛЬЗОВАТЕЛЕЙ
$users = [];
try {
    $stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY user_id");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = 'Ошибка загрузки списка пользователей: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .actions a, .actions button {
            margin-right: 5px;
            text-decoration: none;
            padding: 4px 8px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 18px;
        }
        .actions button { background: none; border: none; cursor: pointer; font-size: 18px; }
        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 10px 0; }
        .form-inline input { padding: 6px; width: 200px; }
        .form-inline button { padding: 6px 16px; }
        .add-form { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-top: 10px; }
        .delete-form { display: inline; }
    </style>
</head>
<body>
    <h2>👥 Управление пользователями</h2>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Форма добавления (или редактирования) -->
    <div class="add-form">
        <h3><?= $editUserId ? '✏️ Редактировать пользователя' : '➕ Добавить пользователя' ?></h3>
        <form action="?<?= $editUserId ? 'action=edit' : '' ?>" method="POST">
            <?php if ($editUserId): ?>
                <input type="hidden" name="action" value="edit">
            <?php endif; ?>
            <div class="form-inline">
                <label for="user_id">ID:</label>
                <input type="text" id="user_id" name="user_id" required 
                       value="<?= htmlspecialchars($editUserId ?? '') ?>"
                       <?= $editUserId ? 'readonly' : '' ?>>
                <label for="username">Имя:</label>
                <input type="text" id="username" name="username" required maxlength="100"
                       value="<?= htmlspecialchars($editUsername) ?>">
                <button type="submit"><?= $editUserId ? 'Обновить' : 'Добавить' ?></button>
                <?php if ($editUserId): ?>
                    <a href="?">Отмена</a>
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
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td class="actions">
                            <!-- Редактировать -->
                            <a href="?edit=<?= urlencode($row['user_id']) ?>" title="Редактировать">✏️</a>
                            <!-- Удалить (через POST форму) -->
                            <form action="?action=delete" method="POST" class="delete-form" 
                                  onsubmit="return confirm('Удалить пользователя «<?= htmlspecialchars($row['username']) ?>»?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['user_id']) ?>">
                                <button type="submit" title="Удалить">🗑️</button>
                            </form>
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
