<?php

namespace Karabin\TranslatableRevisions\Tests;

class AssetPublishTest extends TestCase
{
    /** @test **/
    public function it_can_publish_assets()
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Karabin\TranslatableRevisions\TranslatableRevisionsServiceProvider',
        ])
        ->assertExitCode(0);
    }
}
