<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-size: 10px;
            width: 58mm;
            max-width: 384px;
            margin: 0;
            padding: 0;
        }

        .center {
            text-align: center;
        }

        .receipt {
            padding: 5px;
        }

        .header, .footer {
            margin-bottom: 10px;
        }

        .items {
            width: 100%;
        }

        .items th, .items td {
            text-align: left;
            padding: 2px 0;
        }

        .totals {
            border-top: 1px dashed #000;
            margin-top: 5px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center header">
            <strong><?= $company; ?></strong><br>
            <?= nl2br($company_info); ?><br>
            <?= $transaction_time; ?><br>
        </div>

        <table class="items">
            <?php foreach($cart as $item): ?>
            <tr>
                <td colspan="2"><?= $item['name']; ?></td>
            </tr>
            <tr>
                <td><?= $item['quantity']; ?> x <?= to_currency($item['price']); ?></td>
                <td style="text-align: right;"><?= to_currency($item['total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="totals">
            <div>Total: <?= to_currency($total); ?></div>
            <div>Paid: <?= to_currency($amount_tendered); ?></div>
            <div>Change: <?= to_currency($amount_change); ?></div>
        </div>

        <div class="center footer">
            <?= nl2br($comments); ?><br>
            <?= $this->config->item('return_policy'); ?>
        </div>
    </div>
</body>
</html>