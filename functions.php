<?php
// functions.php – общие функции

function log_action($pdo, $user_id, $action, $object = null, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, object, details) VALUES (:user_id, :action, :object, :details)");
    $stmt->execute([
        ':user_id' => $user_id,
        ':action'  => $action,
        ':object'  => $object,
        ':details' => $details,
    ]);
}

function check_role($required_role, $current_role) {
    $roles_hierarchy = ['operator' => 1, 'admin' => 2, 'owner' => 3];
    if (!isset($roles_hierarchy[$current_role]) || $roles_hierarchy[$current_role] < $roles_hierarchy[$required_role]) {
        die('Доступ запрещён.');
    }
}

// Функция для вывода сообщений (можно использовать в любом файле)
function show_message($message, $type = 'info') {
    if ($message) {
        $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info');
        echo "<div class=\"message $class\">" . htmlspecialchars($message) . "</div>";
    }
}