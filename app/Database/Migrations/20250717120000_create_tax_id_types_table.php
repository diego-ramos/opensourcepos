<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaxIdTypesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'INT',
                'constraint' => 4,
                'null'       => false,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('tax_id_types');
    }

    public function down(): void
    {
        $this->forge->dropTable('tax_id_types');
    }
}
