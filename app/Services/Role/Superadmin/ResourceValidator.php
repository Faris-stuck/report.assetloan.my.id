<?php

namespace App\Services\Role\Superadmin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ResourceValidator
{
    public function rulesFor(string $resource): array
    {
        return match ($resource) {
            'classes' => [
                'class_name' => ['required', 'string', 'max:80'],
                'grade_level' => ['required', 'string', 'max:20'],
                'major' => ['nullable', 'string', 'max:80'],
                'academic_year' => ['required', 'string', 'max:20'],
                'room_name' => ['nullable', 'string', 'max:80'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'subjects' => [
                'subject_name' => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'staff-units' => [
                'unit_name' => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'locations' => [
                'location_name' => ['required', 'string', 'max:150'],
                'location_type' => ['nullable', 'string', 'max:80'],
                'class_id' => ['nullable', Rule::exists('classes', 'id')->where('is_active', true)],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'violation-types' => [
                'violation_name' => ['required', 'string', 'max:150'],
                'point_reduction' => ['required', 'integer', 'min:1', 'max:100'],
                'description' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'damage-categories' => [
                'category_name' => ['required', 'string', 'max:120'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => abort(404),
        };
    }

    public function userRules(?\App\Models\User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(\App\Models\User::ROLES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
