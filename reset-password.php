<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/logs.php';

$error = '';
$success = '';

// 验证令牌
$token = $_GET['token'] ?? '';
if (empty($token)) {
    $error = "无效的重置链接";
} else {
    // 检查令牌是否有效
    $stmt = $pdo->prepare("SELECT id, username, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "无效的重置链接";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error = "重置链接已过期，请重新申请";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "请填写新密码和确认密码";
    } elseif ($new_password !== $confirm_password) {
        $error = "两次输入的密码不一致";
    } elseif (strlen($new_password) < 6) {
        $error = "密码长度至少6位";
    } else {
        // 更新密码并清除重置令牌
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if ($stmt->execute([$new_password, $user['id']])) {
            $success = "密码重置成功！请使用新密码登录。";
            logAction('PASSWORD_RESET_VIA_LINK', "用户通过重置链接重置密码", $user['id']);
        } else {
            $error = "密码重置失败，请重试";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .reset-container {
            max-width: 500px;
            margin: 80px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn-reset {
            width: 100%;
            background: #3498db;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }
        .btn-reset:hover {
            background: #2980b9;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            display: none;
        }
        .alert.show {
            display: block;
        }
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .btn-small {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn-small:hover {
            background: #2980b9;
        }
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        .token-info {
            background: #e8f4fd;
            border: 1px solid #3498db;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2 style="text-align: center; margin-bottom: 10px; color: #2c3e50;">重置密码</h2>
        
        <!-- 错误消息 -->
        <div class="alert error <?php echo $error ? 'show' : ''; ?>">
            <?php echo $error; ?>
            <?php if (strpos($error, '过期') !== false || strpos($error, '无效') !== false): ?>
                <div style="margin-top: 10px;">
                    <a href="login.php" class="btn-small">返回登录</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 成功消息 -->
        <div class="alert success <?php echo $success ? 'show' : ''; ?>">
            <?php echo $success; ?>
            <p style="text-align: center; margin-top: 15px;">
                <a href="login.php" class="btn-small">立即登录</a>
            </p>
        </div>
        
        <?php if (empty($error) && empty($success)): ?>
            <?php if (isset($user)): ?>
            <div class="token-info">
                <p><strong>用户：</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>有效期：</strong> 此链接将在1小时内有效</p>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="请输入新密码" minlength="6">
                    <div class="password-requirements">密码长度至少6位</div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="请再次输入新密码" minlength="6">
                </div>
                <button type="submit" class="btn-reset">重置密码</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php" style="color: #3498db; text-decoration: none;">← 返回登录页面</a>
        </div>
    </div>

    <script>
        // 密码确认验证
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function validatePassword() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('两次输入的密码不一致');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
            }
        });
    </script>
</body>
</html>