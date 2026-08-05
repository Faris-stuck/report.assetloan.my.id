<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keysToNormalize = [
            'qr_code_id',
            'reporter_class_id',
            'reporter_subject_id',
            'reporter_staff_unit_id',
            'related_class_id',
            'location_id',
        ];

        foreach ($keysToNormalize as $key) {
            $value = $this->input($key);
            if ($value === '' || $value === 'null' || $value === 'NULL') {
                $this->merge([$key => null]);
            }
        }

        $trimmed = [];
        foreach ([
            'reporter_name',
            'reporter_phone',
            'reporter_email',
            'title',
            'custom_location',
            'description',
            'reporter_position',
            'bullying_type',
            'victim_name',
            'alleged_actor_name',
            'witness_name',
            'item_name',
            'item_category',
            'damage_condition',
            'suspected_cause',
        ] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $this->merge($trimmed);
    }

    public function rules(): array
    {
        return [
            'qr_code_id' => ['nullable', Rule::exists('qr_codes', 'id')->where('is_active', true)],
            'reporter_type' => ['required', Rule::in(['siswa', 'guru', 'staff'])],
            'reporter_name' => ['required', 'string', 'max:150'],
            'reporter_class_id' => ['exclude_unless:reporter_type,siswa', 'required', Rule::exists('classes', 'id')->where('is_active', true)],
            'reporter_absence_number' => ['exclude_unless:reporter_type,siswa', 'nullable', 'integer', 'min:1', 'max:60'],
            'reporter_subject_id' => ['exclude_unless:reporter_type,guru', 'required', Rule::exists('subjects', 'id')->where('is_active', true)],
            'reporter_staff_unit_id' => ['exclude_unless:reporter_type,staff', 'required', Rule::exists('staff_units', 'id')->where('is_active', true)],
            'reporter_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+() .*-]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digitCount = strlen(preg_replace('/\D+/', '', (string) $value) ?? '');
                    $containsMask = str_contains((string) $value, '*');
                    if ((! $containsMask && $digitCount < 8) || $digitCount > 15) {
                        $fail('Nomor HP harus berisi 8 sampai 15 digit.');
                    }
                },
            ],
            'reporter_email' => ['nullable', 'email:rfc', 'max:150', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $fail('Format alamat email tidak valid.');
                }
            }],
            'report_type' => ['required', Rule::in(['violation', 'damage'])],
            'title' => ['required', 'string', 'max:200'],
            'related_class_id' => ['nullable', 'required_if:report_type,violation', Rule::exists('classes', 'id')->where('is_active', true)],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('is_active', true)],
            'custom_location' => ['nullable', 'string', 'max:150'],
            'incident_date' => ['nullable', 'date', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', Rule::in(['rendah', 'sedang', 'tinggi', 'darurat'])],
            'reporter_position' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:80'],
            'bullying_type' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:80'],
            'victim_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'victim_class_id' => ['exclude_unless:report_type,violation', 'nullable', Rule::exists('classes', 'id')->where('is_active', true)],
            'alleged_actor_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'alleged_actor_class_id' => ['nullable', 'exclude_unless:report_type,violation', Rule::exists('classes', 'id')->where('is_active', true)],
            'witness_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'impact_description' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:2000'],
            'item_name' => ['exclude_unless:report_type,damage', 'required', 'string', 'max:150'],
            'item_category' => ['exclude_unless:report_type,damage', 'nullable', 'string', 'max:100'],
            'damage_condition' => ['exclude_unless:report_type,damage', 'required', 'string', 'max:2000'],
            'suspected_cause' => ['exclude_unless:report_type,damage', 'nullable', 'string', 'max:1000'],
            'priority' => ['exclude_unless:report_type,damage', 'nullable', Rule::in(['rendah', 'sedang', 'tinggi', 'darurat'])],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:4096'],
            'consent' => ['accepted'],
            'captcha' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'reporter_phone.required' => 'Nomor HP wajib diisi agar sekolah dapat menghubungi pelapor.',
            'reporter_phone.regex' => 'Format nomor HP hanya boleh berisi angka, spasi, tanda +, kurung, titik, tanda hubung, atau tanda bintang untuk masking.',
            'reporter_email.email' => 'Format alamat email tidak valid.',
            'related_class_id.required_if' => 'Kelas kejadian wajib dipilih untuk laporan perundungan atau pelanggaran.',
            'title.required' => 'Judul laporan wajib diisi.',
            'description.required' => 'Kronologi wajib diisi.',
            'alleged_actor_name.required' => 'Nama pelaku wajib diisi untuk laporan perundungan.',
            'item_name.required' => 'Nama barang atau fasilitas wajib diisi.',
            'damage_condition.required' => 'Kondisi kerusakan wajib diisi.',
        ];
    }
}
