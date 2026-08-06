<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'role' => UserRole::Patient,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user as an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Create a user as a doctor.
     */
    public function doctor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Doctor,
        ]);
    }

    /**
     * Create a user as a patient.
     */
    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Patient,
        ]);
    }
}
