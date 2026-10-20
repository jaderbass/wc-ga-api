<?php

/*
|--------------------------------------------------------------------------
| Initialer Administrator
|--------------------------------------------------------------------------
|
| Zugangsdaten für den AdminUserSeeder.
| Die Werte werden ausschließlich aus der Umgebung geladen.
|
*/

return [
    'name' => env('ADMIN_NAME'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
