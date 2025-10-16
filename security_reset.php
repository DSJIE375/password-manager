<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/logs.php';

$error = '';
$success = '';
$step = $_GET['step'] ?? '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    
    if ($step === '1') {
        // 步骤1：验证用户名
        if (empty($username)) {
            $error = "请输入用户名";
        } else {
            $stmt = $pdo->prepare("SELECT id, username, security_question1, security_question2 FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (empty($user['security_question1']) || empty($user['security_question2'])) {
                    $error = "该用户未设置完整的安全问题，请联系管理员";
                } else {
                    $_SESSION['reset_user'] = $user;
                    header("Location: security_reset.php?step=2");
                    exit();
                }
            } else {
                $error = "用户不存在";
            }
        }
    } elseif ($step === '2') {
        // 步骤2：验证第一个安全问题
        $user = $_SESSION['reset_user'];
        $answer1 = $_POST['security_answer1'] ?? '';
        
        if (empty($answer1)) {
            $error = "请输入第一个安全问题的答案";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND security_answer1 = ?");
            $stmt->execute([$user['id'], $answer1]);
            
            if ($stmt->fetch()) {
                $_SESSION['answered_question1'] = true;
                header("Location: security_reset.php?step=3");
                exit();
            } else {
                $error = "第一个安全问题答案错误";
            }
        }
    } elseif ($step === '3') {
        // 步骤3：验证第二个安全问题
        $user = $_SESSION['reset_user'];
        $answer2 = $_POST['security_answer2'] ?? '';
        
        if (empty($answer2)) {
            $error = "请输入第二个安全问题的答案";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND security_answer2 = ?");
            $stmt->execute([$user['id'], $answer2]);
            
            if ($stmt->fetch()) {
                // 两个问题都回答正确，进入设置新密码步骤
                $_SESSION['questions_verified'] = true;
                header("Location: security_reset.php?step=4");
                exit();
            } else {
                $error = "第二个安全问题答案错误";
            }
        }
    } elseif ($step === '4') {
        // 步骤4：设置新密码
        $user = $_SESSION['reset_user'];
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "请填写新密码和确认密码";
        } elseif ($new_password !== $confirm_password) {
            $error = "两次输入的密码不一致";
        } elseif (strlen($new_password) < 6) {
            $error = "密码长度至少6位";
        } else {
            // 更新密码
            if (resetUserPassword($user['id'], $new_password)) {
                $success = "密码重置成功！请使用新密码登录。";
                logAction('PASSWORD_RESET', "用户 {$user['username']} 通过安全问题重置密码", $user['id']);
                unset($_SESSION['reset_user'], $_SESSION['answered_question1'], $_SESSION['questions_verified']);
            } else {
                $error = "密码重置失败，请重试";
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
    <title>安全问题找回密码-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .security-container {
            max-width: 500px;
            margin: 80px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .steps {
            display: flex;
            margin-bottom: 30px;
            justify-content: center;
            align-items: center;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 8px;
            font-weight: bold;
            font-size: 14px;
        }
        .step.active {
            background: #3498db;
            color: white;
        }
        .step.completed {
            background: #2ecc71;
            color: white;
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: #e9ecef;
            margin: 0 4px;
            max-width: 50px;
        }
        .step-line.completed {
            background: #2ecc71;
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
        .question-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-weight: 600;
            color: #2c3e50;
            text-align: left;
            font-size: 16px;
            line-height: 1.5;
        }
        .btn-submit {
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
        .btn-submit:hover {
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
            display: none; /* 默认隐藏 */
        }
        .alert.show {
            display: block; /* 有内容时显示 */
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
        .info-text {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
            text-align: center;
        }
        .progress-info {
            text-align: center;
            margin-bottom: 20px;
            color: #3498db;
            font-weight: 600;
        }
        .question-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            display: block;
        }
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="security-container">
        <h2 style="text-align: center; margin-bottom: 10px; color: #2c3e50;">安全问题找回密码</h2>
        <p style="text-align: center; color: #6c757d; margin-bottom: 30px;">请回答安全问题并设置新密码</p>
        
        <!-- 错误消息 - 只在有错误时显示 -->
        <div class="alert error <?php echo $error ? 'show' : ''; ?>">
            <?php echo $error; ?>
        </div>
        
        <!-- 成功消息 - 只在成功时显示 -->
        <div class="alert success <?php echo $success ? 'show' : ''; ?>">
            <?php echo $success; ?>
        </div>
        
        <!-- 步骤指示器 -->
        <div class="steps">
            <div class="step <?php 
                echo $step === '1' ? 'active' : 
                    (in_array($step, ['2','3','4']) ? 'completed' : ''); 
            ?>">1</div>
            <div class="step-line <?php echo in_array($step, ['2','3','4']) ? 'completed' : ''; ?>"></div>
            <div class="step <?php 
                echo $step === '2' ? 'active' : 
                    (in_array($step, ['3','4']) ? 'completed' : ''); 
            ?>">2</div>
            <div class="step-line <?php echo in_array($step, ['3','4']) ? 'completed' : ''; ?>"></div>
            <div class="step <?php 
                echo $step === '3' ? 'active' : 
                    ($step === '4' ? 'completed' : ''); 
            ?>">3</div>
            <div class="step-line <?php echo $step === '4' ? 'completed' : ''; ?>"></div>
            <div class="step <?php echo $step === '4' ? 'active' : ''; ?>">4</div>
        </div>
        
        <?php if (!$success): ?>
            <?php if ($step === '1'): ?>
                <!-- 步骤1：输入用户名 -->
                <form method="POST">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="请输入您的用户名">
                    </div>
                    <button type="submit" class="btn-submit">下一步</button>
                </form>
            <?php elseif ($step === '2'): ?>
                <!-- 步骤2：回答第一个安全问题 -->
                <div class="progress-info">第 1 个安全问题（共 2 个）</div>
                <form method="POST">
                    <div class="form-group">
                        <span class="question-label">您设置的安全问题：</span>
                        <div class="question-display">
                            <?php echo htmlspecialchars($_SESSION['reset_user']['security_question1']); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="security_answer1">请输入答案</label>
                        <input type="text" id="security_answer1" name="security_answer1" required placeholder="请输入您设置的安全问题答案">
                    </div>
                    <div class="info-text">请准确输入您注册时设置的答案</div>
                    <button type="submit" class="btn-submit">下一步</button>
                </form>
            <?php elseif ($step === '3'): ?>
                <!-- 步骤3：回答第二个安全问题 -->
                <div class="progress-info">第 2 个安全问题（共 2 个）</div>
                <form method="POST">
                    <div class="form-group">
                        <span class="question-label">您设置的安全问题：</span>
                        <div class="question-display">
                            <?php echo htmlspecialchars($_SESSION['reset_user']['security_question2']); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="security_answer2">请输入答案</label>
                        <input type="text" id="security_answer2" name="security_answer2" required placeholder="请输入您设置的安全问题答案">
                    </div>
                    <div class="info-text">请准确输入您注册时设置的答案</div>
                    <button type="submit" class="btn-submit">下一步</button>
                </form>
            <?php elseif ($step === '4'): ?>
                <!-- 步骤4：设置新密码 -->
                <div class="progress-info">设置新密码</div>
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
                    <div class="info-text">请牢记您的新密码</div>
                    <button type="submit" class="btn-submit">重置密码</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php" style="color: #3498db; text-decoration: none;">← 返回登录页面</a>
            <br>
            忘记用户名/安全问题?
            <br>
            <a href="forgot_password.php" style="color: #3498db; text-decoration: none;">← 更换方式</a>
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