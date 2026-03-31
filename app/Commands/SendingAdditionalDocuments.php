<?php

namespace App\Commands;

require_once ROOTPATH . 'vendor/autoload.php';

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DianResponseProcessor;
use App\Models\InvoiceDianQueue;
use Config\OSPOS;
use DianFE\DianFE;
use App\Events\Load_config;
use App\Models\Employee;

class SendingAdditionalDocuments extends BaseCommand
{
    protected $group       = 'DIAN';
    protected $name        = 'dian:send-additional-documents';
    protected $description = 'Send all additional documents to DIAN using diego-ramos/dian-facturacion-php';

    public function run(array $params)
    {
        helper('sale');

        $saleId = $params[0] ?? null;
        if (!$saleId) {
            if (is_cli()) {
                CLI::error("❌ Sale ID is required.");
            } else {
                log_message('error', "❌ Sale ID is required.");
            }
            return;
        }

        $queue = new InvoiceDianQueue();
        $entry = $queue->where('sale_id', $saleId)
            ->where('document_type', 'invoice')->first();

        if (!$entry) {
            if (is_cli()) {
                CLI::error("❌ Sale ID {$saleId} not found in queue.");
            } else {
                log_message('error', "❌ Sale ID {$saleId} not found in queue.");
            }
            return;
        }

        $credit_debit = $params[1] ?? null;

        $CIconfig = new Load_config();
        $CIconfig->load_config();
        $config = config(OSPOS::class)->settings;

        $employee_model = model(Employee::class);
        $logged_in_employee_info = $employee_model->get_info(1); // Default to admin for CLI

        // Load up global global_view_data visible to all the loaded views
        $global_view_data = [
            'user_info'       => $logged_in_employee_info,
            'controller_name' => 'sales',
            'config'          => $config
        ];

        // This view allows view-accessible data across multiple views since $this->load->vars() does not exist in CI4
        // calling it here populates the renderer with global data, and saveData => true ensures it persists
        view('viewData', $global_view_data, ['saveData' => true]);

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
            ],
            'output_path' => WRITEPATH . 'dian_xmls'
        ];

        try {
            // Initialize DianFE facade
            $dianfe = new DianFE($dianConfig);
            
            $documentType = $credit_debit === 'credit' ? 'credit_note' : 'debit_note';
            $docData = getDocumentDataForDian($saleId, $documentType);
            
            CLI::write("🚀 Enviando {$documentType} of sale {$saleId} a la DIAN...");
            $result = $dianfe->sendInvoice($docData);
            $trackId = $docData['invoice_number'] ?? null;

            $xmlGenerated = $result['xml'] ?? null;
            $xmlSigned = $result['signedXml'] ?? null;

            CLI::write("Raw Response: " . ($result['response'] ?? ''));

            if (isset($result['response'])) {
                $dianStatus = DianResponseProcessor::processSoapResponse($result['response']);
            } else {
                $errorMessage = $result['error'] ?? 'Unknown error';
                $dianStatus = [
                    'status' => 'error',
                    'dian_status' => 'rejected',
                    'error_message' => $errorMessage,
                    'dian_sent_at' => date('Y-m-d H:i:s'),
                ];
            }

            $queueData = array_merge($dianStatus, [
                'sale_id' => $saleId,
                'trackId' => $trackId,
                'document_type' => $documentType,
                'xml_generated' => $xmlGenerated,
                'xml_signed' => $xmlSigned,
            ]);

            $queueModel = new \App\Models\InvoiceDianQueue();
            $queueModel->insert($queueData);

            CLI::write("✅ {$documentType} processed.", "green");
            if (isset($dianStatus['dian_status'])) {
                CLI::write("Status: " . $dianStatus['dian_status']);
                if ($dianStatus['dian_status'] === 'accepted') {
                    $this->sendAdditionalDocumentByEmail($saleId, $trackId, $xmlSigned, $documentType);
                }
            }
            if (!empty($dianStatus['error_message'])) {
                CLI::write("Error Message: " . $dianStatus['error_message']);
            }
        
        
        }catch (\Throwable $e) {
            if (is_cli()) {
                CLI::error("❌ Error en sale {$saleId}: {$e->getMessage()}");
                CLI::error("❌ Stac ktrace: {$e->getTraceAsString()}");
            } else {
                log_message('error', "❌ Error en sale {$saleId}: {$e->getMessage()}");
                log_message('error', "❌ Stac ktrace: {$e->getTraceAsString()}");
            }
        }
    }

    private function sendAdditionalDocumentByEmail(string $sale_id, string $credit_debit_id, string $xmlContent, string $documentType)
    {
        $data = get_sale_data($sale_id);
        if (!empty($data['customer_email']) && $data['customer_email'] !== 'noemail@noemail.com') {
          $data['credit_debit_id'] = $credit_debit_id;
          $data[$documentType . '_number'] = $credit_debit_id;
          load_dian_data($data['sale_id_num'], $data);
          send_pdf($data, $documentType, $xmlContent);
          if (is_cli()) {
            CLI::write("✅ {$documentType} enviada por correo electrónico a {$data['customer_email']}.", "green");
          }
        }
    }
}