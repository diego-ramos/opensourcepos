<?php

namespace App\Models\Tokens;

use App\Models\Appconfig;

/**
 * Token_invoice_dian class
 **/

class Token_invoice_dian extends Token
{
    private Appconfig $appconfig;

    /**
     * @param string $value
     */
    public function __construct(string $value = '')
    {
        parent::__construct($value);
        $this->appconfig = model(Appconfig::class);
    }

    /**
     * @return string
     */
    public function token_id(): string
    {
        return 'I_DIAN';
    }

    /**
     * @throws ReflectionException
     */
    public function get_value(bool $save = true): string
    {
        $last_used_invoice_number = $this->appconfig->acquire_next_invoice_sequence($save);
        
        if($last_used_invoice_number < $this->appconfig->get_value('col_electronic_range_min')){
            $last_used_invoice_number = $this->appconfig->get_value('col_electronic_range_min');
            if($save) {
                $this->appconfig->save(['last_used_invoice_number' => $last_used_invoice_number]);
            }
        }

        if($last_used_invoice_number > $this->appconfig->get_value('col_electronic_range_max')){
            //throw new \Exception(lang('common.dian_final_invoice_number_reached'));
            return lang('error.dian_final_invoice_number_reached');
        }

        return $last_used_invoice_number;
    }
}
