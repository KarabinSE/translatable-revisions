<?php

namespace Infab\TranslatableRevisions\Tests\Page;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Infab\TranslatableRevisions\Events\DefinitionsPublished;
use Infab\TranslatableRevisions\Events\DefinitionsUpdated;
use Infab\TranslatableRevisions\Events\TranslatedRevisionDeleted;
use Infab\TranslatableRevisions\Events\TranslatedRevisionUpdated;
use Infab\TranslatableRevisions\Models\I18nDefinition;
use Infab\TranslatableRevisions\Models\I18nTerm;
use Infab\TranslatableRevisions\Models\Page;
use Infab\TranslatableRevisions\Models\RevisionMeta;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Infab\TranslatableRevisions\Models\RevisionTemplateField;
use Infab\TranslatableRevisions\Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    /** @test **/
    public function it_can_get_a_representation_of_a_page_model()
    {
        $pageModel = new Page();

        $this->assertInstanceOf(Page::class, $pageModel);
    }

    /** @test **/
    public function it_can_set_the_pages_table_via_config_file()
    {
        $page = new Page();
        $table = $page->getTable();

        $this->assertEquals('pages', $table);

        Config::set('translatable-revisions.revisions_table_name', 'new_pages');
        $this->assertEquals('new_pages', config('translatable-revisions.revisions_table_name'));
    }

    /** @test **/
    public function it_can_have_a_page_template()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $page = Page::factory()->create([
            'template_id' => $template->id,
        ]);

        // Act
        $this->assertInstanceOf(RevisionTemplate::class, $page->template);
    }

    /** @test **/
    public function it_can_have_meta_data()
    {
        // Arrange
        $page = Page::factory()->create();
        $meta_data = RevisionMeta::factory()->count(2)->create();
        $page->meta()->save($meta_data->first());
        $page->meta()->save($meta_data->last());

        // Assert
        $this->assertInstanceOf(RevisionMeta::class, $page->meta->first());
        $this->assertCount(2, $page->meta);
        $this->assertArrayHasKey('meta_key', $meta_data->first()->toArray());
        $this->assertEquals('Infab\TranslatableRevisions\Models\Page', $meta_data->first()->model_type);
    }

    /** @test **/
    public function it_can_update_fields_for_a_page()
    {
        // Arrange
        Event::fake(TranslatedRevisionUpdated::class);
        $template = RevisionTemplate::factory()->create();
        $templateFields = RevisionTemplateField::factory()->count(5)->create([
            'template_id' => $template->id,
            'translated' => true,
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10,
            'created_at' => now()->subYear(),
            'updated_at' => now()->subMonth(),
        ]);

        // Act
        $key = $templateFields->first()->toArray()['key'];
        $fields = $page->updateContent([
            $key => 'En hel del saker',
        ], 'sv');

        $updatedFields = $page->updateContent([
            $key => 'En hel del andra saker',
        ], 'sv');

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => json_encode('En hel del andra saker'),
            'locale' => 'sv',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'pages'.$page->getDelimiter().$page->id.$page->getDelimiter().$page->revision.$page->getDelimiter().$key,
            'description' => $templateFields->first()->name.' for '.$page->title,
        ]);
        Event::assertDispatched(TranslatedRevisionUpdated::class);
    }

    /** @test **/
    public function it_stores_structured_lookup_columns_for_translated_terms()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $field = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'subheader',
            'name' => 'Subheader',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 4,
        ]);

        // Act
        $page->updateContent([
            'subheader' => 'Structured lookup content',
        ], 'sv', 4);

        // Assert
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'pages'.$page->getDelimiter().$page->id.$page->getDelimiter().$page->revision.$page->getDelimiter().$field->key,
            'model_type' => $page->getMorphClass(),
            'model_id' => $page->id,
            'model_version' => 4,
            'field_key' => 'subheader',
        ]);
    }

    /** @test **/
    public function it_can_update_grouped_fields_for_a_page()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $titleField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
        ]);
        $boxField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10,
        ]);

        // Act
        $fields = $page->updateContent([
            'page_title' => 'The page title for the page',
            // 'image' => 71,
            'boxes' => [
                ['title' => 'Box 1 title!', 'url' => 'https://google.com'],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ],
        ]);

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => json_encode('The page title for the page'),
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => json_encode('The page title for the page'),
        ]);
        $terms = DB::table('i18n_terms')->get();

        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'pages'.$page->getDelimiter().$page->id.$page->getDelimiter().$page->revision.$page->getDelimiter().'page_title',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'pages'.$page->getDelimiter().$page->id.$page->getDelimiter().$page->revision.$page->getDelimiter().'boxes',
        ]);

        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'en',
            'content' => json_encode([
                ['title' => 'Box 1 title!', 'url' => 'https://google.com'],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ]),

        ]);
        // $this->assertDatabaseHas('i18n_definitions', [
        //     'locale' => 'en',
        //     'content' => json_encode('https://bog.com'),
        // ]);
        // $this->assertDatabaseHas('i18n_definitions', [
        //     'locale' => 'en',
        //     'content' => json_encode('http://flank.se'),
        // ]);
    }

    /** @test **/
    public function it_uses_the_supplied_locale_for_non_translated_repeater_subfields()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => false,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 2,
        ]);

        // Act
        $page->updateContent([
            'boxes' => [
                ['title' => 'Hej'],
            ],
        ], 'sv', 2);

        $page->updateContent([
            'boxes' => [
                ['title' => 'Hello'],
            ],
        ], 'en', 2);

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'sv',
            'content' => json_encode('Hej'),
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'en',
            'content' => json_encode('Hello'),
        ]);
    }

    /** @test **/
    public function it_can_publish_a_revision()
    {
        // Arrange
        Event::fake(DefinitionsPublished::class);
        $prefix = config('translatable-revisions.i18n_table_prefix_name');
        DB::table($prefix.'i18n_locales')->insert([
            'name' => 'Swedish',
            'native' => 'Svenska',
            'iso_code' => 'sv',
            'regional' => 'se_SV',
            'enabled' => true,
        ]);
        $template = RevisionTemplate::factory()->create();
        $templateFields = RevisionTemplateField::factory()->count(5)->create([
            'template_id' => $template->id,
            'translated' => true,
        ]);
        $page = Page::factory()->create([
            'id' => 1,
            'title' => 'Original start page',
            'template_id' => $template->id,
            'revision' => 1,
            'published_version' => null,
        ]);
        $key = $templateFields->first()->toArray()['key'];

        $fields = $page->updateContent([
            $key => 'En hel del saker',
        ], 1, 'sv', 1);

        $revisionFields = $page->updateContent([
            $key => 'What, helt annat',
        ], 'sv', 2);

        $enFields = $page->updateContent([
            $key => 'A bunch of things',
        ], 'en', 1);

        $enRevisionFields = $page->updateContent([
            $key => 'What, something completeley different!',
        ], 'en', 2);

        // Act
        $page->publish(2);

        // Assert
        $this->assertDatabaseHas('pages', [
            'id' => 1,
            'published_version' => 2,
            'revision' => 3,
        ]);

        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => 'En hel del saker',
            'locale' => 'sv',
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => json_encode('What, helt annat'),
            'locale' => 'sv',
        ]);

        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => json_encode('A bunch of things'),
            'locale' => 'en',
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => json_encode('What, something completeley different!'),
            'locale' => 'en',
        ]);

        Event::assertDispatched(DefinitionsPublished::class);
    }

    /** @test **/
    public function it_can_get_field_content()
    {
        // Arrange
        Event::fake(DefinitionsUpdated::class);
        $template = RevisionTemplate::factory()->create();
        $titleField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
        ]);
        $boxField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
            'type' => 'repeater',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 0,
        ]);

        // Act
        app()->setLocale('sv');
        $fields = $page->updateContent([
            'page_title' => 'The page title for the page',
            'boxes' => [
                [
                    'title' => 'Box 1 title!',
                    'url' => 'https://google.com',
                    'image' => [1],
                    'children' => [
                        [
                            'title' => 'Sweet child',
                            'pop_image' => [2],
                        ],
                    ],
                ],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ],
        ]);
        // dd(I18nDefinition::all()->toArray(), I18nTerm::all()->toArray());

        // Act
        $content = $page->getFieldContent(0);

        // Assert
        $this->assertEquals(3, count($content['boxes']));
        Event::assertDispatched(DefinitionsUpdated::class);
    }

    /** @test **/
    public function it_can_use_snapshot_read_model_when_enabled()
    {
        // Arrange
        Config::set('translatable-revisions.use_snapshot_read_model', true);
        $template = RevisionTemplate::factory()->create();
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
            'type' => 'text',
        ]);
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
            'type' => 'repeater',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 1,
        ]);

        $page->updateContent([
            'page_title' => 'Snapshot title',
            'boxes' => [
                ['title' => 'Box A'],
            ],
        ], 'en', 1);

        // Act
        $content = $page->getFieldContent(1, 'en');

        // Assert
        $this->assertEquals('Snapshot title', $content['page_title']);
        $this->assertDatabaseHas(config('translatable-revisions.revision_snapshots_table_name'), [
            'model_type' => $page->getMorphClass(),
            'model_id' => $page->id,
            'model_version' => 1,
            'locale' => 'en',
        ]);
    }

    /** @test **/
    public function it_refreshes_snapshot_after_content_update()
    {
        // Arrange
        Config::set('translatable-revisions.use_snapshot_read_model', true);
        $template = RevisionTemplate::factory()->create();
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
            'type' => 'text',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 1,
        ]);

        $page->updateContent([
            'page_title' => 'Before update',
        ], 'en', 1);
        $page->getFieldContent(1, 'en');

        // Act
        $page->updateContent([
            'page_title' => 'After update',
        ], 'en', 1);
        $updated = $page->getFieldContent(1, 'en');

        // Assert
        $this->assertEquals('After update', $updated['page_title']);
        $this->assertDatabaseHas(config('translatable-revisions.revision_snapshots_table_name'), [
            'model_type' => $page->getMorphClass(),
            'model_id' => $page->id,
            'model_version' => 1,
            'locale' => 'en',
        ]);
    }

    /** @test **/
    public function it_does_not_purge_other_locale_snapshots_when_saving_one_locale()
    {
        // Arrange
        Config::set('translatable-revisions.use_snapshot_read_model', true);
        $template = RevisionTemplate::factory()->create();
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
            'type' => 'text',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 2,
        ]);

        $page->updateContent([
            'page_title' => 'Hej',
        ], 'sv', 2);
        $page->updateContent([
            'page_title' => 'Hello',
        ], 'en', 2);

        // Warm both locale snapshots
        $page->getFieldContent(2, 'sv');
        $page->getFieldContent(2, 'en');

        $snapshotTable = config('translatable-revisions.revision_snapshots_table_name');
        $this->assertEquals(2, DB::table($snapshotTable)
            ->where('model_type', $page->getMorphClass())
            ->where('model_id', $page->id)
            ->where('model_version', 2)
            ->count());

        // Act: only update Swedish locale
        $page->updateContent([
            'page_title' => 'Hej igen',
        ], 'sv', 2);
        $page->getFieldContent(2, 'sv');

        // Assert: English snapshot still exists
        $this->assertDatabaseHas($snapshotTable, [
            'model_type' => $page->getMorphClass(),
            'model_id' => $page->id,
            'model_version' => 2,
            'locale' => 'en',
        ]);
        $this->assertEquals(2, DB::table($snapshotTable)
            ->where('model_type', $page->getMorphClass())
            ->where('model_id', $page->id)
            ->where('model_version', 2)
            ->count());
    }

    /** @test **/
    public function it_can_backfill_snapshots_with_the_console_command()
    {
        // Arrange
        Config::set('translatable-revisions.use_snapshot_read_model', false);
        $template = RevisionTemplate::factory()->create();
        RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
            'type' => 'text',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 1,
        ]);
        $page->updateContent([
            'page_title' => 'Backfill me',
        ], 'en', 1);

        // Act
        $this->artisan('translatable-revisions:snapshot-backfill', [
            '--model' => [Page::class],
        ])->assertExitCode(0);

        // Assert
        $this->assertDatabaseHas(config('translatable-revisions.revision_snapshots_table_name'), [
            'model_type' => $page->getMorphClass(),
            'model_id' => $page->id,
            'model_version' => 1,
            'locale' => 'en',
        ]);
    }

    /** @test **/
    public function it_can_get_revision_meta_image()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $imageField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'type' => 'image',
            'name' => 'image',
            'translated' => false,
            'key' => 'image',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10,
        ]);

        // Act
        $fields = $page->updateContent([
            'image' => [71, 80, 90],
        ]);

        // Assert
        $content = $page->fresh()->getFieldContent(10);
        $this->assertArrayHasKey('image', $content);
        $this->assertContains(71, $content['image']['meta_value']);
        $this->assertContains(80, $content['image']['meta_value']);
        $this->assertContains(90, $content['image']['meta_value']);
    }

    /** @test **/
    public function it_can_delete_a_page()
    {
        // Arrange
        Event::fake(TranslatedRevisionDeleted::class);
        $template = RevisionTemplate::factory()->create();
        $titleField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title',
        ]);
        $boxField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => false,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
            'type' => 'repeater',
        ]);
        $imageField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'type' => 'image',
            'name' => 'image',
            'translated' => false,
            'key' => 'what_image',
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10,
        ]);
        $fields = $page->updateContent([
            'page_title' => 'The page title for the page',
            'what_image' => [71],
            'boxes' => [
                ['title' => 'Box 1 title!', 'url' => 'https://google.com'],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ],
        ]);

        // Act
        $page->delete();

        // Assert
        $this->assertDatabaseMissing('pages', [
            'id' => $page->id,
        ]);

        $this->assertDatabaseMissing('i18n_terms', [
            'key' => 'pages'.$page->getDelimiter().$page->id.$page->getDelimiter().'10-page_title',
        ]);
        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => 'The page title for the page',
        ]);
        $this->assertDatabaseMissing('revision_meta', [
            'model_id' => $page->id,
            'model_type' => 'Infab\TranslatableRevisions\Models\Page',
        ]);
        Event::assertDispatched(TranslatedRevisionDeleted::class);
    }

    /** @test **/
    public function it_can_get_revision_options()
    {
        // Arrange
        $page = Page::factory()->create([
            'revision' => 10,
        ]);
        $tagsToFlush = $page->getRevisionOptions()->cacheKeysToFlush;

        // Act

        // Assert
        $this->assertIsArray($tagsToFlush);
        $this->assertEquals('cms_pages', $tagsToFlush[0]);
    }

     /** @test **/
     public function it_can_get_indexable_keys_from_options()
     {
         // Arrange
         $page = Page::factory()->create([
             'revision' => 10,
         ]);
         $indexableKeys = $page->getRevisionOptions()->indexableKeys;

         // Act

         // Assert
         $this->assertIsArray($indexableKeys);
         $this->assertEquals('page_title', $indexableKeys[0]);
         $this->assertEquals(true, $page->getRevisionOptions()->isIndexable);
         $this->assertEquals('page_title', $page->getRevisionOptions()->titleKey);
     }
}
