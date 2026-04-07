<?php

use App\Libraries\Barcode_lib;
use App\Libraries\Sale_lib;
use App\Libraries\Tax_lib;
use App\Models\Customer;
use App\Models\Customer_rewards;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Stock_location;
use Config\OSPOS;
use Config\Dian;
use App\Models\InvoiceDianQueue;
use chillerlan\QRCode\QRCode;
use App\Models\Tokens\Token_invoice_count;
use App\Models\Tokens\Token_customer;
use App\Models\Tokens\Token_invoice_sequence;
use Config\Services;
use App\Libraries\Email_lib;
use App\Libraries\Token_lib;
use function PHPUnit\Framework\isNan;

/**
 * Get sale data for invoice/receipt.
 * 
 * @param int $sale_id
 * @return array
 */
function get_sale_data(int $sale_id, ?string $cude = null): array
{
    $sale_lib = new Sale_lib();
    $tax_lib = new Tax_lib();
    $barcode_lib = new Barcode_lib();
    $config = config(OSPOS::class)->settings;
    $sale_model = model(Sale::class);
    $employee_model = model(Employee::class);
    $stock_location_model = model(Stock_location::class);

    $sale_lib->clear_all();
    $sale_lib->reset_cash_rounding();
    
    $sale_info = $sale_model->get_info($sale_id)->getRowArray();
    if (!$sale_info) {
        return [];
    }

    $sale_lib->copy_entire_sale($sale_id);
    
    $data = [];
    $data['cart'] = $sale_lib->get_cart();
    $data['payments'] = $sale_lib->get_payments();
    $data['selected_payment_type'] = $sale_lib->get_payment_type();

    $tax_details = $tax_lib->get_taxes($data['cart'], $sale_id);
    $data['item_taxes'] = $tax_details[1];
    $data['taxes'] = $sale_model->get_sales_taxes($sale_id);
    $data['discount'] = $sale_lib->get_discount();
    $data['transaction_time'] = to_datetime(strtotime($sale_info['sale_time']));
    $data['transaction_date'] = to_date(strtotime($sale_info['sale_time']));
    $data['show_stock_locations'] = $stock_location_model->show_locations('sales');

    $data['include_hsn'] = (bool)$config['include_hsn'];

    $totals = $sale_lib->get_totals($tax_details[0]);
    $data['subtotal'] = $totals['subtotal'];
    $data['payments_total'] = $totals['payment_total'];
    $data['payments_cover_total'] = $totals['payments_cover_total'];
    $data['cash_mode'] = session()->get('cash_mode');
    $data['prediscount_subtotal'] = $totals['prediscount_subtotal'];
    $data['cash_total'] = $totals['cash_total'];
    $data['non_cash_total'] = $totals['total'];
    $data['cash_amount_due'] = $totals['cash_amount_due'];
    $data['non_cash_amount_due'] = $totals['amount_due'];
    $data['tax_total'] = $totals['tax_total'];

    if ($data['cash_mode'] && ($data['selected_payment_type'] === lang('Sales.cash') || $data['payments_total'] > 0)) {
        $data['total'] = $totals['cash_total'];
        $data['amount_due'] = $totals['cash_amount_due'];
    } else {
        $data['total'] = $totals['total'];
        $data['amount_due'] = $totals['amount_due'];
    }

    $data['amount_change'] = $data['amount_due'] * -1;

    $employee_info = $employee_model->get_info($sale_lib->get_employee());
    $data['employee'] = $employee_info->first_name . ' ' . mb_substr($employee_info->last_name, 0, 1);
    
    load_customer_data($sale_lib->get_customer(), $data);

    $data['sale_id_num'] = $sale_id;
    $data['sale_id'] = 'POS ' . $sale_id;
    $data['comments'] = $sale_info['comment'];
    $data['invoice_number'] = $sale_info['invoice_number'];
    $data['quote_number'] = $sale_info['quote_number'];
    $data['sale_status'] = $sale_info['sale_status'];

    $data['company_info'] = implode("\n", [$config['address'], $config['phone']]);

    if ($config['account_number']) {
        $data['company_info'] .= "\n" . lang('Sales.account_number') . ": " . $config['account_number'];
    }
    if ($config['tax_id'] != '') {
        $tax_id_label = lang('Sales.tax_id');
        if ($config['col_electronic_invoice_enable']){
            $tax_id_label = $tax_lib->get_tax_id_type_label($config['tax_id_type']);
        }
        $data['company_info'] .= "\n" . $tax_id_label .": " . $config['tax_id'];
    }

    $data['barcode'] = $barcode_lib->generate_receipt_barcode($data['sale_id']);
    $data['print_after_sale'] = false;
    $data['price_work_orders'] = false;

    $mode = $sale_lib->get_mode();
    if ($mode == 'sale_invoice') {
        $data['mode_label'] = lang('Sales.invoice');
        $data['customer_required'] = lang('Sales.customer_required');
    } elseif ($mode == 'sale_quote') {
        $data['mode_label'] = lang('Sales.quote');
        $data['customer_required'] = lang('Sales.customer_required');
    } elseif ($mode == 'sale_work_order') {
        $data['mode_label'] = lang('Sales.work_order');
        $data['customer_required'] = lang('Sales.customer_required');
    } elseif ($mode == 'return') {
        $data['mode_label'] = lang('Sales.return');
        $data['customer_required'] = lang('Sales.customer_optional');
    } else {
        $data['mode_label'] = lang('Sales.receipt');
        $data['customer_required'] = lang('Sales.customer_optional');
    }

    $data['invoice_view'] = $config['invoice_type'];

    load_dian_data($sale_id, $data, $cude);

    return $data;
}

function load_dian_data(int $sale_id, array &$data, ?string $cude = null)
{
    $config = config(OSPOS::class)->settings;
    $dian_config = config(Dian::class);
    
    $queue = new InvoiceDianQueue();
    $queue_info = $queue->where('sale_id', $sale_id)->first();
    if (!isset($queue_info)) {
        return;
    }
    
    $data['cufe'] = $cude ?? $queue_info['dian_cufe'];
    if($config['col_electronic_invoice_enable'] && is_numeric($data['invoice_number'])) {
        $data['invoice_number'] = $config['col_electronic_prefix'] .$data['invoice_number'];
    }
    $CUDE_CUFE = $cude ? "CUDE" : "CUFE";
    
    $qr_text =
    "NumFac=".$sale_id."\n".
    "FecFac=".$data['transaction_date']."\n".
    "HorFac=".$data['transaction_time']."\n".
    "NitFac=".$config['tax_id']."\n".
    "DocAdq=".$data['customer_tax_id']."\n".
    "ValFac=".number_format($data['subtotal'], 2, '.', '')."\n".
    "ValIva=".number_format($data['tax_total'], 2, '.', '')."\n".
    "ValTot=".number_format($data['non_cash_total'], 2, '.', '')."\n".
    $CUDE_CUFE."=".$data['cufe']."\n".
    "QRCode=".$dian_config->catalog_url."document/searchqr?documentkey=".$data['cufe'];

    $data['qr_code'] = (new QRCode)->render($qr_text);
}

/**
 * Load customer data into the data array.
 * 
 * @param int $customer_id
 * @param array $data
 * @param bool $stats
 * @return array|stdClass|string|null
 */
function load_customer_data(int $customer_id, array &$data, bool $stats = false): array|string|stdClass|null
{
    $customer_model = model(Customer::class);
    $customer_rewards_model = model(Customer_rewards::class);
    $tax_lib = new Tax_lib();
    $config = config(OSPOS::class)->settings;

    $customer_info = '';

    if ($customer_id != -1) { // NEW_ENTRY is usually -1
        $customer_info = $customer_model->get_info($customer_id);
        $data['customer_id'] = $customer_id;

        if (!empty($customer_info->company_name)) {
            $data['customer'] = $customer_info->company_name;
        } else {
            $data['customer'] = $customer_info->first_name . ' ' . $customer_info->last_name;
        }

        $data['first_name'] = $customer_info->first_name;
        $data['last_name'] = $customer_info->last_name;
        $data['customer_email'] = $customer_info->email;
        $data['customer_address'] = $customer_info->address_1;

        if (!empty($customer_info->zip) || !empty($customer_info->city)) {
            $data['customer_location'] = $customer_info->zip . ' ' . $customer_info->city . "\n" . $customer_info->state;
        } else {
            $data['customer_location'] = '';
        }

        $data['customer_account_number'] = $customer_info->account_number;
        $data['customer_discount'] = $customer_info->discount;
        $data['customer_discount_type'] = $customer_info->discount_type;
        $package_id = $customer_info->package_id;

        if ($package_id != null) {
            $package_name = $customer_rewards_model->get_name($package_id);
            $points = $customer_info->points;
            $data['customer_rewards']['package_id'] = $package_id;
            $data['customer_rewards']['points'] = empty($points) ? 0 : $points;
            $data['customer_rewards']['package_name'] = $package_name;
        }

        if ($stats) {
            $cust_stats = $customer_model->get_stats($customer_id);
            $data['customer_total'] = empty($cust_stats) ? 0 : $cust_stats->total;
        }

        $data['customer_info'] = implode("\n", [
            $data['customer'],
            $data['customer_address'],
            $data['customer_location']
        ]);

        if ($data['customer_account_number']) {
            $data['customer_info'] .= "\n" . lang('Sales.account_number') . ": " . $data['customer_account_number'];
        }

        if ($customer_info->tax_id != '') {
            $tax_id_label = lang('Sales.tax_id');
            if ($config['col_electronic_invoice_enable']){
                $tax_id_label = $tax_lib->get_tax_id_type_label($customer_info->tax_id_type);
            }
            $data['customer_info'] .= "\n" . $tax_id_label . ": " . $customer_info->tax_id;
        }
        $data['tax_id'] = $customer_info->tax_id;
        $data['customer_tax_id'] = $customer_info->tax_id;
        $data['customer_tax_id_type'] = $tax_lib->get_tax_id_type_code($customer_info->tax_id_type);
        $data['customer_tax_responsibility'] = $customer_info->tax_responsibility;
        $data['customer_tax_payer_type'] = $customer_info->tax_payer_type;
        $data['customer_tax_scheme'] = $customer_info->tax_scheme;
        $data['customer_city'] = $customer_info->city;
        $data['customer_state'] = $customer_info->state;
        $data['customer_name'] = !empty($customer_info->company_name) ? $customer_info->company_name : $customer_info->first_name . ' ' . $customer_info->last_name;
    }

    return $customer_info;
}

function send_pdf(array $sale_data, string $type = 'invoice', ?string $invoice_xml = null): bool
{
    $config = config(OSPOS::class)->settings;
    $token_lib = new Token_lib();
    $email_lib = new Email_lib();
    $sale_lib = new Sale_lib();

    $result = false;
    $message = lang('Sales.invoice_no_email');

    if (!empty($sale_data['customer_email'])) {
        $to = $sale_data['customer_email'];
        $number = array_key_exists($type . "_number", $sale_data) ?  $sale_data[$type . "_number"] : "";
        $subject = lang('Sales.' . $type) . ' ' . $number;

        $text = $config['invoice_email_message'];
        $tokens = [
            new Token_invoice_sequence($number),
            new Token_invoice_count('POS ' . $sale_data['sale_id']),
            new Token_customer((array)$sale_data)
        ];
        $text = $token_lib->render($text, $tokens);
        $sale_data['mimetype'] = mime_content_type(FCPATH . 'uploads/' . $config['company_logo']);

        // Generate email attachment: invoice in PDF format
        $view = Services::renderer();
        $html = $view->setData($sale_data)->render("sales/$type" . '_email', $sale_data);

        // Load PDF helper
        helper(['dompdf', 'file']);
        $filename = sys_get_temp_dir() . '/' . lang('Sales.' . $type) . '-' . str_replace('/', '-', $number) . '.pdf';
        if (file_put_contents($filename, create_pdf($html)) !== false) {
            $attachment = $filename;

            if (($type == 'invoice' || $type == 'credit_note' || $type == 'debit_note') && !empty($invoice_xml)) {
                $xml_filename = sys_get_temp_dir() . '/' . lang('Sales.' . $type) . '-' . str_replace('/', '-', $number) . '.xml';
                file_put_contents($xml_filename, $invoice_xml);

                $zip_filename = sys_get_temp_dir() . '/' . lang('Sales.' . $type) . '-' . str_replace('/', '-', $number) . '.zip';
                $zip = new \ZipArchive();
                if ($zip->open($zip_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    $zip->addFile($filename, basename($filename));
                    $zip->addFile($xml_filename, basename($xml_filename));
                    $zip->close();
                    $attachment = $zip_filename;
                }
            }

            $result = $email_lib->sendEmail($to, $subject, $text, $attachment);
        }

        $message = lang($result ? "Sales." . $type . "_sent" : "Sales." . $type . "_unsent") . ' ' . $to;
    }

    echo json_encode(['success' => $result, 'message' => $message, 'id' => $sale_data['sale_id_num']]);

    $sale_lib->clear_all();

    return $result;
}

/**
 * Generate DIAN XML Document (Invoice, CreditNote, DebitNote)
 * 
 * @param int $sale_id
 * @param string $documentType 'invoice', 'credit_note', 'debit_note'
 * @return array|bool Unsigned XML string or false on failure
 */
function getDocumentDataForDian(int $sale_id, string $documentType = 'invoice'): array|bool
{
    helper('sale');
    helper('dian');
    $config = config(OSPOS::class)->settings;
    $tax_lib = new Tax_lib();
    $token_lib = new Token_lib();
    $data = get_sale_data($sale_id);

    if (empty($data['cart'])) {
        return false;
    }

    // Build items
    $lineItems = [];
    foreach ($data['cart'] as $item) {
        $qty = (float) $item['quantity'];
        $lineExtension = (float) $item['discounted_total']; 
        
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
            'quantity'      => abs($qty),
            'unit_code'     => 'NIU',
            'unit_price'    => number_format(abs($unit), 2, '.', ''),
            'discount'      => number_format((float) $item['discount'], 2, '.', ''),
            'discount_type' => $item['discount_type'] == 0 ? 'percentage' : 'fixed',
            'tax_percent'   => number_format($taxPercent, 2, '.', ''),
            'tax_amount'    => number_format(abs($taxAmount), 2, '.', ''),
            'tax_scheme'    => ['id' => '01', 'name' => 'IVA'],
            'tax_exempt'    => $taxPercent == 0,
            'line_extension'=> number_format(abs($lineExtension - $taxAmount), 2, '.', '')
        ];
    }

    $taxTotal = abs((float)($data['total'] - $data['subtotal']));
    $subtotal = abs((float)$data['subtotal']);
    $invoiceTotal = abs((float)$data['total']);

    $softwareID = $config['col_electronic_software_id'];
    $pin = $config['col_electronic_pin'];
    
    $now = new \DateTime('now', new \DateTimeZone('America/Bogota'));
    $issueDate = $now->format('Y-m-d');
    $issueTime = $now->format('H:i:s-05:00');
    $signingTimeValue = $now->format('Y-m-d\TH:i:s-05:00');

    $newInvoiceNumber = $data['invoice_number'];

    if($documentType !== 'invoice')
    {
        $invoice_format = str_replace('{I_DIAN}', '{CN_DIAN}', $config['sales_invoice_format']);
        $newInvoiceNumber = $config['col_electronic_credit_prefix'] . $token_lib->render($invoice_format, [], true);
    }

    // Mapping to InvoiceGenerator format
    $docData = [
        'document_type' => $documentType,
        'invoice_env' => $config['col_electronic_test'] ? 'development' :'production',
        'invoice_number' => $newInvoiceNumber,
        'issue_date' =>  $issueDate,
        'issue_time' => $issueTime,
        'technical_key' => $config['col_electronic_tech_id'],
        'software_security_code' => hash('sha384', $softwareID . $pin . $newInvoiceNumber),
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
        ],
        'customer' => [
            'name'                  => $data['customer_name'] ?: 'CONSUMIDOR FINAL',
            'tax_id'                => explode('-', $data['customer_tax_id'] ?? '222222222222')[0],
            'tax_id_dv'             => count(explode('-', $data['customer_tax_id'] ?? '')) > 1 ? explode('-', $data['customer_tax_id'])[1] : null,
            'document_type'         => $data['customer_tax_id_type'] ?? '13',
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
        'cufe' => $data['cufe'] ?? null,
        'payment_means' => array_map(function($p) {
            $p = (array)$p;
            return ['code' => get_dian_payment_code($p['payment_type'] ?? 'Efectivo')];
        }, $data['payments'] ?? []),
    ];

    $res_auth = $documentType !== 'invoice' ? ($config['col_electronic_credit_resolution'] ?? '') : ($config['col_electronic_range_resolution'] ?? '');
    $res_start_date = $documentType !== 'invoice' ? ($config['col_electronic_credit_range_start_date'] ?? '') : ($config['col_electronic_range_start_date'] ?? '');
    $res_end_date = $documentType !== 'invoice' ? ($config['col_electronic_credit_range_end_date'] ?? '') : ($config['col_electronic_range_end_date'] ?? '');
    $res_min = $documentType !== 'invoice' ? ($config['col_electronic_credit_range_min'] ?? '') : ($config['col_electronic_range_min'] ?? '');
    $res_max = $documentType !== 'invoice' ? ($config['col_electronic_credit_range_max'] ?? '') : ($config['col_electronic_range_max'] ?? '');
    $res_prefix = $documentType !== 'invoice' ? ($config['col_electronic_credit_prefix'] ?? '') : ($config['col_electronic_prefix'] ?? '');

    if (!empty($res_auth)) {
        $docData['resolution'] = [
            'authorization_number' => $res_auth,
            'start_date' => $res_start_date,
            'end_date' => $res_end_date,
            'from' => $res_min,
            'to' => $res_max,
            'prefix' => $res_prefix
        ];
    }

    // Reference logic for Credit/Debit notes
    if ($documentType !== 'invoice') {
        $queue_model = model(InvoiceDianQueue::class);

        $parent_queue = $queue_model->where('sale_id', $sale_id)->where('dian_status', 'accepted')->first();

        $docData['billing_reference'] = [
                    'invoice_id' => $data['invoice_number'], // e.g. "SETT1"
                    'uuid' => $parent_queue['dian_cufe'],
                    'issue_date' => date('Y-m-d', strtotime($parent_queue['updated_at']))
                ];
    }

   // return \DianFE\InvoiceGenerator::generate($docData);
   return $docData;
}

/**
 * Generate Credit Note XML
 */
function generateCreditNote(int $sale_id): string|bool
{
    return getDocumentDataForDian($sale_id, 'credit_note');
}

/**
 * Generate Debit Note XML
 */
function generateDebitNote(int $sale_id): string|bool
{
    return getDocumentDataForDian($sale_id, 'debit_note');
}
