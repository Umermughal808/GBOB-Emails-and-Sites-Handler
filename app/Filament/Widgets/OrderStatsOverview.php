<?php

namespace App\Filament\Widgets;

use App\Models\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Orders', Order::count())
                ->description('All-time orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
                
            Stat::make('Completed Orders', Order::where('status', 'completed')->count())
                ->description('Successfully delivered')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('In Progress', Order::where('status', 'in_progress')->count())
                ->description('Currently being processed')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),
                
            Stat::make('Pending Payment', Order::where('invoice_status', 'unpaid')->count())
                ->description('Awaiting payment')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('danger'),
        ];
    }
    
    public static function canView(): bool
    {
        return true;
    }
}
