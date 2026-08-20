<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Report>
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_number' => 'LAP-' . Str::random(6),
            'public_token' => Str::uuid(),
            'access_code_hash' => hash('sha256', Str::random()),
            'reporter_type' => 'siswa',
            'reporter_name' => $this->faker->name(),
            'report_type' => $this->faker->randomElement(['violation', 'damage']),
            'title' => $this->faker->sentence(),
            'incident_date' => $this->faker->date(),
            'description' => $this->faker->paragraph(),
            'status' => 'menunggu_verifikasi',
            'urgency' => $this->faker->randomElement(['rendah', 'sedang', 'tinggi', 'darurat']),
        ];
    }
}
