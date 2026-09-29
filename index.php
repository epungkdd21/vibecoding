<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

init_main_db();
ensure_default_admin();

if (get_logged_in_user()) {
    redirect('dashboard.php');
}

require_once __DIR__ . '/views/layout.php';
