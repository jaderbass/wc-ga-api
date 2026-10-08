<?php

/**
 * Preislisten (pricelist:apply) – Vorschläge für die Hersteller-Zuordnung.
 *
 * Beim Einlesen einer Preisliste bekommt jede neue Herstellerkategorie
 * (Hersteller + Produktart bzw. Zwischenüberschrift) einen Eintrag in der
 * "Hersteller-Zuordnung". Ist er noch leer und ungeprüft, wird er mit dem
 * ersten passenden Vorschlag unten befüllt. Danach gilt, was im Backend steht.
 *
 * - keywords:   Stichwörter in der Herstellerkategorie, mit | getrennt;
 *               Treffer am Wortanfang, * davor = auch mitten im Wort
 * - categories: Kategorienamen (wie im Backend, Namen sind eindeutig)
 * - exclude:    true = "Ausschließen" (keine Kategorie; Shop-Export beachtet das noch nicht)
 *
 * Entscheidungen: Maik (PS Alpin), 08.10.2026 – siehe docs/Kategorie-Zuordnung.md
 */
return [

    'aliens' => [
        // Herstellerkategorie = Produktart aus dem Kurztext, Reihenfolge = Priorität
        'suggestions' => [
            ['keywords' => 'helm mit', 'categories' => ['Schutzhelme']],
            ['keywords' => 'overall|unterziehanzug|sweatshirt|hoody|hoodie|mütze|beanie|warnweste|schal|multifunktionsschal|shirt|t shirt|hose|arbeitshose|jacke', 'exclude' => true],
            ['keywords' => 'hundegurt|hundegeschirr|*hundegeschirr|*hundegurt', 'categories' => ['Rettung & Transport']],
            ['keywords' => 'visier|gehörschutz|kapselgehörschutz|helmhalter|mikrofon|hängesystem|visierhalter|visieradapter', 'categories' => ['Zubehör Schutzhelme']],
            ['keywords' => 'höhensicherungsgerät|halterung', 'categories' => ['Höhensicherungsgeräte']],
            ['keywords' => 'höhensicherungsset|gerätesatz', 'categories' => ['PSAgA-Sets']],
            ['keywords' => 'spiralkabel|tool frog|werkzeughalterung|werkzeugsicherung|zubehörkarabiner|materialkarabiner|elastikschlinge', 'categories' => ['Werkzeugsicherung']],
            ['keywords' => '*cambiumschoner|*kambiumschoner|wurfbeutel|wurfleine|steighilfe', 'categories' => ['Baumpflege-Zubehör']],
            ['keywords' => 'statikseil|arbeitsseil|klemmknotseil|prusikseil|lowering rope|aramidseil|kernmantel', 'categories' => ['Statisch']],
            ['keywords' => 'kletterseil|einfachseil|halb zwillingsseil|halbseil|zwillingsseil|dynamische seil', 'categories' => ['Dynamisch']],
            ['keywords' => 'reepschnur', 'categories' => ['Reepschnur']],
            ['keywords' => 'gummileine|hobbyschnur|yachting rope|outdoorseil|segelleine|polyester seil|vectran seil|lanyard elastisch', 'categories' => ['Segel-Zubehör', 'Segeln']],
            ['keywords' => 'bronze|marinebronze|bronze bolzenkarabiner', 'categories' => ['Zubehör']],
            ['keywords' => 'felshaken|klemmgerät|klemmkeil|chalk|magnesia|magnesium|unterarmtrainer|trekkingstöcke|steigeisen|armtrainer|antistollplatte|beta stick|seilbürste|klettergriff|eispickel|eisgerät|finger tape|sicherungsbrille|klettersteigset|aluklettersteigkarabiner|bohrhakenlasche|verzinkte bohrhakenlasche|flüssigchalk|magnesiumball|rißkletterhandschutz', 'categories' => ['Kletter-Zubehör']],
            ['keywords' => 'express set|expressset|indoor expressset|quick draw', 'categories' => ['Express-Set']],
            ['keywords' => 'expressschlinge', 'categories' => ['Schlingen & Lanyard']],
            ['keywords' => 'karabinerfixierung', 'categories' => ['Zubehör Karabiner']],
            ['keywords' => 'edelstahlkarabiner|edelstahl karabiner', 'categories' => ['Edelstahlkarabiner']],
            ['keywords' => 'stahlkarabiner|einhandkarabiner stahl', 'categories' => ['Stahlkarabiner']],
            ['keywords' => 'alukarabiner|alu karabiner|einhandkarabiner|karabiner', 'categories' => ['Alukarabiner']],
            ['keywords' => 'schraubglied|schraubkettenglied', 'categories' => ['Schraubglieder']],
            ['keywords' => 'aluring|ring|schnelltrennung|stahl schnelltrennung|aufschraubbarer ring', 'categories' => ['Verbindungselemente']],
            ['keywords' => 'komplettgurt|kinderkomplettgurt|auffanggurt', 'categories' => ['Auffanggurt']],
            ['keywords' => 'arbeitsgurt', 'categories' => ['Arbeitsgurt']],
            ['keywords' => 'sitzgurt|klettergurt|kinderklettergurt|hüftgurt|hochseilgartengurt|industriegurt|baumpflegegurt', 'categories' => ['Sitzgurt']],
            ['keywords' => 'brustgurt|zubehörgürtel|verstellbarer zubehörgürtel|hüftgürtel|sitzbrett|fußstütze|polsterung', 'categories' => ['Zubehör Gurte']],
            ['keywords' => 'falldämpferset', 'categories' => ['Verbindungsmittel mit Falldämpfer']],
            ['keywords' => 'falldämpfer|aufreißfalldämpfer', 'categories' => ['Falldämpfer']],
            ['keywords' => '*positionier', 'categories' => ['Positionierung']],
            ['keywords' => 'verbindungsmittel|lanyard|prusik lanyard|stahlseillanyard', 'categories' => ['Verbindungsmittel']],
            ['keywords' => 'mitlaufendes auffangerät|mitlaufendes auffanggerät', 'categories' => ['Mitlaufende Auffanggeräte']],
            ['keywords' => 'kletterhelm|arbeitshelm|helm', 'categories' => ['Schutzhelme']],
            ['keywords' => '*schlinge|*schlingen|tubular band|rolle tubular band|schlauchband', 'categories' => ['Schlingen & Lanyard']],
            ['keywords' => '*rolle', 'categories' => ['Seilrollen']],
            ['keywords' => '*steigklemme|seilklemme|ascender', 'categories' => ['Seilklemmen']],
            ['keywords' => '*abseil', 'categories' => ['Abseilgeräte']],
            ['keywords' => 'sicherungsgerät|bremsplatte|alu bremsplatte|seilbremse', 'categories' => ['Sicherungsgeräte']],
            ['keywords' => 'riggingplatte', 'categories' => ['Riggingplatten']],
            ['keywords' => '*handschuhe', 'categories' => ['Handschuhe']],
            ['keywords' => 'taschenmesser|rettungsmesser|messer|schneidspitze|heißschneidegerät', 'categories' => ['Messer']],
            ['keywords' => '*tasche|rucksack|seilsack|seilrucksack|materialbag|chalk bag|schlauchbeutel', 'categories' => ['Taschen & Rucksäcke']],
            ['keywords' => '*trage|rettungs spineboard|rettungsdreieck|rettungssystem|rettungsbügel|nackenstabilisator', 'categories' => ['Tragen']],
            ['keywords' => 'safety tripod dreibein|dreibein|universal dreibeinhalter', 'categories' => ['Dreibein']],
            ['keywords' => '*winde|akkuwinde', 'categories' => ['Winde']],
            ['keywords' => 'flaschenzug|flaschenzugsystem|flaschenzug set', 'categories' => ['Flaschenzüge']],
            ['keywords' => 'mobiler anschlagpunkt|mobiles anschlagsystem|anschlagmittel|traversenanker|anschlaghilfe|verankerungssystem|sicherheitstraverse|dehn spreizschraube|spreizanker|schwerlastanker|bohrhaken set|gerüsthaken|fensterwagen|universalhalter|halter', 'categories' => ['Anschlageinrichtung']],
            ['keywords' => 'kanten seilschutz|kantenschutz|seilschoner|seilschutz', 'categories' => ['Seilschutz']],
            ['keywords' => 'stirnlampe', 'categories' => ['Stirnlampe']],
            ['keywords' => 'ersatzteil|ersatzteilset', 'categories' => ['Ersatzteil']],
        ],
    ],

    'edelrid' => [
        // Herstellerkategorie = Zwischenüberschrift (deutscher Teil)
        'suggestions' => [
            ['keywords' => 'statikseile|seile mit endverbindung', 'categories' => ['Statisch']],
            ['keywords' => 'reepschnüre', 'categories' => ['Reepschnur']],
            ['keywords' => 'gurte', 'categories' => ['Gurte']],
            ['keywords' => 'helme', 'categories' => ['Schutzhelme']],
            ['keywords' => 'rfid zubehör', 'categories' => ['RFID Tec']],
            ['keywords' => 'verbindungsmittel', 'categories' => ['Verbindungsmittel']],
            ['keywords' => 'hartware karabiner', 'categories' => ['Alukarabiner']],
            ['keywords' => 'hartware zubehör', 'categories' => ['Ersatzteil']],
            ['keywords' => 'transport', 'categories' => ['Taschen & Rucksäcke']],
            ['keywords' => 'zubehör', 'categories' => ['Zubehör']],
            ['keywords' => 'bekleidung', 'exclude' => true],
            ['keywords' => 'merchandising', 'categories' => ['Werbemittel']],
        ],

        // Stichwort-Ausnahmen innerhalb einer Zwischenüberschrift (werden als
        // Stichwort-Regeln für Edelrid angelegt, falls noch nicht vorhanden)
        'keyword_rules' => [
            ['source' => 'REEPSCHNÜRE', 'keywords' => 'throw line|retrieval cone', 'categories' => ['Baumpflege-Zubehör']],
            ['source' => 'REEPSCHNÜRE', 'keywords' => 'hot cutting device|spare blade', 'categories' => ['Messer']],
            ['source' => 'GURTE', 'keywords' => 'spare|back plate|se velcro', 'categories' => ['Zubehör Gurte']],
            ['source' => 'HELME', 'keywords' => 'spare|*kit|*sticker', 'categories' => ['Zubehör Schutzhelme']],
            ['source' => 'VERBINDUNGSMITTEL', 'keywords' => 'shockstop|shock stop', 'categories' => ['Verbindungsmittel mit Falldämpfer']],
            ['source' => 'VERBINDUNGSMITTEL', 'keywords' => '*cambium', 'categories' => ['Baumpflege-Zubehör']],
            ['source' => 'VERBINDUNGSMITTEL', 'keywords' => '*sling', 'categories' => ['Schlingen & Lanyard']],
            ['source' => 'HARTWARE KARABINER', 'keywords' => 'steel', 'categories' => ['Stahlkarabiner']],
            ['source' => 'ZUBEHÖR', 'keywords' => '*knife', 'categories' => ['Messer']],
            ['source' => 'ZUBEHÖR', 'keywords' => 'tool safety leash', 'categories' => ['Werkzeugsicherung']],
            ['source' => 'ZUBEHÖR', 'keywords' => 'corazon|mini o', 'categories' => ['Sicherungsgeräte']],
            ['source' => 'BEKLEIDUNG', 'keywords' => 'glove|gloves', 'categories' => ['Handschuhe']],
        ],
    ],

];
