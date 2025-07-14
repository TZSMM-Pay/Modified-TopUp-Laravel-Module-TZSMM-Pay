Tabs\Tab::make('Payment Gateway')
->icon('heroicon-m-credit-card')
->schema([
    Section::make()
        ->schema([
            TextInput::make('tzsmmpay_api_key')
                ->label('TZSMM Pay API Key')
                ->required(),
            TextInput::make('uddoktapay_min_amount')
                ->label('Min Amount')
                ->required(),
            TextInput::make('uddoktapay_max_amount')
                ->label('Max Amount')
                ->required(),

        ])->columns(2),
]),
