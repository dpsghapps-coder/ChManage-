<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ComingSoonPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_module_not_built_yet_has_a_placeholder_page(): void
    {
        $viewer = $this->userWith(['members.view']);
        $other = $this->userWith([]);

        foreach (config('modules') as $section => $module) {
            foreach ($module['pages'] as $slug => [$title]) {
                $this->actingAs($viewer)->get(route("{$section}.{$slug}"))->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->component('coming-soon')
                        ->where('title', $title)->where('section', $module['title'])->where('path', "/{$section}/{$slug}"));
                $this->actingAs($other)->get(route("{$section}.{$slug}"))->assertForbidden();
            }
        }
    }
}
