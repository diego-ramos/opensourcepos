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

/**
 * Get sale data for invoice/receipt.
 * 
 * @param int $sale_id
 * @return array
 */
function get_sale_data(int $sale_id): array
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

    load_dian_data($sale_id, $data);

    return $data;
}

function load_dian_data(int $sale_id, array &$data)
{
    $config = config(OSPOS::class)->settings;
    $dian_config = config(Dian::class);
    
    $queue = new InvoiceDianQueue();
    $queue_info = $queue->where('sale_id', $sale_id)->first();
    if (!isset($queue_info)) {
        return;
    }
    
    $data['cufe'] = $queue_info['dian_cufe'];

    $qr_text =
    "NumFac=".$sale_id."\n".
    "FecFac=".$data['transaction_date']."\n".
    "HorFac=".$data['transaction_time']."\n".
    "NitFac=".$config['tax_id']."\n".
    "DocAdq=".$data['customer_tax_id']."\n".
    "ValFac=".number_format($data['subtotal'], 2, '.', '')."\n".
    "ValIva=".number_format($data['tax_total'], 2, '.', '')."\n".
    "ValTot=".number_format($data['non_cash_total'], 2, '.', '')."\n".
    "CUFE=".$data['cufe']."\n".
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

            if ($type == 'invoice' && !empty($invoice_xml)) {
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
