<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddXmlStorageToDianQueue extends Migration
{
    public function up()
    {
        $this->forge->addColumn('invoices_dian_queue', [
            'xml_generated' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
                'after' => 'dian_application_response'
            ],
            'xml_signed' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
                'after' => 'xml_generated'
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('invoices_dian_queue', ['xml_generated', 'xml_signed']);
    }
}
