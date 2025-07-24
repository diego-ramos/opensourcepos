<?php

namespace App\Commands;

require_once ROOTPATH . 'vendor/autoload.php';

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
//use App\Models\Appconfig;
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
   // private array $config;

    public function run(array $params)
    {
        $config = config(OSPOS::class)->settings;
//$appconfig = model(Appconfig::class);
        $queue = new InvoiceDianQueue();
        $pending = $queue->where('status', 'pending')->findAll();

        if (empty($pending)) {
            CLI::write('✅ No pending invoices to process.', 'green');
            return;
        }

        CLI::write('📤 Sending ' . count($pending) . ' pending invoice(s) to DIAN...', 'yellow');

        foreach ($pending as $entry) {
            $saleId = $entry['sale_id'];
            CLI::write("➡️  Processing sale_id: $saleId", 'blue');

            //$queue->update($entry['id'], ['status' => 'processing']);

            try {
                $dianConfig = [
                    'cert_path' => $config['col_electronic_invoice_cert_crt_path'],
                    'key_path' => $config['col_electronic_invoice_cert_key_path'],
                    'cert_password' => $config['col_electronic_invoice_cert_key_path'] ?? '', 
                    'wsdl' => $config['col_electronic_invoice_wsdl'],
                ];

                $saleModel = model(Sale::class);

                $sale = $saleModel->get_info((int) $saleId)->getRow();
                //var_dump($sale);
                if (!$sale) {
                    CLI::error("❌ Sale ID $saleId not found.");
                    continue;
                }
                $sale_items = $saleModel->get_sale_items((int) $saleId)->getResultArray();
                CLI::write("SALE ITEMS");
                //var_dump($sale_items);

                $lineItems = [];
                $subtotal = 0;
                $tax = 0;

                foreach ($sale_items as $item) {
                    $quantity = (float) $item['quantity_purchased'];
                    $unit_price = (float) $item['item_unit_price'];
                    $discount = (float) $item['discount'];
                    $discount_type = (int) $item['discount_type'];
                    $tax_percent = isset($item['tax_percent']) ? (float) $item['tax_percent'] : 0.0;

                    // Valor bruto
                    $gross = $quantity * $unit_price;

                    // Descuento
                    $discount_amount = $discount_type === 1
                        ? $gross * $discount / 100
                        : $discount;

                    // Valor neto antes de impuestos
                    $net = $gross - $discount_amount;

                    // Impuesto calculado si hay IVA
                    $tax_amount = $net * $tax_percent / 100;

                    // Totales acumulados
                    $subtotal += $net;
                    $tax += $tax_amount;

                    // Agregamos al arreglo de ítems
                    $lineItems[] = [
                        'description' => $item['description'] ?: 'Ítem ' . $item['item_id'],
                        'quantity' => number_format($quantity, 2, '.', ''),
                        'price' => number_format($unit_price, 2, '.', ''),
                        'line_extension_amount' => number_format($net, 2, '.', ''), // Neto sin impuestos
                        'tax_percent' => number_format($tax_percent, 2, '.', ''),
                        'tax_amount' => number_format($tax_amount, 2, '.', ''),
                        'discount' => number_format($discount_amount, 2, '.', ''),
                        'total' => number_format($net + $tax_amount, 2, '.', '')
                    ];
                }

                
                $dianFe = new DianFE($dianConfig);

                $technicalKey = $config['col_electronic_tech_id'] ?? '1234567890';
                $softwareSecurityCode = hash('sha384', $config['col_electronic_software_id'] . $config['col_electronic_pin'] . $config['tax_id'] . $technicalKey); //hash('sha384', $technicalKey . $technicalKey);

                $invoiceData = [
                    'invoice_number' => $sale->invoice_number,
                    'issue_date' => date('Y-m-d', strtotime($sale->sale_time)),
                    'issue_time' => date('H:i:s', strtotime($sale->sale_time)),

                    // Totales
                    'invoice_total' => number_format($sale->amount_due, 2, '.', ''),
                    'tax_total' => number_format($tax, 2, '.', ''), // Usamos el cálculo real del impuesto
                    //'total' => number_format($subtotal + $tax, 2, '.', ''),

                    // CUFE
                    'technical_key' => $technicalKey,
                    'software_security_code' => $softwareSecurityCode,
                    'emitter_document_number' => $config['tax_id'] ?? '',
                    'customer_document_number' => $sale->customer_tax_id ?? '',

                    // Proveedor (emisor)
                    'supplier' => [
                        'name' => $config['company'] ?? 'Tu Empresa S.A.S.',
                        'document_number' => $config['tax_id'] ?? '',
                        'document_type' => $config['tax_id_type'] ?? 'NIT',
                        'address' => $config['address'] ?? 'Dirección por defecto',
                        'city' => $config['city'] ?? 'Bogotá',
                        'department' => $config['state'] ?? 'Cundinamarca',
                        'country' => 'CO',
                    ],

                    // Cliente
                    'customer' => [
                        'name' => $sale->customer_name ?? 'Consumidor Final',
                        'document_number' => $sale->customer_tax_id ?? '222222222',
                        'document_type' => $sale->customer_tax_id_type ?? 'CC',
                        'address' => $sale->customer_address ?? 'Sin dirección',
                        'email' => $sale->email ?? '',
                    ],
                ];

                $invoiceData['items'] = $lineItems;

                $invoiceData['totals'] = [
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'tax' => number_format($tax, 2, '.', ''),
                    'total' => number_format($subtotal + $tax, 2, '.', '')
                ];

                

                $response = $dianFe->sendInvoice($invoiceData);

                $queue->update($entry['id'], [
                    'status'        => 'sent',
                    'dian_response' => json_encode($response),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);

                DianResponseProcessor::processSoapResponse($entry['id'], $response);

                CLI::write("✅ Sent sale_id $saleId to DIAN.", 'green');
            } catch (\Throwable $e) {
                DianResponseProcessor::processError($entry['id'], $e->getMessage());

                CLI::error("❌ Error sending sale_id $saleId: " . $e->getMessage());
            }
        }

        CLI::write("✅ All pending invoices processed.", 'green');
    }
}
