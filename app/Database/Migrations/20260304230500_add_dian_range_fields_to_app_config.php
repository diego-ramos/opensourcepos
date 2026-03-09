<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDianRangeFieldsToAppConfig extends Migration
{
    public function up()
    {
        $builder = $this->db->table('ospos_app_config');
        
        $builder->insertBatch([
            ['key' => 'col_electronic_range_resolution', 'value' => ''],
            ['key' => 'col_electronic_range_start_date', 'value' => ''],
            ['key' => 'col_electronic_range_end_date', 'value' => ''],
        ]);
    }

    public function down()
    {
        $keys_to_delete = [
            'col_electronic_range_resolution',
            'col_electronic_range_start_date',
            'col_electronic_range_end_date'
        ];

        $this->db->table('ospos_app_config')
            ->whereIn('key', $keys_to_delete)
            ->delete();
    }
}
