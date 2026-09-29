<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

init_main_db();
ensure_default_admin();

if (get_logged_in_user()) {
    redirect('dashboard.php');
}

$error = '';
$defaultUsername = trim((string) ($_GET['username'] ?? ''));
$defaultPassword = (string) ($_GET['password'] ?? '');
$defaultRole = (string) ($_GET['role'] ?? 'student');
$allowedRoles = ['admin', 'teacher', 'student'];
if (!in_array($defaultRole, $allowedRoles, true)) {
    $defaultRole = 'student';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');

    if (!in_array($role, $allowedRoles, true)) {
        $role = 'student';
    }

    if (login_user($username, $password, $role)) {
        redirect('dashboard.php');
    }

    $error = 'Username, password, atau role tidak valid.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduCode</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-shell {
            width: min(100%, 460px);
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 22px;
            box-shadow: 0 24px 52px rgba(15, 23, 42, 0.45);
            overflow: hidden;
        }

        .auth-header {
            padding: 24px 26px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .auth-header h1 {
            margin: 0;
            font-size: 1.7rem;
        }

        .auth-body {
            padding: 26px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .field label {
            color: #dfeaf9;
            font-weight: 600;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.8);
            color: #e2e8f0;
            font-size: 1rem;
        }

        .field input:focus,
        .field select:focus {
            outline: 2px solid rgba(96, 165, 250, 0.35);
            border-color: rgba(96, 165, 250, 0.7);
        }

        .submit-btn {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            color: white;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }

        .error {
            border-radius: 10px;
            padding: 10px 12px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.32);
            color: #fecaca;
            margin-bottom: 12px;
        }

        .helper {
            font-size: 0.85rem;
            color: #93a4b8;
            margin-top: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-header">
            <span class="badge secondary">EduCode</span>
            <h1>Login Platform</h1>
        </div>

        <div class="auth-body">
            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?= htmlspecialchars($defaultUsername !== '' ? $defaultUsername : ((string) ($_POST['username'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan username" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" value="<?= htmlspecialchars((string) ($_POST['password'] ?? $defaultPassword), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan password" required>
                </div>

                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="student" <?= (($defaultRole === 'student' || (string) ($_POST['role'] ?? '') === 'student') ? 'selected' : ''); ?>>Siswa</option>
                        <option value="teacher" <?= (($defaultRole === 'teacher' || (string) ($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''); ?>>Guru</option>
                        <option value="admin" <?= (($defaultRole === 'admin' || (string) ($_POST['role'] ?? '') === 'admin') ? 'selected' : ''); ?>>Admin</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Masuk</button>
            </form>

            <div class="helper">
                Demo login: <strong>admin/admin123</strong>, <strong>teacher/teacher123</strong>, <strong>siswa01/siswa123</strong>
            </div>
        </div>
    </div>
</body>
</html>
