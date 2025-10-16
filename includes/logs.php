<?php
require_once 'config/database.php';

function logAction($action, $description = '', $user_id = null) {
    global $pdo;
    
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
        return true;
    } catch (Exception $e) {
        error_log("日志记录失败: " . $e->getMessage());
        return false;
    }
}

function getSystemLogs($limit = 100, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT sl.*, u.username 
        FROM system_logs sl 
        LEFT JOIN users u ON sl.user_id = u.id 
        ORDER BY sl.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getLogsCount() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_logs");
    $stmt->execute();
    return $stmt->fetch()['count'];
}
?>