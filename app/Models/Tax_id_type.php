<?php

namespace App\Models;

use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Model;

class Tax_id_type extends Model
{
    protected $table = 'tax_id_types';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'label', 'active'];
    public $returnType = 'array';

    /**
     * Get all tax ID types.
     *
     * @return ResultInterface
     */
    public function get_all(): ResultInterface
    {
        $builder = $this->db->table($this->table);
        $builder->orderBy('code');
        
        return $builder->get();
    }

    public function get_active(): ResultInterface
    {
        $builder = $this->db->table($this->table);
        $builder->where('active', 1);
        $builder->orderBy('label');

        return $builder->get();
    }

    public function save_value($data, $id = null)
    {
        if ($id && $id > 0) {
            return $this->update($id, $data);
        } else {
            return $this->insert($data);
        }
    }

    public function delete_value($id)
    {
        return $this->delete($id);
    }
}
