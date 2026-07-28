<?php
require_once dirname(__DIR__) . '/includes/admin_session.php';
require_once dirname(__DIR__) . '/includes/admin_paths.php';

admin_session_start();

// 1. Oturum süresi (30 dk)
$timeout_duration = 1800;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: ' . admin_url('login.php'));
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// 2. Session fixation — 30 dk'da bir ID yenile
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . admin_url('login.php'));
    exit();
}

if (!isset($_SESSION['failed_attempts'])) {
    $_SESSION['failed_attempts'] = 0;
}

require_once dirname(__DIR__) . '/includes/admin_rbac.php';

if (!array_key_exists('admin_is_super', $_SESSION) || !array_key_exists('admin_menu_permissions', $_SESSION)) {
    if (!isset($pdo)) {
        require_once dirname(__DIR__) . '/db.php';
    }
    if (isset($pdo) && $pdo instanceof PDO && isset($_SESSION['user_id'])) {
        try {
            admin_session_refresh_from_db($pdo, (int) $_SESSION['user_id']);
        } catch (Throwable $e) {
            $_SESSION['admin_is_super'] = true;
            $_SESSION['admin_menu_permissions'] = '[]';
        }
    } elseif (isset($_SESSION['user_id'])) {
        $_SESSION['admin_is_super'] = true;
        $_SESSION['admin_menu_permissions'] = '[]';
    }
}

admin_require_access();
