<?= view('partial/header') ?>

<script type="text/javascript">
$(document).ready(function()
{
    <?= view('partial/bootstrap_tables_locale') ?>

    table_support.init({
        resource: '<?= strtolower(get_controller()) ?>',
        headers: <?= $table_headers ?>,
        pageSize: <?= $config['lines_per_page'] ?>,
        uniqueId: 'cash_session_id',
        queryParams: function() {
            return {
                start_date: $("#start_date").val(),
                end_date: $("#end_date").val(),
            }
        }
    });

    $("#start_date, #end_date").datetimepicker({
        format: "<?= dateformat_bootstrap($config['dateformat']) ?>",
        startDate: "2010-01-01"
    }).on("dp.change", function(e) {
        table_support.refresh();
    });
});
</script>

<div id="title_bar" class="btn-toolbar">
    <button class='btn btn-info btn-sm pull-right modal-dlg'
            data-href='<?= site_url("$controller_name/view") ?>'
            data-btn-submit="<?= lang('Common.submit') ?>"
            title='<?= lang('Cash_in_out.cash_in') . " / " . lang('Cash_in_out.cash_out') ?>'>
        <span class="glyphicon glyphicon-usd">&nbsp</span><?= lang('Cash_in_out.cash_in') . " / " . lang('Cash_in_out.cash_out') ?>
    </button>
</div>

<div id="toolbar">
    <div class="pull-left form-inline" style="margin-bottom: 10px;">
        <input type="text" id="start_date" name="start_date" class="form-control input-sm" placeholder="<?= lang('Reports.date_range') ?>" value="<?= date($config['dateformat'], strtotime("-30 days")) ?>"/>
        <input type="text" id="end_date" name="end_date" class="form-control input-sm" placeholder="<?= lang('Reports.date_range') ?>" value="<?= date($config['dateformat']) ?>"/>
    </div>
</div>

<div id="table_holder">
    <table id="table"></table>
</div>

<?= view('partial/footer') ?>
