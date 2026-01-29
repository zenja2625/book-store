<?php

namespace Bookstore\Catalog;

use Backend\Facades\Backend;
use Backend\Models\UserRole;
use System\Classes\PluginBase;

/**
 * Catalog Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'bookstore.catalog::lang.plugin.name',
            'description' => 'bookstore.catalog::lang.plugin.description',
            'author'      => 'Bookstore',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {

    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {

    }

    /**
     * Registers any frontend components implemented in this plugin.
     */
    public function registerComponents(): array
    {
        return []; // Remove this line to activate

        return [
            \Bookstore\Catalog\Components\MyComponent::class => 'myComponent',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     */
    public function registerPermissions(): array
    {
        return []; // Remove this line to activate

        return [
            'bookstore.catalog.some_permission' => [
                'tab' => 'bookstore.catalog::lang.plugin.name',
                'label' => 'bookstore.catalog::lang.permissions.some_permission',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ],
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     */
    public function registerNavigation(): array
    {
        return []; // Remove this line to activate

        return [
            'catalog' => [
                'label'       => 'bookstore.catalog::lang.plugin.name',
                'url'         => Backend::url('bookstore/catalog/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['bookstore.catalog.*'],
                'order'       => 500,
            ],
        ];
    }
}
