<?php

namespace App\Services\Role\Superadmin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ResourceValidator
{
    /**
     * @param int|null $ignoreId Primary key to exclude from unique checks when
     *                           validating an update, otherwise a record would
     *                           always collide with its own stored value.
     */
    public function rulesFor(string $resource, ?int $ignoreId = null): array
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
            // Tabel students tidak punya kolom is_active; kolom point dibatasi
            // 0..100 karena KesiswaanProcessor menahan poin di lantai 0.
            'students' => [
                'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($ignoreId)],
                'name' => ['required', 'string', 'max:150'],
                'class_id' => ['required', Rule::exists('classes', 'id')->where('is_active', true)],
                'parent_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
                'point' => ['sometimes', 'integer', 'min:0', 'max:100'],
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
