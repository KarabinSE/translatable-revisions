<?php

namespace Karabin\TranslatableRevisions\Tests\Page;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Karabin\TranslatableRevisions\Models\RevisionTemplate;
use Karabin\TranslatableRevisions\Models\RevisionTemplateField;
use Karabin\TranslatableRevisions\Tests\TestCase;

class PageTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test **/
    public function it_can_get_a_representation_of_a_page_template_model()
    {
        $RevisionTemplate = new RevisionTemplate();

        $this->assertInstanceOf(RevisionTemplate::class, $RevisionTemplate);
    }

    /** @test **/
    public function a_template_can_have_many_template_fields()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $templateFields = RevisionTemplateField::factory()->count(6)->create([
            'template_id' => $template->id,
        ]);

        // Assert
        $this->assertCount(6, $template->fields);
    }
}
