<?php
require_once 'includes/logs.php';
function getUserCategories($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM categories 
        WHERE user_id = ? OR user_id = 0 
        ORDER BY user_id DESC, name ASC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function addCategory($user_id, $name, $color = null) {
    global $pdo;
    
    if (empty($color)) {
        $colors = ['#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c', '#34495e', '#e67e22'];
        $color = $colors[array_rand($colors)];
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)");
        $result = $stmt->execute([$user_id, trim($name), $color]);
        
        if ($result) {
            logAction('CATEGORY_ADD', "添加分类 - 名称: {$name}", $user_id);
            return true;
        }
    } catch(PDOException $e) {
        logAction('CATEGORY_ADD_FAILED', "添加分类失败 - 名称: {$name}", $user_id);
    }
    return false;
}

function deleteCategory($user_id, $category_id) {
    global $pdo;
    
    // 检查该分类下是否有密码条目
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM password_entries WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        logAction('CATEGORY_DELETE_FAILED', "删除分类失败 - 分类下有密码条目", $user_id);
        return false;
    }
    
     // 获取分类名称用于日志
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$category_id, $user_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        return false;
    }
    
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$category_id, $user_id]);
    
    if ($result) {
        logAction('CATEGORY_DELETE', "删除分类 - 名称: {$category['name']}", $user_id);
        return true;
    }
    
    logAction('CATEGORY_DELETE_FAILED', "删除分类失败 - 名称: {$category['name']}", $user_id);
    return false;
}

function getCategoryName($category_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : '未知分类';
}

function getCategoryColor($category_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT color FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result ? $result['color'] : '#95a5a6';
}
?>