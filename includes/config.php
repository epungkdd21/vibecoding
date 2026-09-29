<?php

declare(strict_types=1);

const APP_NAME = 'EduCode';
const APP_ROOT = __DIR__ . '/..';
const DB_DIR = APP_ROOT . '/db';
const MAIN_DB_PATH = DB_DIR . '/main_db.sqlite';

function app_url(string $path = ''): string
{
    $base = rtrim((string) ($_SERVER['APP_URL'] ?? 'http://localhost:8000'), '/');
    return $base . '/' . ltrim($path, '/');
}

function ensure_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Gagal membuat direktori: %s', $directory));
    }
}
