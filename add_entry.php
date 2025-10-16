<?php
require_once 'includes/auth.php';
require_once 'includes/encryption.php';
require_once 'includes/categories.php';
require_once 'includes/logs.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$categories = getUserCategories($user_id);

// 显示操作消息
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// 处理添加新分类的AJAX请求
if (isset($_POST['ajax']) && $_POST['ajax'] === 'add_category') {
    header('Content-Type: application/json');
    if (addCategory($user_id, $_POST['new_category_name'], $_POST['category_color'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '分类已存在或添加失败']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['platform_name'])) {
    $platform_name = $_POST['platform_name'];
    $website_url = $_POST['website_url'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $category_id = $_POST['category_id'];
    $notes = $_POST['notes'];
    
    $encrypted_password = encryptPassword($password, $user_id);
    
    $stmt = $pdo->prepare("
        INSERT INTO password_entries 
        (user_id, platform_name, website_url, username, encrypted_password, category_id, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$user_id, $platform_name, $website_url, $username, $encrypted_password, $category_id, $notes])) {
        $success = "账号信息已成功保存！";
        // 清空表单
        $_POST = array();
        // 在成功保存密码条目后添加
if ($stmt->execute([$user_id, $platform_name, $website_url, $username, $encrypted_password, $category_id, $notes])) {
    $success = "账号信息已成功保存！";
    // 记录添加密码日志
    logAction('PASSWORD_ADD', "添加密码条目 - 平台: {$platform_name}", $user_id);
    // 清空表单
    $_POST = array();
} else {
    $error = "保存失败，请重试！";
    logAction('PASSWORD_ADD_FAILED', "添加密码失败 - 平台: {$platform_name}", $user_id);
}
    } else {
        $error = "保存失败，请重试！";
    }
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登记账号-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>登记新账号</h1>
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

            <form method="POST" class="entry-form" id="entryForm">
                <div class="form-group">
                    <label for="platform_name">平台/网站名称 *</label>
                    <input type="text" id="platform_name" name="platform_name" value="<?php echo isset($_POST['platform_name']) ? htmlspecialchars($_POST['platform_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="website_url">网址</label>
                    <input type="url" id="website_url" name="website_url" value="<?php echo isset($_POST['website_url']) ? htmlspecialchars($_POST['website_url']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="username">用户名/邮箱 *</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">密码 *</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="password" id="password" name="password" value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>" required style="flex: 1;">
                        <button type="button" onclick="togglePassword()" class="btn-small">显示/隐藏</button>
                        <button type="button" onclick="generatePassword()" class="btn-small">生成密码</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="category_id">分类 *</label>
                    <div class="category-select-container">
                        <select id="category_id" name="category_id" required>
                            <option value="">请选择分类</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <?php if ($cat['user_id'] == 0): ?> (系统)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="showAddCategoryModal()" class="btn-small">+ 新建分类</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">备注</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn-primary">保存账号信息</button>
                    <a href="dashboard.php" class="btn-small">返回</a>
                </div>
            </form>
        </div>
    </div>

    <!-- 添加分类模态框 -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <h3>添加新分类</h3>
            <form id="addCategoryForm">
                <div class="form-group">
                    <label for="new_category_name">分类名称</label>
                    <input type="text" id="new_category_name" name="new_category_name" required>
                </div>
                <div class="form-group">
                    <label for="category_color">颜色</label>
                    <input type="color" id="category_color" name="category_color" value="#3498db">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="hideAddCategoryModal()" class="btn-small">取消</button>
                    <button type="submit" class="btn-primary">添加分类</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
            document.getElementById('new_category_name').focus();
        }

        function hideAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'none';
            document.getElementById('addCategoryForm').reset();
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function generatePassword() {
            const length = 16;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            
            // 确保包含每种字符类型
            password += getRandomChar("abcdefghijklmnopqrstuvwxyz");
            password += getRandomChar("ABCDEFGHIJKLMNOPQRSTUVWXYZ");
            password += getRandomChar("0123456789");
            password += getRandomChar("!@#$%^&*");
            
            for (let i = password.length; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            
            // 打乱顺序
            password = password.split('').sort(() => 0.5 - Math.random()).join('');
            
            document.getElementById('password').value = password;
            document.getElementById('password').type = 'text'; // 显示生成的密码
        }

        function getRandomChar(characters) {
            return characters.charAt(Math.floor(Math.random() * characters.length));
        }

        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax', 'add_category');
            
            fetch('add_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || '添加分类失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('添加分类时发生错误');
            });
        });

        document.getElementById('addCategoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAddCategoryModal();
            }
        });
    </script>
</body>
</html>