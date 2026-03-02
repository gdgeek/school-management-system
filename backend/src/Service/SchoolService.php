<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SchoolRepository;
use App\Repository\ClassRepository;
use App\Repository\TeacherRepository;
use App\Repository\StudentRepository;
use App\Model\School;
use App\Helper\DatabaseHelper;

/**
 * 学校管理服务
 * 处理学校相关的业务逻辑
 */
class SchoolService
{
    public function __construct(
        private SchoolRepository $schoolRepository,
        private ClassRepository $classRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
        private DatabaseHelper $dbHelper
    ) {}

    /**
     * 获取学校列表
     */
    public function getList(int $page = 1, int $pageSize = 20, ?string $search = null): array
    {
        $offset = ($page - 1) * $pageSize;
        
        if ($search) {
            $schools = $this->schoolRepository->search($search, $pageSize, $offset);
        } else {
            $schools = $this->schoolRepository->findAll($pageSize, $offset);
        }
        
        $total = $this->schoolRepository->count();
        
        return [
            'items' => array_map(fn($school) => $school->toArray(), $schools),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * 获取学校详情
     */
    public function getById(int $id): ?array
    {
        $school = $this->schoolRepository->findById($id);
        
        if (!$school) {
            return null;
        }
        
        return $school->toArray();
    }

    /**
     * 创建学校
     */
    public function create(array $data): array
    {
        $school = new School();
        $school->name = $data['name'] ?? '';
        $school->image_id = $data['image_id'] ?? null;
        $school->info = $data['info'] ?? [];
        $school->principal_id = $data['principal_id'] ?? null;
        
        $id = $this->schoolRepository->create($school);
        $school->id = $id;
        
        return $school->toArray();
    }

    /**
     * 更新学校
     */
    public function update(int $id, array $data): ?array
    {
        $school = $this->schoolRepository->findById($id);
        
        if (!$school) {
            return null;
        }
        
        if (isset($data['name'])) {
            $school->name = $data['name'];
        }
        if (isset($data['image_id'])) {
            $school->image_id = $data['image_id'];
        }
        if (isset($data['info'])) {
            $school->info = $data['info'];
        }
        if (isset($data['principal_id'])) {
            $school->principal_id = $data['principal_id'];
        }
        
        $this->schoolRepository->update($school);
        
        return $school->toArray();
    }

    /**
     * 删除学校（级联删除关联数据）
     */
    public function delete(int $id): bool
    {
        $school = $this->schoolRepository->findById($id);
        
        if (!$school) {
            return false;
        }
        
        // 使用事务确保数据一致性
        return $this->dbHelper->transaction(function() use ($id) {
            // 获取学校下的所有班级
            $classes = $this->classRepository->findBySchoolId($id);
            
            // 删除每个班级的教师和学生
            foreach ($classes as $class) {
                $this->teacherRepository->deleteByClassId($class->id);
                $this->studentRepository->deleteByClassId($class->id);
                $this->classRepository->delete($class->id);
            }
            
            // 删除学校
            return $this->schoolRepository->delete($id);
        });
    }

    /**
     * 获取学校的班级列表
     */
    public function getClasses(int $schoolId): array
    {
        $classes = $this->classRepository->findBySchoolId($schoolId);
        
        return array_map(fn($class) => $class->toArray(), $classes);
    }
}
