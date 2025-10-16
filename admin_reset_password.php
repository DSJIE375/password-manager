<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/logs.php';

// 检查管理员权限
redirectIfNotLoggedIn();
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// 获取用户信息
$user_id = $_GET['id'] ?? '';
if (empty($user_id)) {
    header("Location: admin_users.php");
    exit();
}

$user = getUserById($user_id);
if (!$user) {
    header("Location: admin_users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_link'])) {
        // 生成重置链接
        $token = generatePasswordResetToken($user_id);
        
        if ($token) {
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
            $success = "密码重置链接已生成！链接有效期1小时。<br><br><strong>重置链接：</strong><br><div class='reset-link'><input type='text' value='{$reset_link}' readonly onclick='this.select()'></div><small>请将此链接发送给用户，用户可以通过该链接重置密码。</small>";
        } else {
            $error = "生成重置链接失败，请重试";
        }
    } elseif (isset($_POST['revoke_link'])) {
        // 撤销重置链接
        if (revokeResetToken($user_id)) {
            $success = "重置链接已撤销";
            logAction('ADMIN_REVOKE_RESET_LINK', "管理员撤销密码重置链接", $user_id, getCurrentUserId());
        } else {
            $error = "撤销重置链接失败";
        }
    }
}

// 检查是否有有效的重置令牌
$has_active_token = !empty($user['reset_token']) && strtotime($user['reset_expires']) > time();
$token_expires = $has_active_token ? $user['reset_expires'] : null;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置用户密码-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .reset-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .user-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .user-info-item {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }
        .user-info-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 100px;
        }
        .user-info-value {
            color: #495057;
        }
        .token-status {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .token-status.active {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        .token-status.expired {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn-primary {
            flex: 1;
            background: #3498db;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-warning {
            flex: 1;
            background: #f39c12;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn-warning:hover {
            background: #e67e22;
        }
        .btn-cancel {
            flex: 1;
            background: #6c757d;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        .btn-cancel:hover {
            background: #5a6268;
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
        .reset-link {
            background: #e8f4fd;
            border: 2px dashed #3498db;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .reset-link input {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 3px;
            font-family: monospace;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        .instructions {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
        }
        .instructions h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        .user-badge {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        .time-info {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2 style="text-align: center; margin-bottom: 10px; color: #2c3e50;">重置用户密码</h2>
        <p style="text-align: center; color: #6c757d; margin-bottom: 30px;">管理员操作 - 为用户生成密码重置链接</p>
        
        <!-- 错误消息 -->
        <div class="alert error <?php echo $error ? 'show' : ''; ?>">
            <?php echo $error; ?>
        </div>
        
        <!-- 成功消息 -->
        <div class="alert success <?php echo $success ? 'show' : ''; ?>">
            <?php echo $success; ?>
        </div>

        <!-- 用户信息 -->
        <div class="user-info">
            <h3 style="margin-top: 0; color: #2c3e50;">用户信息</h3>
            <div class="user-info-item">
                <span class="user-info-label">用户名：</span>
                <span class="user-info-value">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['is_admin']): ?>
                        <span class="admin-badge">管理员</span>
                    <?php else: ?>
                        <span class="user-badge">普通用户</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="user-info-item">
                <span class="user-info-label">邮箱：</span>
                <span class="user-info-value"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="user-info-item">
                <span class="user-info-label">用户ID：</span>
                <span class="user-info-value"><?php echo $user['id']; ?></span>
            </div>
            <div class="user-info-item">
                <span class="user-info-label">注册时间：</span>
                <span class="user-info-value"><?php echo $user['created_at']; ?></span>
            </div>
        </div>

        <!-- 重置令牌状态 -->
        <?php if ($has_active_token): ?>
        <div class="token-status active">
            <h4 style="margin-top: 0; color: #155724;">✅ 有有效的重置链接</h4>
            <p>该用户当前有一个有效的密码重置链接。</p>
            <p class="time-info">链接有效期至：<?php echo date('Y-m-d H:i:s', strtotime($token_expires)); ?></p>
            <form method="POST">
                <button type="submit" name="revoke_link" class="btn-warning" onclick="return confirm('确定要撤销这个重置链接吗？')">
                    撤销重置链接
                </button>
            </form>
        </div>
        <?php elseif (!empty($user['reset_token'])): ?>
        <div class="token-status expired">
            <h4 style="margin-top: 0; color: #721c24;">❌ 重置链接已过期</h4>
            <p>该用户的密码重置链接已过期。</p>
            <p class="time-info">过期时间：<?php echo date('Y-m-d H:i:s', strtotime($user['reset_expires'])); ?></p>
        </div>
        <?php else: ?>
        <div class="token-status">
            <h4 style="margin-top: 0; color: #856404;">ℹ️ 无有效重置链接</h4>
            <p>该用户当前没有有效的密码重置链接。</p>
        </div>
        <?php endif; ?>

        <!-- 操作说明 -->
        <div class="instructions">
            <h4>操作说明：</h4>
            <ul>
                <li>点击"生成重置链接"按钮创建一个有效期1小时的密码重置链接</li>
                <li>将生成的链接发送给用户，用户可以通过该链接重置密码</li>
                <li>重置链接在以下情况下会自动失效：
                    <ul>
                        <li>用户成功重置密码后</li>
                        <li>链接生成后超过1小时</li>
                        <li>管理员手动撤销链接</li>
                    </ul>
                </li>
                <li>每个用户同一时间只能有一个有效的重置链接</li>
            </ul>
        </div>

        <!-- 操作按钮 -->
        <form method="POST">
            <div class="btn-group">
                <button type="submit" name="generate_link" class="btn-primary">
                    🔗 生成重置链接
                </button>
                <?php if ($has_active_token): ?>
                <button type="submit" name="revoke_link" class="btn-warning" onclick="return confirm('确定要撤销这个重置链接吗？')">
                    🔒 撤销重置链接
                </button>
                <?php endif; ?>
                <a href="admin_users.php" class="btn-cancel">返回用户管理</a>
            </div>
        </form>
    </div>

    <script>
        // 自动选择重置链接文本
        document.addEventListener('DOMContentLoaded', function() {
            const resetLinkInput = document.querySelector('.reset-link input');
            if (resetLinkInput) {
                resetLinkInput.select();
            }
        });
    </script>
</body>
</html>