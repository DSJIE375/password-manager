<?php
require_once 'includes/auth.php';
require_once 'includes/logs.php';
redirectIfNotLoggedIn();

// 只有管理员可以访问此页面
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 分页设置
$limit = 50;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;

// 获取日志
$logs = getSystemLogs($limit, $offset);
$total_logs = getLogsCount();
$total_pages = ceil($total_logs / $limit);

// 清空日志功能
if (isset($_POST['clear_logs'])) {
    $stmt = $pdo->prepare("DELETE FROM system_logs");
    if ($stmt->execute()) {
        $_SESSION['success'] = "系统日志已清空";
        header("Location: system_logs.php");
        exit();
    } else {
        $_SESSION['error'] = "清空日志失败";
    }
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
    <title>系统日志-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .log-level-info { color: #17a2b8; }
        .log-level-warning { color: #ffc107; }
        .log-level-error { color: #dc3545; }
        .log-level-success { color: #28a745; }
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 10px;
        }
        .page-link {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #3498db;
        }
        .page-link.active {
            background: #3498db;
            color: white;
        }
        .log-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .log-stat {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        /* 新增样式 - 卡片式布局 */
        .logs-container {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        .log-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .log-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .log-card.error { border-left-color: #dc3545; }
        .log-card.warning { border-left-color: #ffc107; }
        .log-card.success { border-left-color: #28a745; }
        .log-card.info { border-left-color: #17a2b8; }
        
        .log-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .log-time {
            font-weight: bold;
            color: #2c3e50;
            font-size: 14px;
        }
        .log-user {
            background: #ecf0f1;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .log-ip {
            background: #34495e;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
        }
        .log-action {
            font-weight: bold;
            margin-right: 10px;
        }
        .log-description {
            color: #555;
            line-height: 1.4;
            margin-top: 8px;
            word-break: break-word;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .log-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .log-card {
                padding: 12px;
            }
        }
        
        /* 空状态样式 */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>系统日志</h1>
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
            <?php if (isset($success)): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- 日志统计 -->
            <div class="log-stat">
                <h3>📊 日志统计</h3>
                <p>总日志数：<strong><?php echo $total_logs; ?></strong> 条</p>
                <p>当前页：<strong><?php echo count($logs); ?></strong> 条记录</p>
            </div>

            <!-- 日志操作 -->
            <div class="log-actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_logs" class="btn-small" style="background: #dc3545;" onclick="return confirm('确定要清空所有系统日志吗？此操作不可撤销！')">
                        🗑️ 清空日志
                    </button>
                </form>
                <a href="system_logs.php" class="btn-small">🔄 刷新</a>
                <span style="margin-left: auto; color: #7f8c8d; font-size: 14px;">
                    每页显示: <?php echo $limit; ?> 条
                </span>
            </div>

            <!-- 日志列表 -->
            <h2>📋 系统操作日志</h2>
            
            <?php if (count($logs) > 0): ?>
                <div class="logs-container">
                    <?php foreach($logs as $log): 
                        $logLevel = getLogLevel($log['action']);
                        $levelClass = 'log-level-' . $logLevel;
                        $cardClass = 'log-card ' . $logLevel;
                    ?>
                    <div class="<?php echo $cardClass; ?>">
                        <div class="log-header">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span class="log-time"><?php echo $log['created_at']; ?></span>
                                <span class="log-user">
                                    👤 <?php echo $log['username'] ? htmlspecialchars($log['username']) : '已删除用户'; ?>
                                </span>
                                <span class="log-ip">🌐 <?php echo htmlspecialchars($log['ip_address']); ?></span>
                            </div>
                            <span class="log-action <?php echo $levelClass; ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </div>
                        <?php if (!empty($log['description'])): ?>
                        <div class="log-description">
                            <?php echo htmlspecialchars($log['description']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="system_logs.php?page=<?php echo $page - 1; ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    
                    <?php 
                    // 显示分页数字，最多显示7个页码
                    $start_page = max(1, $page - 3);
                    $end_page = min($total_pages, $start_page + 6);
                    $start_page = max(1, $end_page - 6);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="system_logs.php?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="system_logs.php?page=<?php echo $page + 1; ?>" class="page-link">下一页</a>
                    <?php endif; ?>
                </div>
                <div style="text-align: center; color: #7f8c8d; margin-top: 10px;">
                    第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                    <h3>暂无系统日志</h3>
                    <p>系统还没有记录任何操作日志</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
function getLogLevel($action) {
    $level_map = [
        // 成功操作
        'LOGIN_SUCCESS' => 'success',
        'REGISTER_SUCCESS' => 'success',
        'PASSWORD_ADD' => 'success',
        'PASSWORD_EDIT' => 'success',
        'CATEGORY_ADD' => 'success',
        'USER_PERMISSION_CHANGE' => 'success',
        
        // 信息操作
        'LOGOUT' => 'info',
        'PASSWORD_RESET' => 'info',
        'USER_DELETE_ACCOUNT_ONLY' => 'info',
        
        // 警告操作
        'LOGIN_FAILED' => 'warning',
        'REGISTER_FAILED' => 'warning',
        'PASSWORD_ADD_FAILED' => 'warning',
        'PASSWORD_EDIT_FAILED' => 'warning',
        'CATEGORY_ADD_FAILED' => 'warning',
        'CATEGORY_DELETE_FAILED' => 'warning',
        'PASSWORD_DELETE_FAILED' => 'warning',
        
        // 危险操作
        'PASSWORD_DELETE' => 'error',
        'CATEGORY_DELETE' => 'error',
        'USER_DELETE_FULL' => 'error',
        'ACCOUNT_DELETED' => 'error',
        'LOGS_CLEARED' => 'error'
    ];
    return $level_map[$action] ?? 'info';
}
?>