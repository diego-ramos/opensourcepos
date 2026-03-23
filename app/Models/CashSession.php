<?php

namespace App\Models;

use CodeIgniter\Model;

class CashSession extends Model
{
    protected $table = 'cash_sessions';
    protected $primaryKey = 'cash_session_id';
    protected $useAutoIncrement = true;
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'employee_id',
        'open_date',
        'open_amount',
        'close_date',
        'close_amount',
        'deleted'
    ];

    /**
     * Get the active session for an employee
     */
    public function get_active_session(int $employee_id): ?array
    {
        return $this->where('employee_id', $employee_id)
                    ->where('close_date', null)
                    ->where('deleted', 0)
                    ->first();
    }

    /**
     * Search sessions for the report
     */
    public function search(string $search, array $filters, int $limit, int $offset, string $sort, string $order)
    {
        $builder = $this->db->table($this->table . ' AS sessions');
        $builder->select('sessions.*, people.first_name, people.last_name');
        $builder->join('people', 'people.person_id = sessions.employee_id', 'left');

        if ($search) {
            $builder->groupStart();
            $builder->like('people.first_name', $search);
            $builder->orLike('people.last_name', $search);
            $builder->groupEnd();
        }

        if (!empty($filters['start_date'])) {
            $builder->where('sessions.open_date >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('sessions.open_date <=', $filters['end_date'] . ' 23:59:59');
        }

        $builder->where('sessions.deleted', 0);
        $builder->orderBy($sort, $order);
        
        if ($limit > 0) {
            $builder->limit($limit, $offset);
        }

        return $builder->get();
    }
    
    public function get_found_rows(string $search, array $filters): int
    {
        $builder = $this->db->table($this->table . ' AS sessions');
        $builder->join('people', 'people.person_id = sessions.employee_id', 'left');

        if ($search) {
            $builder->groupStart();
            $builder->like('people.first_name', $search);
            $builder->orLike('people.last_name', $search);
            $builder->groupEnd();
        }

        if (!empty($filters['start_date'])) {
            $builder->where('sessions.open_date >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('sessions.open_date <=', $filters['end_date'] . ' 23:59:59');
        }

        $builder->where('sessions.deleted', 0);
        
        return $builder->countAllResults();
    }
}
