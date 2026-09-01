<?php
// index.php – точка входа, роутинг

session_start();

// Если пользователь не авторизован – показываем форму логина
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/auth.php';
    exit;
}

// Определяем действие по GET-параметрам
$action = $_GET['action'] ?? '';
$view = $_GET['view'] ?? '';

// Обработка специальных команд (logout, change_password, force_change)
if ($action === 'logout' || $action === 'change_password' || isset($_GET['force_change'])) {
    require_once __DIR__ . '/auth.php';
    exit;
}

// Если запрошены логи
if ($view === 'logs' || isset($_GET['logs'])) {
    require_once __DIR__ . '/logs.php';
    exit;
}

// По умолчанию – управление пользователями
require_once __DIR__ . '/users.php';