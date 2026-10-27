<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('imports local images per product folder and serves them', function () {
    Storage::fake('public');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('product_images');
    Schema::dropIfExists('products');
    Schema::create('products', fn ($t) => [$t->id(), $t->string('product_name')->nullable(), $t->timestamps()]);
    Schema::create('product_images', fn ($t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('url'), $t->boolean('is_main')->default(false), $t->timestamps()]);
    Schema::enableForeignKeyConstraints();

    DB::table('products')->insert([['id' => 4014, 'product_name' => 'Knee Ascent Clip'], ['id' => 4015, 'product_name' => 'Hat schon Bild']]);
    DB::table('product_images')->insert(['product_id' => 4015, 'url' => 'https://x/a.jpg', 'is_main' => true]);

    $dir = sys_get_temp_dir().'/psa-bilder-'.uniqid();

    foreach ([4014, 4015, 9999] as $id) {
        mkdir("{$dir}/{$id}", 0777, true);
    }

    file_put_contents("{$dir}/4014/02.jpg", 'b');
    file_put_contents("{$dir}/4014/01.jpg", 'a');
    file_put_contents("{$dir}/4015/01.jpg", 'c');
    file_put_contents("{$dir}/9999/01.jpg", 'd');

    $this->artisan('products:import-local-images', ['directory' => $dir])->assertSuccessful();

    $images = DB::table('product_images')->where('product_id', 4014)->orderBy('id')->get();

    expect($images->pluck('url')->map(fn ($u) => basename($u))->all())->toBe(['01.jpg', '02.jpg'])
        ->and((bool) $images[0]->is_main)->toBeTrue()
        ->and(DB::table('product_images')->where('product_id', 4015)->count())->toBe(1);

    $this->get('/product-image/4014/01.jpg')->assertOk();
    $this->get('/product-image/4014/xx.jpg')->assertNotFound();
});
