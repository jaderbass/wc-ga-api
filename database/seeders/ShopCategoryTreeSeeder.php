<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Legt die Kategorie-Struktur des PS Alpin Shops (shop.psalpin.com, Stand 08.10.2026)
 * plus die neu beschlossenen Kategorien in der Datenbank an.
 *
 * - idempotent: vorhandene Kategorien (gleicher Name) werden wiederverwendet,
 *   nichts wird gelöscht oder umbenannt
 * - eine vorhandene Kategorie ohne Oberkategorie bekommt die Oberkategorie aus dem Baum
 *
 * Aufruf: php artisan db:seed --class=ShopCategoryTreeSeeder --force
 *
 * @see docs/Kategorie-Zuordnung.md
 */
class ShopCategoryTreeSeeder extends Seeder
{
    /**
     * Name => Unterkategorien. Neu beschlossene Kategorien sind mit "(neu)" kommentiert.
     *
     * @var array<string, array<mixed>>
     */
    public const TREE = [
        'Produkte Übersicht' => [
            'Einsatzgebiet' => [
                'Baumpflege' => [],
                'Bergsteigen' => [],
                'Feuerwehr' => [],
                'Klettern' => [],
                'PSAgA' => [],
                'Rettung' => [], // neu
                'Segeln' => [], // neu
                'Spezialkräfte' => [],
                'Sport' => [],
            ],
            'Produkte' => [
                'Abseilgeräte' => ['Rettungsachter' => [], 'Zubehör Abseilgerät' => []],
                'Absturzsicherung' => [],
                'Accessoires' => [],
                'Anschlageinrichtung' => [],
                'Auffanggerät' => ['Auffanggerät mit Bandfalldämpfer' => [], 'Mitlaufende Auffanggeräte' => []],
                'Baumpflege-Zubehör' => [], // neu
                'Baumsteigeisen' => ['Baumsteigeisen-Zubehör' => []],
                'Beleuchtung' => [],
                'Dreibein' => ['Dreibein-Zubehör' => [], 'Winde' => ['Winden-Zubehör' => []]],
                'Ersatzteil' => [],
                'Falldämpfer' => [],
                'Flaschenzüge' => [],
                'Gurte' => ['Arbeitsgurt' => [], 'Auffanggurt' => [], 'Sitzgurt' => [], 'Zubehör Gurte' => []],
                'Handschuhe' => [],
                'Helme' => ['Schutzhelme' => [], 'Zubehör Schutzhelme' => [], 'personalisierte Schutzhelme' => []],
                'Höhensicherungsgeräte' => [], // neu
                'Karabiner' => [
                    'Alukarabiner' => [],
                    'Edelstahlkarabiner' => [],
                    'Express-Set' => [],
                    'Stahlkarabiner' => [],
                    'Zubehör Karabiner' => [],
                ],
                'Kletter-Zubehör' => [], // neu
                'Lastensicherung' => [], // neu
                'Messer' => [],
                'Positionierung' => [],
                'Reepschnur' => [],
                'Rettung & Transport' => ['Tragen' => ['Versand' => [], 'Zubehör für Tragen' => []]],
                'Riggingplatten' => [],
                'Schlingen & Lanyard' => [],
                'Schraubglieder' => [],
                'Segel-Zubehör' => [], // neu
                'Seile' => ['Dynamisch' => [], 'Materialseile' => [], 'Seilschutz' => [], 'Statisch' => []],
                'Seilklemmen' => ['Fußsteigklemme' => []],
                'Seilrollen' => ['Umlenkrollen' => []],
                'Sicherheitshaken' => [],
                'Sicherungsgeräte' => [],
                'Sonstiges' => [],
                'Stirnlampe' => ['Stirnlampe-Zubehör' => []],
                'Taschen & Rucksäcke' => [],
                'Transportboxen' => [],
                'Verbindungselemente' => [],
                'Verbindungsmittel' => ['Verbindungsmittel mit Falldämpfer' => []],
                'Werbemittel' => [],
                'Werkzeugsicherung' => [], // neu
                'Zubehör' => [],
            ],
            'Sets' => ['Feuerwehr-Sets' => [], 'PSAgA-Sets' => []],
        ],
        'RFID Tec' => [],
        'SALE' => [],
    ];

    /** @var array<string, int> */
    public array $stats = ['created' => 0, 'reused' => 0, 'parent_set' => 0];

    public function run(): void
    {
        $this->seedLevel(self::TREE, null);

        $this->command?->info(sprintf(
            'Kategorien: %d neu angelegt, %d schon vorhanden, %d Oberkategorie ergänzt.',
            $this->stats['created'],
            $this->stats['reused'],
            $this->stats['parent_set'],
        ));
    }

    /**
     * @param  array<string, array<mixed>>  $level
     */
    protected function seedLevel(array $level, ?Category $parent): void
    {
        foreach ($level as $name => $children) {
            $category = $this->ensureCategory($name, $parent);
            $this->seedLevel($children, $category);
        }
    }

    protected function ensureCategory(string $name, ?Category $parent): Category
    {
        $category = Category::query()->where('name', $name)->first();

        if ($category !== null) {
            $this->stats['reused']++;

            if ($category->parent_id === null && $parent !== null && $category->id !== $parent->id) {
                $category->update(['parent_id' => $parent->id]);
                $this->stats['parent_set']++;
            }

            return $category;
        }

        $this->stats['created']++;

        return Category::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'parent_id' => $parent?->id,
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '-', 'de') ?: Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
