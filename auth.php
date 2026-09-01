<?php
// auth.php – авторизация, выход, смена пароля

// Подключаем конфиг и функции
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$current_user = $_SESSION['user_id'] ?? null;
$message = '';
$messageType = '';

// Выход
if (isset($_GET['logout'])) {
    if ($current_user) {
        log_action($pdo, $current_user, 'logout', null, 'Выход из системы');
    }
    session_destroy();
    header('Location: ?');
    exit;
}

// Обработка логина
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user_id = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($user_id && $password) {
        $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, force_password_change FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $userData = $stmt->fetch();

        if ($userData && password_verify($password, $userData['password_hash'])) {
            $_SESSION['user_id'] = $userData['user_id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['force_password_change'] = $userData['force_password_change'];

            log_action($pdo, $userData['user_id'], 'login', null, 'Успешный вход');

            if ($userData['force_password_change']) {
                header('Location: ?action=change_password');
                exit;
            }
            header('Location: ?');
            exit;
        } else {
            $message = 'Неверный логин или пароль.';
            $messageType = 'error';
        }
    } else {
        $message = 'Заполните все поля.';
        $messageType = 'error';
    }
}

// Смена пароля (своя или принудительная)
if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
    if (!$current_user) {
        header('Location: ?');
        exit;
    }
    $target_user = $_GET['user'] ?? $current_user;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($new_password) < 6) {
            $message = 'Пароль должен быть не менее 6 символов.';
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Пароли не совпадают.';
            $messageType = 'error';
        } else {
            if ($target_user !== $current_user && $_SESSION['role'] !== 'owner') {
                die('Недостаточно прав.');
            }
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, force_password_change = FALSE, last_password_change = NOW() WHERE user_id = :user_id");
            $stmt->execute([':hash' => $hash, ':user_id' => $target_user]);

            log_action($pdo, $current_user, 'change_password', $target_user, 'Смена пароля');

            if ($target_user === $current_user) {
                $_SESSION['force_password_change'] = false;
                $message = 'Пароль успешно изменён.';
                $messageType = 'success';
                header('Location: ?');
                exit;
            } else {
                $message = "Пароль для пользователя $target_user изменён.";
                $messageType = 'success';
                // Остаёмся на странице, чтобы можно было продолжать
            }
        }
    }

    // Вывод формы смены пароля
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Смена пароля</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        input, button { display: block; width: 100%; margin: 10px 0; padding: 8px; }
        a { text-decoration: none; color: #007bff; }
    </style>
    </head>
    <body>
        <h2>Смена пароля</h2>
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <label>Новый пароль: <input type="password" name="new_password" required></label>
            <label>Подтвердите: <input type="password" name="confirm_password" required></label>
            <button type="submit">Изменить пароль</button>
        </form>
        <a href="?">На главную</a>
    </body>
    </html>
    <?php
    exit;
}

// Если пользователь не авторизован – показываем форму логина
if (!$current_user) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Авторизация</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 40px auto; padding: 20px; }
        .error { color: red; }
        input, button { display: block; width: 100%; margin: 10px 0; padding: 8px; }
    </style>
    </head>
    <body>
        <h2>Вход в систему</h2>
        <?php if ($message): ?>
            <div class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="login" value="1">
            <input type="text" name="user_id" placeholder="Логин" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit">Войти</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Если всё ок – возвращаем управление (роутер продолжит)
return;