<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiPreviewTest extends TestCase
{
    public function test_ui_preview_page_renders(): void
    {
        $this->get('/ui-preview')->assertOk();
    }
}
