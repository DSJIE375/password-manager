<?php
require_once 'includes/auth.php';
require_once 'includes/logs.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 获取当前用户信息
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm_username = $_POST['confirm_username'] ?? '';
    
    if ($confirm_username !== $user['username']) {
        $error = "用户名验证失败，请输入正确的用户名确认注销";
    } else {
        try {
            // 
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            logAction('ACCOUNT_DELETED', "用户自行注销账号: {$user['username']}", $user_id);
                
            if ($stmt->execute([$user_id])) {
                // 记录日志
                
                // 清除会话
                session_destroy();
                
                // 显示成功页面
                $success = true;
            } else {
                $error = "注销失败，请重试";
            }
            
        } catch (Exception $e) {
            $error = "注销失败: " . $e->getMessage();
            error_log("用户注销错误: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注销账号-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .delete-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .danger-zone {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .user-stats {
            background: #e8f4fc;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>注销账号</h1>
            <nav>
    <a href="dashboard.php">仪表盘</a>
    <a href="add_entry.php">登记账号</a>
    <a href="search.php">查询密码</a>
    <a href="manage_categories.php">管理分类</a>
    <?php if (isAdmin()): ?>
        <a href="admin_users.php">用户管理</a>
        <a href="system_logs.php">系统日志</a>
    <?php endif; ?>
    <a href="delete_account.php">注销账号</a>
    <a href="logout.php">退出</a>
</nav>
        </header>

        <div class="delete-container">
            <?php if ($success): ?>
                <div class="alert success">
                    <h3>🎉 账号注销成功</h3>
                    <p><strong>您的账号已成功注销，感谢您使用密码管理系统。</strong>期待您的再次使用，谢谢！</p>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn-primary">返回首页</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="danger-zone">
                    <h3 style="color: #721c24;">⚠️ 危险操作警告</h3>
                    <p>您即将注销您的账号，此操作：</p>
                    <ul>
                        <li>✅ 删除您的用户账号</li>
                        <li>✅ 删除您的的所有密码条目</li>
                        <li>✅ 删除您的的所有分类</li>
                        <li>🔒 您将无法再登录系统</li>
                    </ul>
                    <p style="color: #dc3545; font-weight: bold;">此操作不可撤销！</p>
                </div>

                <div class="user-stats">
                    <h4>📊 您的账户信息</h4>
                    <p><strong>用户名：</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>邮箱：</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="confirm_username">
                            <strong>确认操作</strong><br>
                            请输入您的用户名 <strong><?php echo htmlspecialchars($user['username']); ?></strong> 以确认注销：
                        </label>
                        <input type="text" id="confirm_username" name="confirm_username" required>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="btn-danger" onclick="return confirm('确定要注销账号吗？此操作不可撤销！')">确认注销账号</button>
                        <a href="dashboard.php" class="btn-small">取消</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>