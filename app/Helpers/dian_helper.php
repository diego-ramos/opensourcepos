<?php

/**
 * DIAN helper functions
 */

/**
 * Returns the tax responsibility options required by DIAN
 * 
 * @return array
 */
function get_tax_responsibility_options(): array
{
    return [
        'O-13'    => 'O-13 Gran contribuyente',
        'O-15'    => 'O-15 Autorretenedor',
        'O-23'    => 'O-23 Agente de retención IVA',
        'O-47'    => 'O-47 Régimen simple de tributación',
        'R-99-PN' => 'R-99-PN No aplica – Otros'
    ];
}

/**
 * Returns the tax payer type options required by DIAN
 * 
 * @return array
 */
function get_tax_payer_type_options(): array
{
    return [
        '1' => '1 - Persona Jurídica y asimiladas',
        '2' => '2 - Persona Natural y asimiladas'
    ];
}

/**
 * Returns the human-readable labels for tax responsibility codes.
 * Supports a single code or a semicolon-separated list of codes.
 * 
 * @param string|null $codes
 * @return string
 */
function get_tax_responsibility_labels(?string $codes): string
{
    if (empty($codes)) {
        return '';
    }

    $options = get_tax_responsibility_options();
    $codes_array = explode(';', $codes);
    $labels = [];

    foreach ($codes_array as $code) {
        $labels[] = $options[$code] ?? $code;
    }

    return implode(', ', $labels);
}

/**
 * Returns the human-readable label for a tax payer type.
 * 
 * @param int|string|null $id
 * @return string
 */
function get_tax_payer_type_label($id): string
{
    if (empty($id)) {
        return '';
    }

    $options = get_tax_payer_type_options();
    return $options[$id] ?? (string)$id;
}

/**
 * Maps POS payment type strings to DIAN payment method codes.
 * POS payment types are language-dependent strings stored in the database.
 * 
 * @param string|null $payment_type
 * @return string
 */
function get_dian_payment_code(?string $payment_type): string
{
    if (empty($payment_type)) {
        return 'ZZZ'; // Otro (Default)
    }

    // Decode HTML entities (e.g., &eacute; -> é) and trim
    $payment_type = trim(html_entity_decode($payment_type, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    // Mapping array (Spanish and English)
    $mapping = [
        // Spanish
        'Efectivo'               => '10',
        'Tarjeta de débito'      => '49',
        'Tarjeta de Débito'      => '49',
        'Tarjeta de Crédito'     => '48',
        'Tarjeta de crédito'     => '48',
        'Cheque'                 => '20',
        'Tarjeta de regalo'      => '71',
        'Puntos de recompensa'   => '71',
        'Adeudo'                 => 'ZZZ',
        
        // English
        'Cash'                   => '10',
        'Debit Card'             => '49',
        'Credit Card'            => '48',
        'Check'                  => '20',
        'Gift Card'              => '71',
        'Rewards'                => '71',
        'Due'                    => 'ZZZ'
    ];

    // Case-insensitive search
    foreach ($mapping as $key => $code) {
        if (strcasecmp($key, $payment_type) === 0) {
            return $code;
        }
    }

    return 'ZZZ'; // Default to "Otro"
}
