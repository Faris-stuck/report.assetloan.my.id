<?php

namespace App\Services\Role\Superadmin;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use App\Models\ViolationType;

class ResourceRegistry
{
    private array $map = [
        'classes' => [SchoolClass::class, ['class_name', 'grade_level', 'major', 'academic_year', 'room_name', 'is_active']],
        'subjects' => [Subject::class, ['subject_name', 'is_active']],
        'staff-units' => [StaffUnit::class, ['unit_name', 'is_active']],
        'locations' => [Location::class, ['location_name', 'location_type', 'class_id', 'is_active']],
        'violation-types' => [ViolationType::class, ['violation_name', 'point_reduction', 'description', 'is_active']],
        'damage-categories' => [DamageCategory::class, ['category_name', 'is_active']],
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
