<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('updateStatus')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'shipped' => 'Shipped',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                        ])
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(2),
                ])
                ->fillForm(fn (): array => ['status' => $this->record->status])
                ->action(function (array $data): void {
                    OrderResource::applyStatusUpdate($this->record, $data['status'], $data['notes'] ?? null);
                    $this->record->refresh();
                }),
            Actions\Action::make('updatePaymentStatus')
                ->label('Payment Status')
                ->icon('heroicon-o-credit-card')
                ->form([
                    \Filament\Forms\Components\Select::make('payment_status')
                        ->options([
                            'pending' => 'Pending',
                            'completed' => 'Completed',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                ])
                ->fillForm(fn (): array => ['payment_status' => $this->record->payment_status])
                ->action(function (array $data): void {
                    $this->record->update(['payment_status' => $data['payment_status']]);
                    $this->record->refresh();
                }),
            Actions\DeleteAction::make()
                ->action(function () {
                    OrderResource::deleteOrder($this->record);
                    $this->redirect(OrderResource::getUrl('index'));
                }),
        ];
    }
}
