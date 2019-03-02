<?php namespace Responsiv\Subscribe\Models;

use Model;

class Setting extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'subscribe_settings';
    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->membership_price = 0;
        $this->trial_days = 0;
        $this->is_trial_inclusive = true;
        $this->is_card_upfront = true;
        $this->invoice_advance_days = 0;
        $this->grace_days = 14;
    }
}
