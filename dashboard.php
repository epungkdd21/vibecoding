<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$user = get_logged_in_user();
$role = $user['role'];
$students = list_users_by_role('student');
$teachers = list_users_by_role('teacher');
$logPath = APP_ROOT . '/logs/activity.log';
$activityLog = is_file($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES) : [];
$activityLog = array_slice(array_reverse($activityLog), 0, 8);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EduCode</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            padding: 28px 18px 60px;
        }

        .dashboard {
            max-width: 1100px;
            margin: 0 auto;
        }

        .topbar-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 20px;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            margin-bottom: 22px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(96, 165, 250, 0.12);
            color: #dbeafe;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.7rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .card {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            padding: 20px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 1.2rem;
        }

        .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            color: #dfeaf9;
        }

        .action {
            color: white;
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .muted {
            color: #93a4b8;
        }

        @media (max-width: 760px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="topbar-card">
            <div>
                <div class="role-badge"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></div>
                <h1 style="margin: 12px 0 0;">Selamat datang, <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a class="action" href="profile.php">Profil</a>
                <a class="action" href="logout.php">Logout</a>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Ringkasan</h2>
                <p class="muted">Anda masuk sebagai <strong><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                <p class="muted">Fitur autentikasi dasar sudah aktif dan role-based routing sudah diterapkan.</p>
            </div>

            <?php if ($role === 'admin' || $role === 'teacher'): ?>
                <div class="card">
                    <h2>Daftar siswa</h2>
                    <ul class="user-list">
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <li>
                                    <span><?= htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a class="action" href="profile.php?student_id=<?= (int) $student['id']; ?>">Profil</a>
                                        <a class="action" href="workspace.php?student_id=<?= (int) $student['id']; ?>">Workspace</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><span class="muted">Belum ada siswa</span></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Workspace siswa</h2>
                    <p class="muted">Space tempat siswa membuat folder, file, dan project coding.</p>
                    <p><a class="action" href="workspace.php">Buka workspace</a></p>
                </div>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <div class="card">
                    <h2>Manajemen siswa</h2>
                    <a class="action" href="admin.php">Buat akun siswa</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Guru</h2>
                    <ul class="user-list">
                        <?php if ($teachers): ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <li>
                                    <span><?= htmlspecialchars($teacher['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="muted"><?= htmlspecialchars($teacher['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><span class="muted">Tidak ada guru</span></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($role === 'admin' || $role === 'teacher'): ?>
                <div class="card" style="grid-column: 1 / -1;">
                    <h2>Log aktivitas</h2>
                    <ul class="user-list">
                        <?php if ($activityLog): ?>
                            <?php foreach ($activityLog as $entry): ?>
                                <li><span class="muted"><?= htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?></span></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><span class="muted">Belum ada aktivitas.</span></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
