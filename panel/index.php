<?php
// panel/index.php
// /pnl2/panel erişildiğinde çalışır. Girişe göre yönlendirir (login -> dashboard, değilse -> login)

require_once __DIR__ . '/../inc/auth.php';

// If user is logged in and not timed out, go to dashboard; otherwise go to login
$user = current_user();
if ($user) {
    // check_session_timeout and update last activity are handled by require_login()
    // but we don't want to force redirect to login if timed out; call check_session_timeout manually
    // (check_session_timeout will perform logout & redirect if timed out)
    if (function_exists('check_session_timeout')) {
        check_session_timeout();
    }
    // Now redirect to dashboard
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}