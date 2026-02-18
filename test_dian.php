<?php

require_once 'vendor/autoload.php';

use DianFE\DianFE;

$config = [
    'ambiente'       => 'produccion',
    'nit'            => '123456789',
    'certificado'    => __DIR__ . '/certs/certificado.p12',
    'password_cert'  => 'clave123',
    'software_id'    => 'tu_software_id',
    'pin'            => 'tu_pin',
    'clave_tecnica'  => 'tu_clave_tecnica',
];

$dian = new DianFE($config);
echo "DianFE loaded correctly\n";
