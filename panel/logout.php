<?php
// panel/logout.php
require_once __DIR__ . '/../inc/auth.php';
logout_user();
header('Location: /pnl2/panel/login.php');
exit;