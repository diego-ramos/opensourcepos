<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDianCreditRangeFieldsToAppConfig extends Migration
{
    public function up()
    {
        $builder = $this->db->table('ospos_app_config');
        
        $builder->insertBatch([
            ['key' => 'col_electronic_credit_range_min', 'value' => ''],
            ['key' => 'col_electronic_credit_range_max', 'value' => ''],
            ['key' => 'col_electronic_credit_prefix', 'value' => ''],
            ['key' => 'last_used_credit_note_number', 'value' => '0'],
        ]);
    }

    public function down()
    {
        $keys_to_delete = [
            'col_electronic_credit_range_min',
            'col_electronic_credit_range_max',
            'col_electronic_credit_prefix',
            'last_used_credit_note_number'
        ];

        $this->db->table('ospos_app_config')
            ->whereIn('key', $keys_to_delete)
            ->delete();
    }
}
