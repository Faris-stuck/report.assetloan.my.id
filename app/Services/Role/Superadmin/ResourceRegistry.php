<?php

namespace App\Services\Role\Superadmin;

use App\Models\DamageCategory;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ViolationType;

class ResourceRegistry
{
    private array $map = [
        'classes' => [SchoolClass::class, ['class_name', 'grade_level', 'major', 'academic_year', 'room_name', 'is_active']],
        'subjects' => [Subject::class, ['subject_name', 'is_active']],
        'staff-units' => [StaffUnit::class, ['unit_name', 'is_active']],
        'violation-types' => [ViolationType::class, ['violation_name', 'point_reduction', 'description', 'is_active']],
        'damage-categories' => [DamageCategory::class, ['category_name', 'is_active']],
        // Tanpa entri ini siswa hanya bisa lahir dari seeder, sehingga dropdown
        // "Siswa yang terbukti" di Kesiswaan selalu kosong dan tombol "Proses
        // Laporan" tidak pernah bisa dipakai di instalasi produksi.
        'students' => [Student::class, ['nis', 'name', 'class_id', 'parent_phone', 'point']],
    ];

    /**
     * Return list of [ModelClass, fields] or abort(404)
     *
     * @param string $resource
     * @return array
     */
    public function getModelAndFields(string $resource): array
    {
        return $this->map[$resource] ?? abort(404);
    }

    public function getClassesModel(): string
    {
        return SchoolClass::class;
    }
}
