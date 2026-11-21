<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'phone' => '+989'.fake()->numerify('#########'),
            'phone_verified_at' => now(),
            'account_type' => AccountType::Individual,
            'status' => 'active',
            'last_login_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    public function withoutName(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
        ]);
    }

    public function withoutAccountType(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Individual,
        ]);
    }

    public function organizational(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Organizational,
        ]);
    }
}
