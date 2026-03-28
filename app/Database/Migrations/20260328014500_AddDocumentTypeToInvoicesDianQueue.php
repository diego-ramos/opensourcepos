<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentTypeToInvoicesDianQueue extends Migration
{
    public function up()
    {
        $fields = [
            'document_type' => [
                'type'       => 'ENUM',
                'constraint' => ['invoice', 'credit_note', 'debit_note'],
                'default'    => 'invoice',
                'after'      => 'status'
            ]
        ];
        $this->forge->addColumn('invoices_dian_queue', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('invoices_dian_queue', 'document_type');
    }
}
