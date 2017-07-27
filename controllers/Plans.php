<?php namespace Responsiv\Subscribe\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Plans Back-end Controller
 */
class Plans extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Subscribe', 'subscribe', 'plans');
    }
}