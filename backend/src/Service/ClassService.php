<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ClassRepository;
use App\Repository\SchoolRepository;
use App\Repository\TeacherRepository;
use App\Repository\StudentRepository;
use App\Model\EduClass;
use App\Helper\DatabaseHelper;

class ClassService
{
    public function __construct(
        private ClassRepository $classRepository,
        private SchoolRepository $schoolRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
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

    public function getById(int $id): ?array
    {
        $class = $this->classRepository->findById($id);
        return $class ? $class->toArray() : null;
    }

    public function create(array $data): ?array
    {
        if (empty($data['school_id'])) {
            throw new \InvalidArgumentException('School ID is required');
        }
        
        $school = $this->schoolRepository->findById($data['school_id']);
        if (!$school) {
            throw new \InvalidArgumentException('Invalid school ID');
        }
        
        $class = new EduClass();
        $class->name = $data['name'] ?? '';
        $class->school_id = $data['school_id'];
        $class->image_id = $data['image_id'] ?? null;
        $class->info = $data['info'] ?? [];
        
        $id = $this->classRepository->create($class);
        $class->id = $id;
        
        return $class->toArray();
    }

    public function update(int $id, array $data): ?array
    {
        $class = $this->classRepository->findById($id);
        
        if (!$class) {
            return null;
        }
        
        if (isset($data['school_id']) && $data['school_id'] != $class->school_id) {
            $school = $this->schoolRepository->findById($data['school_id']);
            if (!$school) {
                throw new \InvalidArgumentException('Invalid school ID');
            }
            $class->school_id = $data['school_id'];
        }
        
        if (isset($data['name'])) {
            $class->name = $data['name'];
        }
        if (isset($data['image_id'])) {
            $class->image_id = $data['image_id'];
        }
        if (isset($data['info'])) {
            $class->info = $data['info'];
        }
        
        $this->classRepository->update($class);
        
        return $class->toArray();
    }

    public function delete(int $id): bool
    {
        $class = $this->classRepository->findById($id);
        
        if (!$class) {
            return false;
        }
        
        return $this->dbHelper->transaction(function() use ($id) {
            $this->teacherRepository->deleteByClassId($id);
            $this->studentRepository->deleteByClassId($id);
            return $this->classRepository->delete($id);
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
