<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxIdFieldToCustomers extends Migration
{
    public function up(): void
    {
        $fields = [
            'tax_id_type' => [
                'type'       => 'INT',
                'constraint' => 4,
                'null'       => true,
                'after'      => 'consent'
            ]
        ];

        $this->forge->addColumn('customers', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', 'tax_id_type');
    }
}