<?php
// partial/tax_id_types.php
// Admin UI for managing National ID Types (DIAN codes)
/**
 * @var array $tax_id_types
 */
?>
<table class="table table-bordered" id="tax_id_types_table">
    <thead>
        <tr>
            <th><?php echo lang('Taxes.dian_id_type_code'); ?></th>
            <th><?php echo lang('Taxes.dian_id_type_label'); ?></th>
            <th><?php echo lang('Taxes.active'); ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($tax_id_types)): ?>
            <?php foreach ($tax_id_types as $type): ?>
                <tr>
                    <td>
                        <input type="number" name="tax_id_type[<?php echo $type['id']; ?>][code]" value="<?php echo esc($type['code']); ?>" class="form-control" required />
                    </td>
                    <td>
                        <input type="text" name="tax_id_type[<?php echo $type['id']; ?>][label]" value="<?php echo esc($type['label']); ?>" class="form-control" required />
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="tax_id_type[<?php echo $type['id']; ?>][active]" value="1" <?php echo $type['active'] ? 'checked' : ''; ?> />
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-type"><span class="glyphicon glyphicon-trash"></span></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<button type="button" class="btn btn-default btn-sm" id="add_tax_id_type"><?= lang('Common.add') ?></button>