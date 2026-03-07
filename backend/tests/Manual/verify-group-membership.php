<?php

// 验证用户是否在小组成员表中
// 用法: php verify-group-membership.php <user_id> <group_id>

$userId = $argv[1] ?? 24;
$groupId = $argv[2] ?? 37;

try {
    // 使用项目配置的数据库连接
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;dbname=bujiaban',
        'root',
        ''
    );
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 查询 group_user 表
    $sql = "SELECT * FROM group_user WHERE user_id = :user_id AND group_id = :group_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ 验证成功：用户 $userId 在小组 $groupId 的成员表中\n";
        echo "记录详情:\n";
        print_r($result);
    } else {
        echo "✗ 验证失败：用户 $userId 不在小组 $groupId 的成员表中\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
