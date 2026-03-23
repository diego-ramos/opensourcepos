<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCashInOutModule extends Migration
{
    public function up(): void
    {
        // 1. Create cash_sessions table
        $this->forge->addField([
            'cash_session_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'open_date' => [
                'type' => 'DATETIME',
            ],
            'open_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'default'    => 0.00,
            ],
            'close_date' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'close_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'null'       => true,
            ],
            'deleted' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
        ]);
        $this->forge->addKey('cash_session_id', true);
        $this->forge->addKey('employee_id');
        $this->forge->createTable('cash_sessions');

        // 2. Register module
        $this->db->table('modules')->insert([
            'module_id'     => 'cash_in_out',
            'name_lang_key' => 'Module.cash_in_out',
            'desc_lang_key' => 'Module.cash_in_out_desc',
            'sort'          => 110, // Adjust sort as needed
        ]);

        // 3. Register permissions
        $this->db->table('permissions')->insertBatch([
            [
                'permission_id' => 'cash_in_out',
                'module_id'     => 'cash_in_out',
            ],
            [
                'permission_id' => 'cash_in_out_report',
                'module_id'     => 'cash_in_out',
            ]
        ]);

        // 4. Grant full permissions to admin (person_id 1 usually)
        $this->db->table('grants')->insertBatch([
            [
                'permission_id' => 'cash_in_out',
                'person_id'     => 1,
            ],
            [
                'permission_id' => 'cash_in_out_report',
                'person_id'     => 1,
            ]
        ]);
    }

    public function down(): void
    {
        $this->db->table('grants')->whereIn('permission_id', ['cash_in_out', 'cash_in_out_report'])->delete();
        $this->db->table('permissions')->whereIn('permission_id', ['cash_in_out', 'cash_in_out_report'])->delete();
        $this->db->table('modules')->where('module_id', 'cash_in_out')->delete();
        $this->forge->dropTable('cash_sessions');
    }
}
