<?php

namespace Fissible\Fault\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Fissible\Fault\Filament\Resources\FaultGroupResource;
use Fissible\Fault\Filament\Widgets\FaultSummaryWidget;

class FaultPlugin implements Plugin
{
    protected bool $enabled = true;

    public static function make(): static
    {
        return new static();
    }

    public function enabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getId(): string
    {
        return 'fault';
    }

    public function register(Panel $panel): void
    {
        if (! $this->enabled) {
            return;
        }

        $panel
            ->resources([
                FaultGroupResource::class,
            ])
            ->widgets([
                FaultSummaryWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
