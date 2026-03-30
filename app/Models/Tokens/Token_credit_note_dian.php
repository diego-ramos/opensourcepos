<?php

namespace App\Models\Tokens;

use App\Models\Appconfig;

/**
 * Token_credit_note_dian class
 **/

class Token_credit_note_dian extends Token
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
        return 'CN_DIAN';
    }

    /**
     * @throws \ReflectionException
     */
    public function get_value(bool $save = true): string
    {
        $last_used_credit_note_number = $this->appconfig->acquire_next_credit_note_sequence($save);
        
        if($last_used_credit_note_number < $this->appconfig->get_value('col_electronic_credit_range_min')){
            $last_used_credit_note_number = $this->appconfig->get_value('col_electronic_credit_range_min');
            if($save) {
                $this->appconfig->save(['last_used_credit_note_number' => $last_used_credit_note_number]);
            }
        }

        if($last_used_credit_note_number > $this->appconfig->get_value('col_electronic_credit_range_max')){
            return lang('error.dian_final_invoice_number_reached');
        }

        return $last_used_credit_note_number;
    }
}
