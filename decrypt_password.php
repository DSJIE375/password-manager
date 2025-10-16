<?php
require_once 'includes/auth.php';
require_once 'includes/encryption.php';
redirectIfNotLoggedIn();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry_id = $_POST['entry_id'];
    $user_id = $_SESSION['user_id'];
    $is_admin = isAdmin();
    
    if ($is_admin) {
        // 管理员：可以解密任何条目
        $stmt = $pdo->prepare("SELECT encrypted_password, user_id FROM password_entries WHERE id = ?");
        $stmt->execute([$entry_id]);
        $entry = $stmt->fetch();
    } else {
        // 普通用户：只能解密自己的条目
        $stmt = $pdo->prepare("SELECT encrypted_password, user_id FROM password_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$entry_id, $user_id]);
        $entry = $stmt->fetch();
    }
    
    if ($entry) {
        try {
            $decrypted_password = decryptPassword($entry['encrypted_password'], $entry['user_id']);
            echo json_encode(['success' => true, 'password' => $decrypted_password]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => '解密失败']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => '记录不存在或无权访问']);
    }
}
?>