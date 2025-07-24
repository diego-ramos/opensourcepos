<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceDianQueue extends Model
{
    protected $table = 'invoices_dian_queue';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'sale_id',
        'status',
        'dian_cufe',
        'dian_response_code',
        'dian_response_description',
        'dian_application_response',
        'dian_zip_filename',
        'dian_sent_at',
        'dian_status',
        'error_message',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
