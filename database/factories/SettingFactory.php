<?php

namespace Database\Factories;

use App\Enums\SettingGroup;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        // ⚠ is_encrypted, value'dan ÖNCE geliyor — model bunu şart koşuyor.
        return [
            'group' => SettingGroup::Store,
            'key' => fake()->unique()->slug(2),
            'is_encrypted' => false,
            'value' => fake()->word(),
        ];
    }

    /** Şifreli ayar — ödeme anahtarı, kargo API'si, SMTP parolası. */
    public function sifreli(): static
    {
        return $this->state(fn () => [
            'group' => SettingGroup::Payment,
            'is_encrypted' => true,
        ]);
    }
}
