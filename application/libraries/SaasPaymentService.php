<?php

defined('BASEPATH') or exit('No direct script access allowed');

class SaasPaymentService
{
    /** @var CI_Controller */
    protected $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
    }

    /**
     * Gera uma cobrança PIX para renovação de assinatura SaaS (+30 dias).
     *
     * Suporta:
     *  - Mercado Pago ('mercadopago')
     *  - Asaas ('asaas')
     *  - Efí antiga Gerencianet ('gerencianet')
     *  - Manual / Chave Fixa ('manual')
     *
     * @param array  $formaConfig   Configurações da forma de pagamento PIX
     * @param float  $valor         Valor final calculado da assinatura
     * @param object $empresa       Objeto da empresa assinante
     * @param object $plano         Objeto do plano
     * @param int    $assinaturaId  ID pré-criado da assinatura (opcional)
     *
     * @return array [
     *     'gateway' => string,
     *     'charge_id' => ?string,
     *     'txid' => string,
     *     'qr_code' => string,
     *     'qr_code_image' => ?string,
     * ]
     *
     * @throws \Exception Quando a API rejeita ou credenciais são inválidas
     */
    public function gerarPix(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId = 0): array
    {
        $tipoPix = $formaConfig['tipo_pix'] ?? 'dinamico';

        if ($tipoPix === 'manual') {
            return $this->gerarPixManual($formaConfig, $valor, $empresa, $plano, $assinaturaId);
        }

        $provedor = strtolower((string) ($formaConfig['provedor'] ?? 'mercadopago'));

        return match ($provedor) {
            'mercadopago' => $this->gerarPixMercadoPago($formaConfig, $valor, $empresa, $plano, $assinaturaId),
            'asaas' => $this->gerarPixAsaas($formaConfig, $valor, $empresa, $plano, $assinaturaId),
            'gerencianet' => $this->gerarPixGerencianet($formaConfig, $valor, $empresa, $plano, $assinaturaId),
            default => throw new \Exception('Provedor de pagamento PIX desconhecido: ' . $provedor),
        };
    }

    /**
     * Gera PIX via Mercado Pago.
     */
    protected function gerarPixMercadoPago(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId): array
    {
        $token = trim((string) ($formaConfig['access_token'] ?? $formaConfig['mp_access_token'] ?? ''));
        if (empty($token) && ! empty($_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN'])) {
            $token = trim((string) $_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN']);
        }
        if (empty($token)) {
            throw new \Exception('Access Token do Mercado Pago não configurado. Acesse o menu Gerenciar SaaS > Formas de Pagamento > PIX para configurar as credenciais.');
        }

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken($token);
            $client = new \MercadoPago\Client\Payment\PaymentClient();

            $minutos = max(5, min(1440, (int) ($formaConfig['expiracao_minutos'] ?? 30)));
            $expiraEm = (new \DateTime())->add(new \DateInterval('PT' . $minutos . 'M'))->format('Y-m-d\TH:i:s.000P');

            $email = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
                ? $empresa->email
                : 'contato@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');

            $nome = trim((string) ($empresa->nome_fantasia ?: $empresa->razao_social ?: 'Assinante'));
            $partesNome = explode(' ', $nome, 2);
            $primeiroNome = substr($partesNome[0], 0, 30);
            $sobrenome = isset($partesNome[1]) ? substr($partesNome[1], 0, 30) : 'Empresa';

            $doc = preg_replace('/[^0-9]/', '', (string) ($empresa->cnpj ?? ''));
            $identification = null;
            if (strlen($doc) === 14) {
                $identification = ['type' => 'CNPJ', 'number' => $doc];
            } elseif (strlen($doc) === 11) {
                $identification = ['type' => 'CPF', 'number' => $doc];
            }

            if (! $identification) {
                $usuarioId = (int) ($this->ci->session->userdata('id_admin') ?: 0);
                if ($usuarioId > 0) {
                    $u = $this->ci->db->where('idUsuarios', $usuarioId)->get('usuarios')->row();
                    if ($u && ! empty($u->cpf)) {
                        $userDoc = preg_replace('/[^0-9]/', '', (string) $u->cpf);
                        if (strlen($userDoc) === 11) {
                            $identification = ['type' => 'CPF', 'number' => $userDoc];
                        }
                    }
                    if ($email === ('contato@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br')) && $u && ! empty($u->email) && filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                        $email = $u->email;
                    }
                }
            }

            $payer = [
                'email' => $email,
                'first_name' => $primeiroNome,
                'last_name' => $sobrenome,
            ];
            if ($identification) {
                $payer['identification'] = $identification;
            }

            $paymentRequest = [
                'transaction_amount' => round((float) $valor, 2),
                'description' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                'payment_method_id' => 'pix',
                'date_of_expiration' => $expiraEm,
                'payer' => $payer,
            ];

            $payment = $client->create($paymentRequest);

            $raw = [];
            if (is_object($payment) && method_exists($payment, 'getResponse') && $payment->getResponse()) {
                $raw = $payment->getResponse()->getContent() ?? [];
                if (! is_array($raw)) {
                    $raw = [];
                }
            }

            $qrCode = $raw['point_of_interaction']['transaction_data']['qr_code']
                ?? $payment->point_of_interaction->transaction_data->qr_code
                ?? '';
            $qrImage = $raw['point_of_interaction']['transaction_data']['qr_code_base64']
                ?? $payment->point_of_interaction->transaction_data->qr_code_base64
                ?? null;
            $chargeId = (string) ($raw['id'] ?? $payment->id ?? '');

            if (empty($qrCode)) {
                throw new \Exception('O Mercado Pago não retornou o código PIX Copia e Cola. Verifique se o PIX está ativo na sua conta do Mercado Pago.');
            }

            return [
                'gateway' => 'mercadopago',
                'charge_id' => $chargeId,
                'txid' => $chargeId,
                'qr_code' => $qrCode,
                'qr_code_image' => $qrImage,
            ];
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $content = $e->getApiResponse()->getContent();
            $msg = is_array($content) ? ($content['message'] ?? '') : '';
            if (is_array($content) && ! empty($content['cause'])) {
                $causes = array_map(static fn($c) => is_array($c) ? ($c['description'] ?? '') : (string) $c, $content['cause']);
                $msg .= ' (' . implode(', ', array_filter($causes)) . ')';
            }
            log_message('error', 'SaasPaymentService [MercadoPago API Error]: ' . $msg);
            throw new \Exception('Erro na API Mercado Pago: ' . ($msg ?: $e->getMessage()));
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService [MercadoPago Error]: ' . $e->getMessage());
            throw new \Exception('Falha ao comunicar com o Mercado Pago: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Gera PIX via Asaas.
     */
    protected function gerarPixAsaas(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId): array
    {
        $apiKey = trim((string) ($formaConfig['api_key'] ?? $formaConfig['asaas_api_key'] ?? ''));
        if (empty($apiKey) && ! empty($_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY'])) {
            $apiKey = trim((string) $_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY']);
        }
        if (empty($apiKey)) {
            throw new \Exception('Chave de API do Asaas não configurada. Acesse Gerenciar SaaS > Formas de Pagamento > PIX para configurar as credenciais.');
        }

        $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['asaas_ambiente'] ?? '';
        $isSandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox'])) || str_starts_with($apiKey, '$');
        $ambiente = $isSandbox ? 'homologacao' : 'producao';

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            $asaas = new \CodePhix\Asaas\Asaas($apiKey, $ambiente);

            $doc = preg_replace('/[^0-9]/', '', (string) ($empresa->cnpj ?? ''));
            $customerId = null;

            // Busca cliente existente no Asaas
            if (! empty($doc) && (strlen($doc) === 11 || strlen($doc) === 14)) {
                try {
                    $busca = $asaas->Cliente()->getByCpfCnpj($doc);
                    if (! empty($busca->data) && is_array($busca->data) && isset($busca->data[0]->id)) {
                        $customerId = $busca->data[0]->id;
                    }
                } catch (\Throwable $t) {
                    // Segue para criação
                }
            }

            if (! $customerId && ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $busca = $asaas->Cliente()->getByEmail($empresa->email);
                    if (! empty($busca->data) && is_array($busca->data) && isset($busca->data[0]->id)) {
                        $customerId = $busca->data[0]->id;
                    }
                } catch (\Throwable $t) {
                    // Segue para criação
                }
            }

            // Se não encontrou cliente, cadastra no Asaas
            if (! $customerId) {
                $nomeCli = trim((string) ($empresa->nome_fantasia ?: $empresa->razao_social ?: 'Empresa Assinante'));
                $emailCli = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
                    ? $empresa->email
                    : 'financeiro@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');

                $dadosNovoCliente = [
                    'name' => $nomeCli,
                    'email' => $emailCli,
                ];
                if (! empty($doc) && (strlen($doc) === 11 || strlen($doc) === 14)) {
                    $dadosNovoCliente['cpfCnpj'] = $doc;
                }
                $tel = preg_replace('/[^0-9]/', '', (string) ($empresa->telefone ?? ''));
                if (! empty($tel)) {
                    $dadosNovoCliente['mobilePhone'] = $tel;
                }

                $novo = $asaas->Cliente()->create($dadosNovoCliente);
                if (! empty($novo->id)) {
                    $customerId = $novo->id;
                } elseif (! empty($novo->errors)) {
                    $erroMsg = is_array($novo->errors) ? ($novo->errors[0]->description ?? 'Erro desconhecido') : (string) $novo->errors;
                    throw new \Exception('Asaas: ' . $erroMsg);
                } else {
                    throw new \Exception('Asaas: Não foi possível registrar cliente pagador.');
                }
            }

            // Cria a cobrança PIX no Asaas
            $cobrancaData = [
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => round((float) $valor, 2),
                'dueDate' => date('Y-m-d'),
                'description' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                'externalReference' => 'ASSIN_' . ($assinaturaId ?: time()),
            ];

            $cobranca = $asaas->Cobranca()->create($cobrancaData);
            if (! empty($cobranca->errors)) {
                $erroMsg = is_array($cobranca->errors) ? ($cobranca->errors[0]->description ?? '') : (string) $cobranca->errors;
                throw new \Exception('Asaas: ' . $erroMsg);
            }
            if (empty($cobranca->id)) {
                throw new \Exception('Asaas: Resposta inválida ao criar cobrança.');
            }

            $chargeId = $cobranca->id;

            // Obtém QR Code e Copia e Cola
            $pixInfo = $asaas->Pix()->create($chargeId);
            $qrCode = $pixInfo->payload ?? '';
            $qrImage = $pixInfo->encodedImage ?? null;

            if (empty($qrCode)) {
                throw new \Exception('O Asaas não retornou o código PIX Copia e Cola.');
            }

            return [
                'gateway' => 'asaas',
                'charge_id' => $chargeId,
                'txid' => $chargeId,
                'qr_code' => $qrCode,
                'qr_code_image' => $qrImage,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService [Asaas Error]: ' . $e->getMessage());
            throw new \Exception('Falha ao comunicar com o Asaas: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Gera PIX via Efí (antiga Gerencianet).
     */
    protected function gerarPixGerencianet(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId): array
    {
        $clientId = trim((string) ($formaConfig['client_id'] ?? $formaConfig['efi_client_id'] ?? ''));
        if (empty($clientId) && ! empty($_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_ID'])) {
            $clientId = trim((string) $_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_ID']);
        }
        $clientSecret = trim((string) ($formaConfig['client_secret'] ?? $formaConfig['efi_client_secret'] ?? ''));
        if (empty($clientSecret) && ! empty($_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_SECRET'])) {
            $clientSecret = trim((string) $_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_SECRET']);
        }
        $chavePix = trim((string) ($formaConfig['chave_pix_efi'] ?? $formaConfig['efi_chave_pix'] ?? $formaConfig['chave_pix'] ?? ''));

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Credenciais da Efí (Client ID / Client Secret) não configuradas. Acesse Gerenciar SaaS > Formas de Pagamento > PIX para configurar as credenciais.');
        }
        if (empty($chavePix)) {
            throw new \Exception('Chave PIX da conta Efí não configurada. Informe a chave vinculada à sua conta Efí nas configurações do PIX.');
        }

        $certPath = trim((string) ($formaConfig['certificado'] ?? $formaConfig['efi_certificado'] ?? ''));
        if (empty($certPath) && ! empty($_ENV['PAYMENT_GATEWAYS_EFI_CERTIFICATE'])) {
            $certPath = trim((string) $_ENV['PAYMENT_GATEWAYS_EFI_CERTIFICATE']);
        }
        if (empty($certPath) || ! file_exists($certPath)) {
            throw new \Exception('Para emitir PIX via Efí, é obrigatório informar o caminho do Certificado digital (.pem ou .p12) emitido pela Efí nas configurações.');
        }

        $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['efi_ambiente'] ?? '';
        $sandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox']));

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            $options = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'certificate' => $certPath,
                'sandbox' => $sandbox,
            ];

            $efi = new \Efi\EfiPay($options);

            $minutos = max(5, min(1440, (int) ($formaConfig['expiracao_minutos'] ?? 30)));
            $body = [
                'calendario' => [
                    'expiracao' => $minutos * 60,
                ],
                'valor' => [
                    'original' => number_format((float) $valor, 2, '.', ''),
                ],
                'chave' => $chavePix,
                'solicitacaoPagador' => 'Nexus Ticket - Renovação +30 dias',
            ];

            $doc = preg_replace('/[^0-9]/', '', (string) ($empresa->cnpj ?? ''));
            if (strlen($doc) === 14) {
                $body['devedor'] = [
                    'cnpj' => $doc,
                    'nome' => substr(trim((string) ($empresa->razao_social ?: $empresa->nome_fantasia ?: 'Assinante')), 0, 80),
                ];
            } elseif (strlen($doc) === 11) {
                $body['devedor'] = [
                    'cpf' => $doc,
                    'nome' => substr(trim((string) ($empresa->nome_fantasia ?: $empresa->razao_social ?: 'Assinante')), 0, 80),
                ];
            }

            $cob = $efi->pixCreateImmediateCharge([], $body);

            if (empty($cob['loc']['id'])) {
                throw new \Exception('A Efí não retornou o identificador do Location da cobrança.');
            }

            $txid = $cob['txid'] ?? ('EFI' . date('YmdHis'));
            $locId = (int) $cob['loc']['id'];

            $qr = $efi->pixGenerateQRCode(['id' => $locId]);
            $qrCode = $qr['qrcode'] ?? '';
            $qrImage = $qr['imagemQrcode'] ?? null;

            if (empty($qrCode)) {
                throw new \Exception('A Efí não retornou o código Copia e Cola do QR Code.');
            }

            return [
                'gateway' => 'gerencianet',
                'charge_id' => (string) $txid,
                'txid' => (string) $txid,
                'qr_code' => $qrCode,
                'qr_code_image' => $qrImage,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService [Efi Error]: ' . $e->getMessage());
            throw new \Exception('Falha ao comunicar com a Efí: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Gera PIX manual usando Piggly\Pix com cálculo estrito de CRC16 do Banco Central.
     */
    protected function gerarPixManual(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId): array
    {
        $chave = trim((string) ($formaConfig['chave_pix'] ?? ''));
        if (empty($chave)) {
            throw new \Exception('Chave PIX manual não configurada. Acesse Gerenciar SaaS > Formas de Pagamento > PIX para configurar sua chave.');
        }

        $titular = substr(trim((string) ($formaConfig['titular'] ?? 'Nexus Ticket')), 0, 25);
        $cidade = substr(trim((string) ($formaConfig['cidade'] ?? 'Sao Paulo')), 0, 15);
        $tipoChave = trim((string) ($formaConfig['tipo_chave'] ?? 'aleatoria'));

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            $pigglyTipo = match ($tipoChave) {
                'cpf' => \Piggly\Pix\Parser::KEY_TYPE_CPF,
                'cnpj' => \Piggly\Pix\Parser::KEY_TYPE_CNPJ,
                'email' => \Piggly\Pix\Parser::KEY_TYPE_EMAIL,
                'telefone' => \Piggly\Pix\Parser::KEY_TYPE_PHONE,
                default => \Piggly\Pix\Parser::KEY_TYPE_RANDOM,
            };

            $txid = 'MAPOS' . date('YmdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));

            $payload = (new \Piggly\Pix\StaticPayload())
                ->setAmount(round((float) $valor, 2))
                ->setMerchantName($titular ?: 'Nexus Ticket')
                ->setMerchantCity($cidade ?: 'Sao Paulo')
                ->setPixKey($pigglyTipo, $chave)
                ->setDescription('Nexus Ticket Assinatura')
                ->setTid($txid);

            $qrCode = $payload->getPixCode();
            $qrImage = null;

            try {
                if (\Piggly\Pix\StaticPayload::supportQrCode()) {
                    $qrImage = $payload->getQRCode(\Piggly\Pix\Enums\QrCode::OUTPUT_PNG);
                }
            } catch (\Throwable $t) {
                $qrImage = null;
            }

            return [
                'gateway' => 'manual',
                'charge_id' => $txid,
                'txid' => $txid,
                'qr_code' => $qrCode,
                'qr_code_image' => $qrImage,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService [Manual Pix Error]: ' . $e->getMessage());
            throw new \Exception('Falha ao gerar QR Code PIX: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Consulta o status do pagamento diretamente na API do gateway.
     *
     * @return string 'aprovado'|'pendente'|'cancelado'
     */
    public function consultarStatusPix(object $assinatura, array $formaConfig): string
    {
        $gateway = strtolower((string) ($assinatura->gateway ?? ''));
        if (empty($gateway) || $gateway === 'manual') {
            return 'pendente';
        }

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            switch ($gateway) {
                case 'mercadopago':
                    $token = trim((string) ($formaConfig['access_token'] ?? $formaConfig['mp_access_token'] ?? ''));
                    if (empty($token) && ! empty($_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN'])) {
                        $token = trim((string) $_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN']);
                    }
                    if (empty($token) || empty($assinatura->charge_id)) {
                        return 'pendente';
                    }

                    \MercadoPago\MercadoPagoConfig::setAccessToken($token);
                    $client = new \MercadoPago\Client\Payment\PaymentClient();
                    $payment = $client->get((int) $assinatura->charge_id);

                    if (isset($payment->status)) {
                        if ($payment->status === 'approved') {
                            return 'aprovado';
                        }
                        if (in_array($payment->status, ['cancelled', 'rejected', 'refunded', 'charged_back'], true)) {
                            return 'cancelado';
                        }
                    }

                    return 'pendente';

                case 'asaas':
                    $apiKey = trim((string) ($formaConfig['api_key'] ?? $formaConfig['asaas_api_key'] ?? ''));
                    if (empty($apiKey) && ! empty($_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY'])) {
                        $apiKey = trim((string) $_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY']);
                    }
                    if (empty($apiKey) || empty($assinatura->charge_id)) {
                        return 'pendente';
                    }

                    $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['asaas_ambiente'] ?? '';
                    $isSandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox'])) || str_starts_with($apiKey, '$');
                    $ambiente = $isSandbox ? 'homologacao' : 'producao';

                    $asaas = new \CodePhix\Asaas\Asaas($apiKey, $ambiente);
                    $cobranca = $asaas->Cobranca()->getById($assinatura->charge_id);

                    if (isset($cobranca->status)) {
                        $status = strtoupper((string) $cobranca->status);
                        if (in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
                            return 'aprovado';
                        }
                        if (in_array($status, ['OVERDUE', 'CANCELLED', 'REFUNDED', 'DELETED'], true)) {
                            return 'cancelado';
                        }
                    }

                    return 'pendente';

                case 'gerencianet':
                    $clientId = trim((string) ($formaConfig['client_id'] ?? $formaConfig['efi_client_id'] ?? ''));
                    $clientSecret = trim((string) ($formaConfig['client_secret'] ?? $formaConfig['efi_client_secret'] ?? ''));
                    if (empty($clientId) || empty($clientSecret)) {
                        return 'pendente';
                    }

                    $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['efi_ambiente'] ?? '';
                    $sandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox']));
                    $isCartao = strtoupper((string) ($assinatura->forma_pagamento ?? '')) === 'CARTAO_CREDITO';
                    $certPath = trim((string) ($formaConfig['certificado'] ?? $formaConfig['efi_certificado'] ?? ''));

                    if (! $isCartao && (empty($certPath) || ! file_exists($certPath))) {
                        return 'pendente';
                    }

                    $options = [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'sandbox' => $sandbox,
                    ];
                    if (! empty($certPath) && file_exists($certPath)) {
                        $options['certificate' ] = $certPath;
                    }

                    $efi = new \Efi\EfiPay($options);

                    if ($isCartao) {
                        $chargeId = (int) ($assinatura->charge_id ?: $assinatura->txid);
                        $cob = $efi->detailCharge(['id' => $chargeId]);
                        if (isset($cob['data']['status'])) {
                            $st = strtolower((string) $cob['data']['status']);
                            if ($st === 'paid' || $st === 'settled') {
                                return 'aprovado';
                            }
                            if (in_array($st, ['canceled', 'unpaid'], true)) {
                                return 'cancelado';
                            }
                        }
                    } else {
                        $cob = $efi->pixDetailCharge(['txid' => $assinatura->txid ?: $assinatura->charge_id]);
                        if (isset($cob['status'])) {
                            $status = strtoupper((string) $cob['status']);
                            if ($status === 'CONCLUIDA') {
                                return 'aprovado';
                            }
                            if (in_array($status, ['REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP'], true)) {
                                return 'cancelado';
                            }
                        }
                    }

                    return 'pendente';

                default:
                    return 'pendente';
            }
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService::consultarStatusPix exception: ' . $e->getMessage());

            return 'pendente';
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Processa pagamento de Cartão de Crédito (+30 dias).
     *
     * @param array  $formaConfig    Configurações da forma de pagamento
     * @param float  $valor          Valor final calculado
     * @param object $empresa        Empresa assinante
     * @param object $plano          Plano assinado
     * @param array  $dadosPagamento Dados enviados pelo formulário (token, doc, parcelas)
     * @param int    $assinaturaId   ID da assinatura criada
     *
     * @return array ['status' => 'aprovado'|'pendente', 'charge_id' => string, 'gateway' => string, 'mensagem' => string]
     * @throws \Exception Quando a operadora rejeita ou há erro nas credenciais
     */
    public function processarCartaoCredito(array $formaConfig, float $valor, object $empresa, object $plano, array $dadosPagamento, int $assinaturaId = 0): array
    {
        $provedor = strtolower((string) ($formaConfig['provedor'] ?? 'mercadopago'));

        return match ($provedor) {
            'mercadopago' => $this->processarCartaoCreditoMercadoPago($formaConfig, $valor, $empresa, $plano, $dadosPagamento, $assinaturaId),
            'gerencianet' => $this->processarCartaoCreditoGerencianet($formaConfig, $valor, $empresa, $plano, $dadosPagamento, $assinaturaId),
            'asaas' => $this->processarCartaoCreditoAsaas($formaConfig, $valor, $empresa, $plano, $dadosPagamento, $assinaturaId),
            default => throw new \Exception('Provedor não suportado para cartão de crédito: ' . $provedor),
        };
    }

    /**
     * Processa cartão de crédito via Mercado Pago.
     */
    protected function processarCartaoCreditoMercadoPago(array $formaConfig, float $valor, object $empresa, object $plano, array $dadosPagamento, int $assinaturaId): array
    {
        $token = trim((string) ($formaConfig['access_token'] ?? $formaConfig['mp_access_token'] ?? ''));
        if (empty($token) && ! empty($_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN'])) {
            $token = trim((string) $_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN']);
        }
        if (empty($token)) {
            throw new \Exception('Access Token do Mercado Pago não configurado. Acesse Gerenciar SaaS > Formas de Pagamento > Cartão de Crédito.');
        }

        $cardToken = trim((string) ($dadosPagamento['token'] ?? ''));
        if (empty($cardToken)) {
            throw new \Exception('Token de segurança do cartão não gerado no navegador. Revise os dados digitados.');
        }

        $installments = max(1, min(12, (int) ($dadosPagamento['parcelas'] ?? 1)));

        $email = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
            ? $empresa->email
            : 'contato@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');

        $nome = trim((string) ($dadosPagamento['titular'] ?? $empresa->nome_fantasia ?: $empresa->razao_social ?: 'Assinante'));
        $partesNome = explode(' ', $nome, 2);
        $primeiroNome = substr($partesNome[0], 0, 30);
        $sobrenome = isset($partesNome[1]) ? substr($partesNome[1], 0, 30) : 'Empresa';

        $doc = preg_replace('/[^0-9]/', '', (string) ($dadosPagamento['doc_titular'] ?? $empresa->cnpj ?? ''));
        $identification = null;
        if (strlen($doc) === 14) {
            $identification = ['type' => 'CNPJ', 'number' => $doc];
        } elseif (strlen($doc) === 11) {
            $identification = ['type' => 'CPF', 'number' => $doc];
        }

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken($token);
            $client = new \MercadoPago\Client\Payment\PaymentClient();

            $payer = [
                'email' => $email,
                'first_name' => $primeiroNome,
                'last_name' => $sobrenome,
            ];
            if ($identification) {
                $payer['identification'] = $identification;
            }

            $paymentRequest = [
                'token' => $cardToken,
                'transaction_amount' => round((float) $valor, 2),
                'installments' => $installments,
                'description' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                'payer' => $payer,
                'external_reference' => 'ASSIN_' . ($assinaturaId ?: time()),
            ];

            $payment = $client->create($paymentRequest);

            $status = $payment->status ?? '';
            $chargeId = (string) ($payment->id ?? '');

            if ($status === 'approved') {
                return [
                    'status' => 'aprovado',
                    'charge_id' => $chargeId,
                    'gateway' => 'mercadopago',
                    'mensagem' => 'Pagamento com cartão aprovado com sucesso!',
                ];
            }

            if ($status === 'in_process') {
                return [
                    'status' => 'pendente',
                    'charge_id' => $chargeId,
                    'gateway' => 'mercadopago',
                    'mensagem' => 'O pagamento está em análise de segurança pela operadora.',
                ];
            }

            $statusDetail = (string) ($payment->status_detail ?? '');
            $motivo = $this->traduzirRejeicaoMercadoPago($statusDetail);

            throw new \Exception('Cartão não autorizado: ' . $motivo);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $content = $e->getApiResponse()->getContent();
            $msg = is_array($content) ? ($content['message'] ?? '') : '';
            if (is_array($content) && ! empty($content['cause'])) {
                $causes = array_map(static fn($c) => is_array($c) ? ($c['description'] ?? '') : (string) $c, $content['cause']);
                $msg .= ' (' . implode(', ', array_filter($causes)) . ')';
            }
            throw new \Exception('Operadora Mercado Pago: ' . ($msg ?: $e->getMessage()));
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Traduz detalhes de recusa do Mercado Pago para mensagens claras em português.
     */
    protected function traduzirRejeicaoMercadoPago(string $statusDetail): string
    {
        return match ($statusDetail) {
            'cc_rejected_bad_filled_card_number' => 'Número do cartão inválido.',
            'cc_rejected_bad_filled_date' => 'Data de validade incorreta.',
            'cc_rejected_bad_filled_security_code' => 'Código de segurança (CVV) inválido.',
            'cc_rejected_bad_filled_other' => 'Revise os dados informados do cartão.',
            'cc_rejected_blacklist' => 'Transação não permitida pela operadora do cartão.',
            'cc_rejected_call_for_authorize' => 'Você deve autorizar o pagamento com o banco emissor do cartão.',
            'cc_rejected_card_disabled' => 'Cartão inativo ou bloqueado. Entre em contato com seu banco.',
            'cc_rejected_card_error' => 'Falha ao comunicar com a operadora do cartão. Tente outro cartão.',
            'cc_rejected_duplicated_payment' => 'Você já realizou um pagamento com esse valor recentemente.',
            'cc_rejected_high_risk' => 'Pagamento recusado por segurança pela operadora do cartão.',
            'cc_rejected_insufficient_amount' => 'Saldo insuficiente no cartão.',
            'cc_rejected_invalid_installments' => 'O número de parcelas escolhido não é permitido para este cartão.',
            'cc_rejected_max_attempts' => 'Limite de tentativas atingido. Use outro cartão ou tente mais tarde.',
            default => 'Não autorizado pela operadora do cartão (' . ($statusDetail ?: 'motivo não informado') . ').',
        };
    }

    /**
     * Processa cartão de crédito via Efí (antiga Gerencianet).
     */
    protected function processarCartaoCreditoGerencianet(array $formaConfig, float $valor, object $empresa, object $plano, array $dadosPagamento, int $assinaturaId): array
    {
        $clientId = trim((string) ($formaConfig['client_id'] ?? $formaConfig['efi_client_id'] ?? ''));
        if (empty($clientId) && ! empty($_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_ID'])) {
            $clientId = trim((string) $_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_ID']);
        }
        $clientSecret = trim((string) ($formaConfig['client_secret'] ?? $formaConfig['efi_client_secret'] ?? ''));
        if (empty($clientSecret) && ! empty($_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_SECRET'])) {
            $clientSecret = trim((string) $_ENV['PAYMENT_GATEWAYS_EFI_CREDENTIAIS_CLIENT_SECRET']);
        }

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Credenciais da Efí (Client ID / Client Secret) não configuradas. Acesse Gerenciar SaaS > Formas de Pagamento > Cartão de Crédito.');
        }

        $paymentToken = trim((string) ($dadosPagamento['token'] ?? ''));
        if (empty($paymentToken)) {
            throw new \Exception('Token do cartão não gerado pela Efí no navegador. Verifique os dados digitados.');
        }

        $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['efi_ambiente'] ?? '';
        $sandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox']));

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            $options = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'sandbox' => $sandbox,
            ];
            $api = new \Efi\EfiPay($options);

            $nome = trim((string) ($dadosPagamento['titular'] ?? $empresa->nome_fantasia ?: $empresa->razao_social ?: 'Assinante'));
            $doc = preg_replace('/[^0-9]/', '', (string) ($dadosPagamento['doc_titular'] ?? $empresa->cnpj ?? ''));
            $email = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
                ? $empresa->email
                : 'contato@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');
            $telefone = preg_replace('/[^0-9]/', '', (string) ($empresa->telefone ?? '11999999999'));
            if (strlen($telefone) < 10) {
                $telefone = '11999999999';
            }

            $cep = preg_replace('/[^0-9]/', '', (string) ($empresa->cep ?? '01310100'));
            if (strlen($cep) !== 8) {
                $cep = '01310100';
            }

            $billingAddress = [
                'street' => substr(trim((string) ($empresa->rua ?: 'Avenida Paulista')), 0, 200),
                'number' => (int) preg_replace('/\D/', '', (string) ($empresa->numero ?: '100')) ?: 100,
                'neighborhood' => substr(trim((string) ($empresa->bairro ?: 'Centro')), 0, 100),
                'zipcode' => $cep,
                'city' => substr(trim((string) ($empresa->cidade ?: 'Sao Paulo')), 0, 100),
                'state' => strtoupper(substr(trim((string) ($empresa->estado ?: 'SP')), 0, 2)),
            ];

            $customer = [
                'name' => $nome,
                'phone_number' => $telefone,
                'email' => $email,
            ];

            if (strlen($doc) === 14) {
                $customer['juridical_person'] = [
                    'corporate_name' => substr(trim((string) ($empresa->razao_social ?: $nome)), 0, 100),
                    'cnpj' => $doc,
                ];
            } else {
                $customer['cpf'] = strlen($doc) === 11 ? $doc : '00000000000';
                $customer['birth'] = '1990-01-01';
            }

            $items = [
                [
                    'name' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                    'amount' => 1,
                    'value' => (int) round($valor * 100),
                ],
            ];

            $body = [
                'items' => $items,
                'payment' => [
                    'credit_card' => [
                        'customer' => $customer,
                        'installments' => max(1, min(12, (int) ($dadosPagamento['parcelas'] ?? 1))),
                        'billing_address' => $billingAddress,
                        'payment_token' => $paymentToken,
                    ],
                ],
            ];

            $resp = $api->createOneStepCharge([], $body);

            $chargeId = (string) ($resp['data']['charge_id'] ?? '');
            $status = strtolower((string) ($resp['data']['status'] ?? ''));

            if ($status === 'paid' || $status === 'settled') {
                return [
                    'status' => 'aprovado',
                    'charge_id' => $chargeId,
                    'gateway' => 'gerencianet',
                    'mensagem' => 'Pagamento aprovado com sucesso via Efí!',
                ];
            }

            if ($status === 'unpaid' || $status === 'waiting') {
                return [
                    'status' => 'pendente',
                    'charge_id' => $chargeId,
                    'gateway' => 'gerencianet',
                    'mensagem' => 'Pagamento em análise pela Efí.',
                ];
            }

            throw new \Exception('Pagamento não autorizado pela Efí.');
        } catch (\Efi\Exception\EfiException $e) {
            $desc = $e->errorDescription ?? $e->error ?? $e->getMessage();
            if (is_array($desc)) {
                $desc = json_encode($desc, JSON_UNESCAPED_UNICODE);
            }
            throw new \Exception('Efí: ' . $desc);
        } catch (\Throwable $e) {
            throw new \Exception('Falha ao processar cartão com a Efí: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Processa cartão de crédito via Asaas.
     */
    protected function processarCartaoCreditoAsaas(array $formaConfig, float $valor, object $empresa, object $plano, array $dadosPagamento, int $assinaturaId): array
    {
        $apiKey = trim((string) ($formaConfig['api_key'] ?? $formaConfig['asaas_api_key'] ?? ''));
        if (empty($apiKey) && ! empty($_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY'])) {
            $apiKey = trim((string) $_ENV['PAYMENT_GATEWAYS_ASAAS_CREDENTIAIS_API_KEY']);
        }
        if (empty($apiKey)) {
            throw new \Exception('Chave de API do Asaas não configurada. Acesse Gerenciar SaaS > Formas de Pagamento > Cartão de Crédito.');
        }

        $ambienteConfig = $formaConfig['ambiente'] ?? $formaConfig['asaas_ambiente'] ?? '';
        $isSandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox'])) || str_starts_with($apiKey, '$');
        $ambiente = $isSandbox ? 'homologacao' : 'producao';

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            $asaas = new \CodePhix\Asaas\Asaas($apiKey, $ambiente);

            $doc = preg_replace('/[^0-9]/', '', (string) ($dadosPagamento['doc_titular'] ?? $empresa->cnpj ?? ''));
            $customerId = null;

            if (! empty($doc) && (strlen($doc) === 11 || strlen($doc) === 14)) {
                try {
                    $busca = $asaas->Cliente()->getByCpfCnpj($doc);
                    if (! empty($busca->data) && is_array($busca->data) && isset($busca->data[0]->id)) {
                        $customerId = $busca->data[0]->id;
                    }
                } catch (\Throwable $t) {}
            }

            if (! $customerId) {
                $nomeCli = trim((string) ($dadosPagamento['titular'] ?? $empresa->nome_fantasia ?: $empresa->razao_social ?: 'Empresa Assinante'));
                $emailCli = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
                    ? $empresa->email
                    : 'financeiro@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');

                $dadosNovoCliente = [
                    'name' => $nomeCli,
                    'email' => $emailCli,
                ];
                if (! empty($doc) && (strlen($doc) === 11 || strlen($doc) === 14)) {
                    $dadosNovoCliente['cpfCnpj'] = $doc;
                }
                $novo = $asaas->Cliente()->create($dadosNovoCliente);
                if (! empty($novo->id)) {
                    $customerId = $novo->id;
                } elseif (! empty($novo->errors)) {
                    $erroMsg = is_array($novo->errors) ? ($novo->errors[0]->description ?? 'Erro desconhecido') : (string) $novo->errors;
                    throw new \Exception('Asaas: ' . $erroMsg);
                }
            }

            $cobrancaData = [
                'customer' => $customerId,
                'billingType' => 'CREDIT_CARD',
                'value' => round((float) $valor, 2),
                'dueDate' => date('Y-m-d'),
                'description' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                'externalReference' => 'ASSIN_' . ($assinaturaId ?: time()),
            ];

            if (! empty($dadosPagamento['creditCardToken'])) {
                $cobrancaData['creditCardToken'] = $dadosPagamento['creditCardToken'];
            } elseif (! empty($dadosPagamento['numero_cartao'])) {
                $cobrancaData['creditCard'] = [
                    'holderName' => $dadosPagamento['titular'],
                    'number' => preg_replace('/\D/', '', $dadosPagamento['numero_cartao']),
                    'expiryMonth' => $dadosPagamento['mes_expiracao'],
                    'expiryYear' => $dadosPagamento['ano_expiracao'],
                    'ccv' => $dadosPagamento['cvv'],
                ];
                $cobrancaData['creditCardHolderInfo'] = [
                    'name' => $dadosPagamento['titular'],
                    'email' => $empresa->email ?: 'financeiro@nexustkt.com.br',
                    'cpfCnpj' => $doc,
                    'postalCode' => preg_replace('/\D/', '', $empresa->cep ?: '01310100'),
                    'addressNumber' => $empresa->numero ?: '100',
                    'phone' => preg_replace('/\D/', '', $empresa->telefone ?: '11999999999'),
                ];
            }

            $cobranca = $asaas->Cobranca()->create($cobrancaData);
            if (! empty($cobranca->errors)) {
                $erroMsg = is_array($cobranca->errors) ? ($cobranca->errors[0]->description ?? '') : (string) $cobranca->errors;
                throw new \Exception('Asaas: ' . $erroMsg);
            }

            $chargeId = (string) ($cobranca->id ?? '');
            $status = strtoupper((string) ($cobranca->status ?? ''));

            if (in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
                return [
                    'status' => 'aprovado',
                    'charge_id' => $chargeId,
                    'gateway' => 'asaas',
                    'mensagem' => 'Pagamento aprovado com sucesso via Asaas!',
                ];
            }

            if ($status === 'PENDING') {
                return [
                    'status' => 'pendente',
                    'charge_id' => $chargeId,
                    'gateway' => 'asaas',
                    'mensagem' => 'Pagamento em análise pelo Asaas.',
                ];
            }

            throw new \Exception('Cartão não aprovado pelo Asaas.');
        } catch (\Throwable $e) {
            throw new \Exception('Falha ao processar cartão com o Asaas: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    /**
     * Gera sessão de pagamento para Cartão de Débito via Mercado Pago Checkout Pro (3DS).
     *
     * @param array  $formaConfig  Configurações do Cartão de Débito
     * @param float  $valor        Valor a ser cobrado
     * @param object $empresa      Empresa assinante
     * @param object $plano        Plano da assinatura
     * @param int    $assinaturaId ID da assinatura
     *
     * @return array ['status' => 'redirecionar', 'checkout_url' => string, 'charge_id' => string, 'gateway' => 'mercadopago']
     * @throws \Exception
     */
    public function processarCartaoDebito(array $formaConfig, float $valor, object $empresa, object $plano, int $assinaturaId = 0): array
    {
        $token = trim((string) ($formaConfig['access_token'] ?? $formaConfig['mp_access_token'] ?? ''));
        if (empty($token) && ! empty($_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN'])) {
            $token = trim((string) $_ENV['PAYMENT_GATEWAYS_MERCADOPAGO_CREDENTIAIS_ACCESS_TOKEN']);
        }
        if (empty($token)) {
            throw new \Exception('Access Token do Mercado Pago não configurado para Cartão de Débito.');
        }

        $email = ! empty($empresa->email) && filter_var($empresa->email, FILTER_VALIDATE_EMAIL)
            ? $empresa->email
            : 'contato@' . ($_SERVER['HTTP_HOST'] ?? 'nexustkt.com.br');
        $nome = trim((string) ($empresa->nome_fantasia ?: $empresa->razao_social ?: 'Assinante'));

        $prevErrorReporting = error_reporting();
        error_reporting($prevErrorReporting & ~E_DEPRECATED);

        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken($token);
            $client = new \MercadoPago\Client\Preference\PreferenceClient();

            $preferenceRequest = [
                'items' => [
                    [
                        'title' => 'Nexus Ticket - Renovação +30 dias (' . ($plano->nome ?? 'Plano') . ')',
                        'quantity' => 1,
                        'unit_price' => round((float) $valor, 2),
                        'currency_id' => 'BRL',
                    ],
                ],
                'payer' => [
                    'email' => $email,
                    'name' => $nome,
                ],
                'payment_methods' => [
                    'excluded_payment_types' => [
                        ['id' => 'ticket'],
                    ],
                    'default_payment_method_id' => 'debit_card',
                ],
                'back_urls' => [
                    'success' => site_url('assinatura/retornoCheckout?status=success&assinatura_id=' . $assinaturaId),
                    'failure' => site_url('assinatura/retornoCheckout?status=failure&assinatura_id=' . $assinaturaId),
                    'pending' => site_url('assinatura/retornoCheckout?status=pending&assinatura_id=' . $assinaturaId),
                ],
                'auto_return' => 'approved',
                'external_reference' => 'ASSIN_' . ($assinaturaId ?: time()),
                'notification_url' => site_url('assinatura/webhook/mercadopago'),
            ];

            $preference = $client->create($preferenceRequest);

            $ambienteConfig = $formaConfig['ambiente'] ?? '';
            $isSandbox = ($ambienteConfig === 'homologacao') || (! empty($formaConfig['sandbox']));

            $checkoutUrl = ($isSandbox && ! empty($preference->sandbox_init_point))
                ? $preference->sandbox_init_point
                : ($preference->init_point ?? '');

            if (empty($checkoutUrl)) {
                throw new \Exception('O Mercado Pago não retornou o link de checkout para autenticação 3DS.');
            }

            return [
                'status' => 'redirecionar',
                'checkout_url' => $checkoutUrl,
                'charge_id' => (string) ($preference->id ?? ''),
                'gateway' => 'mercadopago',
            ];
        } catch (\Throwable $e) {
            log_message('error', 'SaasPaymentService::processarCartaoDebito error: ' . $e->getMessage());
            throw new \Exception('Falha ao gerar sessão de débito no Mercado Pago: ' . $e->getMessage());
        } finally {
            error_reporting($prevErrorReporting);
        }
    }
}
