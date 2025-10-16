<?php
require_once 'includes/auth.php';
require_once 'includes/encryption.php';
require_once 'includes/categories.php';
require_once 'includes/logs.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$categories = getUserCategories($user_id);
$error = '';
$success = '';

// 获取要编辑的条目
$entry_id = $_GET['id'] ?? 0;
$entry = null;

if ($entry_id) {
    if ($is_admin) {
        // 管理员：可以编辑任何条目
        $stmt = $pdo->prepare("
            SELECT pe.*, c.name as category_name 
            FROM password_entries pe 
            LEFT JOIN categories c ON pe.category_id = c.id 
            WHERE pe.id = ?
        ");
        $stmt->execute([$entry_id]);
    } else {
        // 普通用户：只能编辑自己的条目
        $stmt = $pdo->prepare("
            SELECT pe.*, c.name as category_name 
            FROM password_entries pe 
            LEFT JOIN categories c ON pe.category_id = c.id 
            WHERE pe.id = ? AND pe.user_id = ?
        ");
        $stmt->execute([$entry_id, $user_id]);
    }
    
    $entry = $stmt->fetch();
    
    if (!$entry) {
        die("记录不存在或无权访问");
    }
    
    // 解密密码用于编辑
    try {
        $entry['password'] = decryptPassword($entry['encrypted_password'], $entry['user_id']);
    } catch (Exception $e) {
        $error = "密码解密失败";
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $platform_name = $_POST['platform_name'];
    $website_url = $_POST['website_url'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $category_id = $_POST['category_id'];
    $notes = $_POST['notes'];
    
    if (empty($platform_name) || empty($username) || empty($password)) {
        $error = "请填写必填字段";
    } else {
        // 使用条目的原始用户ID进行加密（保持所有者不变）
        $target_user_id = $entry['user_id'];
        $encrypted_password = encryptPassword($password, $target_user_id);
        
        if ($is_admin) {
            // 管理员：可以更新任何条目
            $stmt = $pdo->prepare("
                UPDATE password_entries 
                SET platform_name = ?, website_url = ?, username = ?, encrypted_password = ?, category_id = ?, notes = ?, last_updated = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $execute_params = [$platform_name, $website_url, $username, $encrypted_password, $category_id, $notes, $entry_id];
        } else {
            // 普通用户：只能更新自己的条目
            $stmt = $pdo->prepare("
                UPDATE password_entries 
                SET platform_name = ?, website_url = ?, username = ?, encrypted_password = ?, category_id = ?, notes = ?, last_updated = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $execute_params = [$platform_name, $website_url, $username, $encrypted_password, $category_id, $notes, $entry_id, $user_id];
        }
        
        if ($stmt->execute($execute_params)) {
            $success = "账号信息已成功更新！";
            // 记录编辑密码日志
    logAction('PASSWORD_EDIT', "编辑密码条目 - 平台: {$platform_name}", $user_id);
            // 重新获取更新后的数据
            if ($is_admin) {
                $stmt = $pdo->prepare("SELECT * FROM password_entries WHERE id = ?");
                $stmt->execute([$entry_id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM password_entries WHERE id = ? AND user_id = ?");
                $stmt->execute([$entry_id, $user_id]);
            }
            $entry = $stmt->fetch();
            $entry['password'] = $password; // 显示明文密码
        } else {
            $error = "更新失败，请重试！";
            logAction('PASSWORD_EDIT_FAILED', "编辑密码失败 - 平台: {$platform_name}", $user_id);
        }
        
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑账号-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>编辑账号信息</h1>
            <?php if ($is_admin): ?>
                <div style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;">
                    🔧 超级管理员模式
                </div>
            <?php endif; ?>
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

        <div class="form-container">
            <?php if ($success): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($entry): ?>
                <?php if ($is_admin && $entry['user_id'] != $user_id): ?>
                    <div class="alert" style="background: #fff3cd; border-color: #ffeaa7; color: #856404;">
                        <strong>管理员模式：</strong> 您正在编辑用户 <strong><?php 
                            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                            $stmt->execute([$entry['user_id']]);
                            $owner = $stmt->fetch();
                            echo htmlspecialchars($owner['username']);
                        ?></strong> 的账号信息
                    </div>
                <?php endif; ?>
                
            <form method="POST" class="entry-form">
                <div class="form-group">
                    <label for="platform_name">平台/网站名称 *</label>
                    <input type="text" id="platform_name" name="platform_name" value="<?php echo htmlspecialchars($entry['platform_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="website_url">网址</label>
                    <input type="url" id="website_url" name="website_url" value="<?php echo htmlspecialchars($entry['website_url'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="username">用户名/邮箱 *</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($entry['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">密码 *</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($entry['password']); ?>" required style="flex: 1;">
                        <button type="button" onclick="togglePassword()" class="btn-small">显示/隐藏</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category_id">分类 *</label>
                    <div class="category-select-container">
                        <select id="category_id" name="category_id" required>
                            <option value="">请选择分类</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($entry['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <?php if ($cat['user_id'] == 0): ?> (系统)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="manage_categories.php" class="btn-small">管理分类</a>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">备注</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($entry['notes'] ?? ''); ?></textarea>
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="submit" class="btn-primary">更新账号信息</button>
                    <a href="search.php" class="btn-small">返回查询</a>
                </div>
            </form>
            <?php else: ?>
                <div class="alert error">未找到要编辑的记录</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</body>
</html>