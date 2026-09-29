<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workspace.php';

header('Content-Type: application/json; charset=UTF-8');

function json_db_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR);
    exit;
}

require_login();

$user = get_logged_in_user();
$targetStudentId = (int) ($_POST['student_id'] ?? $_GET['student_id'] ?? $user['id']);

if ($user['role'] === 'student' && $targetStudentId !== (int) $user['id']) {
    json_db_error(403, 'Anda hanya dapat mengakses database milik sendiri.');
}

if ($user['role'] === 'admin' || $user['role'] === 'teacher') {
    if ($targetStudentId <= 0) {
        json_db_error(400, 'ID siswa tidak valid.');
    }
}

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? 'execute');

try {
    if ($action === 'tables') {
        echo json_encode(['success' => true, 'tables' => list_student_sql_tables($targetStudentId)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action !== 'execute') {
        json_db_error(400, 'Aksi tidak dikenal.');
    }

    $query = (string) ($_POST['query'] ?? $_GET['query'] ?? '');
    $result = execute_student_sql($targetStudentId, $query);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
} catch (Throwable $exception) {
    json_db_error(400, $exception->getMessage());
}
