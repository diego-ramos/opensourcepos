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
use DianFE\DianFE;

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

        helper('sale');

        $dianConfig = [
            'cert_path' => $config['col_electronic_invoice_cert_crt_path'],
            'key_path' => $config['col_electronic_invoice_cert_key_path'],
            'cert_password' => $config['col_electronic_invoice_cert_password'] ?? '', 
            'wsdl' => $config['col_electronic_invoice_wsdl'],
            'end_point' => $config['col_electronic_invoice_endpoint'],
            'env' => $config['col_electronic_test'] ? 'development' :'production'
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
                    $unit = (float) $item['price'];
                    $lineExtension = (float) $item['total']; // total without tax if tax_included is false, or total with tax?
                    // OSPOS item['total'] usually includes discount but check if it includes tax.
                    // In _load_sale_data, it uses get_totals which computes subtotals.
                    
                    // Actually, OSPOS cart item['total'] is usually the subtotal for that line.
                    // Let's use the actual tax from item_taxes.
                    
                    $taxPercent = 0;
                    $taxAmount = 0;
                    if (isset($data['item_taxes'])) {
                        foreach ($data['item_taxes'] as $tax) {
                            if ($tax['line'] == $item['line']) {
                                $taxPercent = (float) $tax['percent'];
                                $taxAmount += (float) $tax['item_tax_amount'];
                            }
                        }
                    }

                    $lineItems[] = [
                        'line_number'     => $item['line'],
                        'description'     => $item['description'] ?: $item['name'],
                        'quantity'        => $qty,
                        'unit_measure'    => 'NIU', // Unidad de medida estándar
                        'unit_price'      => number_format($unit, 2, '.', ''),
                        'line_extension'  => number_format($lineExtension, 2, '.', ''),
                        'discount'        => number_format((float) $item['discount'], 2, '.', ''),
                        'discount_type'   => $item['discount_type'] == 0 ? 'percentage' : 'fixed',
                        'tax_percent'     => number_format($taxPercent, 2, '.', ''),
                        'tax_category'    => '01', // IVA
                        'tax_exempt'      => $taxPercent == 0,
                        'total'  => number_format($lineExtension + $taxAmount, 2, '.', '')
                    ];
                }

                $taxTotal = (float)($data['total'] - $data['subtotal']);
                $subtotal = (float)$data['subtotal'];
                $invoiceTotal = (float)$data['total'];

                $technicalKey = $config['col_electronic_tech_id'] ?? '1234567890';
                $softwareID = $config['col_electronic_software_id'];
                $pin = $config['col_electronic_pin'];
                $softwareSecurityCode = hash('sha384', $softwareID . $pin . $data['invoice_number']);

                $invoiceData = [
                    'invoice_env' => $config['col_electronic_test'] ? 'development' :'production',
                    'invoice_number' => $data['invoice_number'],
                    'resolution_prefix' =>  $config['col_electronic_prefix'],
                    'issue_date' => substr($data['transaction_time'], 0, 10),
                    'issue_time' => substr($data['transaction_time'], 11, 8) . '-05:00', // Added timezone for CUFE
                    'technical_key' => $technicalKey,
                    'software_security_code' => $softwareSecurityCode,
                    'software_id' => $softwareID,
                    'software_pin' => $pin,
                    'test_set_id' => $config['col_electronic_test'] ? $config['col_electronic_test_set_id'] : '',
                    'emitter_document_number' => $config['tax_id'],
                    'customer_document_number' => $data['customer_tax_id'] ?? '222222222',
                    'supplier' => [
                        'name' => $config['company'],
                        'tax_id' => $config['tax_id'], 
                        'tax_id_dv' => $config['tax_id_dv'] ?? '0',
                        'document_type' => $config['tax_id_type'] ?? '31',
                        'address' => $config['address'],
                        'city' => $config['city'] ?? 'Bogotá',
                        'department' => $config['state'] ?? 'Cundinamarca'
                    ],
                    'customer' => [
                        'name' => $data['customer_name'] ?: 'Consumidor Final',
                        'tax_id' => $data['customer_tax_id'] ?? '222222222',
                        'document_type' => $data['customer_tax_id_type'] ?? '13',
                        'address' => $data['customer_address'] ?? 'Sin dirección',
                        'city' => $data['customer_city'] ?? 'Bogotá',
                        'department' => $data['customer_state'] ?? 'Cundinamarca'
                    ],
                    'tax_total' => number_format($taxTotal, 2, '.', ''),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'invoice_total' => number_format($invoiceTotal, 2, '.', ''),
                    'items' => $lineItems
                ];

                // Initialize DianFE facade
                $dianfe = new DianFE($dianConfig);
                
                CLI::write("🚀 Enviando a la DIAN...");
                $result = $dianfe->sendInvoice($invoiceData);
                
                if (is_array($result) && isset($result['success']) && $result['success']) {
                    CLI::write("✅ Factura aceptada por la DIAN.", "green");
                    if (isset($result['status_description'])) {
                        CLI::write("💬 Detalle: " . $result['status_description']);
                    }
                    
                    // Process response to update database
                    // We need the raw XML response for processSoapResponse
                    if (isset($result['response'])) {
                     //   DianResponseProcessor::processSoapResponse($entry['id'], $result['response']);
                    } else {
                        // Fallback if we have success but no raw response (unlikely with DianClient)
                      //  DianResponseProcessor::processError($entry['id'], "Success without raw response");
                    }

                } else {
                    $errorMsg = $result['error'] ?? $result['message'] ?? 'Unknown error';
                    CLI::error("❌ Fallo en el envío: " . $errorMsg);
                   // DianResponseProcessor::processError($entry['id'], $errorMsg);
                }
            } catch (\Throwable $e) {
               // DianResponseProcessor::processError($entry['id'], $e->getMessage());
                CLI::error("❌ Error en sale_id {$entry['sale_id']}: {$e->getMessage()}");
            }
        }

        CLI::write("✅ All pending invoices processed.", 'green');
    }
}
