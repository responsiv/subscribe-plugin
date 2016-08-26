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
        return []; // Remove this line to activate

        return [
            'Responsiv\Subscribe\Components\MyComponent' => 'myComponent',
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
            'dunning' => [
                'label'       => 'Retries and Dunning',
                'description' => 'Enable and configure dunning strategies and retries.',
                'icon'        => 'icon-bell',
                'url'         => Backend::url('responsiv/subscribe/dunnings'),
                'category'    => 'Subscriptions',
                'order'       => 500,
            ],
            'notifications' => [
                'label'       => 'Notification Workflows',
                'description' => 'Create and change notification emails for memberships.',
                'icon'        => 'icon-tag',
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

}
