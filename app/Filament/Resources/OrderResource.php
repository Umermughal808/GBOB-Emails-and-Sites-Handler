<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Models\Order;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Wizard;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;
use App\Filament\Resources\OrderResource\Pages;
use Filament\Tables\Columns\Summarizers\Average;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = '🚀 Order Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'order_number';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $modelLabel = 'Order';
    protected static ?string $pluralModelLabel = 'Orders';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() > 10 ? 'success' : 'primary';
    }

    public static function form(Form $form): Form
{
    return $form->schema([
        // Section 1: Basic Order Information
        Forms\Components\Section::make('Order Details')
            ->description('Manage basic order information and status')
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Card::make()
                        ->heading('📋 Order Information')
                        ->description('Essential order details')
                        ->schema([
                            Forms\Components\TextInput::make('order_number')
                                ->hiddenOn('create')
                                ->disabled()
                                ->dehydrated()
                                ->prefixIcon('heroicon-o-hashtag')
                                ->placeholder('Auto-generated')
                                ->helperText('System generated order number'),
                            
                            Forms\Components\Select::make('order_type_id')
                                ->relationship('orderType', 'name')
                                ->searchable()
                                ->preload()
                                
                                ->prefixIcon('heroicon-o-tag')
                                ->placeholder('Select order type')
                                ->helperText('Choose the type of order you are creating'),
                            
                            Forms\Components\Select::make('site_id')
                                ->relationship('site', 'name')
                                ->searchable()
                                ->preload()
                                ->prefixIcon('heroicon-o-globe-alt')
                                ->placeholder('Select site')
                                ->helperText('Choose the site for this order')
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    $site = \App\Models\Models\Site::find($state);
                                    $set('admin_fee', $site ? $site->admin_fee : 0);
                                }),
                        ]),
                ]),
            ]),

        // Section 2: Client Information
        Forms\Components\Section::make('Client Information')
            ->description('Manage client contact details and communication')
            ->icon('heroicon-o-user-circle')
            ->collapsible()
            ->schema([
                Forms\Components\Card::make()
                    ->heading('👤 Client Contact Details')
                    ->description('Essential client information for communication')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('client_name')
                                
                                ->prefixIcon('heroicon-o-user')
                                ->placeholder('Enter client name')
                                ->maxLength(255)
                                ->live(debounce: 300)
                                ->afterStateUpdated(function ($state, $set) {
                                    // Auto-generate email suggestion if name is provided
                                    if ($state) {
                                        $suggestion = strtolower(str_replace(' ', '.', $state)) . '@example.com';
                                        $set('email_suggestion', $suggestion);
                                    }
                                })
                                ->helperText('Full name of the client'),
                            
                            Forms\Components\TextInput::make('client_email')
                                ->email()
                                ->prefixIcon('heroicon-o-envelope')
                                ->placeholder('client@example.com')
                                ->maxLength(255)
                                ->helperText('Valid email address required for communication'),
                            
                            Forms\Components\TextInput::make('client_phone')
                                ->tel()
                                ->prefixIcon('heroicon-o-phone')
                                ->placeholder('+1 (555) 123-4567')
                                ->maxLength(20)
                                ->helperText('Include country code for international numbers'),
                        ]),
                    ]),
            ]),

        // Section 3: Content Details
        Forms\Components\Section::make('Content Details')
            ->description('Article and content information management')
            ->icon('heroicon-o-document')
            ->collapsible()
            ->schema([
                Forms\Components\Card::make()
                    ->heading('📄 Article & Content Information')
                    ->description('Manage content details and publication status')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('article_name')
                                ->label('Article Title')
                                ->prefixIcon('heroicon-o-document-text')
                                ->placeholder('Article title or document name')
                                ->maxLength(500)
                                ->columnSpanFull()
                                ->helperText('Descriptive title for the article or content'),
                            
                            Forms\Components\TextInput::make('post_url')
                                ->url()
                                ->prefixIcon('heroicon-o-link')
                                ->placeholder('https://example.com/article')
                                ->helperText('URL where the content will be published'),
                            
                            Forms\Components\Select::make('live_link_status')
                                ->options([
                                    'pending' => '⏳ Pending Review',
                                    'rejected' => '❌ Rejected',
                                    'live' => '🟢 Live & Published',
                                ])
                                ->default('pending')
                                ->prefixIcon('heroicon-o-signal')
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    if ($state !== 'live') {
                                        $set('live_link_url', null);
                                    }
                                })
                                ->helperText('Current publication status'),
                        ]),
                        
                        Forms\Components\TextInput::make('live_link_url')
                            ->url()
                            ->visible(fn ($get) => $get('live_link_status') === 'live')
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->placeholder('https://live-site.com/published-article')
                            ->helperText('Live URL of the published content')
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->rows(4)
                            ->placeholder('Add any special instructions, requirements, or notes for this order...')
                            ->helperText('Internal notes and special requirements')
                            ->columnSpanFull(),
                    ]),
            ]),

        // Section 4: Financial Information
        Forms\Components\Section::make('Financial Details')
            ->description('Pricing, payment information and profit calculations')
            ->icon('heroicon-o-currency-dollar')
            ->collapsible()
            ->schema([
                Forms\Components\Card::make()
                    ->heading('💰 Financial Breakdown')
                    ->description('Manage pricing and calculate profits')
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\TextInput::make('client_price')
                                ->label('Client Fee ($)')
                                ->numeric()                               
                                ->live(debounce: 300)
                                ->afterStateUpdated(function ($state, $set, $get) {
                                    $clientPrice = (float) ($state ?? 0);
                                    $adminFee = (float) ($get('admin_fee') ?? 0);
                                    $netProfit = $clientPrice - $adminFee;
                                    $set('net_profit', number_format($netProfit, 2, '.', ''));
                                })
                                ->helperText('Amount charged to client'),
                            
                            Forms\Components\TextInput::make('admin_fee')
    ->label('Admin Fee (from Site)')
    ->numeric()
    ->prefix('$')
    ->disabled()
    ->dehydrated()
    ->columnSpan(1),
                            
                            Forms\Components\TextInput::make('net_profit')
                                ->label('Net Profit ($)')
                                ->numeric()
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Calculated automatically')
                                ->suffixIcon('heroicon-o-chart-bar')
                                ->extraAttributes([
                                    'style' => 'font-weight: bold; color: #059669;'
                                ]),
                            
                            Forms\Components\Select::make('invoice_status')
                                ->label('Payment Status')
                                ->options([
                                    'unpaid' => '❌ Unpaid',
                                    'partial' => '⏳ Partially Paid',
                                    'paid' => '✅ Fully Paid',
                                ])
                                ->default('unpaid')
                                
                                ->prefixIcon('heroicon-o-credit-card')
                                ->helperText('Current payment status'),
                        ]),
                    ]),
            ]),

        // Section 5: Client Invoice Management
        Forms\Components\Section::make('Client Invoice Management')
            ->description('Upload and manage client invoices')
            ->icon('heroicon-o-document-arrow-up')
            ->collapsible()
            ->schema([
                Forms\Components\Card::make()
                    ->heading('📄 Client Invoice')
                    ->description('Invoice sent to the client')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('client_invoice_url')
                                ->label('Invoice URL')
                                ->url()
                                
                                ->prefixIcon('heroicon-o-link')
                                ->placeholder('https://invoice-platform.com/invoice/12345')
                                ->helperText('Direct link to the client invoice')
                                ->columnSpanFull(),
                            
                            Forms\Components\FileUpload::make('client_invoice_file')
                                ->label('Invoice Document')
                                ->downloadable()
                                ->openable()
                                ->directory('client-invoices')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(5120)
                                ->helperText('Upload PDF or image file (Max: 5MB)')
                                ->columnSpan(1),
                            
                            Forms\Components\FileUpload::make('client_invoice_picture')
                                ->label('Invoice Screenshot')
                                ->image()
                                ->imageEditor()
                                ->directory('client-invoice-pictures')
                                ->preserveFilenames()
                                ->maxSize(2048)
                                ->helperText('Screenshot or photo of the invoice')
                                ->columnSpan(1),
                        ]),
                    ]),
            ]),

        // Section 6: Admin Invoice Management
        Forms\Components\Section::make('Admin Invoice Management')
            ->description('Upload and manage administrative invoices')
            ->icon('heroicon-o-cog-6-tooth')
            ->collapsible()
            ->schema([
                Forms\Components\Card::make()
                    ->heading('⚙️ Admin Invoice')
                    ->description('Internal administrative invoice')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('admin_invoice_url')
                                ->label('Admin Invoice URL')
                                ->url()
                                ->prefixIcon('heroicon-o-link')
                                ->placeholder('https://admin-system.com/invoice/12345')
                                ->helperText('Link to admin invoice (optional)')
                                ->columnSpanFull(),
                            
                            Forms\Components\FileUpload::make('admin_invoice_file')
                                ->label('Admin Invoice Document')
                                ->downloadable()
                                ->openable()
                                ->directory('admin-invoices')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->maxSize(5120)
                                ->helperText('Upload PDF or image file (Max: 5MB)')
                                ->columnSpan(1),
                            
                            Forms\Components\FileUpload::make('admin_invoice_picture')
                                ->label('Admin Invoice Screenshot')
                                ->image()
                                ->imageEditor()
                                ->directory('admin-invoice-pictures')
                                ->preserveFilenames()
                                ->maxSize(2048)
                                ->helperText('Screenshot of admin invoice')
                                ->columnSpan(1),
                        ]),
                    ]),
            ]),
    ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-hashtag')
                    ->tooltip('Click to copy order number'),
                
                Tables\Columns\TextColumn::make('site.admin_fee')
                    ->label('Admin Fee')
                    ->money('usd', true)
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user')
                    ->description(fn ($record) => $record->client_email)
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->client_name),
                
                Tables\Columns\TextColumn::make('orderType.name')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-tag')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->icon('heroicon-o-globe-alt')
                    ->limit(15)
                    ->tooltip(fn ($record) => $record->site?->name),
                
                Tables\Columns\TextColumn::make('client_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->weight(FontWeight::Bold),
                
                Tables\Columns\TextColumn::make('net_profit')
                    ->label('Profit')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->icon('heroicon-o-chart-bar')
                    ->color(fn ($record) => $record->net_profit >= 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold),
                
                Tables\Columns\TextColumn::make('invoice_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'paid' => 'heroicon-o-check-circle',
                        'partial' => 'heroicon-o-clock',
                        'unpaid' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'info',
                        'draft' => 'gray',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'in_progress' => 'heroicon-o-arrow-path',
                        'draft' => 'heroicon-o-document',
                        'rejected' => 'heroicon-o-x-circle',
                        'cancelled' => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completed',
                        'in_progress' => 'In Progress',
                        'draft' => 'Draft',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-calendar')
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at->format('F j, Y \a\t g:i A')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple()
                    ->indicator('Status'),
                
                Tables\Filters\SelectFilter::make('invoice_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                    ])
                    ->multiple()
                    ->indicator('Payment'),
                
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info')
                        ->icon('heroicon-o-eye'),
                    
                    Tables\Actions\EditAction::make()
                        ->color('warning')
                        ->icon('heroicon-o-pencil-square'),
                    
                    Tables\Actions\DeleteAction::make()
                        ->icon('heroicon-o-trash'),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
                
                Tables\Actions\Action::make('check_payment')
                    ->label('💳')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Check Payment Status')
                    ->color('success')
                    ->action(function ($record) {
                        try {
                            $result = $record->checkPaymentStatus();
                            
                            if ($result['success']) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Payment Status Updated!')
                                    ->body($result['message'])
                                    ->success()
                                    ->duration(5000)
                                    ->icon('heroicon-o-check-circle')
                                    ->send();
                            } else {
                                throw new \Exception($result['message']);
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Payment Check Failed ❌')
                                ->body($e->getMessage())
                                ->danger()
                                ->duration(5000)
                                ->icon('heroicon-o-exclamation-triangle')
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => !empty($record->client_invoice_path)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),
                    
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                ]);
                            });
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Orders Completed! 🎉')
                                ->body(count($records) . ' orders marked as completed')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->url(route('export.orders'))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Start by creating your first amazing order!')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('🚀 Create First Order')
                    ->icon('heroicon-o-plus'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['orderType', 'site']);
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Order Type' => $record->orderType?->name,
            'Client' => $record->client_name,
            'Status' => ucfirst(str_replace('_', ' ', $record->status)),
            'Amount' => '$' . number_format($record->client_price, 2),
        ];
    }
}