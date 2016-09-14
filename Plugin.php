<?php namespace Responsiv\Subscribe;

use Event;
use Backend;
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

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->extendPayNavigation();
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            'Responsiv\Subscribe\Components\PlanList' => 'subscribePlanList',
            'Responsiv\Subscribe\Components\Register' => 'subscribeRegister',
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
            'statuses' => [
                'label'       => 'Membership Route',
                'description' => 'Configure possible order statuses, status colors, transitions and email notification rules.',
                'icon'        => 'icon-exchange',
                'url'         => Backend::url('responsiv/subscribe/currencies'),
                'category'    => 'Subscriptions',
                'order'       => 500,
            ],
        ];
    }

    protected function extendPayNavigation()
    {
        Event::listen('backend.menu.extendItems', function($manager) {
            $manager->addSideMenuItems('Responsiv.Pay', 'pay', [
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
            ]);
        });
    }

    public function registerMailTemplates()
    {
        return [
            'responsiv.subscribe::mail.billing_report' => 'Sent to administrators when the system finishes processing the automated billing.',
            'responsiv.subscribe::mail.invoice_report' => 'Sent to administrators when the system finishes generating membership orders.',
            'responsiv.subscribe::mail.membership_thankyou' => 'Sent to customers on new membership subscription order.',
            'responsiv.subscribe::mail.new_membership_internal' => 'Sent to the store team members when an membership changes its status.',
            'responsiv.subscribe::mail.membership_status_update_internal' => 'Sent to the store team members on new membership.',
        ];
    }

}
