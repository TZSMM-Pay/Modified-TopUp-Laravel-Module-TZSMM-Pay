<?php

namespace App\Filament\Pages;

use App\Constants\TopupProvider;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Artisan;
use Webbingbrasil\FilamentCopyActions\Forms\Actions\CopyAction;

class Settings extends SettingsPage
{
    protected static ?string $navigationLabel = 'System Settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationGroup = 'Settings';

    protected static string $settings = GeneralSettings::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['google_callback'] = config('services.google.redirect');
        $data['cron_job'] = "curl -s " . route('cron') . " >/dev/null 2>&1";

        return $data;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('site_name')
                                            ->label('Site Name')
                                            ->required(),
                                        TextInput::make('site_title')
                                            ->label('Site Title')
                                            ->required(),
                                        TextInput::make('home_title')
                                            ->label('Home Page Title')
                                            ->required(),
                                        TextInput::make('paginate_per_page')
                                            ->label('Paginate Per Page')
                                            ->numeric()
                                            ->required(),
                                        TextInput::make('base_currency')
                                            ->label('Currency Code')
                                            ->required(),
                                        TextInput::make('currency_symbol')
                                            ->label('Currency Symbol')
                                            ->required(),
                                        FileUpload::make('logo')
                                            ->maxSize(10240)
                                            ->image()
                                            ->directory('settings')
                                            ->moveFiles(),
                                        FileUpload::make('favicon')
                                            ->maxSize(10240)
                                            ->image()
                                            ->directory('settings')
                                            ->moveFiles(),
                                        TextInput::make('google_client_id')
                                            ->label('Google Client Id'),
                                        TextInput::make('google_client_secret')
                                            ->label('Google Client Secret'),
                                        TextInput::make('google_callback')
                                            ->label('Google Callback')
                                            ->suffixAction(CopyAction::make())
                                            ->readOnly(),
                                        TextInput::make('cron_job')
                                            ->label('Cron Job Command')
                                            ->suffixAction(CopyAction::make())
                                            ->readOnly(),
                                        TextInput::make('support_time')
                                            ->label('Support Time'),
                                        Textarea::make('header_tags')
                                            ->label('Header Tags')
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Enter Header tags, custom JavaScript, or CSS for personalized header options (use <script> or <style> tags)')
                                            ->maxLength(10000)
                                            ->rows(6)
                                            ->columnSpanFull(),
                                        Textarea::make('footer_js')
                                            ->label('Custom JS')
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Enter JavaScript code to be globally available on all pages (use <script> tags).')
                                            ->maxLength(10000)
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])->columns(2),
                                Section::make()
                                    ->schema([
                                        Toggle::make('wallet')
                                            ->label('Enable User Wallet'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Mail')
                            ->icon('heroicon-m-envelope')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('smtp_from_address')
                                            ->label('SMTP From Address')
                                            ->columnSpanFull(),
                                        TextInput::make('smtp_host')
                                            ->label('SMTP Hostname'),
                                        TextInput::make('smtp_port')
                                            ->label('SMTP Port'),
                                        TextInput::make('smtp_username')
                                            ->label('SMTP Username'),
                                        TextInput::make('smtp_password')
                                            ->label('SMTP Password'),
                                        TextInput::make('telegram_bot_token')
                                            ->label('Telegraph Bot Token'),
                                        TextInput::make('telegram_chat_id')
                                            ->label('Telegram Chat ID'),

                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Payment Gateway')
->icon('heroicon-m-credit-card')
->schema([
    Section::make()
        ->schema([
            TextInput::make('tzsmmpay_api_key')
                ->label('TZSMM Pay API Key')
                ->required(),
            TextInput::make('tzsmmpay_min_amount')
                ->label('Min Amount')
                ->required(),
            TextInput::make('tzsmmpay_max_amount')
                ->label('Max Amount')
                ->required(),

        ])->columns(2),
]),
                        Tabs\Tab::make('Social Links')
                            ->icon('heroicon-m-link')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('facebook_link')
                                            ->label('Facebook Page URL'),
                                        TextInput::make('messenger_link')
                                            ->label('Messenger URL'),
                                        TextInput::make('youtube_link')
                                            ->label('YouTube Channel URL'),
                                        TextInput::make('email_address')
                                            ->label('Email Address')
                                            ->email(),
                                        TextInput::make('whatsapp_number')
                                            ->label('WhatsApp Number'),
                                        TextInput::make('support_number')
                                            ->label('Support Number'),
                                        TextInput::make('tutorial_video_link')
                                            ->label('Tutorial Video Link'),
                                        TextInput::make('add_money_video_link')
                                            ->label('Add Money Video Link'),
                                        TextInput::make('backup_code_video_link')
                                            ->label('Backup Code Video Link'),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Theme')
                            ->icon('heroicon-m-paint-brush')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        ColorPicker::make('theme_color')
                                            ->label('Theme Color'),
                                        ColorPicker::make('logo_color')
                                            ->label('Logo Color'),
                                        ColorPicker::make('background_color')
                                            ->label('Background Color'),
                                        ColorPicker::make('font_color')
                                            ->label('Font Color'),
                                        ColorPicker::make('navigation_background_color')
                                            ->label('Navigation Background Color'),
                                        ColorPicker::make('navigation_font_color')
                                            ->label('Navigation Font Color'),
                                        ColorPicker::make('footer_color')
                                            ->label('Footer Color'),
                                        ColorPicker::make('footer_font_color')
                                            ->label('Footer Font Color'),
                                        ColorPicker::make('content_box_color')
                                            ->label('Content Box Color'),
                                        ColorPicker::make('notice_background_color')
                                            ->label('Notice Background Color'),
                                        ColorPicker::make('notice_font_color')
                                            ->label('Notice Font Color'),
                                    ])->columns(2),
                                Section::make()
                                    ->schema([
                                        Toggle::make('background_image')
                                            ->label('Enable Background Image'),
                                        Toggle::make('footer_menu')
                                            ->label('Enable Footer Menu'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('SEO')
                            ->icon('heroicon-m-bolt')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('seo_description')
                                            ->label('Description')
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Optimize your SEO with a captivating website description.')
                                            ->maxLength(10000)
                                            ->rows(6)
                                            ->columnSpanFull(),
                                        TagsInput::make('seo_keywords')
                                            ->label('Keywords')
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Enter relevant keywords separated by commas.')
                                            ->separator(',')
                                            ->splitKeys(['Tab', ','])
                                            ->columnSpanFull(),
                                        FileUpload::make('fb_og_image')
                                            ->label('Facebook OG Image')
                                            ->maxSize(10240)
                                            ->image()
                                            ->directory('settings')
                                            ->moveFiles(),
                                        FileUpload::make('twitter_og_image')
                                            ->label('Twitter OG Image')
                                            ->maxSize(10240)
                                            ->image()
                                            ->directory('settings')
                                            ->moveFiles(),
                                    ])->columns(2),
                            ]),
                        Tabs\Tab::make('Notice')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Toggle::make('enable_notice')
                                            ->label('Enable')
                                            ->live()
                                            ->columnSpanFull(),

                                        TextInput::make('notice_title')
                                            ->label('Title')
                                            ->hidden(fn(Get $get): bool => !$get('enable_notice')),
                                        Textarea::make('notice_content')
                                            ->label('Description')
                                            ->maxLength(10000)
                                            ->rows(6)
                                            ->hidden(fn(Get $get): bool => !$get('enable_notice'))
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('PWA')
                            ->icon('heroicon-m-device-phone-mobile')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Toggle::make('enable_pwa')
                                            ->label('Enable')
                                            ->live()
                                            ->columnSpanFull(),
                                        FileUpload::make('pwa_icon')
                                            ->label('PWA Icon')
                                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Resulation must be 512x512 pixels')
                                            ->maxSize(10240)
                                            ->image()
                                            ->directory('settings')
                                            ->moveFiles()
                                            ->hidden(fn(Get $get): bool => !$get('enable_pwa')),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Topup Server')
                            ->icon('heroicon-m-server')
                            ->visible(fn(): bool => gs()->enable_auto_topup)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('topup_provider')
                                            ->label('Topup Provider')
                                            ->options(TopupProvider::OPTIONS)
                                            ->columnSpanFull(),
                                        TextInput::make('free_fire_server_url')
                                            ->label('Server URL')
                                            ->hidden(fn(Get $get): bool => $get('provider') === TopupProvider::FREEFIRE),
                                        TextInput::make('free_fire_server_api_key')
                                            ->label('API KEY')
                                            ->hidden(fn(Get $get): bool => $get('provider') === TopupProvider::FREEFIRE),
                                    ])->columns(2),
                            ]),

                        // ...
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getState();
        setEnvValues([
            'APP_NAME'             => $data['site_name'],
            'GOOGLE_CLIENT_ID'     => $data['google_client_id'],
            'GOOGLE_CLIENT_SECRET' => $data['google_client_secret'],
            'MAIL_FROM_ADDRESS'    => $data['smtp_from_address'],
            'MAIL_HOST'            => $data['smtp_host'],
            'MAIL_PORT'            => $data['smtp_port'],
            'MAIL_USERNAME'        => $data['smtp_username'],
            'MAIL_PASSWORD'        => $data['smtp_password'],
        ]);
    }

    protected function afterSave(): void
    {
        try {
            if (config('app.url') !== request()->root()) {
                setEnvValue('APP_URL', request()->root());
            }
        } catch (\Exception $e) {
        }

        try {
            $output = Artisan::call('config:clear');
        } catch (\Exception $e) {
        }
    }
}
