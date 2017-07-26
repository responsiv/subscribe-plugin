<?php namespace Responsiv\Subscribe;

use Event;
use Backend;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use RainLab\User\Models\User as UserModel;
use System\Classes\PluginBase;

/**
 * Subscribe Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['Responsiv.Pay'];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Subscribe',
            'description' => 'Subscription manager',
            'author'      => 'Responsiv Internet',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSubscriptionEvents();

        /*
         * Console commands
         */
        $this->registerConsoleCommand('subscribe.run', 'Responsiv\Subscribe\Console\SubscriptionRun');
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->extendUserModel();
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            'Responsiv\Subscribe\Components\Subscribe'  => 'subscribe',
            'Responsiv\Subscribe\Components\Payment'    => 'subscribePayment',
            'Responsiv\Subscribe\Components\PlanList'   => 'subscribePlanList',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'responsiv.subscribe.manage_memberships' => [
                'tab' => 'Subscribe',
                'label' => 'Manage subscriptions'
            ],
            'responsiv.subscribe.manage_plans' => [
                'tab' => 'Subscribe',
                'label' => 'Manage subscription plans'
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Membership Settings',
                'description' => 'Configure settings that apply to memberships.',
                'icon'        => 'icon-exchange',
                'class'       => 'Responsiv\Subscribe\Models\Setting',
                'category'    => 'Pay',
                'order'       => 530,
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'subscribe' => [
                'label'       => 'Subscribers',
                'url'         => Backend::url('responsiv/subscribe/memberships'),
                'icon'        => 'icon-newspaper-o',
                'iconSvg'     => 'plugins/responsiv/subscribe/assets/images/subscribe-icon.svg',
                'permissions' => ['subscribe.*'],
                'order'       => 490,

                'sideMenu' => [
                    'memberships' => [
                        'label'       => 'Memberships',
                        'icon'        => 'icon-users',
                        'url'         => Backend::url('responsiv/subscribe/memberships'),
                        'permissions' => ['pay.*'],
                    ],
                    'plans' => [
                        'label'       => 'Plans',
                        'icon'        => 'icon-clipboard',
                        'url'         => Backend::url('responsiv/subscribe/plans'),
                        'permissions' => ['pay.*'],
                    ]
                ]
            ]
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'responsiv.subscribe::mail.billing_report',
            'responsiv.subscribe::mail.invoice_report',
            'responsiv.subscribe::mail.membership_thankyou',
            'responsiv.subscribe::mail.new_membership_internal',
            'responsiv.subscribe::mail.membership_status_update_internal'
        ];
    }

    /**
     * Register events related to this plugin, needs to be public for unit testing.
     */
    public function registerSubscriptionEvents()
    {
        $manager = SubscriptionEngine::instance();
        Event::listen('responsiv.pay.invoicePaid', [$manager, 'invoiceAfterPayment']);
    }

    /**
     * Extends the User model provided by the RainLab.User plugin.
     */
    protected function extendUserModel()
    {
        UserModel::extend(function($model) {
            $model->implement[] = 'Responsiv.Subscribe.Behaviors.SubscriberModel';
        });
    }
}
