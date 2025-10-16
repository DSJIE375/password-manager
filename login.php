<?php
session_start();
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $action = $_POST['action'];
    
    if ($action === 'login') {
        if (loginUser($username, $password)) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "用户名或密码错误！";
        }
    } elseif ($action === 'register') {
        $email = $_POST['email'];
        $security_question1 = $_POST['security_question1'];
        $security_answer1 = $_POST['security_answer1'];
        $security_question2 = $_POST['security_question2'];
        $security_answer2 = $_POST['security_answer2'];
        

        if ($security_question1 === $security_question2) {
            $error = "两个安全问题不能相同！";
        } elseif (empty($security_question1) || empty($security_question2) || empty($security_answer1) || empty($security_answer2)) {
            $error = "请完整填写安全问题和答案！";
        } else {
            if (registerUser($username, $password, $email, $security_question1, $security_answer1, $security_question2, $security_answer2)) {
                $success = "注册成功！请登录。";
            } else {
                $error = "注册失败，用户名或邮箱已存在！";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-container {
            max-width: 450px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            
        }
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .tab.active {
            border-bottom: 2px solid #3498db;
            color: #3498db;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn-login {
            width: 100%;
            background: #3498db;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .btn-login:hover {
            background: #2980b9;
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
        .security-note {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">密码管理系统</h2>
        
       
        <div class="alert error <?php echo $error ? 'show' : ''; ?>">
            <?php echo $error; ?>
        </div>
        
        
        <div class="alert success <?php echo $success ? 'show' : ''; ?>">
            <?php echo $success; ?>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('login')">登录</div>
            <div class="tab" onclick="switchTab('register')">注册</div>
        </div>

        <!-- 登录表单 -->
        <div id="login-tab" class="tab-content active">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="login_username">用户名</label>
                    <input type="text" id="login_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login_password">密码</label>
                    <input type="password" id="login_password" name="password" required>
                </div>
                <button type="submit" class="btn-login">登录</button>
            </form>
            <div style="text-align: center; margin-top: 15px;">
                <a href="forgot_password.php" style="color: #3498db; text-decoration: none; font-size: 14px;">忘记账号/密码？</a>
            </div>
        </div>

        <!-- 注册表单 -->
        <div id="register-tab" class="tab-content">
            <form method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="reg_username">用户名</label>
                    <input type="text" id="reg_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="reg_email">邮箱</label>
                    <input type="email" id="reg_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="reg_password">密码</label>
                    <input type="password" id="reg_password" name="password" required>
                </div>
                
                <!-- 安全问题1 -->
                <div class="form-group">
                    <label for="security_question1">安全问题 1</label>
                    <input type="text" id="security_question1" name="security_question1" required placeholder="例如：DSJIE_工作室官网">
                    <div class="security-note">请设置一个容易记住但别人难以猜到的问题</div>
                </div>
                <div class="form-group">
                    <label for="security_answer1">安全问题 1 答案</label>
                    <input type="text" id="security_answer1" name="security_answer1" required placeholder="例如：www.dsjie375.cn">
                </div>
                
                <!-- 安全问题2 -->
                <div class="form-group">
                    <label for="security_question2">安全问题 2</label>
                    <input type="text" id="security_question2" name="security_question2" required placeholder="例如：我喜欢的偶像">
                    <div class="security-note">请设置另一个不同的安全问题</div>
                </div>
                <div class="form-group">
                    <label for="security_answer2">安全问题 2 答案</label>
                    <input type="text" id="security_answer2" name="security_answer2" required placeholder="例如：李庚希">
                </div>
                
                <button type="submit" class="btn-login">注册</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }
    </script>
</body>
</html>