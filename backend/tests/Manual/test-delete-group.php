<?php
/**
 * 测试删除小组功能
 * 验证删除小组时是否正确级联删除班级、教师和学生
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// 加载依赖注入容器
$container = require __DIR__ . '/../../config/di.php';

// 获取 PDO 连接
$pdo = $container->get(PDO::class);

echo "=== 删除小组级联删除测试 ===\n\n";

// 1. 创建测试数据
echo "步骤 1: 创建测试数据\n";
echo "-------------------\n";

$schoolId = $classId = $groupId = $teacherId = $studentId = null;

try {
    $pdo->beginTransaction();
    
    // 创建学校
    $pdo->exec("INSERT INTO edu_school (name, principal_id, info, created_at, updated_at) 
                VALUES ('测试学校', 1, '{}', NOW(), NOW())");
    $schoolId = $pdo->lastInsertId();
    echo "✓ 创建学校 ID: $schoolId\n";
    
    // 创建班级
    $pdo->exec("INSERT INTO edu_class (name, school_id, info, created_at, updated_at) 
                VALUES ('测试班级', $schoolId, '{}', NOW(), NOW())");
    $classId = $pdo->lastInsertId();
    echo "✓ 创建班级 ID: $classId\n";
    
    // 创建小组
    $pdo->exec("INSERT INTO `group` (name, description, user_id, info, created_at, updated_at) 
                VALUES ('测试小组', '测试描述', 1, '{}', NOW(), NOW())");
    $groupId = $pdo->lastInsertId();
    echo "✓ 创建小组 ID: $groupId\n";
    
    // 关联班级和小组
    $pdo->exec("INSERT INTO edu_class_group (class_id, group_id) VALUES ($classId, $groupId)");
    echo "✓ 创建班级-小组关联\n";
    
    // 创建教师
    $pdo->exec("INSERT INTO edu_teacher (user_id, class_id, info, created_at, updated_at) 
                VALUES (1, $classId, '{}', NOW(), NOW())");
    $teacherId = $pdo->lastInsertId();
    echo "✓ 创建教师 ID: $teacherId\n";
    
    // 创建学生
    $pdo->exec("INSERT INTO edu_student (user_id, class_id, info, created_at, updated_at) 
                VALUES (2, $classId, '{}', NOW(), NOW())");
    $studentId = $pdo->lastInsertId();
    echo "✓ 创建学生 ID: $studentId\n";
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "✗ 创建测试数据失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 2. 验证数据存在
echo "步骤 2: 验证数据存在\n";
echo "-------------------\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_class WHERE id = ?");
$stmt->execute([$classId]);
$classCount = $stmt->fetchColumn();
echo ($classCount > 0 ? "✓" : "✗") . " 班级存在: $classCount\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM `group` WHERE id = ?");
$stmt->execute([$groupId]);
$groupCount = $stmt->fetchColumn();
echo ($groupCount > 0 ? "✓" : "✗") . " 小组存在: $groupCount\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_class_group WHERE class_id = ? AND group_id = ?");
$stmt->execute([$classId, $groupId]);
$relationCount = $stmt->fetchColumn();
echo ($relationCount > 0 ? "✓" : "✗") . " 班级-小组关联存在: $relationCount\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_teacher WHERE class_id = ?");
$stmt->execute([$classId]);
$teacherCount = $stmt->fetchColumn();
echo ($teacherCount > 0 ? "✓" : "✗") . " 教师存在: $teacherCount\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_student WHERE class_id = ?");
$stmt->execute([$classId]);
$studentCount = $stmt->fetchColumn();
echo ($studentCount > 0 ? "✓" : "✗") . " 学生存在: $studentCount\n";

echo "\n";

// 3. 删除小组
echo "步骤 3: 删除小组\n";
echo "-------------------\n";

$groupService = $container->get(\App\Service\GroupService::class);

try {
    $result = $groupService->delete($groupId);
    echo ($result ? "✓" : "✗") . " 删除小组: " . ($result ? "成功" : "失败") . "\n";
} catch (Exception $e) {
    echo "✗ 删除小组失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    
    // 清理测试数据
    try {
        $pdo->exec("DELETE FROM edu_student WHERE id = $studentId");
        $pdo->exec("DELETE FROM edu_teacher WHERE id = $teacherId");
        $pdo->exec("DELETE FROM edu_class_group WHERE class_id = $classId");
        $pdo->exec("DELETE FROM `group` WHERE id = $groupId");
        $pdo->exec("DELETE FROM edu_class WHERE id = $classId");
        $pdo->exec("DELETE FROM edu_school WHERE id = $schoolId");
    } catch (Exception $cleanupError) {
        // 忽略清理错误
    }
    
    exit(1);
}

echo "\n";

// 4. 验证级联删除
echo "步骤 4: 验证级联删除\n";
echo "-------------------\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_class WHERE id = ?");
$stmt->execute([$classId]);
$classCount = $stmt->fetchColumn();
echo ($classCount == 0 ? "✓" : "✗") . " 班级已删除: " . ($classCount == 0 ? "是" : "否 (剩余 $classCount)") . "\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM `group` WHERE id = ?");
$stmt->execute([$groupId]);
$groupCount = $stmt->fetchColumn();
echo ($groupCount == 0 ? "✓" : "✗") . " 小组已删除: " . ($groupCount == 0 ? "是" : "否 (剩余 $groupCount)") . "\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_class_group WHERE class_id = ? AND group_id = ?");
$stmt->execute([$classId, $groupId]);
$relationCount = $stmt->fetchColumn();
echo ($relationCount == 0 ? "✓" : "✗") . " 班级-小组关联已删除: " . ($relationCount == 0 ? "是" : "否 (剩余 $relationCount)") . "\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_teacher WHERE class_id = ?");
$stmt->execute([$classId]);
$teacherCount = $stmt->fetchColumn();
echo ($teacherCount == 0 ? "✓" : "✗") . " 教师已删除: " . ($teacherCount == 0 ? "是" : "否 (剩余 $teacherCount)") . "\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM edu_student WHERE class_id = ?");
$stmt->execute([$classId]);
$studentCount = $stmt->fetchColumn();
echo ($studentCount == 0 ? "✓" : "✗") . " 学生已删除: " . ($studentCount == 0 ? "是" : "否 (剩余 $studentCount)") . "\n";

echo "\n";

// 5. 清理测试数据
echo "步骤 5: 清理测试数据\n";
echo "-------------------\n";

try {
    $pdo->prepare("DELETE FROM edu_school WHERE id = ?")->execute([$schoolId]);
    echo "✓ 清理学校数据\n";
} catch (Exception $e) {
    echo "✗ 清理失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
