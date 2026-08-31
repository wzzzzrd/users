<?php
$host = 'localhost';
$db   = 'user_db';
$user = 'postgres';
$pass = 'postgres';

$dsn = "pgsql:host=$host;dbname=postgres";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$message = '';
$messageType = '';
$pdo = null;

try {
    // 1. Подключаемся к стандартной БД postgres
    $pdo = new PDO("pgsql:host=$host;dbname=postgres", $user, $pass, $options);
    
    // 2. Проверяем, существует ли база данных $db
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$db'");
    if (!$stmt->fetchColumn()) {
        $pdo->exec("CREATE DATABASE $db");
        $message = "База данных '$db' создана.";
        $messageType = 'info';
    }
    
    // 3. Переключаемся на базу $db
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 4. Проверяем наличие таблицы users
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (PDOException $e) {
        // Таблицы нет – создаём
        $createSql = "CREATE TABLE users (
            user_id VARCHAR(100) PRIMARY KEY,
            username VARCHAR(100) NOT NULL
        )";
        $pdo->exec($createSql);
        $message = "Таблица 'users' создана.";
        $messageType = 'info';
    }
} catch (PDOException $e) {
    // Ошибка подключения или создания
    $message = 'Ошибка: ' . $e->getMessage();
    $messageType = 'error';
    $pdo = null;
}


// --- ОБРАБОТКА ДЕЙСТВИЙ (только если $pdo существует) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    // 1. Удаление
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delete_id = trim((string)($_POST['user_id'] ?? ''));
        if ($delete_id !== '' && strlen($delete_id) <= 100) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $delete_id]);
                $message = 'Пользователь удалён.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Ошибка удаления.';
                $messageType = 'error';
            }
        } else {
            $message = 'Некорректный ID.';
            $messageType = 'error';
        }
    }

    // 2. Добавление или обновление
    if (isset($_POST['action']) && in_array($_POST['action'], ['add', 'update'])) {
        $user_id = trim((string)($_POST['user_id'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));

        // Валидация
        $errors = [];
        if ($user_id === '' || strlen($user_id) > 100) {
            $errors[] = 'ID пользователя должен быть указан и не превышать 100 символов.';
        }
        if (strlen($username) === 0 || strlen($username) > 100) {
            $errors[] = 'Имя пользователя должно быть от 1 до 100 символов.';
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    // Добавление
                    $sql = "INSERT INTO users (user_id, username) VALUES (:user_id, :username)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':username' => $username,
                    ]);
                    $message = 'Пользователь успешно добавлен!';
                    $messageType = 'success';
                } else {
                    // Обновление (редактирование)
                    $edit_id = trim((string)($_POST['edit_id'] ?? ''));
                    if ($edit_id === '' || strlen($edit_id) > 100) {
                        throw new Exception('Неверный ID для редактирования.');
                    }
                    $sql = "UPDATE users SET username = :username WHERE user_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':user_id' => $edit_id,
                        ':username' => $username,
                    ]);
                    $message = 'Пользователь обновлён!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $errorCode = $e->errorInfo[1] ?? 0;
                if ($errorCode === 23505) { // PostgreSQL unique violation
                    $message = 'Ошибка: пользователь с таким ID уже существует.';
                } else {
                    $message = 'Произошла ошибка при сохранении. Попробуйте позже.';
                }
                $messageType = 'error';
            } catch (Exception $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = implode(' ', $errors);
            $messageType = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($pdo)) {
    // Если POST-запрос, а подключения нет – сообщаем об ошибке
    $message = 'Невозможно выполнить операцию: подключение к базе данных отсутствует.';
    $messageType = 'error';
}

// --- ПОЛУЧЕНИЕ СПИСКА ПОЛЬЗОВАТЕЛЕЙ (только если $pdo существует) ---
$users = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY user_id");
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Таблица ещё не существует – игнорируем
        $users = [];
    }
}

// --- ПОДГОТОВКА ДАННЫХ ДЛЯ РЕДАКТИРОВАНИЯ (если передан GET-параметр edit) ---
$editMode = false;
$editUserId = null;
$editUsername = '';
if (isset($_GET['edit']) && isset($pdo)) {
    $editId = trim((string)$_GET['edit']);
    if ($editId !== '' && strlen($editId) <= 100) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $editId]);
            $userData = $stmt->fetch();
            if ($userData) {
                $editMode = true;
                $editUserId = $userData['user_id'];
                $editUsername = $userData['username'];
            } else {
                $message = 'Пользователь не найден.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Ошибка загрузки данных.';
            $messageType = 'error';
        }
    } else {
        $message = 'Некорректный ID для редактирования.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        label { display: block; margin-top: 10px; }
        input { padding: 6px; width: 250px; }
        button { margin-top: 15px; padding: 8px 16px; cursor: pointer; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .actions a, .actions button { margin-right: 5px; }
        .actions form { display: inline; }
        .btn-edit, .btn-delete { padding: 4px 8px; text-decoration: none; border: none; cursor: pointer; }
        .btn-edit { background: #007bff; color: #fff; border-radius: 4px; }
        .btn-delete { background: #dc3545; color: #fff; border-radius: 4px; }
        .btn-edit:hover { background: #0069d9; }
        .btn-delete:hover { background: #c82333; }
    </style>
</head>
<body>
    <h2>Управление пользователями</h2>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ФОРМА ДОБАВЛЕНИЯ / РЕДАКТИРОВАНИЯ -->
    <h3><?= $editMode ? 'Редактировать пользователя' : 'Добавить пользователя' ?></h3>
    <form action="" method="POST">
        <?php if ($editMode): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="edit_id" value="<?= htmlspecialchars($editUserId) ?>">
            <label for="user_id">ID пользователя (изменять нельзя):</label>
            <input type="text" id="user_id" name="user_id" value="<?= htmlspecialchars($editUserId) ?>" readonly style="background:#e9ecef;">
        <?php else: ?>
            <input type="hidden" name="action" value="add">
            <label for="user_id">ID пользователя:</label>
            <input type="text" id="user_id" name="user_id" required maxlength="100">
        <?php endif; ?>

        <label for="username">Имя пользователя:</label>
        <input type="text" id="username" name="username" required maxlength="100" value="<?= htmlspecialchars($editUsername) ?>">

        <button type="submit"><?= $editMode ? 'Обновить' : 'Сохранить' ?></button>
        <?php if ($editMode): ?>
            <a href="?">Отмена</a>
        <?php endif; ?>
    </form>

    <!-- ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ -->
    <h3>Список пользователей</h3>
    <?php if (isset($pdo) && count($users) > 0): ?>
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
                            <a href="?edit=<?= urlencode($row['user_id']) ?>" class="btn-edit">✏️ Редактировать</a>
                            <form action="" method="POST" onsubmit="return confirm('Удалить пользователя ID <?= htmlspecialchars($row['user_id']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['user_id']) ?>">
                                <button type="submit" class="btn-delete">🗑️ Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Пользователей пока нет.</p>
    <?php endif; ?>
</body>
</html>