<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TaxIdTypesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['code' => 11, 'label' => 'Registro civil de nacimiento', 'active' => 1],
            ['code' => 12, 'label' => 'Tarjeta de identidad', 'active' => 1],
            ['code' => 13, 'label' => 'Cédula de ciudadanía', 'active' => 1],
            ['code' => 21, 'label' => 'Tarjeta de extranjería', 'active' => 1],
            ['code' => 22, 'label' => 'Cédula de extranjería', 'active' => 1],
            ['code' => 31, 'label' => 'NIT', 'active' => 1],
            ['code' => 41, 'label' => 'Pasaporte', 'active' => 1],
            ['code' => 42, 'label' => 'Tipo de documento extranjero', 'active' => 1],
            ['code' => 50, 'label' => 'NUIP', 'active' => 1],
            ['code' => 91, 'label' => 'Sin identificación del exterior o para uso definido por la DIAN', 'active' => 1],
            ['code' => 99, 'label' => 'Otro documento extranjero', 'active' => 1],
        ];
        $this->db->table('tax_id_types')->insertBatch($data);
    }
}
