<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddColElectronicInvoiceEnableToAppConfig extends Migration
{
    public function up()
    {
        // Insert the config value if it does not exist
        $builder = $this->db->table('ospos_app_config');
        
        $builder->insertBatch([
            ['key' => 'col_electronic_invoice_enable', 'value' => '0'],
            ['key' => 'col_electronic_software_id', 'value' => '0'],
            ['key' => 'col_electronic_pin', 'value' => '0'],
            ['key' => 'col_electronic_invoice_wsdl', 'value' => ''],
            ['key' => 'col_electronic_invoice_cert_password', 'value' => ''],
            ['key' => 'col_electronic_invoice_cert_key_path', 'value' => ''], 
            ['key' => 'col_electronic_invoice_cert_crt_path', 'value' => ''], 
            ['key' => 'tax_id_type', 'value' => '0'],
            ['key' => 'col_electronic_range_min', 'value' => '0'],
            ['key' => 'col_electronic_range_max', 'value' => '0'],
            ['key' => 'col_electronic_prefix', 'value' => ''],
            ['key' => 'col_electronic_tech_id', 'value' => '']
        ]);
    }

    public function down()
    {
        // List of config keys to delete
        $keys_to_delete = [
            'col_electronic_invoice_enable',
            'col_electronic_software_id',
            'col_electronic_pin',
            'col_electronic_invoice_wsdl',
            'col_electronic_invoice_cert_password',
            'col_electronic_invoice_cert_key_path',
            'col_electronic_invoice_cert_crt_path',
            'tax_id_type',
            'col_electronic_range_min',
            'col_electronic_range_max',
            'col_electronic_prefix',
            'col_electronic_tech_id'
        ];

        $this->db->table('ospos_app_config')
            ->whereIn('key', $keys_to_delete)
            ->delete();
        }
}
