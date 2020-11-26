<?php

/*
|--------------------------------------------------------------------------
| Lotus Params
|--------------------------------------------------------------------------
|
| Set any additional routing parameters needed for your application here,
| and you can also change default paths to your pages and collections.
|
*/

return [
    'allowed' => [
        'actions' => [
            '__construct', '__invoke', '__call', 'middleware', 'getMiddleware',
        ],
        'responders' => [
            'send', 'make'
        ]
    ],
    'pages' => [
        'path' => env('PAGE_PATH', 'resources/content/pages'),
    ],
    'collections' => [
        'path' => env('COLLECTION_PATH', 'resources/content/collections'),
    ],
];
