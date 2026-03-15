<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaxLevelCodeToAppConfig extends Migration
{
    public function up()
    {
        $builder = $this->db->table('ospos_app_config');
        
        $builder->insert([
            'key'   => 'tax_level_code',
            'value' => 'R-99-PN'
        ]);
    }

    public function down()
    {
        $this->db->table('ospos_app_config')
            ->where('key', 'tax_level_code')
            ->delete();
    }
}
