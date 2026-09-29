<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';

require_login();

$currentUser = get_logged_in_user();
$currentRole = $currentUser['role'];
$requestedStudentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : (int) $currentUser['id'];

if ($currentRole === 'student') {
    $requestedStudentId = (int) $currentUser['id'];
}

if ($requestedStudentId <= 0) {
    redirect('dashboard.php');
}

if ($currentRole !== 'student' && !in_array($currentRole, ['admin', 'teacher'], true)) {
    redirect('dashboard.php');
}

$pdo = get_main_db();
$stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $requestedStudentId]);
$student = $stmt->fetch();

if (!$student) {
    redirect('dashboard.php');
}

$workspaceRoot = get_student_workspace_root((int) $student['id']);
$recentFiles = [];
$folderCount = 0;
$fileCount = 0;

if (is_dir($workspaceRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspaceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            $folderCount++;
            continue;
        }

        $fileCount++;
        $recentFiles[] = [
            'name' => basename($path),
            'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $path),
            'mtime' => filemtime($path) ?: 0,
        ];
    }

    usort($recentFiles, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    $recentFiles = array_slice($recentFiles, 0, 6);
}

$createdAt = date('d M Y', strtotime((string) $student['created_at']));
$passwordMessage = '';
$canResetPassword = false;

if ($currentRole === 'admin') {
    $canResetPassword = true;
} elseif (($currentRole === 'teacher' || $currentRole === 'student') && $student['id'] === (int) $currentUser['id']) {
    $canResetPassword = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password']) && $canResetPassword) {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $requireCurrentPassword = $currentRole !== 'admin';

    $result = update_user_password(
        (int) $student['id'],
        $newPassword,
        $requireCurrentPassword ? $currentPassword : null
    );
    $passwordMessage = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Siswa - EduCode</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            padding: 28px 18px 60px;
        }

        .profile-shell {
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
            grid-template-columns: 1.2fr 0.8fr;
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

        .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .info-box {
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.05);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }

        .info-box label {
            display: block;
            font-size: 0.72rem;
            color: #93a4b8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .info-box strong {
            font-size: 1.05rem;
        }

        .muted {
            color: #93a4b8;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .action {
            color: white;
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .action.secondary {
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .workspace-path {
            word-break: break-all;
            color: #dbeafe;
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

        .field input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            background: rgba(52, 211, 153, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.28);
            color: #d1fae5;
        }

        @media (max-width: 760px) {
            .grid,
            .row {
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
    <div class="profile-shell">
        <div class="topbar-card">
            <div>
                <div class="role-badge"><?= htmlspecialchars($student['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                <h1 style="margin: 12px 0 0;">Profil <?= htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="actions">
                <a class="action secondary" href="dashboard.php">Kembali ke dashboard</a>
                <a class="action" href="workspace.php<?php if ($student['id'] !== (int) $currentUser['id']): ?>?student_id=<?= (int) $student['id']; ?><?php endif; ?>">Buka workspace</a>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Informasi akun</h2>
                <div class="row">
                    <div class="info-box">
                        <label>Username</label>
                        <strong><?= htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="info-box">
                        <label>Role</label>
                        <strong><?= htmlspecialchars($student['role'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="info-box">
                        <label>Terdaftar sejak</label>
                        <strong><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="info-box">
                        <label>Workspace</label>
                        <strong><?= htmlspecialchars((string) $student['id'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Statistik workspace</h2>
                <div class="row">
                    <div class="info-box">
                        <label>Folder</label>
                        <strong><?= (int) $folderCount; ?></strong>
                    </div>
                    <div class="info-box">
                        <label>File</label>
                        <strong><?= (int) $fileCount; ?></strong>
                    </div>
                </div>
                <p class="workspace-path" style="margin-top: 18px;"><span class="muted">Lokasi:</span><br><?= htmlspecialchars($workspaceRoot, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>

        <div class="card" style="margin-top: 18px;">
            <h2>File terbaru</h2>
            <ul class="file-list">
                <?php if ($recentFiles): ?>
                    <?php foreach ($recentFiles as $file): ?>
                        <li>
                            <span><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="muted"><?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><span class="muted">Belum ada file yang dibuat.</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($canResetPassword): ?>
            <div class="card" style="margin-top: 18px;">
                <h2>Ubah password</h2>
                <?php if ($passwordMessage !== ''): ?>
                    <div class="message"><?= htmlspecialchars($passwordMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" action="profile.php<?= $student['id'] !== (int) $currentUser['id'] ? '?student_id=' . (int) $student['id'] : ''; ?>">
                    <?php if ($currentRole !== 'admin'): ?>
                        <div class="field">
                            <label for="current_password">Password saat ini</label>
                            <input id="current_password" name="current_password" type="password" placeholder="Masukkan password lama" required>
                        </div>
                    <?php endif; ?>

                    <div class="field">
                        <label for="new_password">Password baru</label>
                        <input id="new_password" name="new_password" type="password" minlength="6" placeholder="Minimal 6 karakter" required>
                    </div>

                    <button type="submit" class="action">Simpan password</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
