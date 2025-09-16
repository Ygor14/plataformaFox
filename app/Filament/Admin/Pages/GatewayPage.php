<?php

namespace App\Filament\Admin\Pages;

use App\Models\Gateway;
use App\Models\AproveSaveSetting; // Importação do modelo para verificação de senha
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash; // Importação para verificação da senha
use Illuminate\Support\HtmlString;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;

class GatewayPage extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.gateway-page';

    public ?array $data = [];
    public Gateway $setting;

    /**
     * @dev @anonymous
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return void
     */
    public function mount(): void
    {
        $gateway = Gateway::first();
        if (!empty($gateway)) {
            $this->setting = $gateway;
            $this->form->fill($this->setting->toArray());
        } else {
            $this->form->fill();
        }
    }

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                  
                //   Section::make('Stripe')
                //       ->description('Ajustes de credenciais para a Stripe')
                //      ->schema([
                //          TextInput::make('stripe_public_key')
                //              ->label('Chave Publica')
                //              ->placeholder('Digite a chave publica')
                //              ->maxLength(191)
                //              ->columnSpanFull(),
                //         TextInput::make('stripe_secret_key')
                //             ->label('Chave Privada')
                //             ->placeholder('Digite a chave privada')
                //             ->maxLength(191)
                //              ->columnSpanFull(),
                //          TextInput::make('stripe_webhook_key')
                //             ->label('Chave Webhook')
                //             ->placeholder('Digite a chave do webhook')
                //             ->maxLength(191)
                //              ->columnSpanFull(),
                //      ]),

                Section::make('DIGITO PAY')
                ->description(new HtmlString('
                                <div style="display: flex; align-items: center;">
                                    Precisa de uma conta na Digito Pay? Responda o formulário de contato e solicite sua conta.:
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://app.digitopayoficial.com.br/auth/login"
                                    target="_blank">
                                        Dashboard
                                    </a>
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://wa.me/554888142566"
                                    target="_blank">
                                        Gerente
                                    </a>
                                </div>
                    '),)
                    ->schema([
                        TextInput::make('digitopay_uri')
                            ->label('CLIENTE URL')
                            ->placeholder('Digite a url da api')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('digitopay_cliente_id')
                            ->label('CLIENTE ID')
                            ->placeholder('Digite o client ID')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('digitopay_cliente_secret')
                            ->label('CLIENTE SECRETO')
                            ->placeholder('Digite o client secret')
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),
                Section::make('BSPAY E PIXUP')
                ->description(new HtmlString('
                                <div style="display: flex; align-items: center;">
                                    Precisa de uma conta na Digito Pay? Responda o formulário de contato e solicite sua conta.:
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://dashboard.pixupbr.com/"
                                    target="_blank">
                                        Dashboard
                                    </a>
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://wa.me/557189320292"
                                    target="_blank">
                                        Gerente
                                    </a>
                                </div>
                    <b>Seu Webhook:  ' . url("/bspay/callback", [], true) . "</b>"))
                    ->schema([
                        TextInput::make('bspay_uri')
                            ->label('CLIENTE URL')
                            ->placeholder('Digite a url da api')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('bspay_cliente_id')
                            ->label('CLIENTE ID')
                            ->placeholder('Digite o client ID')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('bspay_cliente_secret')
                            ->label('CLIENTE SECRETO')
                            ->placeholder('Digite o client secret')
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),
                Section::make('EZZEPAY')
                                        ->description(new HtmlString('
                                <div style="display: flex; align-items: center;">
                                    Precisa de uma conta na Digito Pay? Responda o formulário de contato e solicite sua conta.:
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://app.ezzebank.com/login"
                                    target="_blank">
                                        Dashboard
                                    </a>
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://wa.me/556192262660"
                                    target="_blank">
                                        Gerente
                                    </a>
                                </div>
                    <b>Seu Webhook:  ' . url("/ezzepay/webhook", [], true) . "</b>"))
                    ->schema([
                        TextInput::make('ezze_uri')
                            ->label('CLIENTE URL')
                            ->placeholder('Digite a url da api')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('ezze_client')
                            ->label('CLIENTE ID')
                            ->placeholder('Digite o client ID')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('ezze_secret')
                            ->label('CLIENTE SECRETO')
                            ->placeholder('Digite o client secret')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('ezze_user')
                            ->label('USUARIO DO WEBHOOK')
                            ->placeholder('Digite o usuário de autenticação do webhook')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('ezze_senha')
                            ->label('SENHA DO WEBHOOK')
                            ->placeholder('Digite a senha de autenticação do webhook')
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),
                Section::make('SUITEPAY (Não Aceita Igame Fácil!)')
                    ->description(new HtmlString('
                                <div style="display: flex; align-items: center;">
                                    Precisa de uma conta na Digito Pay? Responda o formulário de contato e solicite sua conta.:
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://www.suitpay.app/"
                                    target="_blank">
                                        Dashboard
                                    </a>
                                    <a class="dark:text-white"
                                    style="
                                            font-size: 14px;
                                            font-weight: 600;
                                            width: 127px;
                                            display: flex;
                                            background-color: #f800ff;
                                            padding: 10px;
                                            border-radius: 11px;
                                            justify-content: center;
                                            margin-left: 10px;
                                    "
                                    href="https://wa.me/556299055428"
                                    target="_blank">
                                        Gerente
                                    </a>
                                </div>
                    <b>Para fazer saques libere o IP</b>'))
                    ->schema([
                        TextInput::make('suitpay_uri')
                            ->label('CLIENTE URL')
                            ->placeholder('Digite a url da api')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('suitpay_cliente_id')
                            ->label('CLIENTE ID')
                            ->placeholder('Digite o client ID')
                            ->maxLength(191)
                            ->columnSpanFull(),
                        TextInput::make('suitpay_cliente_secret')
                            ->label('CLIENTE SECRETO')
                            ->placeholder('Digite o client secret')
                            ->maxLength(191)
                            ->columnSpanFull(),
                    ]),
                
                // Seção para a senha de aprovação
                Section::make('Digite a senha de confirmação')
                    ->description('Obrigatório digitar sua senha de confirmação!')
                    ->schema([
                        TextInput::make('approval_password_save')
                            ->label('Senha de Aprovação')
                            ->password()
                            ->required()
                            ->helperText('Digite a senha para salvar as alterações.')
                            ->maxLength(191),
                    ])->columns(2),
            ])
            ->statePath('data');
    }


    /**
     * @return void
     */
    public function submit(): void
    {
        try {
            if (env('APP_DEMO')) {
                Notification::make()
                    ->title('Atenção')
                    ->body('Você não pode realizar está alteração na versão demo')
                    ->danger()
                    ->send();
                return;
            }

            // Verificação da senha
            $approvalSettings = AproveSaveSetting::first();
            $inputPassword = $this->data['approval_password_save'] ?? '';

            if (!Hash::check($inputPassword, $approvalSettings->approval_password_save)) {
                Notification::make()
                    ->title('Erro de Autenticação')
                    ->body('Senha incorreta. Por favor, tente novamente.')
                    ->danger()
                    ->send();
                return;
            }

            $setting = Gateway::first();
            if (!empty($setting)) {
                if ($setting->update($this->data)) {
                    if (!empty($this->data['stripe_public_key'])) {
                        $envs = DotenvEditor::load(base_path('.env'));

                        $envs->setKeys([
                            'STRIPE_KEY' => $this->data['stripe_public_key'],
                            'STRIPE_SECRET' => $this->data['stripe_secret_key'],
                            'STRIPE_WEBHOOK_SECRET' => $this->data['stripe_webhook_key'],
                        ]);

                        $envs->save();
                    }

                    Notification::make()
                        ->title('Chaves Alteradas')
                        ->body('Suas chaves foram alteradas com sucesso!')
                        ->success()
                        ->send();
                }
            } else {
                if (Gateway::create($this->data)) {
                    Notification::make()
                        ->title('Chaves Criadas')
                        ->body('Suas chaves foram criadas com sucesso!')
                        ->success()
                        ->send();
                }
            }

        } catch (Halt $exception) {
            Notification::make()
                ->title('Erro ao alterar dados!')
                ->body('Erro ao alterar dados!')
                ->danger()
                ->send();
        }
    }
}
