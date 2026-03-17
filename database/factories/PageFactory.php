<?php

namespace Karabin\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Karabin\TranslatableRevisions\Models\Page;
use Karabin\TranslatableRevisions\Models\RevisionTemplate;

class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->words(3, true),
            'template_id' => RevisionTemplate::factory()->create()->id,
        ];
    }
}
