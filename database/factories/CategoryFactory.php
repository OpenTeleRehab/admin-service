<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => 'Health condition',
            'type' => 'exercise',
            'parent_id' => null
        ];
        [
            'title' => 'Cerebral palsy',
            'type' => 'exercise',
            'parent_id' => 1
        ];
    }
}
