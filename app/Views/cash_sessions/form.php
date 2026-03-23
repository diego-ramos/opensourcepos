<div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>

<ul id="error_message_box" class="error_message_box"></ul>

<?= form_open("cash_in_out/save", ['id' => 'cash_session_form', 'class' => 'form-horizontal']) ?>
    <fieldset id="cash_session_info">
        <div class="form-group form-group-sm">
            <?= form_label(lang('Cash_in_out.amount'), 'amount', ['class' => 'control-label col-xs-3 required']) ?>
            <div class='col-xs-8'>
                <div class="input-group input-group-sm">
                    <?php if (!empty($config['currency_side'])): ?>
                        <span class="input-group-addon"><b><?= esc($config['currency_symbol']) ?></b></span>
                    <?php endif; ?>
                    <?= form_input([
                        'name'  => 'amount',
                        'id'    => 'amount',
                        'class' => 'form-control input-sm',
                        'value' => ''
                    ]) ?>
                    <?php if (empty($config['currency_side'])): ?>
                        <span class="input-group-addon"><b><?= esc($config['currency_symbol']) ?></b></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($active_session): ?>
            <div class="alert alert-info" style="margin: 10px;">
                <?= lang('Cash_in_out.open_date') ?>: <?= to_datetime(strtotime($active_session['open_date'])) ?><br>
                <?= lang('Cash_in_out.open_amount') ?>: <?= to_currency($active_session['open_amount']) ?>
            </div>
        <?php endif; ?>
    </fieldset>
<?= form_close() ?>

<script type="text/javascript">
$(document).ready(function()
{
    $('#cash_session_form').validate({
        submitHandler: function(form) {
            $(form).ajaxSubmit({
                success: function(response) {
                    dialog_support.hide();
                    table_support.handle_submit(response, null, null);
                    // Refresh the header button if we are in a page with the header
                    if (typeof refresh_cash_button === 'function') {
                        refresh_cash_button();
                    }
                },
                dataType: 'json'
            });
        },
        errorLabelContainer: "#error_message_box",
        wrapper: "li",
        rules: {
            amount: {
                required: true,
                number: true
            }
        },
        messages: {
            amount: {
                required: "<?= lang('Common.amount_required') ?>",
                number: "<?= lang('Common.amount_number') ?>"
            }
        }
    });
});
</script>
