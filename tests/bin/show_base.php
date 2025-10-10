<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = config('woo.api.base_url');
$host = parse_url($base, PHP_URL_HOST);
printf("BASE=%s\nHOST=%s\nLEN=%d\n", $base, $host, strlen($base));
echo "HEX: ";
for ($i = 0; $i < strlen($base); $i++) printf("%02X ", ord($base[$i]));
echo "\nResolved IPs: ";
var_export(gethostbynamel($host));
echo "\n";
