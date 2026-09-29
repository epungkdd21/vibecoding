<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';

header('Content-Type: application/json; charset=UTF-8');

function json_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR);
    exit;
}

require_login();

$user = get_logged_in_user();
$targetStudentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? $user['id']);
$allowedViewRoles = ['admin', 'teacher'];

if ($user['role'] === 'student' && $targetStudentId !== (int) $user['id']) {
    json_error(403, 'Anda hanya dapat mengakses workspace milik sendiri.');
}

if (in_array($user['role'], $allowedViewRoles, true) && $targetStudentId <= 0) {
    json_error(400, 'ID siswa tidak valid.');
}

if (!in_array($user['role'], ['student', 'admin', 'teacher'], true)) {
    json_error(403, 'Role tidak memiliki akses ke workspace.');
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'tree');
try {
    if ($action === 'tree') {
        echo json_encode(get_student_tree($targetStudentId), JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'read') {
        $relativePath = (string) ($_GET['path'] ?? $_POST['path'] ?? '');
        echo json_encode(read_file_in_workspace($targetStudentId, $relativePath), JSON_THROW_ON_ERROR);
        exit;
    }

    if ($user['role'] === 'student') {
        if ($action === 'create-folder') {
            $parentPath = (string) ($_POST['parent_path'] ?? '');
            $folderName = (string) ($_POST['name'] ?? '');
            echo json_encode(create_directory_in_workspace($targetStudentId, $parentPath, $folderName), JSON_THROW_ON_ERROR);
            exit;
        }

        if ($action === 'create-file') {
            $parentPath = (string) ($_POST['parent_path'] ?? '');
            $fileName = (string) ($_POST['name'] ?? '');
            echo json_encode(create_file_in_workspace($targetStudentId, $parentPath, $fileName), JSON_THROW_ON_ERROR);
            exit;
        }

        if ($action === 'save-file') {
            $relativePath = (string) ($_POST['path'] ?? '');
            $content = (string) ($_POST['content'] ?? '');
            echo json_encode(save_file_in_workspace($targetStudentId, $relativePath, $content), JSON_THROW_ON_ERROR);
            exit;
        }

        if ($action === 'delete-item') {
            $relativePath = (string) ($_POST['path'] ?? '');
            echo json_encode(delete_workspace_item($targetStudentId, $relativePath), JSON_THROW_ON_ERROR);
            exit;
        }
    }

    json_error(403, 'Aksi tidak diizinkan untuk role ini.');
} catch (Throwable $exception) {
    json_error(400, $exception->getMessage());
}
