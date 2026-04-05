<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifySessionsPrimaryKey extends Migration
{
    public function up()
    {
        $prefix = $this->db->DBPrefix;
        $table = $prefix . 'sessions';

        // Drop the existing primary key and add a new one with only 'id'
        $this->db->query("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)");
    }

    public function down()
    {
        $prefix = $this->db->DBPrefix;
        $table = $prefix . 'sessions';

        // Revert back to composite primary key
        $this->db->query("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `ip_address`)");
    }
}
