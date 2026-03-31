<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDianCreditResolutionToAppConfig extends Migration
{
    public function up()
    {
        $builder = $this->db->table('ospos_app_config');
        
        $builder->insertBatch([
            ['key' => 'col_electronic_credit_resolution', 'value' => ''],
        ]);
    }

    public function down()
    {
        $keys_to_delete = [
            'col_electronic_credit_resolution',
        ];

        $this->db->table('ospos_app_config')
            ->whereIn('key', $keys_to_delete)
            ->delete();
    }
}
