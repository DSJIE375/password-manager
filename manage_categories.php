<?php
require_once 'includes/auth.php';
require_once 'includes/categories.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$categories = getUserCategories($user_id);

// 处理添加分类
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $color = $_POST['color'];
    
    if (addCategory($user_id, $name, $color)) {
        $success = "分类添加成功！";
        header("Location: manage_categories.php");
        exit();
    } else {
        $error = "分类已存在或添加失败！";
    }
}


if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    if (deleteCategory($user_id, $category_id)) {
        $success = "分类删除成功！";
        header("Location: manage_categories.php");
        exit();
    } else {
        $error = "删除失败！只能删除自己创建的空分类（分类下不能有密码条目）。";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理分类-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>管理分类</h1>
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

            <h2>添加新分类</h2>
            <form method="POST" class="entry-form" style="max-width: 400px;">
                <input type="hidden" name="add_category" value="1">
                <div class="form-group">
                    <label for="name">分类名称</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="color">颜色</label>
                    <input type="color" id="color" name="color" value="#3498db">
                </div>
                <button type="submit" class="btn-primary">添加分类</button>
            </form>

            <h2 style="margin-top: 40px;">我的分类</h2>
            <div class="categories-list">
                <?php foreach($categories as $cat): ?>
                    <div class="category-item" style="border-left-color: <?php echo $cat['color']; ?>">
                        <div>
                            <span class="category-tag" style="background: <?php echo $cat['color']; ?>; color: white;">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </span>
                            <?php if ($cat['user_id'] == 0): ?>
                                <small style="color: #666;">(系统默认分类)</small>
                            <?php else: ?>
                                <small style="color: #666;">(自定义分类)</small>
                            <?php endif; ?>
                        </div>
                        <?php if ($cat['user_id'] != 0): ?>
                            <a href="manage_categories.php?delete=<?php echo $cat['id']; ?>" 
                               onclick="return confirm('确定要删除这个分类吗？')" 
                               class="btn-small" 
                               style="background: #e74c3c;">删除</a>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">系统分类不可删除</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>