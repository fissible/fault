<?php

namespace Fissible\Fault\Filament\Resources;

use Filament\Infolists\Components\TextEntry;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\FaultService;

class FaultGroupResource extends Resource
{
    protected static ?string $model = FaultGroup::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';
    protected static string|\UnitEnum|null $navigationGroup = 'Logs';
    protected static ?string $navigationLabel = 'Faults';
    protected static ?string $modelLabel = 'Fault';
    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'resolved' => 'success',
                        'ignored' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('class_name')
                    ->label('Exception')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->tooltip(fn (FaultGroup $record) => $record->class_name)
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('file')
                    ->label('Location')
                    ->formatStateUsing(fn (FaultGroup $record) => $record->relativeFile() . ':' . $record->line)
                    ->searchable(['file']),
                Tables\Columns\TextColumn::make('occurrence_count')
                    ->label('Count')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_seen_at')
                    ->label('First Seen')
                    ->dateTime('M j H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime('M j H:i')
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                        'ignored' => 'Ignored',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FaultGroup $record) => $record->isOpen())
                    ->action(fn (FaultGroup $record) => app(FaultService::class)->resolve($record, resolvedBy: auth()->id())),
                Tables\Actions\Action::make('ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (FaultGroup $record) => $record->isOpen())
                    ->action(fn (FaultGroup $record) => app(FaultService::class)->ignore($record, ignoredBy: auth()->id())),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_resolve')
                    ->label('Mark Resolved')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(FaultService::class);
                        $records->each(fn ($r) => $service->resolve($r, resolvedBy: auth()->id()));
                    }),
                Tables\Actions\BulkAction::make('bulk_ignore')
                    ->label('Mark Ignored')
                    ->icon('heroicon-o-eye-slash')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(FaultService::class);
                        $records->each(fn ($r) => $service->ignore($r, ignoredBy: auth()->id()));
                    }),
            ])
            ->poll('30s');
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist->schema([
            Section::make('Exception Details')
                ->schema([
                    TextEntry::make('class_name')->label('Class'),
                    TextEntry::make('message'),
                    TextEntry::make('file')->label('File'),
                    TextEntry::make('line'),
                    TextEntry::make('group_hash')->label('Fingerprint')->copyable(),
                ]),
            Section::make('Occurrence')
                ->schema([
                    TextEntry::make('occurrence_count')->label('Total Occurrences'),
                    TextEntry::make('first_seen_at')->dateTime(),
                    TextEntry::make('last_seen_at')->dateTime(),
                    TextEntry::make('app_version')->placeholder('Unknown'),
                ]),
            Section::make('Stack Trace')
                ->schema([
                    TextEntry::make('sample_context')
                        ->label('')
                        ->formatStateUsing(function ($state) {
                            if (! is_array($state)) {
                                return 'No trace available';
                            }
                            $frames = collect($state);
                            $top = $frames->take(5);
                            $bottom = $frames->count() > 8 ? $frames->slice(-3) : collect();
                            $display = $top;
                            if ($bottom->isNotEmpty()) {
                                $display = $display->push('... ' . ($frames->count() - 8) . ' frames omitted ...');
                                $display = $display->merge($bottom);
                            }
                            return $display->map(function ($frame) {
                                if (is_string($frame)) {
                                    return $frame;
                                }
                                $file = $frame['file'] ?? '?';
                                $line = $frame['line'] ?? '?';
                                $func = $frame['function'] ?? '?';
                                return "{$file}:{$line} — {$func}()";
                            })->implode("\n");
                        })
                        ->markdown(),
                ]),
            Section::make('Resolution')
                ->schema([
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'open' => 'danger',
                            'resolved' => 'success',
                            'ignored' => 'gray',
                            default => 'gray',
                        }),
                    TextEntry::make('resolution_notes')->placeholder('No notes'),
                    TextEntry::make('resolved_in_version')->label('Resolved in Version')->placeholder('—'),
                    TextEntry::make('resolved_at')->dateTime()->placeholder('—'),
                ]),
            Section::make('Generated Test')
                ->schema([
                    TextEntry::make('generated_test')
                        ->label('')
                        ->markdown()
                        ->placeholder('No test generated'),
                ])
                ->visible(fn (FaultGroup $record) => $record->generated_test !== null),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FaultGroupResource\Pages\ListFaultGroups::route('/'),
            'view' => FaultGroupResource\Pages\ViewFaultGroup::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
