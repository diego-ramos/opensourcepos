<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxSchemeToCustomers extends Migration
{
    public function up(): void
    {
        $fields = [
            'tax_scheme' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'null'       => true,
                'after'      => 'tax_payer_type'
            ]
        ];

        $this->forge->addColumn('customers', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', 'tax_scheme');
    }
}
