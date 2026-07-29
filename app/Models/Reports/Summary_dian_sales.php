<?php

namespace App\Models\Reports;

use CodeIgniter\Database\BaseBuilder;

class Summary_dian_sales extends Summary_report
{
    /**
     * @return array[]
     */
    protected function _get_data_columns(): array
    {
        return [
            ['sale_month' => lang('Reports.sale_month'), 'sortable' => false],
            ['sales'     => lang('Reports.sales'), 'sorter' => 'number_sorter'],
            ['quantity'  => lang('Reports.quantity'), 'sorter' => 'number_sorter'],
            ['subtotal'  => lang('Reports.subtotal'), 'sorter' => 'number_sorter'],
            ['tax'       => lang('Reports.tax'), 'sorter' => 'number_sorter'],
            ['total'     => lang('Reports.total'), 'sorter' => 'number_sorter'],
            ['cost'      => lang('Reports.cost'), 'sorter' => 'number_sorter'],
            ['profit'    => lang('Reports.profit'), 'sorter' => 'number_sorter']
        ];
    }

    /**
     * @param array $inputs
     * @param BaseBuilder $builder
     * @return void
     */
    protected function _select(array $inputs, BaseBuilder &$builder): void
    {
        parent::_select($inputs, $builder);

        $builder->select("
                DATE_FORMAT(sales.sale_time, '%Y-%m') AS sale_month,
                SUM(sales_items.quantity_purchased) AS quantity_purchased,
                COUNT(DISTINCT sales.sale_id) AS sales
        ");
    }

    /**
     * @param BaseBuilder $builder
     * @return void
     */
    protected function _from(BaseBuilder &$builder): void
    {
        parent::_from($builder);
        $builder->join('invoices_dian_queue AS dian', 'sales.sale_id = dian.sale_id', 'inner');
    }

    /**
     * @param array $inputs
     * @param BaseBuilder $builder
     * @return void
     */
    protected function _where(array $inputs, BaseBuilder &$builder): void
    {
        parent::_where($inputs, $builder);
        $builder->where('dian.dian_status', 'accepted');
    }

    /**
     * @param array $inputs
     * @return array
     */
    public function getSummaryData(array $inputs): array
    {
        $builder = $this->db->table('sales_items AS sales_items');

        parent::_select($inputs, $builder);
        $this->_from($builder);
        $this->_where($inputs, $builder);

        return $builder->get()->getRowArray();
    }

    /**
     * @param BaseBuilder $builder
     * @return void
     */
    protected function _group_order(BaseBuilder &$builder): void
    {
        $builder->groupBy('sale_month');
        $builder->orderBy('sale_month');
    }
}
