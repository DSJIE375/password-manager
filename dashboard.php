<?php
require_once 'includes/auth.php';
require_once 'includes/categories.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

// 根据用户权限获取数据
if ($is_admin) {
    // 超级管理员：获取所有用户的数据
    // 获取总账号数
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM password_entries");
    $stmt->execute();
    $total = $stmt->fetch()['total'];
    
    // 获取用户统计
    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users");
    $stmt->execute();
    $user_count = $stmt->fetch()['user_count'];
    
    // 获取分类统计
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category_name,
            c.color as category_color,
            COUNT(pe.id) as count
        FROM password_entries pe
        LEFT JOIN categories c ON pe.category_id = c.id
        GROUP BY c.id, c.name, c.color
        ORDER BY count DESC
    ");
    $stmt->execute();
    $category_stats = $stmt->fetchAll();
    
    // 获取最近添加的条目（所有用户）
    $stmt = $pdo->prepare("
        SELECT pe.id, pe.platform_name, pe.username, c.name as category_name, c.color as category_color, 
               pe.last_updated, u.username as owner
        FROM password_entries pe
        LEFT JOIN categories c ON pe.category_id = c.id
        LEFT JOIN users u ON pe.user_id = u.id
        ORDER BY pe.last_updated DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_entries = $stmt->fetchAll();
    
    // 获取用户列表
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} else {
    // 普通用户：只获取自己的数据
    // 获取总账号数
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM password_entries WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total = $stmt->fetch()['total'];
    
    // 获取分类统计
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category_name,
            c.color as category_color,
            COUNT(pe.id) as count
        FROM password_entries pe
        LEFT JOIN categories c ON pe.category_id = c.id
        WHERE pe.user_id = ?
        GROUP BY c.id, c.name, c.color
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $category_stats = $stmt->fetchAll();
    
    // 获取最近添加的条目
    $stmt = $pdo->prepare("
        SELECT pe.id, pe.platform_name, pe.username, c.name as category_name, c.color as category_color, pe.last_updated 
        FROM password_entries pe
        LEFT JOIN categories c ON pe.category_id = c.id
        WHERE pe.user_id = ? 
        ORDER BY pe.last_updated DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_entries = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码管理仪表盘-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="shortcut icon" href="https://www.dsjie375.cn/imvi/logo/SVG/logo.svg">
</head>
<body>
    <div class="container">
        <header>
            <h1>密码管理系统 - 欢迎, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <?php if ($is_admin): ?>
                <div style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;">
                    🔧 超级管理员
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

        <div class="dashboard">
            <?php if ($is_admin): ?>
                <!-- 超级管理员视图 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>总账号数</h3>
                        <p class="number"><?php echo $total; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>总用户数</h3>
                        <p class="number"><?php echo $user_count; ?></p>
                    </div>
                    <?php foreach($category_stats as $stat): ?>
                    <div class="stat-card">
                        <h3><?php echo htmlspecialchars($stat['category_name']); ?></h3>
                        <p class="number"><?php echo $stat['count']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="">
                    <!-- 最近添加的账号 -->
                    <div class="recent-entries">
                        <h2>最近添加的账号</h2>
                        <?php if (count($recent_entries) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>平台</th>
                                        <th>用户名</th>
                                        <th>分类</th>
                                        <th>所有者</th>
                                        <th>更新时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['platform_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                        <td>
                                            <span class="category-tag" style="background: <?php echo $entry['category_color']; ?>; color: white;">
                                                <?php echo htmlspecialchars($entry['category_name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['owner']); ?></td>
                                        <td><?php echo $entry['last_updated']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-results">还没有任何账号记录</p>
                        <?php endif; ?>
                    </div>

                    <!-- 用户列表 -->
                    <div class="recent-entries">
                        <h2>用户列表</h2>
                        <?php if (count($users) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>注册时间</th>
                                        <th>状态</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            <?php if ($user['id'] == $user_id): ?>
                                                <span style="color: #3498db;">(当前用户)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo $user['created_at']; ?></td>
                                        <td>
                                            <?php 
                                                // 这里可以添加管理员状态检查
                                                $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
                                                $stmt->execute([$user['id']]);
                                                $user_info = $stmt->fetch();
                                                if ($user_info['is_admin']) {
                                                    echo '<span style="color: #e74c3c;">管理员</span>';
                                                } else {
                                                    echo '<span style="color: #27ae60;">普通用户</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-results">还没有用户</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- 普通用户视图 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>总账号数</h3>
                        <p class="number"><?php echo $total; ?></p>
                    </div>
                    <?php foreach($category_stats as $stat): ?>
                    <div class="stat-card">
                        <h3><?php echo htmlspecialchars($stat['category_name']); ?></h3>
                        <p class="number"><?php echo $stat['count']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="recent-entries">
                    <h2>最近添加的账号</h2>
                    <?php if (count($recent_entries) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>平台</th>
                                    <th>用户名</th>
                                    <th>分类</th>
                                    <th>更新时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_entries as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['platform_name']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                    <td>
                                        <span class="category-tag" style="background: <?php echo $entry['category_color']; ?>; color: white;">
                                            <?php echo htmlspecialchars($entry['category_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $entry['last_updated']; ?></td>
                                    <td>
                                        <a href="edit_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #3498db;">编辑</a>
                                        <a href="delete_entry.php?id=<?php echo $entry['id']; ?>" class="btn-small" style="background: #e74c3c;" onclick="return confirm('确定要删除这个账号吗？')">删除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-results">还没有添加任何账号，<a href="add_entry.php">立即添加</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>