<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function log_activity(string $message): void
{
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf("[%s] %s\n", $timestamp, $message);
    file_put_contents($logDir . '/activity.log', $line, FILE_APPEND | LOCK_EX);
}

function get_main_db(): PDO
{
    ensure_directory(DB_DIR);

    $pdo = new PDO('sqlite:' . MAIN_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}

function init_main_db(): void
{
    $pdo = get_main_db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ("admin", "teacher", "student")),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

function ensure_default_user(string $username, string $password, string $role): void
{
    $pdo = get_main_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND role = :role LIMIT 1');
    $stmt->execute([':username' => $username, ':role' => $role]);

    if ($stmt->fetch()) {
        return;
    }

    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)');
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':role' => $role,
    ]);
}

function ensure_default_admin(): void
{
    ensure_default_user('admin', 'admin123', 'admin');
    ensure_default_user('teacher', 'teacher123', 'teacher');
    ensure_default_user('siswa01', 'siswa123', 'student');
}

function get_logged_in_user(): ?array
{
    if (empty($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function require_login(): void
{
    if (!get_logged_in_user()) {
        redirect('login.php');
    }
}

function require_role(array $roles): void
{
    $user = get_logged_in_user();

    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('dashboard.php');
    }
}
