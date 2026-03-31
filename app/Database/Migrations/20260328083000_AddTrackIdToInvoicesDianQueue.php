<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTrackIdToInvoicesDianQueue extends Migration
{
    public function up()
    {
        $fields = [
            'trackId' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
                'after'      => 'sale_id'
            ]
        ];
        $this->forge->addColumn('invoices_dian_queue', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('invoices_dian_queue', 'trackId');
    }
}
