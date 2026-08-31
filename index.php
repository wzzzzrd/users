<?php
$host = 'localhost';
$db   = 'user_db';
$user = 'postgres';     
$pass = 'postgres';              


$dsn = "pgsql:host=$host;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $pdo = new PDO($dsn, $user, $pass, $options);

            $sql = "INSERT INTO users (user_id, username) VALUES (:user_id, :username)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':username' => $username,
            ]);

            $message = 'Пользователь успешно добавлен!';
            $messageType = 'success';
        } catch (PDOException $e) {
            // Обработка ошибок БД
            $errorCode = $e->errorInfo[1] ?? 0;
            if ($errorCode === 23505) { // PostgreSQL unique violation
                $message = 'Ошибка: пользователь с таким ID уже существует.';
            } else {
                 $message = 'Произошла ошибка при сохранении. Попробуйте позже.';
            }
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Ввод ID и имени пользователя</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        label { display: block; margin-top: 10px; }
        input { padding: 6px; width: 250px; }
        button { margin-top: 15px; padding: 8px 16px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <h2>Добавить пользователя</h2>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <label for="user_id">ID пользователя:</label>
        <input type="text" id="user_id" name="user_id" required min="1">

        <label for="username">Имя пользователя:</label>
        <input type="text" id="username" name="username" required maxlength="50">

        <button type="submit">Сохранить</button>
    </form>
</body>
</html>

