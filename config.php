<?php
// config.php – подключение к БД и миграция

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

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Устанавливаем клиентскую кодировку UTF-8
    $pdo->exec("SET client_encoding TO 'UTF8'");
} catch (PDOException $e) {
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

// Автоматическая миграция
function migrateDatabase($pdo) {
    // Проверка таблицы users
    $tableExists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name='users'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE users (
                user_id VARCHAR(100) PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255),
                role VARCHAR(20) DEFAULT 'operator',
                force_password_change BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT NOW(),
                last_password_change TIMESTAMP
            )
        ");
        $hash = password_hash('owner123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (user_id, username, password_hash, role) VALUES ('owner', 'Владелец', :hash, 'owner')");
        $stmt->execute([':hash' => $hash]);
    } else {
        $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='users'")->fetchAll(PDO::FETCH_COLUMN);
        $needed = ['password_hash', 'role', 'force_password_change', 'created_at', 'last_password_change'];
        foreach ($needed as $col) {
            if (!in_array($col, $columns)) {
                $type = match($col) {
                    'password_hash' => 'VARCHAR(255)',
                    'role' => "VARCHAR(20) DEFAULT 'operator'",
                    'force_password_change' => 'BOOLEAN DEFAULT FALSE',
                    'created_at' => 'TIMESTAMP DEFAULT NOW()',
                    'last_password_change' => 'TIMESTAMP',
                };
                $pdo->exec("ALTER TABLE users ADD COLUMN $col $type");
            }
        }
        // Проверяем наличие владельца
        $owner = $pdo->query("SELECT 1 FROM users WHERE user_id='owner'")->fetch();
        if (!$owner) {
            $hash = password_hash('owner123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (user_id, username, password_hash, role) VALUES ('owner', 'Владелец', :hash, 'owner')");
            $stmt->execute([':hash' => $hash]);
        }
    }
    // Таблица логов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id SERIAL PRIMARY KEY,
            user_id VARCHAR(100) REFERENCES users(user_id) ON DELETE SET NULL,
            action VARCHAR(100) NOT NULL,
            object VARCHAR(255),
            details TEXT,
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");
}

migrateDatabase($pdo);

// Запускаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
