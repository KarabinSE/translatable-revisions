<?php

namespace Karabin\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Karabin\TranslatableRevisions\Models\RevisionTemplate;

class RevisionTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RevisionTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'slug' => Str::slug($this->faker->name),
        ];
    }
}
