<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxResponsibilityToCustomers extends Migration
{
    public function up(): void
    {
        $fields = [
            'tax_responsibility' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'tax_id_type'
            ]
        ];

        $this->forge->addColumn('customers', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', 'tax_responsibility');
    }
}
