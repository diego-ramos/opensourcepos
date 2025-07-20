<?php
// partial/dian_configuration.php
// Admin UI for managing DIAN configuration
?>

<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_software_id'), 'col_electronic_software_id', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'name'  => 'col_electronic_software_id',
            'id'    => 'col_electronic_software_id',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_software_id'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_software_pin'), 'col_electronic_software_pin', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'password',
            'name'  => 'col_electronic_pin',
            'id'    => 'col_electronic_pin',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_pin'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_invoice_wsdl'), 'col_electronic_invoice_wsdl', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'url',
            'name'  => 'col_electronic_invoice_wsdl',
            'id'    => 'col_electronic_invoice_wsdl',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_invoice_wsdl'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_invoice_cert_path'), 'col_electronic_invoice_cert_path', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'name'  => 'col_electronic_invoice_cert_path',
            'id'    => 'col_electronic_invoice_cert_path',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_invoice_cert_path'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_invoice_cert_password'), 'col_electronic_invoice_cert_password', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'password',
            'name'  => 'col_electronic_invoice_cert_password',
            'id'    => 'col_electronic_invoice_cert_password',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_invoice_cert_password'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
