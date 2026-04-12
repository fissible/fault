<?php

namespace Fissible\Fault\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Fissible\Fault\Models\FaultGroup;

class FaultSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $openCount = FaultGroup::where('status', 'open')->count();
        $newToday = FaultGroup::where('first_seen_at', '>=', now()->startOfDay())->count();
        $seenToday = FaultGroup::where('last_seen_at', '>=', now()->startOfDay())->count();

        return [
            Stat::make('Open Faults', $openCount)
                ->color($openCount > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-bug-ant'),
            Stat::make('New Today', $newToday)
                ->description('New fault groups first seen today')
                ->color($newToday > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Seen Today', $seenToday)
                ->description('Fault groups with occurrences today')
                ->icon('heroicon-o-eye'),
        ];
    }
}
