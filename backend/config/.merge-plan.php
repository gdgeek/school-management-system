<?php

declare(strict_types=1);

// Yii Config merge plan
// Format: environment => group => package => files[]

return [
    '/' => [
        'params' => [
            '/' => [
                'params.php',
            ],
        ],
        'di' => [
            '/' => [
                'di.php',
            ],
        ],
        'di-web' => [
            '/' => [
                'di.php',
            ],
        ],
        'common' => [
            '/' => [
                'common.php',
            ],
        ],
        'web' => [
            '/' => [
                'web.php',
            ],
        ],
    ],
    'development' => [
        'params' => [
            '/' => [
                'development/params.php',
            ],
        ],
    ],
];
