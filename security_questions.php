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
                    $error = "该用户未设置完整的安全问题，请使用其他方式找回密码";
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
            $stmt->execute([$user['id'], hash('sha256', $answer1)]);
            
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
            $stmt->execute([$user['id'], hash('sha256', $answer2)]);
            
            if ($stmt->fetch()) {
                // 两个问题都回答正确，生成临时密码
                $temp_password = generateRandomPassword();
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                
                if ($stmt->execute([password_hash($temp_password, PASSWORD_DEFAULT), $user['id']])) {
                    $success = "验证成功！您的临时密码是：<strong>{$temp_password}</strong><br>请立即登录并修改密码。";
                    logAction('SECURITY_QUESTION_RESET', "用户 {$user['username']} 通过安全问题重置密码", $user['id']);
                    unset($_SESSION['reset_user'], $_SESSION['answered_question1']);
                } else {
                    $error = "密码重置失败，请重试";
                }
            } else {
                $error = "第二个安全问题答案错误";
            }
        }
    }
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
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
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            font-size: 16px;
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
            height: 3px;
            background: #e9ecef;
            margin: 0 5px;
            max-width: 60px;
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
        .password-display {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #155724;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid transparent;
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
    </style>
</head>
<body>
    <div class="security-container">
        <h2 style="text-align: center; margin-bottom: 10px; color: #2c3e50;">安全问题找回密码</h2>
        <p style="text-align: center; color: #6c757d; margin-bottom: 30px;">请回答您设置的两个安全问题来重置密码</p>
        
        <!-- 步骤指示器 -->
        <div class="steps">
            <div class="step <?php 
                echo $step === '1' ? 'active' : 
                    ($step === '2' || $step === '3' ? 'completed' : ''); 
            ?>">1</div>
            <div class="step-line <?php echo ($step === '2' || $step === '3') ? 'completed' : ''; ?>"></div>
            <div class="step <?php 
                echo $step === '2' ? 'active' : 
                    ($step === '3' ? 'completed' : ''); 
            ?>">2</div>
            <div class="step-line <?php echo $step === '3' ? 'completed' : ''; ?>"></div>
            <div class="step <?php echo $step === '3' ? 'active' : ''; ?>">3</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success">
                <?php echo $success; ?>
                <div class="password-display">
                    <?php echo $temp_password; ?>
                </div>
                <p style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn-small">立即登录</a>
                </p>
            </div>
        <?php else: ?>
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
                    <button type="submit" class="btn-submit">验证答案并重置密码</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php" style="color: #3498db; text-decoration: none;">← 返回登录页面</a>
        </div>
    </div>
</body>
</html>