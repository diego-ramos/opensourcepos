<?php

namespace App\Controllers;

use App\Models\CashSession;
use Config\OSPOS;
use Config\Services;

class Cash_in_out extends Secure_Controller
{
    private CashSession $cash_session;
    private array $config;

    public function __construct()
    {
        parent::__construct('cash_in_out');

        helper(['form', 'number', 'tabular']);

        $this->cash_session = model(CashSession::class);
        $this->config = config(OSPOS::class)->settings;
    }

    public function getIndex(): void
    {
        if (!$this->employee->has_grant('cash_in_out_report', $this->employee->get_logged_in_employee_info()->person_id)) {
            header("Location:" . base_url("no_access/cash_in_out/cash_in_out_report"));
            exit();
        }

        $data['table_headers'] = get_cash_in_out_manage_table_headers();
        echo view('cash_sessions/manage', $data);
    }

    public function getSearch(): void
    {
        if (!$this->employee->has_grant('cash_in_out_report', $this->employee->get_logged_in_employee_info()->person_id)) {
            echo json_encode(['total' => 0, 'rows' => []]);
            return;
        }

        $start_date = $this->request->getGet('start_date');
        $end_date = $this->request->getGet('end_date');

        // Convert to MySQL format if provided
        $date_format = $this->config['dateformat'];
        if ($start_date) {
            $dt = \DateTime::createFromFormat($date_format, $start_date);
            $start_date = $dt ? $dt->format('Y-m-d') : $start_date;
        }
        if ($end_date) {
            $dt = \DateTime::createFromFormat($date_format, $end_date);
            $end_date = $dt ? $dt->format('Y-m-d') : $end_date;
        }

        $search = (string) $this->request->getGet('search');
        $limit = $this->request->getGet('limit', FILTER_SANITIZE_NUMBER_INT) ?? 20;
        $offset = $this->request->getGet('offset', FILTER_SANITIZE_NUMBER_INT) ?? 0;
        $sort = $this->request->getGet('sort');
        $sort = ($sort == 'cash_session_id' || empty($sort)) ? 'sessions.cash_session_id' : $sort;
        $order = $this->request->getGet('order') ?? 'desc';

        $filters = [
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ];

        $sessions = $this->cash_session->search($search, $filters, $limit, $offset, $sort, $order);
        $total_rows = $this->cash_session->get_found_rows($search, $filters);

        $data_rows = [];
        foreach ($sessions->getResult() as $session) {
            $data_rows[] = get_cash_in_out_data_row($session);
        }

        echo json_encode(['total' => $total_rows, 'rows' => $data_rows]);
    }

    /**
     * AJAX endpoint to check if the current user has an open session
     */
    public function getCheck_session(): void
    {
        $employee_id = $this->employee->get_logged_in_employee_info()->person_id;
        $active_session = $this->cash_session->get_active_session($employee_id);

        echo json_encode([
            'has_active' => !empty($active_session),
            'session_id' => $active_session['cash_session_id'] ?? null
        ]);
    }

    public function getView(int $id = -1): void
    {
        $employee_id = $this->employee->get_logged_in_employee_info()->person_id;
        $active_session = $this->cash_session->get_active_session($employee_id);

        $data = [
            'active_session' => $active_session,
            'employee_id'    => $employee_id
        ];

        echo view('cash_sessions/form', $data);
    }

    public function postSave(int $id = -1): void
    {
        $employee_id = $this->employee->get_logged_in_employee_info()->person_id;
        $active_session = $this->cash_session->get_active_session($employee_id);
        $amount = parse_decimals($this->request->getPost('amount'));

        if ($active_session) {
            // Cash Out
            $data = [
                'close_date'   => date('Y-m-d H:i:s'),
                'close_amount' => $amount
            ];
            if ($this->cash_session->update($active_session['cash_session_id'], $data)) {
                echo json_encode(['success' => true, 'message' => lang('Cash_in_out.cash_out_successful')]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving Cash Out']);
            }
        } else {
            // Cash In
            $data = [
                'employee_id' => $employee_id,
                'open_date'   => date('Y-m-d H:i:s'),
                'open_amount' => $amount
            ];
            if ($this->cash_session->insert($data)) {
                echo json_encode(['success' => true, 'message' => lang('Cash_in_out.cash_in_successful')]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving Cash In']);
            }
        }
    }
}
