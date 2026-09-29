<?php

declare(strict_types=1);

require_once __DIR__ . '/workspace.php';

function find_user_by_username(PDO $pdo, string $username): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function login_user(string $username, string $password, string $role): bool
{
    $username = trim($username);
    $role = trim($role);

    if ($username === '' || $password === '' || $role === '') {
        return false;
    }

    $pdo = get_main_db();
    $user = find_user_by_username($pdo, $username);

    if (!$user || $user['role'] !== $role) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];

    if ($user['role'] === 'student') {
        ensure_student_workspace((int) $user['id']);
    }

    log_activity(sprintf('User login successful: %s (%s)', $user['username'], $user['role']));

    return true;
}

function register_user(string $username, string $password, string $role): array
{
    $username = trim($username);
    $role = trim($role);
    $allowedRoles = ['student', 'teacher'];

    if ($username === '' || strlen($password) < 6 || !in_array($role, $allowedRoles, true)) {
        return ['success' => false, 'message' => 'Username tidak boleh kosong, password minimal 6 karakter, dan role harus student atau teacher.'];
    }

    $pdo = get_main_db();
    $existingUser = find_user_by_username($pdo, $username);
    if ($existingUser) {
        return ['success' => false, 'message' => 'Username sudah digunakan.'];
    }

    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)');
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':role' => $role,
    ]);

    log_activity(sprintf('New %s registered: %s', $role, $username));

    return ['success' => true, 'message' => sprintf('%s baru berhasil dibuat.', $role === 'teacher' ? 'Guru' : 'Siswa')];
}

function register_student(string $username, string $password): array
{
    return register_user($username, $password, 'student');
}

function update_user_password(int $userId, string $newPassword, ?string $currentPassword = null): array
{
    $newPassword = trim($newPassword);
    $userId = (int) $userId;

    if ($userId <= 0 || strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Password baru minimal 6 karakter.'];
    }

    $pdo = get_main_db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'User tidak ditemukan.'];
    }

    if ($currentPassword !== null && !password_verify($currentPassword, (string) $user['password_hash'])) {
        return ['success' => false, 'message' => 'Password saat ini tidak valid.'];
    }

    $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute([
        ':password_hash' => $passwordHash,
        ':id' => $userId,
    ]);

    log_activity(sprintf('Password updated for user ID %d', $userId));

    return ['success' => true, 'message' => 'Password berhasil diubah.'];
}

function list_users_by_role(string $role): array
{
    $pdo = get_main_db();
    $stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE role = :role ORDER BY created_at DESC');
    $stmt->execute([':role' => $role]);

    return $stmt->fetchAll();
}
