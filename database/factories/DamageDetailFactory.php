<?php

namespace Database\Factories;

use App\Models\DamageDetail;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageDetail>
 */
class DamageDetailFactory extends Factory
{
    protected $model = DamageDetail::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'item_name' => fake()->words(2, true),
            'item_category' => 'Elektronik',
            'damage_condition' => fake()->sentence(),
            'suspected_cause' => fake()->sentence(),
            'priority' => null,
            'scheduled_repair_at' => null,
            'repaired_at' => null,
        ];
    }
}
