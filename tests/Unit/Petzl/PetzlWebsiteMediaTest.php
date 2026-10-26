<?php

use App\Services\Petzl\PetzlWebsiteMedia;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function petzlPage(string $sku, array $images): string
{
    $img = implode('', array_map(fn ($file) => '<img src="https://www.petzl.com/dw/image/v2/BLQG_PRD/on/demandware.static/-/Sites-petzl-master-catalog/default/dwabc123/media/product/'.$file.'?sw=800">', $images));

    return '<html><script>{"sku":"'.$sku.'_SPO_x"}</script>'.$img.'</html>';
}

it('finds the product page that really contains our article number', function () {
    Http::fake([
        'www.petzl.com/de/de/search*' => Http::response(
            '<a href="/de/de/professional/produkte/abseilgerate/id-s/I-D-S_PRO_a1.html"></a>'
            .'<a href="/de/de/sport/produkte/stirnlampen/tikka/TIKKA_SPO_a2.html"></a>'
        ),
        'www.petzl.com/de/de/professional/*' => Http::response(petzlPage('D020AA00', ['D020AA00-ID-S_LowRes_1.jpeg'])),
        'www.petzl.com/de/de/sport/*' => Http::response(petzlPage('E061AB01', [
            'E061AB01-TIKKA_LowRes_1.jpeg',
            'E061AB01-TIKKA-focus-1_LowRes_2.jpeg',
            'E099EB00-CORE_LowRes_3.jpeg',      // Zubehör-Empfehlung, gehört nicht dazu
            'E061AB01-TIKKA_LowRes_1.jpeg',     // doppelt
        ])),
    ]);

    $media = new PetzlWebsiteMedia;
    $page = $media->findProductPage(['E061AB00', 'E061AB01']);

    expect($page['url'])->toBe('https://www.petzl.com/de/de/sport/produkte/stirnlampen/tikka/TIKKA_SPO_a2.html')
        ->and(array_map('basename', $media->imageUrls($page['html'], ['E061AB00', 'E061AB01'])))
        ->toBe(['E061AB01-TIKKA_LowRes_1.jpeg', 'E061AB01-TIKKA-focus-1_LowRes_2.jpeg'])
        ->and($media->imageUrls($page['html'], ['E061AB01'])[0])
        ->toStartWith('https://www.petzl.com/on/demandware.static/-/Sites-petzl-master-catalog/default/dwabc123/media/product/');
});

it('accepts images named after another size or the base number', function () {
    $html = petzlPage('C073BA01', [
        'C073BA00-NEWTON-INTER_LowRes_1.jpeg',      // erste Größe
        'C072GA-VOLT-LIGHT-EUR_LowRes_2.jpeg',      // nur Stammnummer
        'C073FA00-NEWTON-EASYFIT_LowRes_3.jpeg',    // anderes Modell
    ]);

    expect(array_map('basename', (new PetzlWebsiteMedia)->imageUrls($html, ['C073BA01', 'C073BA02'])))
        ->toBe(['C073BA00-NEWTON-INTER_LowRes_1.jpeg'])
        ->and(array_map('basename', (new PetzlWebsiteMedia)->imageUrls($html, ['C072GA01'])))
        ->toBe(['C072GA-VOLT-LIGHT-EUR_LowRes_2.jpeg']);
});

it('accepts images of older article numbers with spaces', function () {
    $html = petzlPage('M33A SL', [
        'M33A-SL-OK-SL_LowRes_1.jpeg',
        'L22A-ZILLON-focus-1_LowRes_2.jpeg',
        'D020AA00-ID-S_LowRes_3.jpeg',              // Empfehlung, gehört nicht dazu
    ]);

    expect(array_map('basename', (new PetzlWebsiteMedia)->imageUrls($html, ['M33A SL'])))
        ->toBe(['M33A-SL-OK-SL_LowRes_1.jpeg'])
        ->and(array_map('basename', (new PetzlWebsiteMedia)->imageUrls($html, ['L22A 2.5', 'L22A 3'])))
        ->toBe(['L22A-ZILLON-focus-1_LowRes_2.jpeg']);
});

it('also searches the newer article number when the product has an old and a new version', function () {
    Http::fake([
        'www.petzl.com/de/de/search?q=C069AB00' => Http::response('<a href="/de/de/professional/produkte/baumpflegegurte/sequoia/SEQUOIA_PRO_a1.html"></a>'),
        'www.petzl.com/de/de/search*' => Http::response(''),
        'www.petzl.com/de/de/professional/*' => Http::response(petzlPage('C069AB00', ['C069AB-SEQUOIA_LowRes_1.jpeg'])),
    ]);

    $media = new PetzlWebsiteMedia;
    $skus = ['C069AA00', 'C069AA01', 'C069AB00', 'C069AB01'];
    $page = $media->findProductPage($skus);

    expect($page['url'])->toEndWith('/sequoia/SEQUOIA_PRO_a1.html')
        ->and(array_map('basename', $media->imageUrls($page['html'], $skus)))->toBe(['C069AB-SEQUOIA_LowRes_1.jpeg']);
});

it('takes the gallery images of a given page and leaves out recommendations', function () {
    $html = '<img src="https://www.petzl.com/dw/image/v2/BLQG_PRD/on/demandware.static/-/Sites-petzl-master-catalog/default/dw1/media/product/068bK00000m0mnRQAQ_casque-industrie-vignette.png">'
        .petzlPage('L01', [
            'L01-Lezard_LowRes_1.jpeg',
            'L01-Lezard-focus-1_LowRes_2.jpeg',
            'D020AA00-ID-S_LowRes_3.jpeg',
            'C069CB00-Pont-dattache_LowRes_4.jpeg',
        ]);

    expect(array_map('basename', (new PetzlWebsiteMedia)->galleryImageUrls($html)))
        ->toBe(['L01-Lezard_LowRes_1.jpeg', 'L01-Lezard-focus-1_LowRes_2.jpeg']);

    $html = petzlPage('C069CB00', ['C069CB00-Pont-dattache_LowRes_1.jpeg', 'C069CB-Pont-focus-1_LowRes_2.jpeg', 'C069BB-SEQUOIA-PLUS_LowRes_3.jpeg']);

    expect(array_map('basename', (new PetzlWebsiteMedia)->galleryImageUrls($html)))
        ->toBe(['C069CB00-Pont-dattache_LowRes_1.jpeg', 'C069CB-Pont-focus-1_LowRes_2.jpeg']);
});

it('returns nothing when no candidate page contains the article number', function () {
    Http::fake([
        'www.petzl.com/de/de/search*' => Http::response('<a href="/de/de/professional/produkte/x/VERTEX_PRO_a1.html"></a>'),
        'www.petzl.com/de/de/professional/*' => Http::response(petzlPage('A010CA00', [])),
    ]);

    expect((new PetzlWebsiteMedia)->findProductPage(['E120BA00']))->toBeNull();
});
