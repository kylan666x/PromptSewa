<?php

use App\Models\Category;
use App\Models\Prompt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('category page shows only prompts in that category', function () {
    $writing = Category::factory()->create(['name' => 'Writing & Content', 'slug' => 'writing-content']);
    $image = Category::factory()->create(['name' => 'Image Generation', 'slug' => 'image-generation']);

    Prompt::factory()->published()->create(['title' => 'Writing Category Hit', 'category_id' => $writing->id]);
    Prompt::factory()->published()->create(['title' => 'Image Category Hit', 'category_id' => $image->id]);

    $this->get('/categories/writing-content')
        ->assertOk()
        ->assertSee('Writing Category Hit')
        ->assertDontSee('Image Category Hit');
});

test('unknown category slug returns 404', function () {
    $this->get('/categories/does-not-exist')->assertNotFound();
});

test('inactive categories are not browsable', function () {
    Category::factory()->inactive()->create(['name' => 'Archived Stuff', 'slug' => 'archived-stuff']);

    $this->get('/categories/archived-stuff')->assertNotFound();
});

test('category pages render via route model binding with slug key', function () {
    $category = Category::factory()->create(['name' => 'Marketing & Growth', 'slug' => 'marketing-growth']);
    Prompt::factory()->count(2)->published()->create(['category_id' => $category->id]);

    $response = $this->get('/categories/marketing-growth');

    $response->assertOk();
    expect($response->viewData('category')->is($category))->toBeTrue();
});
