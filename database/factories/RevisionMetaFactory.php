<?php

namespace Karabin\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Karabin\TranslatableRevisions\Models\RevisionMeta;

class RevisionMetaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RevisionMeta::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'meta_key' => Str::slug($this->faker->words(3, true), '_'),
            'model_version' => random_int(1, 12),
            'meta_value' => json_encode([
                'title' => $this->faker->sentence,
                'content' => $this->faker->randomHtml(1, 1),
            ]),
        ];
    }
}
