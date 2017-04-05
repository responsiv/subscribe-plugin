<?php namespace Responsiv\Subscribe\Models;

use Model;

class Setting extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'subscribe_settings';
    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->membership_fee = 0;
        $this->trial_days = 0;
        $this->grace_days = 14;
        $this->is_trial_inclusive = false;
    }
}
