<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
require_role(['admin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');
    $allowedRoles = ['student', 'teacher'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'student';
    }

    $result = register_user($username, $password, $role);
    $message = $result['message'];
}

$students = list_users_by_role('student');
$teachers = list_users_by_role('teacher');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - EduCode</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            padding: 28px 18px 60px;
        }

        .admin-shell {
            max-width: 980px;
            margin: 0 auto;
        }

        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 18px;
        }

        .panel {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            padding: 20px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .field label {
            font-weight: 600;
            color: #e2e8f0;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
        }

        .primary-btn,
        .secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 12px 16px;
            text-decoration: none;
            font-weight: 700;
            border: 0;
            cursor: pointer;
        }

        .primary-btn {
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            color: white;
        }

        .secondary-btn {
            background: rgba(148, 163, 184, 0.1);
            color: white;
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            background: rgba(52, 211, 153, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.28);
            color: #d1fae5;
        }

        .students-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .students-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        @media (max-width: 760px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }

            .top-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <div class="top-row">
            <div>
                <span class="badge secondary">Admin Mode</span>
                <h1 style="margin: 12px 0 0;">Registrasi akun</h1>
            </div>
            <div>
                <a class="secondary-btn" href="dashboard.php">Kembali</a>
            </div>
        </div>

        <div class="panel-grid">
            <div class="panel">
                <?php if ($message !== ''): ?>
                    <div class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" action="admin.php">
                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="student">Siswa</option>
                            <option value="teacher">Guru</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" placeholder="contoh: siswa01 atau guru01" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="Minimal 6 karakter" required>
                    </div>

                    <button type="submit" class="primary-btn">Daftarkan akun</button>
                </form>
            </div>

            <div class="panel">
                <h2 style="margin-top: 0;">Daftar akun</h2>
                <h3 style="margin-top: 0; color: #dfeaf9;">Siswa</h3>
                <ul class="students-list">
                    <?php if ($students): ?>
                        <?php foreach ($students as $student): ?>
                            <li>
                                <span><?= htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span style="display:flex; gap:8px; align-items:center;">
                                    <a class="secondary-btn" href="profile.php?student_id=<?= (int) $student['id']; ?>" style="padding:8px 10px; font-size:0.82rem;">Profil</a>
                                    <span style="color: #93a4b8;"><?= htmlspecialchars($student['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span style="color: #93a4b8;">Belum ada siswa.</span></li>
                    <?php endif; ?>
                </ul>

                <h3 style="margin-top: 20px; color: #dfeaf9;">Guru</h3>
                <ul class="students-list">
                    <?php if ($teachers): ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <li>
                                <span><?= htmlspecialchars($teacher['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span style="display:flex; gap:8px; align-items:center;">
                                    <a class="secondary-btn" href="profile.php?student_id=<?= (int) $teacher['id']; ?>" style="padding:8px 10px; font-size:0.82rem;">Profil</a>
                                    <span style="color: #93a4b8;"><?= htmlspecialchars($teacher['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span style="color: #93a4b8;">Belum ada guru.</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
