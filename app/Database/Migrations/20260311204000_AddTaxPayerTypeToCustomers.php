<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxPayerTypeToCustomers extends Migration
{
    public function up(): void
    {
        $fields = [
            'tax_payer_type' => [
                'type'       => 'INT',
                'constraint' => 1,
                'null'       => true,
                'after'      => 'tax_responsibility'
            ]
        ];

        $this->forge->addColumn('customers', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', 'tax_payer_type');
    }
}
