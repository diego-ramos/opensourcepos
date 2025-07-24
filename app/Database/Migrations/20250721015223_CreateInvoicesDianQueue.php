<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoicesDianQueue extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true
            ],
            'sale_id' => [
                'type' => 'INT',
                'null' => false
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'sent', 'error'],
                'default'    => 'pending',
            ],
            'dian_cufe' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'dian_response_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'dian_response_description' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'dian_application_response' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'dian_zip_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'dian_sent_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
            ],
            'dian_status' => [
                'type'       => 'ENUM',
                'constraint' => ['accepted', 'rejected'],
                'default'    => 'accepted',
            ],
            'error_message' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);


        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('sale_id', 'sales', 'sale_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('invoices_dian_queue');
    }

    public function down()
    {
        $this->forge->dropTable('invoices_dian_queue');
    }

}
