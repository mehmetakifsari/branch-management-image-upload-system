<?php
// panel/logout.php
require_once __DIR__ . '/../inc/auth.php';
logout_user();
header('Location: ../panel/login.php');
exit;