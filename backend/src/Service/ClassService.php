<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClassRepository;
use App\Repository\SchoolRepository;
use App\Repository\TeacherRepository;
use App\Repository\StudentRepository;
use App\Repository\GroupRepository;
use App\Repository\ClassGroupRepository;
use App\Model\EduClass;
use App\Model\Group;
use App\Model\ClassGroup;
use App\Helper\DatabaseHelper;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

class ClassService
{
    public function __construct(
        private ClassRepository $classRepository,
        private SchoolRepository $schoolRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
        private GroupRepository $groupRepository,
        private ClassGroupRepository $classGroupRepository,
        private DatabaseHelper $dbHelper
    ) {}

    public function getList(int $page = 1, int $pageSize = 20, ?int $schoolId = null): array
    {
        $offset = ($page - 1) * $pageSize;
        
        if ($schoolId) {
            $classes = $this->classRepository->findBySchoolId($schoolId);
            $total = count($classes);
            $classes = array_slice($classes, $offset, $pageSize);
        } else {
            $classes = $this->classRepository->findAll($pageSize, $offset);
            $total = count($this->classRepository->findAll(10000, 0));
        }
        
        return [
            'items' => array_map(fn($class) => $class->toArray(), $classes),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ];
    }

    public function getById(int $id): array
    {
        $class = $this->classRepository->findById($id);
        if (!$class) {
            throw new NotFoundException("Class not found: {$id}");
        }
        return $class->toArray();
    }

    public function create(array $data, ?int $currentUserId = null): array
    {
        if (!isset($data['school_id']) || $data['school_id'] === 0 || $data['school_id'] === null || $data['school_id'] === '') {
            throw new ValidationException(['school_id' => 'School ID is required']);
        }
        
        $school = $this->schoolRepository->findById((int)$data['school_id']);
        if (!$school) {
            throw new ValidationException(['school_id' => 'Invalid school ID']);
        }
        
        if (empty($data['name'])) {
            throw new ValidationException(['name' => 'Class name is required']);
        }
        
        if (empty($currentUserId)) {
            throw new ValidationException(['user' => '当前用户未登录，无法创建小组']);
        }
        
        return $this->dbHelper->transaction(function() use ($data, $currentUserId) {
            // 创建班级
            $class = new EduClass();
            $class->name = $data['name'] ?? '';
            $class->school_id = $data['school_id'];
            $class->image_id = null;
            $class->info = is_string($data['info'] ?? null) && !empty($data['info'])
                ? ['description' => $data['info']]
                : ($data['info'] ?? []);
            
            $classId = $this->classRepository->create($class);
            $class->id = $classId;
            
            // 自动创建同名小组
            $group = new Group();
            $group->name = $class->name;
            $group->description = "班级「{$class->name}」的默认小组";
            $group->user_id = $currentUserId;
            $group->info = [];
            
            $groupId = $this->groupRepository->create($group);
            
            // 关联班级和小组
            $classGroup = new ClassGroup();
            $classGroup->class_id = $classId;
            $classGroup->group_id = $groupId;
            $this->classGroupRepository->create($classGroup);
            
            // 返回班级数据，包含创建的小组 ID
            $result = $class->toArray();
            $result['group_id'] = $groupId;
            return $result;
        });
    }

    public function update(int $id, array $data): array
    {
        $class = $this->classRepository->findById($id);
        
        if (!$class) {
            throw new NotFoundException("Class not found: {$id}");
        }
        
        if (isset($data['school_id']) && $data['school_id'] != $class->school_id) {
            $school = $this->schoolRepository->findById($data['school_id']);
            if (!$school) {
                throw new ValidationException(['school_id' => 'Invalid school ID']);
            }
            $class->school_id = $data['school_id'];
        }
        
        if (isset($data['name'])) {
            $class->name = $data['name'];
        }
        if (isset($data['info'])) {
            $class->info = is_string($data['info']) && !empty($data['info'])
                ? ['description' => $data['info']]
                : $data['info'];
        }
        
        $this->classRepository->update($class);
        
        return $class->toArray();
    }

    public function delete(int $id, bool $deleteGroups = false): void
    {
        $class = $this->classRepository->findById($id);
        
        if (!$class) {
            throw new NotFoundException("Class not found: {$id}");
        }
        
        $this->dbHelper->transaction(function() use ($id, $deleteGroups) {
            // 根据参数决定是否删除关联的小组
            if ($deleteGroups) {
                $classGroups = $this->classGroupRepository->findByClassId($id);
                foreach ($classGroups as $cg) {
                    $this->classGroupRepository->delete($id, $cg->group_id);
                    $this->groupRepository->delete($cg->group_id);
                }
            } else {
                // 只删除关联关系，不删除小组
                $classGroups = $this->classGroupRepository->findByClassId($id);
                foreach ($classGroups as $cg) {
                    $this->classGroupRepository->delete($id, $cg->group_id);
                }
            }
            
            // 删除班级下的教师和学生
            $this->teacherRepository->deleteByClassId($id);
            $this->studentRepository->deleteByClassId($id);
            
            $this->classRepository->delete($id);
        });
    }

    public function getTeachers(int $classId): array
    {
        $teachers = $this->teacherRepository->findByClassId($classId);
        return array_map(fn($teacher) => $teacher->toArray(), $teachers);
    }

    public function getStudents(int $classId): array
    {
        $students = $this->studentRepository->findByClassId($classId);
        return array_map(fn($student) => $student->toArray(), $students);
    }
}
