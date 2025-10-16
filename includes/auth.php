<?php
require_once 'config/database.php';
require_once 'includes/logs.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function loginUser($username, $password) {
    global $pdo;
    
    // 获取用户信息（包含管理员状态）
    $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // 明文密码验证
        if ($password === $user['password_hash']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            
            // 记录登录成功日志
            logAction('LOGIN_SUCCESS', "用户登录成功", $user['id']);
            return true;
        } else {
            // 记录密码错误日志
            logAction('LOGIN_FAILED', "密码错误 - 用户名: {$username}");
        }
    } else {
        // 记录用户不存在日志
        logAction('LOGIN_FAILED', "用户不存在 - 用户名: {$username}");
    }
    return false;
}

function registerUser($username, $password, $email, $security_question1, $security_answer1, $security_question2, $security_answer2) {
    global $pdo;
    
    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        logAction('REGISTER_FAILED', "用户名已存在 - 用户名: {$username}");
        return false;
    }
    
    // 检查两个安全问题是否相同
    if ($security_question1 === $security_question2) {
        logAction('REGISTER_FAILED', "安全问题相同 - 用户名: {$username}");
        return false;
    }
    
    try {
        // 使用明文存储密码，新用户默认不是管理员
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, security_question1, security_answer1, security_question2, security_answer2) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $username, 
            $password, 
            $email,
            $security_question1,
            $security_answer1, // 明文存储答案
            $security_question2,
            $security_answer2  // 明文存储答案
        ]);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            logAction('REGISTER_SUCCESS', "新用户注册 - 用户名: {$username}", $user_id);
            return true;
        }
    } catch(PDOException $e) {
        logAction('REGISTER_FAILED', "注册失败 - 用户名: {$username} - 错误: " . $e->getMessage());
    }
    return false;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function verifySecurityQuestions($user_id, $answer1, $answer2) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT security_answer1, security_answer2 FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // 直接比较明文答案
        return $user['security_answer1'] === $answer1 && $user['security_answer2'] === $answer2;
    }
    
    return false;
}

function resetUserPassword($user_id, $new_password) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    return $stmt->execute([$new_password, $user_id]);
}

function generatePasswordResetToken($user_id) {
    global $pdo;
    
    // 生成重置令牌
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // 存储重置令牌
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $result = $stmt->execute([$token, $expires, $user_id]);
    
    if ($result) {
        logAction('ADMIN_GENERATE_RESET_LINK', "管理员生成密码重置链接", $user_id, getCurrentUserId());
        return $token;
    }
    return false;
}

function getUserById($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, username, email, is_admin, created_at, reset_token, reset_expires FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function revokeResetToken($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}
?>
<link rel="shortcut icon" href="https://www.dsjie375.cn/imvi/logo/SVG/logo.svg">
<div style=" margin: 10px; text-align: center;" >
    <img border="0" alt="DSJIE_工作室官网" src="https://www.dsjie375.cn/imvi/logo/SVG/logo.svg" width="50" height="50">
    <br/>
    <a style=" margin: 10px; background: #FF6700; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;" href="https://www.dsjie375.cn" target="_blank">DSJIE_工作室</a>
    <a style=" margin: 10px; background: #FF6700; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;" href="https://www.dsjie375.cn/index.php/2025/10/13/password-manager/" target="_blank">【讨论网址】</a>
    
    <a style=" margin: 10px; background: #1f2328; color: white; padding: 5px 10px; border-radius: 15px; display: inline-block; margin-left: 10px; font-size: 12px;" href="https://github.com/DSJIE375/password-manager" target="_blank">GitHub开源地址</a>
</div>