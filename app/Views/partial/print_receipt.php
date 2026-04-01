<?php
/**
 * @var string $selected_printer
 * @var bool $print_after_sale
 * @var array $config
 */
?>

<script src="/js/qz-tray.js"></script>

<script>
    async function printReceipt58() {

        const config = qz.configs.create("Vendor-POS-Printer", {
            encoding: "UTF-8"
        });

        const html = document.getElementById("receipt_wrapper_58").outerHTML;

        const printData = [{
            type: 'raw',
            format: 'html',
            flavor: 'plain',
            data: html,
            options: {
                    dotDensity: "double",
                    language: "ESCPOS",
                    pageWidth: "576",
                    x: "0",
                    y: "0",
                }
            }, 
            // feeds (líneas en blanco)
            { type: 'raw', format: 'command', data: '\n\n\n\n' },

            // corte parcial
            { type: 'raw', format: 'command', data: '\x1D\x56\x01' }
        ];

        await qz.print(config, printData);
    }
</script>

<script type="text/javascript">
  async function printdoc() {
        // Install Firefox addon in order to use this plugin
        if (window.jsPrintSetup) {
            // Set top margins in millimeters
            jsPrintSetup.setOption('marginTop', '<?= $config['print_top_margin'] ?>');
            jsPrintSetup.setOption('marginLeft', '<?= $config['print_left_margin'] ?>');
            jsPrintSetup.setOption('marginBottom', '<?= $config['print_bottom_margin'] ?>');
            jsPrintSetup.setOption('marginRight', '<?= $config['print_right_margin'] ?>');

            <?php if (!$config['print_header']) { ?>
                // Set page header
                jsPrintSetup.setOption('headerStrLeft', '');
                jsPrintSetup.setOption('headerStrCenter', '');
                jsPrintSetup.setOption('headerStrRight', '');
            <?php } ?>
            <?php if (!$config['print_footer']) { ?>
                // Set empty page footer
                jsPrintSetup.setOption('footerStrLeft', '');
                jsPrintSetup.setOption('footerStrCenter', '');
                jsPrintSetup.setOption('footerStrRight', '');
            <?php } ?>

            var printers = jsPrintSetup.getPrintersList().split(',');
            // Get right printer here..
            for (var index in printers) {
                var default_ticket_printer = window.localStorage && localStorage['<?= esc($selected_printer, 'js') ?>'];
                var selected_printer = printers[index];
                if (selected_printer == default_ticket_printer) {
                    // Select Epson label printer
                    jsPrintSetup.setPrinter(selected_printer);
                    // Clears user preferences always silent print value
                    // to enable using 'printSilent' option
                    jsPrintSetup.clearSilentPrint();
                    <?php if (!$config['print_silently']) { ?>
                        // Suppress print dialog (for this context only)
                        jsPrintSetup.setOption('printSilent', 1);
                    <?php } ?>
                    // Do Print
                    // When print is submitted it is executed asynchronous and
                    // script flow continues after print independently of completetion of print process!
                    jsPrintSetup.print();
                }
            }
        } else {
            const isWindows = navigator.platform.toLowerCase().includes('win');

            if (isWindows) {
                window.print();
                return;
            }

            try {
                if (!qz.websocket.isActive()) {
                    await qz.websocket.connect({ retries: 0, delay: 0 });
                }

                await printReceipt58();

            } catch (e) {
                window.print();
            }
        }
    }

    <?php if ($print_after_sale) { ?>
        $(window).on('load', (function() {
            // Executes when complete page is fully loaded, including all frames, objects and images
            printdoc();

            // After a delay, return to sales view
            setTimeout(function() {
                window.location.href = "<?= site_url('sales') ?>";
            }, <?= $config['print_delay_autoreturn'] * 1000 ?>);
        }));

    <?php } ?>
</script>
