<?php

namespace App\Commands;

require_once ROOTPATH . 'vendor/autoload.php';

use DOMDocument;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DianResponseProcessor;
use App\Models\InvoiceDianQueue;
use App\Models\Sale;
use Config\OSPOS;
use DianFE\InvoiceGenerator;
use DianFE\XmlSigner;
use DianFE\Compressor;
use Lopezsoft\UBL21dian\Client;
use Lopezsoft\UBL21dian\Templates\SOAP\GetStatus;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillSync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillAsync;
use Lopezsoft\UBL21dian\XAdES\SignInvoice;

class SendPendingInvoices extends BaseCommand
{
    protected $group       = 'DIAN';
    protected $name        = 'dian:send-pending-invoices';
    protected $description = 'Send all pending invoices to DIAN using diego-ramos/dian-facturacion-php';

    public function run(array $params)
    {
        $config = config(OSPOS::class)->settings;
        $queue = new InvoiceDianQueue();
        $pending = $queue->where('status', 'pending')->findAll();

        if (empty($pending)) {
            CLI::write('✅ No pending invoices to process.', 'green');
            return;
        }

        CLI::write('📤 Sending ' . count($pending) . ' pending invoice(s) to DIAN...', 'yellow');

        $salesModel = model(Sale::class);
        
        $dianConfig = [
            'cert_path' => $config['col_electronic_invoice_cert_crt_path'],
            'key_path' => $config['col_electronic_invoice_cert_key_path'],
            'cert_password' => $config['col_electronic_invoice_cert_password'] ?? '', 
            'wsdl' => $config['col_electronic_invoice_wsdl'],
            'end_point' => $config['col_electronic_invoice_endpoint'],
            'env' => $config['col_electronic_test'] ? 'development' :'production'
        ];
        
        
//         $client = new DianClientManual($dianConfig['cert_path'], $dianConfig['cert_password']);
        
//         // GetStatus example
//         $response = $client->getStatus('123456789');
//         echo $response;
        
//         // SendBillSync example
//         $fileName = '0001.xml';
//         $xmlContent = base64_encode(file_get_contents('0001.xml'));
//         $response = $client->sendBillSync($fileName, $xmlContent);
//         echo $response;
        
//         // Debug
//         echo $client->getLastRequest();
//         echo $client->getLastResponse();
        
//         if(true) {
//             return;
//         }
        
        //$dianFe = new DianFE($dianConfig);
        
        
        foreach ($pending as $entry) {
            CLI::write("➡️  Procesando sale_id: {$entry['sale_id']}");
            try {
                $sale = $salesModel->get_info((int) $entry['sale_id'])->getRowArray();
                //$customer = $salesModel->get_customer((int) $entry['sale_id']); //->getRowArray();
                $items = $salesModel->get_sale_items((int) $entry['sale_id'])->getResultArray();
                if (empty($sale) || empty($items)) {
                    DianResponseProcessor::processError($entry['id'], "Sale or items not found for sale_id {$entry['sale_id']}");
                    CLI::error("❌ Sale or items not found for sale_id {$entry['sale_id']}");
                    continue;
                }
                // Construir items
                $lineItems = [];
                $subtotal = 0;
                foreach ($items as $item) {
                    $qty = (float) $item['quantity_purchased'];
                    $unit = (float) $item['item_unit_price'];
                    $lineTotal = $qty * $unit;
                    $lineItems[] = [
                        'line_number'     => $item['line'],
                        'description'     => $item['description'] ?: 'Producto',
                        'quantity'        => $qty,
                        'unit_measure'    => 'NIU', // Unidad de medida estándar
                        'unit_price'      => number_format($unit, 2, '.', ''),
                        'line_extension'  => number_format($lineTotal, 2, '.', ''),
                        'discount'        => number_format((float) $item['discount'], 2, '.', ''),
                        'discount_type'   => $item['discount_type'] ?? 'percentage',
                        'tax_percent'     => 19.00,
                        'tax_category'    => '01', // IVA
                        'tax_exempt'      => false, // o true según el caso
                        'total'  => number_format($lineTotal * 1.19, 2, '.', '')
                    ];
                    $subtotal += $lineTotal;
                }

                $taxTotal = $subtotal * 0.19;
                $invoiceTotal = $subtotal + $taxTotal;

                $technicalKey = $config['col_electronic_tech_id'] ?? '1234567890';
                $softwareSecurityCode = hash('sha384', $softwareID . $pin . $sale['invoice_number']);
                //$softwareSecurityCode = hash('sha384', $config['col_electronic_software_id'] . $config['col_electronic_pin']); 

                $invoiceData = [
                    'CIIU' => 6666, //TODO: Pendiente por configurar
                    'invoice_env' => $config['col_electronic_test'] ? 'development' :'production',
                    'invoice_number' => $sale['invoice_number'],
                    'resolution_prefix' =>  $config['col_electronic_prefix'],
                    'issue_date' => substr($sale['sale_time'], 0, 10),
                    'issue_time' => substr($sale['sale_time'], 11, 8),
                    'technical_key' => $technicalKey,
                    'software_security_code' => $softwareSecurityCode,
                    'software_id' => $config['col_electronic_software_id'],
                    'software_pin' => $config['col_electronic_pin'],
                    'test_set_id' => $config['col_electronic_test'] ? $config['col_electronic_test_set_id'] :'',
                    'emitter_document_number' => $config['tax_id'],
                    'customer_document_number' => $sale['customer_tax_id'] ?? '222222222',
                    'supplier' => [
                        'company_name' => $config['company'],
                        'tax_id' => $config['tax_id'], 
                        'tax_id_dv' => $config['tax_id_dv'],   // se puede obtener del tax_id parseando el -n parra no tener que almacenar un campo adicional
                        'tax_id_type' => $config['tax_id_type'],
                        'address' => $config['address'],
                        'city' => '',
                        'department' => ''
                    ],
                    'customer' => [
                        'name' => $sale['customer_name'],
                        'tax_id' => $sale['customer_tax_id'] ?? '123456789',
                        'document_type' => $sale['customer_tax_id_type'] ?? '13',
                        'address' => $sale['customer_address'] ?? 'Sin dirección',
                        'city' => '',
                        'department' => ''
                    ],
                    'tax_subtotals' => [
                        [
                            'taxable_amount' => number_format($subtotal, 2, '.', ''),
                            'tax_amount'     => number_format($taxTotal, 2, '.', ''),
                            'tax_percent'    => 19.00,
                            'tax_category'   => '01',
                            'tax_name'       => 'IVA'
                        ]
                    ],
                    'invoice_line_count' => count($lineItems),
                    'items' => $lineItems,
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'tax_total' => number_format($taxTotal, 2, '.', ''),
                    'invoice_total' => number_format($invoiceTotal, 2, '.', '')
                ];

                // Generar, firmar, comprimir y enviar
               // $result = $dianFe->sendInvoice($invoiceData);
                
                // 1. Crear una plantilla UBL (factura, nota crédito, etc.)
                //$template = new GetStatus($dianConfig['cert_path'], $dianConfig['cert_password']);
                
                //$cufe = InvoiceGenerator::generateCufe($invoiceData);
                //$invoiceXml = InvoiceGenerator::generate($invoiceData);
                
                
                //use diegoramos\UBL21dian\XmlRendern\XmlTemplateRenderer;
                //use diegoramos\UBL21dian\XmlRendern\XmlTemplateEnum;
                
                $renderer = new XmlTemplateRenderer(); // no need to pass path
                $xml = $renderer->render(XmlTemplateEnum::Invoice, [
                    'InvoiceId' => 'INV-1001',
                    'IssueDate' => '2025-10-22',
                    'CustomerName' => 'Cliente Prueba S.A.S',
                    'TotalAmount' => '350000.00',
                ]);
                
                file_put_contents(__DIR__ . '/InvoiceOutput.xml', $xml);
                
                
                
                $signInvoice = new SignInvoice($dianConfig['cert_path'], $dianConfig['cert_password']);
                $signInvoice->softwareID = $config['col_electronic_software_id'];
                $signInvoice->pin = $config['col_electronic_pin'];
                $signInvoice->technicalKey = $config['col_electronic_tech_id'];
                
                $sendBillAsync = new SendBillAsync($company->certificate->path, $company->certificate->password);
                $sendBillAsync->To = $company->software->url;
                $sendBillAsync->fileName = "{$resolution->next_consecutive}.xml";
                $sendBillAsync->contentFile = $this->zipBase64($company, $resolution, $signInvoice->sign($invoiceXml)->xml);
                
                
                //$signInvoice->sign($this->xmlString);
                //$signedXml = XmlSigner::sign($xml, $this->certPath, $this->keyPath, $this->config['cert_password']);
                //$zipContent = Compressor::compress($signedXml, $cufe);
                
               // $template = new SendBillSync($dianConfig['cert_path'], $dianConfig['cert_password'], $xml);
                //$template->fileName = $cufe.'zip';
                // 2. Llenar datos básicos de la plantilla
               // $template->trackId = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
                
                // Sign
                $signInvoice->sign($xml);
                
                $domDocumentValidate = new DOMDocument();
                $domDocumentValidate->validateOnParse = true;
               // $domDocumentValidate->loadXML($signInvoice->xml);
                
                CLI::write("\nAntes de enviar:\n");
                // Sign to send
    //            $client = $template->signToSend();
                
                
                
//                 $domDocumentValidate = new DOMDocument();
//                 $domDocumentValidate->validateOnParse = true;
//                 $domDocumentValidate->loadXML($client->getResponse());
//                 print_r($domDocumentValidate);
                print_r($signInvoice);
                
                
                CLI::write("\nDespues de enviar:\n");
                print_r($domDocumentValidate);
                //$template->setNumero('F001');
                //$template->setFechaEmision('2025-10-21T00:00:00-05:00');
                // ... otros datos obligatorios (emisor, receptor, totales, etc.)
                
                // 3. Crear el cliente pasándole la plantilla
              //  $client = new Client($template);
                
                // 4. Generar XML o enviarlo
               // $xml = $client->xml();
              //  echo $xml;

                //DianResponseProcessor::processSoapResponse($entry['id'], $response);
                $cufe = "xxXXXxxx";//$result['response']['cufe'] ?? '';

//                 if($result['success']) {
//                     CLI::write("\n✅ Factura enviada. CUFE: {$cufe}\n");
//                     //echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//                     print_r($result);
//                 }else {
//                     CLI::write("\n❌ Factura CUFE: {$cufe} envio fallido");
//                     echo "\n";
//                     //echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//                     print_r($result);
//                     echo "\n";
//                 }
            } catch (\Throwable $e) {
               // DianResponseProcessor::processError($entry['id'], $e->getMessage());
                CLI::error("❌ Error en sale_id {$entry['sale_id']}: {$e->getMessage()}");
            }
        }

        CLI::write("✅ All pending invoices processed.", 'green');
    }
}
