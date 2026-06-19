<?php

namespace App\Enums;

enum Permission: string
{
    case DashboardView  = 'dashboard.view';
    case OrdersView     = 'orders.view';
    case OrdersManage   = 'orders.manage';
    case UsersView      = 'users.view';
    case UsersManage    = 'users.manage';
    case RolesManage    = 'roles.manage';
    case InvoicesView   = 'invoices.view';
    case InvoicesManage = 'invoices.manage';
    case SupportView    = 'support.view';
    case SupportManage  = 'support.manage';
    case SettingsManage = 'settings.manage';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            'dashboard.view'  => 'View Dashboard',
            'orders.view'     => 'View Orders',
            'orders.manage'   => 'Manage Orders',
            'users.view'      => 'View Users',
            'users.manage'    => 'Manage Users',
            'roles.manage'    => 'Manage Roles',
            'invoices.view'   => 'View Invoices',
            'invoices.manage' => 'Manage Invoices',
            'support.view'    => 'View Support Tickets',
            'support.manage'  => 'Manage Support Tickets',
            'settings.manage' => 'Manage Settings',
        ];
    }
}
