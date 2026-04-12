<?php

namespace Fissible\Fault\Filament\Resources\FaultGroupResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Fissible\Fault\Filament\Resources\FaultGroupResource;
use Fissible\Fault\Services\FaultService;

class ViewFaultGroup extends ViewRecord
{
    protected static string $resource = FaultGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isOpen())
                ->form([
                    \Filament\Forms\Components\Textarea::make('notes')->label('Resolution Notes'),
                    \Filament\Forms\Components\TextInput::make('version')->label('Resolved in Version'),
                ])
                ->action(function (array $data) {
                    app(FaultService::class)->resolve(
                        $this->record,
                        $data['notes'] ?? null,
                        auth()->id(),
                        $data['version'] ?? null,
                    );
                }),
            Actions\Action::make('ignore')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isOpen())
                ->action(fn () => app(FaultService::class)->ignore($this->record, ignoredBy: auth()->id())),
            Actions\Action::make('reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => ! $this->record->isOpen())
                ->action(fn () => app(FaultService::class)->reopen($this->record)),
            Actions\Action::make('generate_test')
                ->icon('heroicon-o-beaker')
                ->requiresConfirmation()
                ->label($this->record->generated_test ? 'Regenerate Test' : 'Generate Test')
                ->action(fn () => app(FaultService::class)->generateTest($this->record)),
            Actions\Action::make('delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Type "DELETE" to confirm. This cannot be undone.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('confirm')
                        ->label('Type DELETE to confirm')
                        ->required()
                        ->rules(['in:DELETE']),
                ])
                ->action(function () {
                    app(FaultService::class)->delete($this->record);
                    $this->redirect(FaultGroupResource::getUrl());
                }),
        ];
    }
}
