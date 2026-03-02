<?php

declare(strict_types=1);

/**
 * 验证现有数据库表结构
 * 
 * 此脚本用于验证主项目数据库中的学校管理相关表是否存在且结构正确
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// 数据库连接配置
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'xrugc';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ 数据库连接成功\n\n";
    
    // 需要验证的表
    $requiredTables = [
        'edu_school' => [
            'id', 'name', 'created_at', 'updated_at', 'image_id', 'info', 'principal_id'
        ],
        'edu_class' => [
            'id', 'name', 'created_at', 'updated_at', 'school_id', 'image_id', 'info'
        ],
        'edu_teacher' => [
            'id', 'user_id', 'class_id'
        ],
        'edu_student' => [
            'id', 'user_id', 'class_id'
        ],
        'group' => [
            'id', 'name', 'description', 'user_id', 'image_id', 'info', 'created_at', 'updated_at'
        ],
        'edu_class_group' => [
            'id', 'class_id', 'group_id'
        ],
        'user' => [
            'id', 'username', 'nickname'
        ],
        'file' => [
            'id', 'url'
        ]
    ];
    
    $allTablesExist = true;
    
    foreach ($requiredTables as $tableName => $columns) {
        // 检查表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            echo "✗ 表 $tableName 不存在\n";
            $allTablesExist = false;
            continue;
        }
        
        echo "✓ 表 $tableName 存在\n";
        
        // 检查列是否存在
        $stmt = $pdo->query("DESCRIBE $tableName");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingColumns = array_diff($columns, $existingColumns);
        
        if (!empty($missingColumns)) {
            echo "  ⚠ 缺少列: " . implode(', ', $missingColumns) . "\n";
        } else {
            echo "  ✓ 所有必需列都存在\n";
        }
    }
    
    echo "\n";
    
    if ($allTablesExist) {
        echo "✓ 所有必需的表都存在，数据库结构验证通过\n";
        exit(0);
    } else {
        echo "✗ 部分表不存在，请先运行主项目的数据库迁移\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}
