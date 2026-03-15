<?php

namespace App\Commands;

require_once ROOTPATH . 'vendor/autoload.php';

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DianResponseProcessor;
use App\Libraries\Tax_lib;
use App\Models\InvoiceDianQueue;
use Config\OSPOS;
use DianFE\DianFE;
use DateTime;
use DateTimeZone;
use App\Events\Load_config;

class SendPendingInvoices extends BaseCommand
{
    protected $group       = 'DIAN';
    protected $name        = 'dian:send-pending-invoices';
    protected $description = 'Send all pending invoices to DIAN using diego-ramos/dian-facturacion-php';

    public function run(array $params)
    {
        $saleId = $params[0] ?? null;
        $CIconfig = new Load_config();
        $CIconfig->load_config();

        $config = config(OSPOS::class)->settings;
        $queue = new InvoiceDianQueue();
        $queue->where('status', 'pending');
        if(isset($saleId))
        {
            $queue->where('sale_id', $saleId);
        }
        $pending = $queue->findAll();

        if (empty($pending)) {
            CLI::write('✅ No pending invoices to process.', 'green');
            return;
        }

        CLI::write('📤 Sending ' . count($pending) . ' pending invoice(s) to DIAN...', 'yellow');

        helper('sale');
        helper('dian');

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
        
        foreach ($pending as $entry) {
            CLI::write("➡️  Procesando sale_id: {$entry['sale_id']}");
            try {
                $data = get_sale_data((int) $entry['sale_id']);

                if (empty($data['cart'])) {
                    DianResponseProcessor::processError($entry['id'], "Sale or items not found for sale_id {$entry['sale_id']}");
                    CLI::error("❌ Sale or items not found for sale_id {$entry['sale_id']}");
                    continue;
                }

                // Construir items
                $lineItems = [];
                foreach ($data['cart'] as $item) {
                    $qty = (float) $item['quantity'];
                   
                    $lineExtension = (float) $item['discounted_total']; // total without tax if tax_included is false, or total with tax?
                    // OSPOS item['total'] usually includes discount but check if it includes tax.
                    // In _load_sale_data, it uses get_totals which computes subtotals.
                    
                    // Actually, OSPOS cart item['total'] is usually the subtotal for that line.
                    // Let's use the actual tax from item_taxes.
                    
                    $taxPercent = 0;
                    $taxAmount = 0;
                    if (isset($data['item_taxes']) && sizeof($data['item_taxes']) > 0) {
                        foreach ($data['item_taxes'] as $tax) {
                            if ($tax['line'] == $item['line']) {
                                $taxPercent = (float) $tax['percent'];
                                $taxAmount += (float) $tax['item_tax_amount'];
                                $unit = (float) $lineExtension - $taxAmount;
                            }
                        }
                    }else {
                        $unit = (float) $item['price'];
                        $taxPercent = 0;
                        $taxAmount = 0;
                    }

                    $lineItems[] = [
                        'line_number'    => $item['line'],
                        'description'   => $item['description'] ?: $item['name'],
                        'item_code'     => $item['item_id'] ?? '01',
                        'quantity'      => $qty,
                        'unit_code'     => 'NIU',
                        'unit_price'    => number_format($unit, 2, '.', ''),
                        'discount'      => number_format((float) $item['discount'], 2, '.', ''),
                        'discount_type' => $item['discount_type'] == 0 ? 'percentage' : 'fixed',
                        'tax_percent'   => number_format($taxPercent, 2, '.', ''),
                        'tax_amount'    => number_format($taxAmount, 2, '.', ''),
                        'tax_scheme'    => ['id' => '01', 'name' => 'IVA'],
                        'tax_exempt'    => $taxPercent == 0,
                        'line_extension'=> number_format($lineExtension - $taxAmount, 2, '.', '')
                    ];
                }

                $taxTotal = (float)($data['total'] - $data['subtotal']);
                $subtotal = (float)$data['subtotal'];
                $invoiceTotal = (float)$data['total'];

                $technicalKey = $config['col_electronic_tech_id'] ?? '1234567890';
                $softwareID = $config['col_electronic_software_id'];
                $pin = $config['col_electronic_pin'];
                $softwareSecurityCode = hash('sha384', $softwareID . $pin . $data['invoice_number']);

                $tax_lib = new Tax_lib();

                $now = new DateTime('now', new DateTimeZone('America/Bogota'));

                $issueDate = $now->format('Y-m-d');
                $issueTime = $now->format('H:i:s-05:00');
                $signingTimeValue = $now->format('Y-m-d\TH:i:s-05:00');

                $invoiceData = [
                    'invoice_env' => $config['col_electronic_test'] ? 'development' :'production',
                    'invoice_number' => $config['col_electronic_prefix'].$data['invoice_number'],
                    'resolution_prefix' =>  $config['col_electronic_prefix'],
                    'issue_date' =>  $issueDate,
                    'issue_time' => $issueTime,
                    'technical_key' => $technicalKey,
                    'software_security_code' => $softwareSecurityCode,
                    'software_id' => $softwareID,
                    'software_pin' => $pin,
                    'test_set_id' => $config['col_electronic_test'] ? $config['col_electronic_test_set_id'] : '',
                    'supplier' => [
                        'name'                  => $config['company'],
                        'tax_id'                => substr($config['tax_id'], 0, -2),
                        'tax_id_dv'             => substr($config['tax_id'], -1),
                        'document_type'         => $tax_lib->get_tax_id_type_code($config['tax_id_type']) ?? '31',
                        'additional_account_id' => '2',
                        'industry_code'         => $config['col_ciiu_code'] ?? '',
                        'tax_level_code'        => $config['tax_level_code'] ?? 'R-99-PN',
                        'tax_scheme_id'         => '01',
                        'tax_scheme_name'       => 'IVA',
                        'phone'                 => $config['phone'] ?? '',
                        'email'                 => $config['email'] ?? '',
                        'address'               => [
                            'id'                   => $config['col_city_code'] ?? '05615',
                            'city_name'            => $config['city'] ?? 'Rionegro',
                            'postal_zone'          => $config['col_postal_zone'] ?? '',
                            'country_subentity'    => $config['state'] ?? 'Antioquia',
                            'country_subentity_code'=> $config['col_state_code'] ?? '05',
                            'address_line'         => $config['address'] ?? '',
                            'country_code'         => 'CO',
                            'country_name'         => 'Colombia',
                        ],
                    ], //tax_responsibility
                    'customer' => [
                        'name'                  => $data['customer_name'] ?: 'CONSUMIDOR FINAL',
                        'tax_id'                => explode('-', $data['customer_tax_id'] ?? '222222222222')[0],
                        'tax_id_dv'             => count(explode('-', $data['customer_tax_id'] ?? '')) > 1 ? explode('-', $data['customer_tax_id'])[1] : '0',
                        'document_type'         => $tax_lib->get_tax_id_type_code($data['customer_tax_id_type']) ?? '13',
                        'additional_account_id' => $data['customer_tax_payer_type'] ?? '1',
                        'tax_level_code'        => $data['customer_tax_responsibility'] ?? 'R-99-PN',
                        'tax_scheme_id'         => $data['customer_tax_scheme'] ?: (($data['customer_tax_id'] == '222222222222') ? 'ZZ' : '01'),
                        'tax_scheme_name'       => get_tax_scheme_name($data['customer_tax_scheme'] ?: (($data['customer_tax_id'] == '222222222222') ? 'No aplica' : 'IVA')),
                        'phone'                 => $data['customer_phone'] ?? '0000000',
                        'email'                 => $data['customer_email'] ?? $config['email'] ?? 'noemail@noemail.com',
                        'address'               => [
                            'id'                   => get_dian_city_code($data['customer_state'] ?? '', $data['customer_city'] ?? ''),
                            'city_name'            => !empty($data['customer_city']) ? $data['customer_city'] : (!empty($config['city']) ? $config['city'] : 'Rionegro'),
                            'postal_zone'          => !empty($data['customer_postal_zone']) ? $data['customer_postal_zone'] : (!empty($config['col_postal_zone']) ? $config['col_postal_zone'] : ''),
                            'country_subentity'    => !empty($data['customer_state']) ? $data['customer_state'] : (!empty($config['state']) ? $config['state'] : 'Antioquia'),
                            'country_subentity_code'=> get_dian_state_code($data['customer_state'] ?? ''),
                            'address_line'         => !empty($data['customer_address']) ? $data['customer_address'] : (!empty($config['address']) ? $config['address'] : ''),
                            'country_code'         => 'CO',
                            'country_name'         => 'Colombia',
                        ],
                    ],
                    'tax_total' => number_format($taxTotal, 2, '.', ''),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'invoice_total' => number_format($invoiceTotal, 2, '.', ''),
                    'items' => $lineItems,
                    'signing_time' => $signingTimeValue,
                    'payment_means' => array_map(function($p) {
                        $p = (array)$p;
                        return ['code' => get_dian_payment_code($p['payment_type'] ?? 'Efectivo')];
                    }, $data['payments'] ?? []),
                ];

                $invoiceData['resolution'] = $dianConfig['resolution'];

                // Initialize DianFE facade
                $dianfe = new DianFE($dianConfig);
                
                CLI::write("🚀 Enviando a la DIAN...");
                $result = $dianfe->sendInvoice($invoiceData);
                
                $xmlGenerated = $result['xml'] ?? null;
                $xmlSigned = $result['signedXml'] ?? null;

                if (is_array($result) && isset($result['response'])) {
                    if (isset($result['status_description'])) {
                        $color = ($result['success'] ?? false) ? 'green' : 'red';
                        $prefix = ($result['success'] ?? false) ? '✅' : '❌';
                        CLI::write("$prefix DIAN Status: [{$result['status_code']}] {$result['status_description']}", $color);
                        log_message('info', "DIAN: Invoice for Sale ID {$entry['sale_id']} status: {$result['status_description']}");
                        $dianStatus = DianResponseProcessor::processAndUpdateSoapResponse($entry['id'], $result['response'], $xmlGenerated, $xmlSigned);
                        if ($dianStatus === 'accepted') {
                            $this->sendInvoiceByEmail($data, $result['signedXml']);
                        }
                    } else {
                        $errorMsg = $result['error'] ?? 'Error desconocido';
                        CLI::error("❌ Fallo en el envío: " . $errorMsg);
                        log_message('error', "DIAN Error (Sale ID {$entry['sale_id']}): " . $errorMsg);
                        DianResponseProcessor::processError($entry['id'], $errorMsg, $xmlGenerated, $xmlSigned);
                    }
                } else {
                    $errorMsg = $result['error'] ?? 'Error desconocido';
                    CLI::error("❌ Fallo en el envío: " . $errorMsg);
                    log_message('error', "DIAN Error (Sale ID {$entry['sale_id']}): " . $errorMsg);
                    DianResponseProcessor::processError($entry['id'], $errorMsg, $xmlGenerated, $xmlSigned);
                }
            } catch (\Throwable $e) {
               DianResponseProcessor::processError($entry['id'], $e->getMessage(), $xmlGenerated ?? null, $xmlSigned ?? null);
                CLI::error("❌ Error en sale_id {$entry['sale_id']}: {$e->getMessage()}");
                log_message('error', "DIAN critical exception for sale_id {$entry['sale_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                CLI::error("❌ Stacktrace: {$e->getTraceAsString()}");
            }
        }

        CLI::write("✅ All pending invoices processed.", 'green');
    }

    private function sendInvoiceByEmail(array $invoiceData, string $xmlContent)
    {
        if (!empty($invoiceData['customer_email']) && $invoiceData['customer_email'] !== 'noemail@noemail.com') {
          load_dian_data($invoiceData['sale_id_num'], $invoiceData);
          send_pdf($invoiceData, 'invoice', $xmlContent);
          CLI::write("✅ Factura enviada por correo electrónico a {$invoiceData['customer_email']}.", "green");
        }
    }
    
}
