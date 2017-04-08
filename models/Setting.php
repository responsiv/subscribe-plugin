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
        $this->is_trial_inclusive = false;
        $this->grace_days = 14;
        $this->invoice_advance_days = 0;
    }
}
