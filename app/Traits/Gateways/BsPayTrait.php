<?php

namespace App\Traits\Gateways;

use App\Helpers\Core;
use App\Models\AffiliateHistory;
use App\Models\AffiliateLogs;
use App\Models\AffiliateWithdraw;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Helpers\Core as Helper;
use App\Notifications\NewDepositNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


trait BsPayTrait
{
    protected static string $uriBsPay;
    protected static string $clienteIdBsPay;
    protected static string $clienteSecretBsPay;

    private static function generateCredentialsBsPay()
    {
        $setting = Gateway::first();
        if (!empty($setting)) {
            self::$uriBsPay = $setting->getAttributes()['bspay_uri'];
            self::$clienteIdBsPay = $setting->getAttributes()['bspay_cliente_id'];
            self::$clienteSecretBsPay = $setting->getAttributes()['bspay_cliente_secret'];
        }
    }
    private static function getTokenBsPay()
    {
        $string = self::$clienteIdBsPay . ":" . self::$clienteSecretBsPay;
        $basic = base64_encode($string);
        $response = Http::asMultipart()
            ->withHeaders([
                'Authorization' => 'Basic ' . $basic,
            ])
            ->post(self::$uriBsPay . 'oauth/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->successful()) {
            $responseData = $response->json();
            if (isset($responseData['access_token'])) {
                return ['error' => '', 'acessToken' => $responseData['access_token']];
            } else {
                return ['error' => 'Internal Server Error', 'acessToken' => ""];
            }
        } else {
            return ['error' => $response->status() . $response->body(), 'acessToken' => ""];
        }
    }

    public function requestQrcodeBsPay($request)
    {
        try {
            $setting = Core::getSetting();
            $rules = [
                'amount' => ['required', 'numeric', 'min:' . $setting->min_deposit, 'max:' . $setting->max_deposit],
                'cpf'    => ['required', 'string', 'max:255'],
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
            self::generateCredentialsBsPay();
            $token = self::getTokenBsPay();
            if ($token['error'] != "") {
                return response()->json(['error' => "Peça para o banco liberar o Pix de cash in e cash out"], 500);
            }
            $idUnico = uniqid();


            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ] 
            ])->withHeaders([
                "Authorization" => "Bearer " . $token['acessToken']
            ])->post(self::$uriBsPay . 'pix/qrcode', [
                "payerQuestion " => "Depósito via PIX",
                "external_id" => $idUnico,
                "postbackUrl" => url('/bspay/callback', [], true),
                "payer" => [
                    'document' => \Helper::soNumero($request->cpf),
                    'name' => auth('api')->user()->name,
                    'email' => auth('api')->user()->email
                ],
                "amount" => (float) $request->input("amount")

            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $token = self::generateTransactionBsPay(
                    $responseData['transactionId'],
                    $request->input("amount"),
                    $idUnico,
                    $request->input('accept_bonus') ?? false
                );
                self::generateDepositBsPay($responseData['transactionId'], $request->input("amount"));
                return response()->json(['status' => true, 'idTransaction' => $responseData['transactionId'], 'qrcode' => $responseData['qrcode'],'token' => $token]);
            }
            return response()->json(['error' => "Ocorreu uma falha ao entrar em contato com o banco."], 500);
        } catch (Exception $e) {
            Log::info($e);
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }
    


    private static function pixCashOutBsPay($id, $tipo)
    {
        Log::info('Iniciando pixCashOutBsPay', [
            'withdrawal_id' => $id,
            'tipo' => $tipo
        ]);
    
        $withdrawal = Withdrawal::find($id);
        if ($tipo == "afiliado") {
            $withdrawal = AffiliateWithdraw::find($id);
        }
    
        if (!$withdrawal) {
            Log::error('Withdrawal não encontrado', ['withdrawal_id' => $id]);
            return false;
        }
    
        self::generateCredentialsBsPay();
    
        Log::info('Credenciais BsPay utilizadas', [
            'uri' => self::$uriBsPay,
            'cliente_id' => self::$clienteIdBsPay,
            'cliente_secret' => substr(self::$clienteSecretBsPay, 0, 4) . '********' // opcionalmente mascarar
        ]);
    
        $token = self::getTokenBsPay();
        if ($token['error'] != "") {
            Log::error('Erro ao obter token BsPay', ['erro' => $token['error']]);
            return false;
        }
    
        Log::info('Token BsPay obtido com sucesso', [
            'token' => substr($token['acessToken'], 0, 10) . '...' // não logar o token inteiro por segurança
        ]);
    
        $idUnico = uniqid();
        $key = null;
        $tipoKey = null;
    
        $user = User::find($withdrawal->user_id);
        if (!$user) {
            Log::error('Usuário não encontrado para o saque', ['user_id' => $withdrawal->user_id]);
            return false;
        }
    
        switch ($withdrawal->pix_type) {
            case 'document':
                $pixKeySemPontuacao = preg_replace('/[^0-9]/', '', $withdrawal->pix_key);
                if (strlen($pixKeySemPontuacao) == 11) {
                    $tipoKey = "CPF";
                } else {
                    $tipoKey = "CNPJ";
                }
                $key = $pixKeySemPontuacao;
                break;
            case 'phoneNumber':
                $key = "+55" . $withdrawal->pix_key;
                $tipoKey = "TELEFONE";
                break;
            case 'email':
                $key = $withdrawal->pix_key;
                $tipoKey = "EMAIL";
                break;
            case 'randomKey':
                $key = $withdrawal->pix_key;
                $tipoKey = "CHAVE_ALEATORIA";
                break;
        }
    
        Log::info('Dados para saque montados', [
            'amount' => $withdrawal->amount,
            'cpf' => $withdrawal->cpf,
            'name' => $user->name,
            'pix_type' => "CPF",
            'pix_key' => $key
        ]);
    
        try {
            $endpoint = self::$uriBsPay . 'pix/payment';
            Log::info('Endpoint BsPay chamado', ['endpoint' => $endpoint]);
            
            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]
            ])->withHeaders([
                "Authorization" => "Bearer " . $token['acessToken']
            ])->post($endpoint, [
                "amount" => $withdrawal->amount,
                "external_id" => $idUnico,
                "description" => "Solicitação de saque",
                "creditParty" => [
                    'taxId' => $withdrawal->cpf,
                    'name' => $withdrawal->name,
                    'keyType' => "CPF",
                    "key" => $key
                ],
            ]);
    
            Log::info('Resposta BsPay recebida', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
    
            if ($response->successful()) {
                $responseData = $response->json();
                $withdrawal->update(['status' => 1]);
                Log::info('Saque BsPay efetuado com sucesso', ['withdrawal_id' => $id]);
                return true;
            } else {
                Log::error('Falha na solicitação de saque para BsPay', [
                    'withdrawal_id' => $id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exceção ao tentar saque BsPay', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    
  
    private static function webhookBsPay(Request $request)
    {
        $requestBody = $request->input("requestBody");
        $idTransaction = $requestBody['transactionId'];
        $transaction = Transaction::where('payment_id', $idTransaction)->where('status', 0)->first();
        if ($transaction != null && $transaction->idUnico == $requestBody['external_id']) {
            $payment = self::finalizePaymentBsPay($request);
            if ($payment == true) {
                return response()->json([], 200);
            } else {
                return response()->json([], 500);
            }
        }
        return response()->json([], 401);
    }

    private static function generateDepositBsPay($idTransaction, $amount)
    {
        $userId = auth('api')->user()->id;
        $wallet = Wallet::where('user_id', $userId)->first();

        Deposit::create([
            'payment_id' => $idTransaction,
            'user_id'   => $userId,
            'amount'    => $amount,
            'type'      => 'pix',
            'currency'  => $wallet->currency,
            'symbol'    => $wallet->symbol,
            'status'    => 0
        ]);
    }

    private static function generateTransactionBsPay($idTransaction, $amount, $id, $accept_bonus = false)
    {
        $setting = Core::getSetting();
        $token = bin2hex(random_bytes(16)); // token seguro
    
        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id' => auth('api')->user()->id,
            'payment_method' => 'pix',
            'price' => $amount,
            'currency' => $setting->currency_code,
            'status' => 0,
            'idUnico' => $id,
            'accept_bonus' => $accept_bonus,
            'token' => $token, // token seguro
        ]);
    
        return $token;
    }
    

    public static function finalizePaymentBsPay($idTransaction): bool
    {
        $transaction = Transaction::where('payment_id', $idTransaction)->where('status', 0)->first();
        $setting = Helper::getSetting();

        if (!empty($transaction)) {
            $user = User::find($transaction->user_id);
            $wallet = Wallet::where('user_id', $transaction->user_id)->first();

            if (!empty($wallet)) {
                // Verifica se é o primeiro depósito
                $checkTransactions = Transaction::where('user_id', $transaction->user_id)
                    ->where('status', 1)
                    ->count();

                if ($checkTransactions == 0 || empty($checkTransactions)) {
                    if ($transaction->accept_bonus) {
                        // Pagar o bônus de primeiro depósito
                        $bonus = Helper::porcentagem_xn($setting->initial_bonus, $transaction->price);
                        $wallet->increment('balance_bonus', $bonus);

                        if (!$setting->disable_rollover) {
                            $wallet->update(['balance_bonus_rollover' => $bonus * $setting->rollover]);
                        }
                    }
                }

                // Rollover do depósito
                if (!$setting->disable_rollover) {
                    $wallet->increment('balance_deposit_rollover', ($transaction->price * intval($setting->rollover_deposit)));
                }

                // Acumular bônus
                Helper::payBonusVip($wallet, $transaction->price);

                // Dinheiro direto para carteira de saque ou jogos
                if ($setting->disable_rollover) {
                    $wallet->increment('balance_withdrawal', $transaction->price);
                } else {
                    $wallet->increment('balance', $transaction->price);
                }

                if ($transaction->update(['status' => 1])) {
                    $deposit = Deposit::where('payment_id', $idTransaction)->where('status', 0)->first();

                    if (!empty($deposit)) {
                        // CPA para primeiro depósito
                        $affHistoryCPA = AffiliateHistory::where('user_id', $user->id)
                            ->where('commission_type', 'cpa')
                            ->first();

                        if (!empty($affHistoryCPA)) {
                            $affHistoryCPA->increment('deposited_amount', $transaction->price);

                            $sponsorCpa = User::find($user->inviter);
                            if (!empty($sponsorCpa) && $affHistoryCPA->status == 'pendente') {
                                if ($affHistoryCPA->deposited_amount >= $sponsorCpa->affiliate_baseline || $deposit->amount >= $sponsorCpa->affiliate_baseline) {
                                    $walletCpa = Wallet::where('user_id', $affHistoryCPA->inviter)->first();
                                    if (!empty($walletCpa)) {
                                        $walletCpa->increment('refer_rewards', $sponsorCpa->affiliate_cpa);
                                        $affHistoryCPA->update(['status' => 1, 'commission_paid' => $sponsorCpa->affiliate_cpa]);
                                    }
                                }
                            }
                        }

                        // Comissão percentual para níveis 1 e 2
                        if ($transaction->price >= $setting->cpa_percentage_baseline) {
                            $inviterN1 = User::find($user->inviter);

                            if (!empty($inviterN1)) {
                                $commissionN1 = $transaction->price * ($setting->cpa_percentage_n1 / 100);
                                $walletN1 = Wallet::where('user_id', $inviterN1->id)->first();
                                if (!empty($walletN1)) {
                                    $walletN1->increment('refer_rewards', $commissionN1);
                                }

                                // Nível 2
                                $inviterN2 = User::find($inviterN1->inviter);
                                if (!empty($inviterN2)) {
                                    $commissionN2 = $transaction->price * ($setting->cpa_percentage_n2 / 100);
                                    $walletN2 = Wallet::where('user_id', $inviterN2->id)->first();
                                    if (!empty($walletN2)) {
                                        $walletN2->increment('refer_rewards', $commissionN2);
                                    }
                                }
                            }
                        }

                        if ($deposit->update(['status' => 1])) {
                            $admins = User::where('role_id', 0)->get();
                            foreach ($admins as $admin) {
                                $admin->notify(new NewDepositNotification($user->name, $transaction->price));
                            }

                            return true;
                        }
                        return false;
                    }
                    return false;
                }
                return false;
            }
            return false;
        }
        return false;
    }


}
