<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Repo'da login/session değişkenleriniz farklıysa burayı uyarlayın.
// Bu örnek $_SESSION['user_id'] ve $_SESSION['username'] varsayıyor.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo "<script>window.CURRENT_USER = null;</script>";
} else {
    $uid = (int) $_SESSION['user_id'];
    $uname = addslashes($_SESSION['username']);
    echo "<script>window.CURRENT_USER = { user_id: {$uid}, username: '{$uname}' };</script>";
}
?>
<link rel="stylesheet" href="/assets/css/chat-widget.css">
<script src="/assets/js/chat-widget.js" defer></script>