<?php
// partial/tax_id_types.php
// Admin UI for managing National ID Types (DIAN codes)
/**
 * @var array $active_tax_id_types
 * @var array $config
 */
?>
<div class="panel panel-default">
    
    <div class="panel-body">
        <?= form_open('config/saveColombiaElectronicInvoice/', ['id' => 'tax_id_types_config_form', 'class' => 'form-horizontal']) ?>
        
            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.col_electronic_invoice_enable'), 'col_electronic_invoice_enable', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-1">
                    <?= form_checkbox([
                        'name'    => 'col_electronic_invoice_enable',
                        'value'   => 'col_electronic_invoice_enable',
                        'id'      => 'col_electronic_invoice_enable',
                        'checked' => $config['col_electronic_invoice_enable'] == 1
                    ]) ?>
                </div>
            </div>
            <div class="container-fluid">
                <ul class="nav nav-tabs" id="electronicInvoiceTabs" data-toggle="tab">
                    <li class="active electronicInvoiceTabs"><a href="#tax_id_types_tabs" data-toggle="tab" title="<?= lang('Config.tax_id_type_configuration') ?>"><?= lang('Config.tax_id_type_configuration') ?></a></li>
                    <li class="electronicInvoiceTabs"><a href="#dian_configuration_tabs" data-toggle="tab" title="<?= lang('Config.dian_configuration') ?>"><?= lang('Config.dian_configuration') ?></a></li>
                    <!-- <li><a href="#message_tabs" data-toggle="tab" title="<?= lang('Config.message_configuration') ?>"><?= lang('Config.message') ?></a></li>
                    <li><a href="#integrations_tabs" data-toggle="tab" title="<?= lang('Config.integrations_configuration') ?>"><?= lang('Config.integrations') ?></a></li>
                    <li><a href="#license_tabs" data-toggle="tab" title="<?= lang('Config.license_configuration') ?>"><?= lang('Config.license') ?></a></li> -->
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active electronicInvoiceTabs" id="tax_id_types_tabs"><?= view('partial/tax_id_types') ?></div>
                    <div class="tab-pane" id="dian_configuration_tabs"><?= view('partial/dian_configuration') ?></div>
                   <!-- <div class="tab-pane" id="message_tabs"><?= view('configs/message_config') ?></div>
                    <div class="tab-pane" id="integrations_tabs"><?= view('configs/integrations_config') ?></div>
                    <div class="tab-pane" id="license_tabs"><br><?= view('configs/license_config') ?></div> -->
                </div>
            </div>
            
            <?= form_submit([
                'name'  => 'submit_tax_id_type',
                'id'    => 'submit_tax_id_type',
                'value' => lang('Common.submit'),
                'class' => 'btn btn-primary btn-sm pull-right'
            ]) ?>
        <?= form_close() ?>
    </div>
</div>
<script  type="text/javascript">


    $(document).ready(function() {
        var enable_disable_col_electronic_invoice = (function() {
            var col_electronic_invoice_enable = $("#col_electronic_invoice_enable").is(":checked");
            $("input[name*='tax_id_type'], #add_tax_id_type")
                .not("input[name=col_electronic_invoice_enable], #submit_tax_id_type")
                .prop("disabled", !col_electronic_invoice_enable);
            if (col_electronic_invoice_enable) {
                $(".electronicInvoiceTabs").show();
            } else {
                $(".electronicInvoiceTabs").hide();
            }
            return arguments.callee;
        })();

         $("#col_electronic_invoice_enable").change(enable_disable_col_electronic_invoice);

        // Add new row
        $('#add_tax_id_type').on('click', function(e) {
            e.preventDefault();
            const tbody = $('#tax_id_types_table tbody');
            const row = $('<tr>');
            row.html(`
                <td><input type="number" name="tax_id_type[-1][code]" class="form-control" required /></td>
                <td><input type="text" name="tax_id_type[-1][label]" class="form-control" required /></td>
                <td class="text-center"><input type="checkbox" name="tax_id_type[-1][active]" value="1" checked /></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-type"><span class="glyphicon glyphicon-trash"></span></button></td>
            `);
            tbody.append(row);
        });
        // Remove row
        $('#tax_id_types_table').on('click', '.remove-type', function() {
            $(this).closest('tr').remove();
        });
        // AJAX submit only, no custom rules/messages
        $('#tax_id_types_config_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if ($.notify) {
                        $.notify({ message: response.message }, { type: response.success ? 'success' : 'danger' });
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    if ($.notify) {
                        $.notify({ message: '<?= lang('Common.error') ?>' }, { type: 'danger' });
                    } else {
                        alert('<?= lang('Common.error') ?>');
                    }
                }
            });
        });
    });
</script>
