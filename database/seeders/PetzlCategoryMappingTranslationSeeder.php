<?php

namespace Database\Seeders;

use App\Models\PetzlCategoryMapping;
use Illuminate\Database\Seeder;

class PetzlCategoryMappingTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $translations = [
            ['Anchors', 'Anchor slings and straps', 'Anschlageinrichtungen', 'Anschlagschlingen und -bänder'],
            ['Anchors', 'Anchor straps for tree care', 'Anschlageinrichtungen', 'Anschlagbänder für die Baumpflege'],
            ['Anchors', 'Quick adjustment anchor', 'Anschlageinrichtungen', 'Schnell verstellbare Anschlageinrichtungen'],
            ['Anchors', 'Rigging equipment', 'Anschlageinrichtungen', 'Rigging-Ausrüstung'],
            ['Anchors', 'Rock and concrete anchors', 'Anschlageinrichtungen', 'Anschlageinrichtungen für Fels und Beton'],
            ['Anchors', 'Temporary horizontal lifeline', 'Anschlageinrichtungen', 'Temporäre horizontale Anschlageinrichtungen'],

            ['Connectors', 'Connector positioning accessories', 'Verbindungselemente', 'Zubehör zur Positionierung von Verbindungselementen'],
            ['Connectors', 'High strength carabiners', 'Verbindungselemente', 'Hochfeste Karabiner'],
            ['Connectors', 'Lanyard end connectors', 'Verbindungselemente', 'Verbindungselemente für Verbindungsmittelenden'],
            ['Connectors', 'Light carabiners', 'Verbindungselemente', 'Leichte Karabiner'],
            ['Connectors', 'Semi-permanent connectors', 'Verbindungselemente', 'Halbpermanente Verbindungselemente'],
            ['Connectors', 'Special lightweight carabiners', 'Verbindungselemente', 'Spezielle leichte Karabiner'],
            ['Connectors', 'Ultra-light carabiners', 'Verbindungselemente', 'Ultraleichte Karabiner'],

            ['Descenders', 'Descenders for technical rescue', 'Abseilgeräte', 'Abseilgeräte für die technische Rettung'],
            ['Descenders', 'Individual evacuation system', 'Abseilgeräte', 'Individuelle Evakuierungssysteme'],
            ['Descenders', 'Mechanical Prusik for tree care', 'Abseilgeräte', 'Mechanische Prusiks für die Baumpflege'],
            ['Descenders', 'Self-braking descenders', 'Abseilgeräte', 'Selbstbremsende Abseilgeräte'],
            ['Descenders', 'Standard descenders', 'Abseilgeräte', 'Standard-Abseilgeräte'],

            ['Harnesses', 'Fall arrest and work positioning harness', 'Gurte', 'Auffang- und Haltegurte'],
            ['Harnesses', 'Fall arrest harnesses', 'Gurte', 'Auffanggurte'],
            ['Harnesses', 'Harness accessories', 'Gurte', 'Gurtzubehör'],
            ['Harnesses', 'Harnesses for fall arrest, work positioning and suspension', 'Gurte', 'Gurte für Auffangen, Arbeitsplatzpositionierung und freies Hängen'],
            ['Harnesses', 'Litter and evacuation triangles', 'Gurte', 'Rettungstragen und Rettungsdreiecke'],
            ['Harnesses', 'Rescue harnesses', 'Gurte', 'Rettungsgurte'],
            ['Harnesses', 'Rope access harnesses', 'Gurte', 'Gurte für seilunterstützte Arbeiten'],
            ['Harnesses', 'Tree care harnesses', 'Gurte', 'Gurte für die Baumpflege'],

            ['HEADLAMPS', 'Compact, durable Headlamps', 'Stirnlampen', 'Kompakte, robuste Stirnlampen'],
            ['HEADLAMPS', 'Headlamp accessories', 'Stirnlampen', 'Stirnlampenzubehör'],
            ['HEADLAMPS', 'Intelligent powerful Headlamps', 'Stirnlampen', 'Intelligente, leistungsstarke Stirnlampen'],
            ['HEADLAMPS', 'Ultra-compact and versatile headlamps', 'Stirnlampen', 'Ultrakompakte, vielseitige Stirnlampen'],

            ['Helmets', 'Comfortable helmets', 'Helme', 'Komfortable Helme'],
            ['Helmets', 'Hats and balaclava', 'Helme', 'Mützen und Sturmhauben'],
            ['Helmets', 'Helmet accessories', 'Helme', 'Helmzubehör'],
            ['Helmets', 'Lightweight helmets', 'Helme', 'Leichte Helme'],
            ['Helmets', 'Protective eye shields', 'Helme', 'Schutzvisiere'],

            ['Kits', 'Fall arrest and work positioning kit', 'Sets', 'Sets für Auffangen und Arbeitsplatzpositionierung'],
            ['Kits', 'Mobile fall arrester kits', 'Sets', 'Sets mit mitlaufendem Auffanggerät'],
            ['Kits', 'Rescue kit', 'Sets', 'Rettungssets'],
            ['Kits', 'Temporary horizontal lifelines and fall arrest kits', 'Sets', 'Temporäre horizontale Anschlageinrichtungen und Auffangsets'],

            ['Lanyards and energy absorbers', 'Fall arrest lanyards', 'Verbindungsmittel und Falldämpfer', 'Verbindungsmittel zur Absturzsicherung'],
            ['Lanyards and energy absorbers', 'Helivac lanyards', 'Verbindungsmittel und Falldämpfer', 'HELIVAC-Verbindungsmittel'],
            ['Lanyards and energy absorbers', 'Rope access lanyards', 'Verbindungsmittel und Falldämpfer', 'Verbindungsmittel für seilunterstützte Arbeiten'],
            ['Lanyards and energy absorbers', 'Tree care lanyards', 'Verbindungsmittel und Falldämpfer', 'Verbindungsmittel für die Baumpflege'],
            ['Lanyards and energy absorbers', 'Work positioning lanyards', 'Verbindungsmittel und Falldämpfer', 'Verbindungsmittel zur Arbeitsplatzpositionierung'],

            ['Mobile fall arresters', 'Absorbers for mobile fall arresters', 'Mitlaufende Auffanggeräte', 'Falldämpfer für mitlaufende Auffanggeräte'],
            ['Mobile fall arresters', 'Mobile fall arresters', 'Mitlaufende Auffanggeräte', 'Mitlaufende Auffanggeräte'],
            ['Mobile fall arresters', 'Ropes for mobile fall arresters', 'Mitlaufende Auffanggeräte', 'Seile für mitlaufende Auffanggeräte'],

            ['Packs and accessories', 'Accessories', 'Transportsäcke und Zubehör', 'Zubehör'],
            ['Packs and accessories', 'Durable packs', 'Transportsäcke und Zubehör', 'Robuste Transportsäcke'],
            ['Packs and accessories', 'Technical Packs', 'Transportsäcke und Zubehör', 'Technische Rucksäcke'],
            ['Packs and accessories', 'Transport bags', 'Transportsäcke und Zubehör', 'Transportsäcke'],
            ['Packs and accessories', 'Tree care accessories', 'Transportsäcke und Zubehör', 'Zubehör für die Baumpflege'],
            ['Packs and accessories', 'Upright packs', 'Transportsäcke und Zubehör', 'Aufrecht stehende Transportsäcke'],

            ['Pulleys', 'Haul system', 'Seilrollen', 'Flaschenzugsysteme'],
            ['Pulleys', 'High-efficiency pulleys', 'Seilrollen', 'Hochleistungs-Seilrollen'],
            ['Pulleys', 'High-efficiency pulleys with swivel', 'Seilrollen', 'Hochleistungs-Seilrollen mit Wirbel'],
            ['Pulleys', 'Progress capture pulleys', 'Seilrollen', 'Seilrollen mit Rücklaufsperre'],
            ['Pulleys', 'Prusik pulleys', 'Seilrollen', 'Prusik-Seilrollen'],
            ['Pulleys', 'Pulley-carabiners', 'Seilrollen', 'Karabiner mit integrierter Seilrolle'],
            ['Pulleys', 'Single pulleys', 'Seilrollen', 'Einfache Seilrollen'],
            ['Pulleys', 'Transport pulleys', 'Seilrollen', 'Transport-Seilrollen'],

            ['Rope clamps', 'Cam-loaded rope clamps', 'Seilklemmen', 'Seilklemmen mit Klemmnocken'],
            ['Rope clamps', 'Emergency rope clamp', 'Seilklemmen', 'Notfall-Seilklemmen'],
            ['Rope clamps', 'Rope clamps for progression', 'Seilklemmen', 'Seilklemmen für den Aufstieg'],
            ['Rope clamps', 'Versatile rope clamp', 'Seilklemmen', 'Vielseitige Seilklemmen'],

            ['Ropes', 'Accessory cords', 'Seile', 'Reepschnüre'],
            ['Ropes', 'Dynamic rope', 'Seile', 'Dynamische Seile'],
            ['Ropes', 'Low stretch kernmantel ropes', 'Seile', 'Kernmantelseile mit geringer Dehnung'],
            ['Ropes', 'Rope accessories', 'Seile', 'Seilzubehör'],
            ['Ropes', 'Rope protectors', 'Seile', 'Seilschutz'],
            ['Ropes', 'Technical cord', 'Seile', 'Technische Reepschnüre'],

            ['Spare parts', 'Absorbers for mobile fall arresters spare parts', 'Ersatzteile', 'Ersatzteile für Falldämpfer von mitlaufenden Auffanggeräten'],
            ['Spare parts', 'Anchors spare parts', 'Ersatzteile', 'Ersatzteile für Anschlageinrichtungen'],
            ['Spare parts', 'Descenders spare parts', 'Ersatzteile', 'Ersatzteile für Abseilgeräte'],
            ['Spare parts', 'Harnesses spare parts', 'Ersatzteile', 'Ersatzteile für Gurte'],
            ['Spare parts', 'Helmets spare parts', 'Ersatzteile', 'Ersatzteile für Helme'],
            ['Spare parts', 'Kit spare parts', 'Ersatzteile', 'Ersatzteile für Sets'],
            ['Spare parts', 'Lanyards spare parts', 'Ersatzteile', 'Ersatzteile für Verbindungsmittel'],
            ['Spare parts', 'Packs and accessories spare parts', 'Ersatzteile', 'Ersatzteile für Transportsäcke und Zubehör'],
            ['Spare parts', 'Rope clamps spare parts', 'Ersatzteile', 'Ersatzteile für Seilklemmen'],
            ['Spare parts', 'Rope protectors spare parts', 'Ersatzteile', 'Ersatzteile für Seilschutz'],

            ['Spare parts Lighting', 'Headlamps spare parts', 'Ersatzteile für Stirnlampen', 'Ersatzteile für Stirnlampen'],
        ];

        foreach ($translations as [$sourceCategory, $sourceSubcategory, $translatedCategory, $translatedSubcategory]) {
            PetzlCategoryMapping::query()
                ->where('source_category', $sourceCategory)
                ->where('source_subcategory', $sourceSubcategory)
                ->update([
                    'translated_category' => $translatedCategory,
                    'translated_subcategory' => $translatedSubcategory,
                ]);
        }
    }
}