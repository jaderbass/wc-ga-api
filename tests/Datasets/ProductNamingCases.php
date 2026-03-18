<?php

return [
    [
        'label' => 'simple ohne attribute',
        'input' => [
            'manufacturer' => 'Edelrid',
            'kind' => 'simple',
            'category' => 'Karabiner',
            'designation' => 'Edelrid HMS Strike Screw',
            'properties' => [],
        ],
        'expected' => 'EDELRID - Karabiner - Hms Strike Screw',
    ],

    [
        'label' => 'simple mit einem attribut',
        'input' => [
            'manufacturer' => 'Petzl',
            'kind' => 'simple',
            'category' => 'Schutzhelme',
            'designation' => 'Petzl Vertex',
            'properties' => ['White'],
        ],
        'expected' => 'PETZL - Schutzhelme - Vertex - White',
    ],

    [
        'label' => 'simple mit zwei attributen',
        'input' => [
            'manufacturer' => 'Petzl',
            'kind' => 'simple',
            'category' => 'Seile',
            'designation' => 'Petzl Volta',
            'properties' => ['Orange', '60 m'],
        ],
        'expected' => 'PETZL - Seile - Volta - Orange - 60 m',
    ],

    [
        'label' => 'simple mit drei attributen',
        'input' => [
            'manufacturer' => 'Aliens',
            'kind' => 'simple',
            'category' => 'Karabiner',
            'designation' => 'Aliens Snap Link',
            'properties' => ['Red', '100 cm', 'Auslaufprodukt'],
        ],
        'expected' => 'ALIENS - Karabiner - Snap Link - Red - 100 cm - Auslaufprodukt',
    ],

    [
        'label' => 'simple mit mehr als drei attributen',
        'input' => [
            'manufacturer' => 'Edelrid',
            'kind' => 'simple',
            'category' => 'Sitz- oder Arbeitsgurte',
            'designation' => 'Edelrid Core Plus',
            'properties' => ['Black', 'Size M', 'Steel', 'Extra'],
        ],
        'expected' => 'EDELRID - Sitz- oder Arbeitsgurte - Core Plus - Black - Size M - Steel',
        'note' => 'nur p1 bis p3 dürfen im Namen landen',
    ],

    [
        'label' => 'variable parent nur ein attribut',
        'input' => [
            'manufacturer' => 'Petzl',
            'kind' => 'variable',
            'category' => 'Seile',
            'designation' => 'Petzl Axis',
            'properties' => ['Black', '200 m'],
        ],
        'expected' => 'PETZL - Seile - Axis - Black',
        'note' => 'bei variable nur p1 im Parent-Namen',
    ],

    [
        'label' => 'variable parent ohne attribut',
        'input' => [
            'manufacturer' => 'Aliens',
            'kind' => 'variable',
            'category' => 'Karabiner',
            'designation' => 'Aliens Cam Set',
            'properties' => [],
        ],
        'expected' => 'ALIENS - Karabiner - Cam Set',
    ],

    [
        'label' => 'doppelter hersteller wird entfernt',
        'input' => [
            'manufacturer' => 'Edelrid',
            'kind' => 'simple',
            'category' => 'Karabiner',
            'designation' => 'Edelrid Bulletproof Screw FG',
            'properties' => [],
        ],
        'expected' => 'EDELRID - Karabiner - Bulletproof Screw Fg',
    ],

    [
        'label' => 'fallback allgemeine kategorie',
        'input' => [
            'manufacturer' => 'Petzl',
            'kind' => 'simple',
            'category' => 'Allgemein',
            'designation' => 'Petzl Spezialtool X',
            'properties' => [],
        ],
        'expected' => 'PETZL - Allgemein - Spezialtool X',
    ],

    [
        'label' => 'set vorerst wie template',
        'input' => [
            'manufacturer' => 'Aliens',
            'kind' => 'set',
            'category' => 'Karabiner',
            'designation' => 'Aliens Rack Pack',
            'properties' => ['Auslaufprodukt'],
        ],
        'expected' => 'ALIENS - Karabiner - Rack Pack - Auslaufprodukt',
        'note' => 'später evtl. sonderbehandlung für sets',
    ],
];
