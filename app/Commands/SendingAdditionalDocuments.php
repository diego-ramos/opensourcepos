<?php

namespace App\Commands;

require_once ROOTPATH . 'vendor/autoload.php';

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DianResponseProcessor;
use Config\OSPOS;
use DianFE\DianFE;
use App\Events\Load_config;

class SendingAdditionalDocuments extends BaseCommand
{
    protected $group       = 'DIAN';
    protected $name        = 'dian:send-additional-documents';
    protected $description = 'Send all additional documents to DIAN using diego-ramos/dian-facturacion-php';

    public function run(array $params)
    {
        helper('sale');

        $saleId = $params[0] ?? null;
        $credit_debit = $params[1] ?? null;

        $CIconfig = new Load_config();
        $CIconfig->load_config();

        $config = config(OSPOS::class)->settings;

        $dianConfig = [
            'cert_path' => $config['col_electronic_invoice_cert_crt_path'],
            'key_path' => $config['col_electronic_invoice_cert_key_path'],
            'cert_password' => $config['col_electronic_invoice_cert_password'] ?? '', 
            'wsdl' => $config['col_electronic_invoice_wsdl'],
            'end_point' => $config['col_electronic_invoice_endpoint'],
            'env' => $config['col_electronic_test'] ? 'development' :'production',
            'resolution' => [
                'authorization_number' => $config['col_electronic_range_resolution'],
                'start_date' => $config['col_electronic_range_start_date'],
                'end_date' => $config['col_electronic_range_end_date'],
                'from' => $config['col_electronic_range_min'],
                'to' => $config['col_electronic_range_max'],
                'prefix' => $config['col_electronic_prefix']
            ]
        ];

        try {
            // Initialize DianFE facade
            $dianfe = new DianFE($dianConfig);
            
            if($credit_debit === 'credit') {
                CLI::write("🚀 Enviando credit_note of sale {$saleId} a la DIAN...");
                $result = $dianfe->sendInvoice(getDocumentDataForDian($saleId, 'credit_note'));
            }else {
                CLI::write("🚀 Enviando debit_note of sale {$saleId} a la DIAN...");
                $result = $dianfe->sendInvoice(getDocumentDataForDian($saleId, 'debit_note'));
            }

            CLI::write("Raw Response: " . $result['response']);

            $dianStatus = DianResponseProcessor::processSoapResponse($result['response']);

            CLI::write("✅ Credit_note enviada a la DIAN", "green");
            CLI::write("Status: " . $dianStatus['dian_status']);
            CLI::write("Error Message: " . $dianStatus['error_message']);
        
        }catch (\Throwable $e) {
            CLI::error("❌ Error en sale {$saleId}: {$e->getMessage()}");
            CLI::error("❌ Stac ktrace: {$e->getTraceAsString()}");
        }
    }
}