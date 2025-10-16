<?php
require_once 'includes/auth.php';
require_once 'includes/logs.php';

if (isLoggedIn()) {
    // 记录登出日志
    logAction('LOGOUT', "用户登出系统");
}

session_destroy();
header("Location: login.php");
exit();
?>