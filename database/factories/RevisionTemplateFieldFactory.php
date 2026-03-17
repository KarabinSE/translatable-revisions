<?php

namespace Karabin\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Karabin\TranslatableRevisions\Models\RevisionTemplateField;

class RevisionTemplateFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RevisionTemplateField::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'key' => $this->faker->randomElement(['page_header', 'page_title', 'page_thing', 'page_aroo', 'widget_title', 'widget_body', 'page_main_content']),
            'name' => $this->faker->domainName,
            'type' => $this->faker->randomElement(['text', 'html']),
        ];
    }
}
