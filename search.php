<?php
require_once 'includes/auth.php';
require_once 'includes/encryption.php';
require_once 'includes/categories.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();
$categories = getUserCategories($user_id);
$results = [];
$search_term = '';
$username_filter = '';
$owner_filter = '';
$sort_by = 'my_data_first';
$sort_order = 'asc'; 
$all_users = [];
if ($is_admin) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_term = $_POST['search_term'];
    $category_id = $_POST['category_id'];
    $username_filter = $is_admin ? $_POST['username_filter'] : '';
    $owner_filter = $is_admin ? $_POST['owner_filter'] : '';
    if ($is_admin && isset($_POST['sort_by'])) {
        $sort_by = $_POST['sort_by'];
    }
    if ($is_admin && isset($_POST['sort_order'])) {
        $sort_order = $_POST['sort_order'];
    }
    
    if ($is_admin) {
        $sql = "
            SELECT pe.*, c.name as category_name, c.color as category_color, u.username as owner
            FROM password_entries pe 
            LEFT JOIN categories c ON pe.category_id = c.id 
            LEFT JOIN users u ON pe.user_id = u.id
            WHERE pe.platform_name LIKE ?
        ";
        $params = ["%$search_term%"];
        
        // 添加用户名筛选条件
        if (!empty($username_filter)) {
            $sql .= " AND u.username LIKE ?";
            $params[] = "%$username_filter%";
        }
        
        // 添加所有者筛选条件
        if (!empty($owner_filter)) {
            $sql .= " AND u.id = ?";
            $params[] = $owner_filter;
        }
    } else {
        $sql = "
            SELECT pe.*, c.name as category_name, c.color as category_color 
            FROM password_entries pe 
            LEFT JOIN categories c ON pe.category_id = c.id 
            WHERE pe.user_id = ? AND pe.platform_name LIKE ?
        ";
        $params = [$user_id, "%$search_term%"];
    }
    
    if (!empty($category_id)) {
        $sql .= " AND pe.category_id = ?";
        $params[] = $category_id;
    }
    
    // 动态排序逻辑
    if ($is_admin) {
        if ($sort_by === 'my_data_first') {
            $sql .= " ORDER BY CASE WHEN pe.user_id = ? THEN 0 ELSE 1 END, pe.platform_name";
            $params[] = $user_id;
        } else {
            // 其他字段排序
            $sql .= " ORDER BY $sort_by $sort_order";
        }
    } else {
        $sql .= " ORDER BY pe.platform_name";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查询密码-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* 移动端优化样式 */
        @media (max-width: 768px) {
            .search-form .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-group {
                width: 100% !important;
            }
            
            .form-group input, 
            .form-group select {
                width: 100%;
                box-sizing: border-box;
            }
            
            .results-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px;
                padding: 0 15px;
            }
            
            table {
                min-width: 800px;
                font-size: 14px;
            }
            
            table th,
            table td {
                padding: 8px 6px;
                white-space: nowrap;
            }
            
            .mobile-card {
                display: block;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 12px;
                margin-bottom: 10px;
                background: white;
            }
            
            .mobile-card-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
                padding-bottom: 8px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .mobile-card-label {
                font-weight: bold;
                color: #666;
                min-width: 60px;
                margin-right: 10px;
            }
            
            .mobile-card-value {
                flex: 1;
                text-align: right;
                word-break: break-all;
            }
            
            .mobile-actions {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-top: 10px;
            }
            
            .btn-small {
                padding: 4px 8px;
                font-size: 12px;
            }
            
            .category-tag {
                font-size: 11px;
                padding: 2px 6px;
            }
            
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
            
            /* 排序控件在移动端的样式 */
            .search-form .form-row .form-group:nth-last-child(-n+3) {
                width: 48% !important;
                min-width: auto !important;
            }
            
            .search-form .form-row .form-group:last-child {
                width: 100% !important;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-table {
                display: table;
            }
            
            .mobile-cards {
                display: none;
            }
        }
        
        /* 通用样式 */
        .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        .owner-filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .owner-filter-group select {
            flex: 1;
        }
        
        .sort-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>
    <script>
        function decryptPassword(entryId) {
            fetch('decrypt_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'entry_id=' + entryId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新桌面端显示
                    const desktopPassword = document.getElementById('password-' + entryId);
                    const desktopBtn = document.getElementById('btn-' + entryId);
                    const desktopCopy = document.getElementById('copy-' + entryId);
                    
                    if (desktopPassword) {
                        desktopPassword.textContent = data.password;
                        if (desktopBtn) desktopBtn.style.display = 'none';
                        if (desktopCopy) desktopCopy.style.display = 'inline-block';
                    }
                    
                    // 更新移动端显示
                    const mobilePassword = document.getElementById('mobile-password-' + entryId);
                    const mobileBtn = document.getElementById('mobile-btn-' + entryId);
                    const mobileCopy = document.getElementById('mobile-copy-' + entryId);
                    
                    if (mobilePassword) {
                        mobilePassword.textContent = data.password;
                        if (mobileBtn) mobileBtn.style.display = 'none';
                        if (mobileCopy) mobileCopy.style.display = 'inline-block';
                    }
                } else {
                    alert('解密失败: ' + data.error);
                }
            });
        }
        
        function copyPassword(password) {
            navigator.clipboard.writeText(password).then(function() {
                alert('密码已复制到剪贴板');
            }, function(err) {
                console.error('复制失败: ', err);
                // 备用复制方法
                const textArea = document.createElement('textarea');
                textArea.value = password;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('密码已复制到剪贴板');
            });
        }
        
        // 清除所有者筛选
        function clearOwnerFilter() {
            document.querySelector('select[name="owner_filter"]').value = '';
        }
    </script>
</head>
<body>
    <div class="container">
        <header>
            <h1>查询密码</h1>
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

        <div class="search-container">
            <?php if (isset($success)): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="search_term" placeholder="搜索平台名称..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    
                    <?php if ($is_admin): ?>
                    <div class="form-group">
                        <input type="text" name="username_filter" placeholder="按用户名筛选..." value="<?php echo htmlspecialchars($username_filter); ?>">
                    </div>
                    <div class="form-group owner-filter-group">
                        <select name="owner_filter">
                            <option value="">所有用户</option>
                            <?php foreach($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo (isset($_POST['owner_filter']) && $_POST['owner_filter'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $user_id): ?>
                                        (我)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($owner_filter)): ?>
                            <button type="button" onclick="clearOwnerFilter()" class="btn-small" style="background: #95a5a6;">清除</button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 修改后的排序控件：我的数据优先放在第一位 -->
                    <div class="form-group">
                        <select name="sort_by">
                            <option value="my_data_first" <?php echo ($sort_by === 'my_data_first') ? 'selected' : ''; ?>>我的数据优先</option>
                            <option value="platform_name" <?php echo ($sort_by === 'platform_name') ? 'selected' : ''; ?>>按平台名称</option>
                            <option value="pe.created_at" <?php echo ($sort_by === 'pe.created_at') ? 'selected' : ''; ?>>按创建时间</option>
                            <option value="owner" <?php echo ($sort_by === 'owner') ? 'selected' : ''; ?>>按所有者</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="sort_order">
                            <option value="asc" <?php echo ($sort_order === 'asc') ? 'selected' : ''; ?>>升序</option>
                            <option value="desc" <?php echo ($sort_order === 'desc') ? 'selected' : ''; ?>>降序</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <select name="category_id">
                            <option value="">所有分类</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">搜索</button>
                </div>
            </form>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="results">
                    <h2>
                        搜索结果 (<?php echo count($results); ?> 条)
                        <?php if ($is_admin): ?>
                            <span style="font-size: 14px; color: #666; margin-left: 10px;">
                                🔍 查看所有用户数据
                                <?php if (!empty($username_filter)): ?>
                                    | 用户名筛选: <?php echo htmlspecialchars($username_filter); ?>
                                <?php endif; ?>
                                <?php if (!empty($owner_filter)): ?>
                                    <?php 
                                        $selected_owner = '';
                                        foreach($all_users as $user) {
                                            if ($user['id'] == $owner_filter) {
                                                $selected_owner = $user['username'];
                                                break;
                                            }
                                        }
                                    ?>
                                    | 所有者: <?php echo htmlspecialchars($selected_owner); ?>
                                <?php endif; ?>
                                <?php 
                                    $sort_display = [
                                        'my_data_first' => '我的数据优先',
                                        'platform_name' => '平台名称',
                                        'pe.created_at' => '创建时间',
                                        'owner' => '所有者'
                                    ];
                                ?>
                                | 排序: <?php echo $sort_display[$sort_by] . ' ' . ($sort_order === 'asc' ? '↑' : '↓'); ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if (count($results) > 0): ?>
                        <!-- 桌面端表格视图 -->
                        <div class="desktop-table">
                            <div class="results-table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <?php if ($is_admin): ?>
                                                <th>所有者</th>
                                            <?php endif; ?>
                                            <th>平台</th>
                                            <th>网址</th>
                                            <th>用户名</th>
                                            <th>密码</th>
                                            <th>分类</th>
                                            <th>备注</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($results as $entry): ?>
                                        <tr>
                                            <?php if ($is_admin): ?>
                                                <td>
                                                    <span style="font-weight: 600; color: #3498db;">
                                                        <?php echo htmlspecialchars($entry['owner']); ?>
                                                    </span>
                                                    <?php if ($entry['user_id'] == $user_id): ?>
                                                        <br><small style="color: #27ae60;">(我的)</small>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($entry['platform_name']); ?></td>
                                            <td>
                                                <?php if ($entry['website_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($entry['website_url']); ?>" target="_blank" class="btn-small">访问</a>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                            <td>
                                                <span id="password-<?php echo $entry['id']; ?>">••••••••</span>
                                                <button id="btn-<?php echo $entry['id']; ?>" 
                                                        onclick="decryptPassword(<?php echo $entry['id']; ?>)" 
                                                        class="btn-small">
                                                    显示
                                                </button>
                                                <button onclick="copyPassword(document.getElementById('password-<?php echo $entry['id']; ?>').textContent)" 
                                                        class="btn-small" 
                                                        style="display: none;" 
                                                        id="copy-<?php echo $entry['id']; ?>">
                                                    复制
                                                </button>
                                            </td>
                                            <td>
                                                <span class="category-tag" style="background: <?php echo $entry['category_color']; ?>; color: white;">
                                                    <?php echo htmlspecialchars($entry['category_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($entry['notes']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($is_admin || $entry['user_id'] == $user_id): ?>
                                                        <a href="edit_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #3498db;">编辑</a>
                                                        <a href="delete_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #e74c3c;" onclick="return confirm('确定要删除这个账号吗？')">删除</a>
                                                    <?php else: ?>
                                                        <span style="color: #999; font-size: 12px;">只读</span>
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
                            <?php foreach($results as $entry): ?>
                            <div class="mobile-card">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">平台</span>
                                    <span class="mobile-card-value"><?php echo htmlspecialchars($entry['platform_name']); ?></span>
                                </div>
                                
                                <?php if ($is_admin): ?>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">所有者</span>
                                    <span class="mobile-card-value">
                                        <?php echo htmlspecialchars($entry['owner']); ?>
                                        <?php if ($entry['user_id'] == $user_id): ?>
                                            <small style="color: #27ae60;">(我的)</small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">网址</span>
                                    <span class="mobile-card-value">
                                        <?php if ($entry['website_url']): ?>
                                            <a href="<?php echo htmlspecialchars($entry['website_url']); ?>" target="_blank" class="btn-small">访问</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">用户名</span>
                                    <span class="mobile-card-value"><?php echo htmlspecialchars($entry['username']); ?></span>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">密码</span>
                                    <span class="mobile-card-value">
                                        <span id="mobile-password-<?php echo $entry['id']; ?>">••••••••</span>
                                        <button id="mobile-btn-<?php echo $entry['id']; ?>" 
                                                onclick="decryptPassword(<?php echo $entry['id']; ?>)" 
                                                class="btn-small">
                                            显示
                                        </button>
                                        <button onclick="copyPassword(document.getElementById('mobile-password-<?php echo $entry['id']; ?>').textContent)" 
                                                class="btn-small" 
                                                style="display: none;" 
                                                id="mobile-copy-<?php echo $entry['id']; ?>">
                                            复制
                                        </button>
                                    </span>
                                </div>
                                
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">分类</span>
                                    <span class="mobile-card-value">
                                        <span class="category-tag" style="background: <?php echo $entry['category_color']; ?>; color: white;">
                                            <?php echo htmlspecialchars($entry['category_name']); ?>
                                        </span>
                                    </span>
                                </div>
                                
                                <?php if (!empty($entry['notes'])): ?>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">备注</span>
                                    <span class="mobile-card-value"><?php echo htmlspecialchars($entry['notes']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mobile-actions">
                                    <?php if ($is_admin || $entry['user_id'] == $user_id): ?>
                                        <a href="edit_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #3498db;">编辑</a>
                                        <a href="delete_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #e74c3c;" onclick="return confirm('确定要删除这个账号吗？')">删除</a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">只读</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-results">未找到匹配的账号信息。</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>