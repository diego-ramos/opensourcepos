<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedTaxIdTypes extends Migration
{
    public function up()
    {
        // Call the seeder to insert initial national ID types
        $seeder = \Config\Database::seeder();
        $seeder->call('TaxIdTypesSeeder');
    }

    public function down()
    {
        // Optionally, truncate the table on rollback
        $this->db->table('tax_id_types')->truncate();
    }
}
