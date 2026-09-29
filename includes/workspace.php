<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const WORKSPACE_ROOT = __DIR__ . '/../workspace';

function sanitize_workspace_name(string $name): string
{
    $trimmed = trim($name);
    if ($trimmed === '' || strlen($trimmed) > 64) {
        throw new InvalidArgumentException('Nama file atau folder tidak valid.');
    }

    if (preg_match('/^(\.\.?|\.)$/', $trimmed)) {
        throw new InvalidArgumentException('Nama file atau folder tidak boleh berupa . atau ..');
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $trimmed)) {
        throw new InvalidArgumentException('Nama hanya boleh berisi huruf, angka, titik, underscore, dan dash.');
    }

    return $trimmed;
}

function normalize_relative_path(string $relativePath): string
{
    $normalized = str_replace('\\', '/', trim((string) $relativePath));
    $normalized = trim($normalized, '/');

    if ($normalized === '' || $normalized === '.') {
        return '';
    }

    $segments = explode('/', $normalized);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new InvalidArgumentException('Path tidak aman.');
        }

        sanitize_workspace_name($segment);
    }

    return implode('/', $segments);
}

function get_student_workspace_root(int $studentId): string
{
    if ($studentId <= 0) {
        throw new InvalidArgumentException('ID siswa tidak valid.');
    }

    $root = WORKSPACE_ROOT . DIRECTORY_SEPARATOR . (string) $studentId;
    ensure_directory($root);

    return $root;
}

function ensure_student_workspace(int $studentId): string
{
    return get_student_workspace_root($studentId);
}

function resolve_workspace_path(int $studentId, string $relativePath): string
{
    $root = get_student_workspace_root($studentId);
    $normalized = normalize_relative_path($relativePath);

    if ($normalized === '') {
        return $root;
    }

    $candidate = $root . DIRECTORY_SEPARATOR . $normalized;
    $realRoot = realpath($root) ?: $root;
    $realCandidate = realpath(dirname($candidate)) ?: dirname($candidate);

    if (strncmp($realCandidate, $realRoot, strlen($realRoot)) !== 0) {
        throw new InvalidArgumentException('Akses ke path di luar workspace siswa ditolak.');
    }

    return $candidate;
}

function build_directory_tree(string $rootPath): array
{
    $root = realpath($rootPath) ?: $rootPath;
    if (!is_dir($root)) {
        return [];
    }

    $items = [];
    $entries = scandir($root);
    sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $root . DIRECTORY_SEPARATOR . $entry;
        $isDir = is_dir($fullPath);
        $relativePath = ltrim(str_replace($root, '', $fullPath), DIRECTORY_SEPARATOR);
        $items[] = [
            'name' => $entry,
            'type' => $isDir ? 'folder' : 'file',
            'path' => $relativePath,
            'children' => $isDir ? build_directory_tree($fullPath) : [],
        ];
    }

    return $items;
}

function get_student_tree(int $studentId): array
{
    $root = get_student_workspace_root($studentId);

    return [
        'student_id' => $studentId,
        'root' => '',
        'items' => build_directory_tree($root),
    ];
}

function create_directory_in_workspace(int $studentId, string $parentPath, string $folderName): array
{
    $name = sanitize_workspace_name($folderName);
    $parent = resolve_workspace_path($studentId, $parentPath);

    if (!is_dir($parent)) {
        throw new InvalidArgumentException('Folder induk tidak ditemukan.');
    }

    $target = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (is_dir($target)) {
        throw new InvalidArgumentException('Folder sudah ada.');
    }

    if (!mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException('Gagal membuat folder.');
    }

    return ['success' => true, 'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $target)];
}

function create_file_in_workspace(int $studentId, string $parentPath, string $fileName): array
{
    $name = sanitize_workspace_name($fileName);
    $parent = resolve_workspace_path($studentId, $parentPath);

    if (!is_dir($parent)) {
        throw new InvalidArgumentException('Folder induk tidak ditemukan.');
    }

    $target = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (file_exists($target)) {
        throw new InvalidArgumentException('File sudah ada.');
    }

    $handle = fopen($target, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Gagal membuat file.');
    }

    fclose($handle);

    return ['success' => true, 'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $target)];
}

function read_file_in_workspace(int $studentId, string $relativePath): array
{
    $target = resolve_workspace_path($studentId, $relativePath);

    if (!is_file($target)) {
        throw new InvalidArgumentException('File tidak ditemukan.');
    }

    $content = file_get_contents($target);
    if ($content === false) {
        throw new RuntimeException('Gagal membaca file.');
    }

    return [
        'success' => true,
        'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $target),
        'content' => $content,
    ];
}

function save_file_in_workspace(int $studentId, string $relativePath, string $content): array
{
    $target = resolve_workspace_path($studentId, $relativePath);
    $parent = dirname($target);

    if (!is_dir($parent)) {
        throw new InvalidArgumentException('Folder induk tidak ditemukan.');
    }

    $written = file_put_contents($target, $content);
    if ($written === false) {
        throw new RuntimeException('Gagal menyimpan file.');
    }

    return ['success' => true, 'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $target), 'bytes' => $written];
}

function delete_workspace_item(int $studentId, string $relativePath): array
{
    $target = resolve_workspace_path($studentId, $relativePath);

    if (is_dir($target) && !is_link($target)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($target);
    } elseif (is_file($target) || is_link($target)) {
        unlink($target);
    } else {
        throw new InvalidArgumentException('Item tidak ditemukan.');
    }

    return ['success' => true, 'path' => str_replace(WORKSPACE_ROOT . DIRECTORY_SEPARATOR, '', $target)];
}

function get_student_db_path(int $studentId): string
{
    $root = ensure_student_workspace($studentId);
    $dbPath = $root . DIRECTORY_SEPARATOR . 'student_db.sqlite';

    if (!file_exists($dbPath)) {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        unset($pdo);
    }

    return $dbPath;
}

function execute_student_sql(int $studentId, string $query): array
{
    $sql = trim($query);
    if ($sql === '') {
        throw new InvalidArgumentException('Query SQL tidak boleh kosong.');
    }

    $dbPath = get_student_db_path($studentId);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $lowerSql = strtolower($sql);
    $isReadQuery = preg_match('/^\s*(select|pragma|show|describe|desc|explain)\b/', $lowerSql) === 1;

    $statement = $pdo->query($sql);
    if ($statement === false) {
        throw new RuntimeException('Query SQL gagal dieksekusi.');
    }

    if ($isReadQuery) {
        $rows = $statement->fetchAll();
        $columns = $rows ? array_keys($rows[0]) : [];

        return [
            'success' => true,
            'type' => 'read',
            'columns' => $columns,
            'rows' => $rows,
            'query' => $sql,
        ];
    }

    return [
        'success' => true,
        'type' => 'write',
        'affected_rows' => $statement->rowCount(),
        'query' => $sql,
    ];
}

function list_student_sql_tables(int $studentId): array
{
    $dbPath = get_student_db_path($studentId);
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
    $rows = $stmt ? $stmt->fetchAll() : [];

    return array_map(static fn (array $row): string => (string) $row['name'], $rows);
}
