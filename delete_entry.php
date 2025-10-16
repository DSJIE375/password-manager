<?php
require_once 'includes/auth.php';
require_once 'includes/logs.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$entry_id = $_GET['id'] ?? 0;

if ($entry_id) {
    if ($is_admin) {
        // 管理员：可以删除任何条目
        $stmt = $pdo->prepare("SELECT platform_name, user_id FROM password_entries WHERE id = ?");
        $stmt->execute([$entry_id]);
    } else {
        // 普通用户：只能删除自己的条目
        $stmt = $pdo->prepare("SELECT platform_name, user_id FROM password_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$entry_id, $user_id]);
    }
    
    $entry = $stmt->fetch();
    
    if ($entry) {
        // 执行删除
        if ($is_admin) {
            $stmt = $pdo->prepare("DELETE FROM password_entries WHERE id = ?");
            $execute_params = [$entry_id];
        } else {
            $stmt = $pdo->prepare("DELETE FROM password_entries WHERE id = ? AND user_id = ?");
            $execute_params = [$entry_id, $user_id];
        }
        
       if ($stmt->execute($execute_params)) {
    if ($is_admin && $entry['user_id'] != $user_id) {
        // 获取被删除条目的所有者用户名
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$entry['user_id']]);
        $owner = $stmt->fetch();
        $_SESSION['success'] = "已删除用户 '{$owner['username']}' 的账号 '{$entry['platform_name']}'";
        logAction('PASSWORD_DELETE', "管理员删除密码条目 - 平台: {$entry['platform_name']} - 所有者: {$owner['username']}", $user_id);
    } else {
        $_SESSION['success'] = "账号 '{$entry['platform_name']}' 已成功删除";
        logAction('PASSWORD_DELETE', "删除密码条目 - 平台: {$entry['platform_name']}", $user_id);
    }
} else {
    $_SESSION['error'] = "删除失败，请重试";
    logAction('PASSWORD_DELETE_FAILED', "删除密码失败 - 平台: {$entry['platform_name']}", $user_id);
}
    } else {
        $_SESSION['error'] = "记录不存在或无权访问";
    }
} else {
    $_SESSION['error'] = "无效的请求";
}

// 重定向回查询页面
header("Location: search.php");
exit();
?>