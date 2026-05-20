<?php

namespace App\Enums;

enum Permission: string
{
    case Dashboard      = 'dashboard';
    case BudgetReports  = 'budgetReports';
    case KpiTracking    = 'kpiTracking';
    case UserManagement = 'userManagement';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
