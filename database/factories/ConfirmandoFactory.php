<?php

namespace Database\Factories;

use App\Models\Confirmando;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfirmandoFactory extends Factory
{
    /**
     * El nombre del modelo correspondiente.
     *
     * @var string
     */
    protected $model = Confirmando::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName().' '.$this->faker->lastName(),
            'celular' => $this->faker->numerify('9########'),
            'genero' => $this->faker->randomElement(['m', 'f']),
            'fecha_nacimiento' => $this->faker->dateTimeBetween('-17 years', '-15 years'),

            // Asigna un 'grupo_id' de un grupo que ya exista
            'grupo_id' => Grupo::inRandomOrder()->first()->id,
        ];
    }
}
