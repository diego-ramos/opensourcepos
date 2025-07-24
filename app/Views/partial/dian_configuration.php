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
    <?= form_label(lang('Config.col_electronic_tech_id'), 'col_electronic_tech_id', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'name'  => 'col_electronic_tech_id',
            'id'    => 'col_electronic_tech_id',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_tech_id'] ?? '',
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
    <?= form_label(lang('Config.col_electronic_invoice_cert_key_path'), 'col_electronic_invoice_cert_key_path', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'name'  => 'col_electronic_invoice_cert_key_path',
            'id'    => 'col_electronic_invoice_cert_key_path',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_invoice_cert_key_path'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_invoice_cert_crt_path'), 'col_electronic_invoice_cert_crt_path', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'name'  => 'col_electronic_invoice_cert_crt_path',
            'id'    => 'col_electronic_invoice_cert_crt_path',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_invoice_cert_crt_path'] ?? '',
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
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_range_min'), 'col_electronic_range_min', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'number',
            'name'  => 'col_electronic_range_min',
            'id'    => 'col_electronic_range_min',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_range_min'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_range_max'), 'col_electronic_range_max', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'number',
            'name'  => 'col_electronic_range_max',
            'id'    => 'col_electronic_range_max',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_range_max'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
<div class="form-group form-group-sm">
    <?= form_label(lang('Config.col_electronic_prefix'), 'col_electronic_prefix', ['class' => 'control-label col-xs-3']) ?>
    <div class="col-xs-4">
        <?= form_input([
            'type'  => 'text',
            'name'  => 'col_electronic_prefix',
            'id'    => 'col_electronic_prefix',
            'class' => 'form-control input-sm',
            'value' => $config['col_electronic_prefix'] ?? '',
            'required' => true
        ]) ?>
    </div>
</div>
