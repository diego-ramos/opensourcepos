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
