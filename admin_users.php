<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/logs.php';
require_once 'config/database.php';

redirectIfNotLoggedIn();

// 只有管理员可以访问此页面
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 获取所有用户
$stmt = $pdo->prepare("SELECT id, username, email, is_admin, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();

// 处理管理员权限修改
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_admin'])) {
        $target_user_id = $_POST['user_id'];
        $is_admin = $_POST['is_admin'] ? 1 : 0;
        
        // 获取目标用户名
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch();
        $target_username = $target_user ? $target_user['username'] : '未知用户';
        
        // 不能修改自己的管理员状态
        if ($target_user_id != $user_id) {
            $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            if ($stmt->execute([$is_admin, $target_user_id])) {
                $action_text = $is_admin ? '设置为管理员' : '取消管理员';
                $_SESSION['success'] = "用户权限已更新";
                logAction('USER_PERMISSION_CHANGE', "{$action_text} - 目标用户: {$target_username}", $user_id);
            } else {
                $_SESSION['error'] = "更新失败";
            }
        } else {
            $_SESSION['error'] = "不能修改自己的管理员状态";
        }
        header("Location: admin_users.php");
        exit();
    }
    
    // 处理删除用户
    if (isset($_POST['delete_user'])) {
        $target_user_id = $_POST['user_id'];
        $delete_type = $_POST['delete_type']; 
        
        // 不能删除自己
        if ($target_user_id == $user_id) {
            $_SESSION['error'] = "不能删除自己的账号";
            header("Location: admin_users.php");
            exit();
        }
        
        // 获取用户名用于消息
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch();
        
        if (!$target_user) {
            $_SESSION['error'] = "用户不存在";
            header("Location: admin_users.php");
            exit();
        }
        
        $target_username = $target_user['username'];
        
        try {
            if ($delete_type === 'account_and_data') {
                // 删除用户及其所有数据
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM password_entries WHERE user_id = ?");
                $stmt->execute([$target_user_id]);
                $entry_count = $stmt->fetch()['count'];
                
                if ($entry_count > 0) {
                    $stmt = $pdo->prepare("DELETE FROM password_entries WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                }
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE user_id = ? AND user_id != 0");
                $stmt->execute([$target_user_id]);
                $category_count = $stmt->fetch()['count'];
                
                if ($category_count > 0) {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE user_id = ? AND user_id != 0");
                    $stmt->execute([$target_user_id]);
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_user_id]);
                
                $pdo->commit();
                $_SESSION['success'] = "用户 '{$target_username}' 及其所有数据已成功删除（{$entry_count} 条密码记录，{$category_count} 个分类）";
                logAction('USER_DELETE_FULL', "完全删除用户 - 用户名: {$target_username} - 删除条目: {$entry_count} 条 - 删除分类: {$category_count} 个", $user_id);
                
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM password_entries WHERE user_id = ?");
                $stmt->execute([$target_user_id]);
                $entry_count = $stmt->fetch()['count'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE user_id = ? AND user_id != 0");
                $stmt->execute([$target_user_id]);
                $category_count = $stmt->fetch()['count'];
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $result = $stmt->execute([$target_user_id]);
                
                if ($result) {
                    $_SESSION['success'] = "用户 '{$target_username}' 账号已删除，数据保留在系统中（{$entry_count} 条密码记录，{$category_count} 个分类）";
                    logAction('USER_DELETE_ACCOUNT_ONLY', "仅删除用户账号 - 用户名: {$target_username} - 保留条目: {$entry_count} 条 - 保留分类: {$category_count} 个", $user_id);
                } else {
                    $_SESSION['error'] = "删除用户失败，可能是数据库外键约束导致。请先手动处理该用户的数据。";
                }
            }
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = "删除失败: " . $e->getMessage();
            error_log("删除用户错误: " . $e->getMessage());
        }
        
        header("Location: admin_users.php");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $target_user_id = $_GET['delete'];
    
    if ($target_user_id == $user_id) {
        $_SESSION['error'] = "不能删除自己的账号";
        header("Location: admin_users.php");
        exit();
    }
    
    // 获取用户信息用于确认
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch();
    
    if (!$target_user) {
        $_SESSION['error'] = "用户不存在";
        header("Location: admin_users.php");
        exit();
    }
    
    // 获取用户的数据统计
    $stmt = $pdo->prepare("SELECT COUNT(*) as entry_count FROM password_entries WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $entry_count = $stmt->fetch()['entry_count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as category_count FROM categories WHERE user_id = ? AND user_id != 0");
    $stmt->execute([$target_user_id]);
    $category_count = $stmt->fetch()['category_count'];
    
    // 显示确认页面
    $confirm_delete = true;
    $delete_user_id = $target_user_id;
    $delete_username = $target_user['username'];
    $user_entry_count = $entry_count;
    $user_category_count = $category_count;
}

// 显示操作消息
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* 移动端优化样式 */
        @media (max-width: 768px) {
            .users-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px;
                padding: 0 15px;
            }
            
            .desktop-table {
                min-width: 1000px;
                font-size: 14px;
            }
            
            .desktop-table th,
            .desktop-table td {
                padding: 8px 6px;
                white-space: nowrap;
            }
            
            .mobile-user-card {
                display: block;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                background: white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .mobile-user-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .mobile-user-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .mobile-user-label {
                font-weight: bold;
                color: #666;
                min-width: 80px;
                margin-right: 15px;
            }
            
            .mobile-user-value {
                flex: 1;
                text-align: right;
                word-break: break-all;
            }
            
            .mobile-actions {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-top: 15px;
                flex-wrap: wrap;
            }
            
            .btn-small {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
            
            .user-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-number {
                font-size: 1.5em;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-table {
                display: table;
            }
            
            .mobile-cards {
                display: none;
            }
            
            .users-table-container {
                overflow-x: auto;
            }
            
            table {
                width: 100%;
                min-width: 1000px;
            }
        }
        
        /* 通用样式 */
        .users-table-container {
            margin-top: 20px;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .danger-zone {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .danger-zone h3 {
            color: #721c24;
            margin-bottom: 10px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-reset {
            background: #9b59b6;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        
        .btn-reset:hover {
            background: #8e44ad;
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
        
        .delete-option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .delete-option:hover {
            border-color: #3498db;
        }
        
        .delete-option.selected {
            border-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .delete-option h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .data-stats {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .option-icon {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .debug-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            color: #856404;
        }
        
        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .user-badge {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .current-user-badge {
            background: #27ae60;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 5px;
        }
        
        .data-count {
            font-size: 12px;
            color: #666;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-admin {
            background: #e74c3c;
            color: white;
        }
        
        .status-user {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>用户管理</h1>
            <div style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;">
                🔧 超级管理员
            </div>
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
            <!-- 错误消息 -->
            <div class="alert error <?php echo isset($error) ? 'show' : ''; ?>">
                <?php echo $error ?? ''; ?>
            </div>
            
            <!-- 成功消息 -->
            <div class="alert success <?php echo isset($success) ? 'show' : ''; ?>">
                <?php echo $success ?? ''; ?>
            </div>

            <!-- 调试信息 -->
            <?php if (isset($_GET['debug'])): ?>
            <div class="debug-info">
                <strong>调试信息：</strong><br>
                当前用户ID: <?php echo $user_id; ?><br>
                会话用户ID: <?php echo $_SESSION['user_id']; ?><br>
                是否是管理员: <?php echo isAdmin() ? '是' : '否'; ?>
            </div>
            <?php endif; ?>

            <!-- 用户统计 -->
            <div class="user-stats">
                <?php
                // 获取用户统计信息
                $total_users = count($users);
                $admin_count = 0;
                $regular_count = 0;
                
                foreach ($users as $user) {
                    if ($user['is_admin']) {
                        $admin_count++;
                    } else {
                        $regular_count++;
                    }
                }
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">总用户数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $admin_count; ?></div>
                    <div class="stat-label">管理员</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $regular_count; ?></div>
                    <div class="stat-label">普通用户</div>
                </div>
            </div>

            <!-- 删除确认对话框 -->
            <?php if (isset($confirm_delete) && $confirm_delete): ?>
            <div class="danger-zone">
                <h3>⚠️ 确认删除用户</h3>
                <p>您即将删除用户 <strong><?php echo htmlspecialchars($delete_username); ?></strong> (ID: <?php echo $delete_user_id; ?>)</p>
                
                <div class="data-stats">
                    <p><strong>用户数据统计：</strong></p>
                    <ul>
                        <li>密码条目：<?php echo $user_entry_count; ?> 条</li>
                        <li>自定义分类：<?php echo $user_category_count; ?> 个</li>
                    </ul>
                </div>
                
                <p><strong>请选择删除方式：</strong></p>
                
                <div class="delete-option" onclick="selectOption('account_and_data')" id="option_account_and_data">
                    <h4><span class="option-icon">🗑️</span> 删除账号及所有数据</h4>
                    <p><strong>完全清理：</strong>删除用户账号及其所有密码条目和分类</p>
                    <ul>
                        <li>✅ 删除用户账号</li>
                        <li>✅ 删除 <?php echo $user_entry_count; ?> 条密码记录</li>
                        <li>✅ 删除 <?php echo $user_category_count; ?> 个自定义分类</li>
                        <li>🔄 系统将完全清理该用户的所有数据</li>
                    </ul>
                </div>
                
                <div class="delete-option" onclick="selectOption('account_only')" id="option_account_only">
                    <h4><span class="option-icon">👤</span> 仅删除账号</h4>
                    <p><strong>保留数据：</strong>只删除用户账号，保留其密码条目和分类</p>
                    <ul>
                        <li>✅ 删除用户账号</li>
                        <li>📁 保留 <?php echo $user_entry_count; ?> 条密码记录</li>
                        <li>📁 保留 <?php echo $user_category_count; ?> 个自定义分类</li>
                        <li>👀 管理员可继续查看和管理这些数据</li>
                    </ul>
                </div>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" value="<?php echo $delete_user_id; ?>">
                    <input type="hidden" name="delete_type" id="deleteType" value="">
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" name="delete_user" class="btn-danger" id="deleteButton" disabled>确认删除</button>
                        <a href="admin_users.php" class="btn-small">取消</a>
                    </div>
                </form>
            </div>
            
            <script>
                function selectOption(type) {
                    // 更新选项样式
                    document.querySelectorAll('.delete-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    document.getElementById('option_' + type).classList.add('selected');
                    
                    // 设置删除类型
                    document.getElementById('deleteType').value = type;
                    
                    // 启用删除按钮
                    document.getElementById('deleteButton').disabled = false;
                    
                    // 更新按钮文本
                    if (type === 'account_and_data') {
                        document.getElementById('deleteButton').textContent = '确认删除账号及所有数据';
                        document.getElementById('deleteButton').style.background = '#dc3545';
                    } else {
                        document.getElementById('deleteButton').textContent = '确认仅删除账号';
                        document.getElementById('deleteButton').style.background = '#e67e22';
                    }
                }
                
                // 默认选择第一种方式
                document.addEventListener('DOMContentLoaded', function() {
                    selectOption('account_and_data');
                });
            </script>
            <?php endif; ?>

            <h2>系统用户列表</h2>
            
            <?php if (count($users) > 0): ?>
                <!-- 桌面端表格视图 -->
                <div class="desktop-table">
                    <div class="users-table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户名</th>
                                    <th>邮箱</th>
                                    <th>注册时间</th>
                                    <th>状态</th>
                                    <th>数据统计</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): 
                                    // 获取用户的密码条目数量
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as entry_count FROM password_entries WHERE user_id = ?");
                                    $stmt->execute([$user['id']]);
                                    $entry_count = $stmt->fetch()['entry_count'];
                                    
                                    // 获取用户的分类数量
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as category_count FROM categories WHERE user_id = ? AND user_id != 0");
                                    $stmt->execute([$user['id']]);
                                    $category_count = $stmt->fetch()['category_count'];
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['id'] == $user_id): ?>
                                            <span class="current-user-badge">当前用户</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="status-badge status-admin">管理员</span>
                                        <?php else: ?>
                                            <span class="status-badge status-user">普通用户</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="text-align: center;">
                                            <div style="font-weight: bold;"><?php echo $entry_count; ?></div>
                                            <div class="data-count">密码条目</div>
                                            <div style="font-weight: bold; margin-top: 5px;"><?php echo $category_count; ?></div>
                                            <div class="data-count">分类数量</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;">
                                            <?php if ($user['id'] != $user_id): ?>
                                                <!-- 管理员权限切换 -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="is_admin" value="<?php echo $user['is_admin'] ? '0' : '1'; ?>">
                                                    <button type="submit" name="toggle_admin" class="btn-small" 
                                                            style="background: <?php echo $user['is_admin'] ? '#e67e22' : '#3498db'; ?>;"
                                                            onclick="return confirm('确定要<?php echo $user['is_admin'] ? '取消' : '设置'; ?>用户 <?php echo htmlspecialchars($user['username']); ?> 的管理员权限吗？')">
                                                        <?php echo $user['is_admin'] ? '取消管理员' : '设为管理员'; ?>
                                                    </button>
                                                </form>
                                                
                                                <!-- 重置密码 -->
                                                <a href="admin_reset_password.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn-reset">
                                                    🔑 重置密码
                                                </a>
                                                
                                                <!-- 删除用户 -->
                                                <a href="admin_users.php?delete=<?php echo $user['id']; ?>" 
                                                   class="btn-danger">
                                                    删除用户
                                                </a>
                                            <?php else: ?>
                                            <!-- 重置密码 -->
                                                <a href="admin_reset_password.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn-reset">
                                                    🔑 重置密码
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 移动端卡片视图 -->
                <div class="mobile-cards">
                    <?php foreach($users as $user): 
                        // 获取用户的密码条目数量
                        $stmt = $pdo->prepare("SELECT COUNT(*) as entry_count FROM password_entries WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        $entry_count = $stmt->fetch()['entry_count'];
                        
                        // 获取用户的分类数量
                        $stmt = $pdo->prepare("SELECT COUNT(*) as category_count FROM categories WHERE user_id = ? AND user_id != 0");
                        $stmt->execute([$user['id']]);
                        $category_count = $stmt->fetch()['category_count'];
                    ?>
                    <div class="mobile-user-card">
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">用户名</span>
                            <span class="mobile-user-value">
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($user['id'] == $user_id): ?>
                                    <span class="current-user-badge">当前用户</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">用户ID</span>
                            <span class="mobile-user-value"><?php echo $user['id']; ?></span>
                        </div>
                        
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">邮箱</span>
                            <span class="mobile-user-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">注册时间</span>
                            <span class="mobile-user-value"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                        </div>
                        
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">状态</span>
                            <span class="mobile-user-value">
                                <?php if ($user['is_admin']): ?>
                                    <span class="status-badge status-admin">管理员</span>
                                <?php else: ?>
                                    <span class="status-badge status-user">普通用户</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="mobile-user-row">
                            <span class="mobile-user-label">数据统计</span>
                            <span class="mobile-user-value">
                                <div style="text-align: right;">
                                    <div><strong><?php echo $entry_count; ?></strong> 条密码</div>
                                    <div><strong><?php echo $category_count; ?></strong> 个分类</div>
                                </div>
                            </span>
                        </div>
                        
                        <?php if ($user['id'] != $user_id): ?>
                        <div class="mobile-actions">
                            <!-- 管理员权限切换 -->
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="is_admin" value="<?php echo $user['is_admin'] ? '0' : '1'; ?>">
                                <button type="submit" name="toggle_admin" class="btn-small" 
                                        style="background: <?php echo $user['is_admin'] ? '#e67e22' : '#3498db'; ?>;"
                                        onclick="return confirm('确定要<?php echo $user['is_admin'] ? '取消' : '设置'; ?>用户 <?php echo htmlspecialchars($user['username']); ?> 的管理员权限吗？')">
                                    <?php echo $user['is_admin'] ? '取消管理员' : '设为管理员'; ?>
                                </button>
                            </form>
                            
                            <!-- 重置密码 -->
                            <a href="admin_reset_password.php?id=<?php echo $user['id']; ?>" 
                               class="btn-reset">
                                🔑 重置密码
                            </a>
                            
                            <!-- 删除用户 -->
                            <a href="admin_users.php?delete=<?php echo $user['id']; ?>" 
                               class="btn-danger">
                                删除用户
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="mobile-actions">
                        <!-- 重置密码 -->
                        <a href="admin_reset_password.php?id=<?php echo $user['id']; ?>" 
                         class="btn-reset">
                         🔑 重置密码
                         </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <!--<a href="admin_users.php?debug=1" class="btn-small" style="background: #95a5a6;">调试信息</a>-->
                </div>
            <?php else: ?>
                <p class="no-results">还没有用户</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>