<?php

namespace Fayne\QrCode\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Fayne\QrCode\Models\Qrcode;

class QrcodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Qrcode>
     */
    protected $model = Qrcode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'type' => 'wechat_mini_app',
            'internal_name' => $this->faker->unique()->word(),
            'scene' => $this->faker->unique()->regexify('[A-Za-z0-9]{32}'),
            'path' => 'pages/index/index',
        ];
    }

    public function webUrl(): static
    {
        return $this->state(fn () => [
            'type' => 'web_url',
            'scene' => null,
            'path' => null,
            'target_url' => 'https://example.com',
        ]);
    }
}
