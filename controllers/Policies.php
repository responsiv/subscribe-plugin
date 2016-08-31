<?php namespace Responsiv\Subscribe\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Responsiv\Subscribe\Models\Policy;

/**
 * Policies Back-end Controller
 */
class Policies extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        if (post('path_mode')) {
            $this->formConfig = 'config_path_form.yaml';
        }

        parent::__construct();

        BackendMenu::setContext('Responsiv.Pay', 'pay', 'plans');
    }

    //
    // Path form
    //

    protected function getPolicyModel()
    {
        $model = new Policy;

        if (count($this->params)) {
            $planId = reset($this->params);
            return $model::find($planId) ?: $model;
        }

        return $model;
    }

    protected function refreshPlanPathList()
    {
        $plans = $this->getPolicyModel()
            ->paths()
            ->withDeferred($this->formGetSessionKey())
            ->get()
        ;

        $this->vars['paths'] = $plans;

        return ['#pathList' => $this->makePartial('path_list')];
    }

    public function onCreatePathForm()
    {
        $this->asExtension('FormController')->create();

        return $this->makePartial('path_create_form');
    }

    public function onCreatePath()
    {
        $this->asExtension('FormController')->create_onSave();

        $model = $this->formGetModel();

        $plan = new Policy;

        $plan->paths()->add($model, $this->formGetSessionKey());

        return $this->refreshPlanPathList();
    }

    public function onUpdatePathForm()
    {
        $this->asExtension('FormController')->update(post('record_id'));

        $this->vars['recordId'] = post('record_id');

        return $this->makePartial('path_update_form');
    }

    public function onUpdatePath()
    {
        $this->asExtension('FormController')->update_onSave(post('record_id'));

        return $this->refreshPlanPathList();
    }

    public function onDeletePath()
    {
        $this->initForm($model = $this->formFindModelObject(post('record_id')));

        $plan = new Policy;

        $plan->paths()->remove($model, $this->formGetSessionKey());

        return $this->refreshPlanPathList();
    }
}
