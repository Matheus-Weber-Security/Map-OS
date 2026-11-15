<?php

use Libraries\Gateways\BasePaymentGateway;
use Libraries\Gateways\Contracts\PaymentGateway;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Exceptions\MPException;
use MercadoPago\MercadoPagoConfig;

class MercadoPago extends BasePaymentGateway
{
    private $mercadoPagoConfig;

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->config('payment_gateways');
        $this->ci->load->model('Os_model');
        $this->ci->load->model('vendas_model');
        $this->ci->load->model('cobrancas_model');
        $this->ci->load->model('mapos_model');
        $this->ci->load->model('email_model');

        $this->mercadoPagoConfig = $this->ci->config->item('payment_gateways')['MercadoPago'];

        if (! empty($this->mercadoPagoConfig['credentials']['access_token'])) {
            MercadoPagoConfig::setAccessToken($this->mercadoPagoConfig['credentials']['access_token']);
        }
    }

    public function cancelar($id)
    {
        $cobranca = $this->ci->cobrancas_model->getById($id);
        if (!$cobranca) {
            throw new \Exception('Cobrança não existe!');
        }

        try {
            $client = new PaymentClient();
            $payment = $client->cancel($cobranca->charge_id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->atualizarDados($id);
    }

    public function enviarPorEmail($id)
    {
        $cobranca = $this->ci->cobrancas_model->getById($id);
        if (!$cobranca) {
            throw new \Exception('Cobrança não existe!');
        }

        $emitente = $this->ci->mapos_model->getEmitente();
        $emitenteObj = is_array($emitente) ? ($emitente[0] ?? null) : $emitente;
        if (!$emitenteObj) {
            throw new \Exception('Emitente não configurado!');
        }

        $html = $this->ci->load->view(
            'cobrancas/emails/cobranca',
            [
                'cobranca' => $cobranca,
                'emitente' => $emitenteObj,
                'paymentGatewaysConfig' => $this->ci->config->item('payment_gateways'),
            ],
            true
        );

        $assunto = 'Cobrança - ' . $emitenteObj->nome;
        if ($cobranca->os_id) {
            $assunto .= ' - OS #' . $cobranca->os_id;
        } else {
            $assunto .= ' - Venda #' . $cobranca->vendas_id;
        }
        $remetentes = [$cobranca->email];
        foreach ($remetentes as $remetente) {
            $headers = [
                'From' => $emitenteObj ? "\"{$emitenteObj->nome}\" <{$emitenteObj->email}>" : '',
                'Subject' => $assunto,
                'Return-Path' => '',
            ];
            $email = [
                'empresa_id' => (int) ($cobranca->empresa_id ?? ($emitenteObj->empresa_id ?? 1)),
                'to' => $remetente,
                'message' => $html,
                'status' => 'pending',
                'date' => date('Y-m-d H:i:s'),
                'headers' => json_encode($headers),
            ];
            $this->ci->email_model->add('email_queue', $email);
        }
    }

    public function atualizarDados($id)
    {
        $cobranca = $this->ci->cobrancas_model->getById($id);
        if (!$cobranca) {
            throw new \Exception('Cobrança não existe!');
        }

        try {
            $client = new PaymentClient();
            $payment = $client->get($cobranca->charge_id);
        } catch (MPApiException | MPException $e) {
            throw new \Exception($this->mpErrorMessage($e));
        }

        $dadosAtualizacao = ['status' => $payment->status];

        // Se a cobrança estiver sem link ou código de barras, tenta atualizar com os dados da API
        if (empty($cobranca->link) || empty($cobranca->barcode)) {
            $dadosBoleto = $this->extrairDadosBoleto($payment);
            if (! empty($dadosBoleto['link']) && empty($cobranca->link)) {
                $dadosAtualizacao['link'] = $dadosBoleto['link'];
                $dadosAtualizacao['pdf'] = $dadosBoleto['pdf'];
            }
            if (! empty($dadosBoleto['barcode']) && empty($cobranca->barcode)) {
                $dadosAtualizacao['barcode'] = $dadosBoleto['barcode'];
            }
        }

        $this->ci->cobrancas_model->edit(
            'cobrancas',
            $dadosAtualizacao,
            'idCobranca',
            $id
        );

        // Cobrança foi paga ou foi confirmada de forma manual, então damos baixa se configurado
        if ($payment->status === 'approved') {
            $this->ci->cobrancas_model->darBaixaAutomatica($id, $payment->status);
        }

        $this->ci->session->set_flashdata('success', 'Cobrança atualizada com sucesso!');
        log_info('Atualizou cobrança Mercado Pago. ID: ' . $id);
    }

    private function mpErrorMessage(\Exception $e): string
    {
        if ($e instanceof \MercadoPago\Exceptions\MPApiException) {
            $content = $e->getApiResponse()->getContent();

            if (is_object($content)) {
                $content = json_decode(json_encode($content), true);
            }

            if (is_array($content)) {
                $msg = $content['message'] ?? $content['error'] ?? $content['cause'][0]['description'] ?? null;
                if (!$msg) {
                    $msg = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                return "Erro API Mercado Pago: " . $msg;
            }

            if (is_string($content)) {
                return "Erro API Mercado Pago: " . $content;
            }

            return "Erro API Mercado Pago: resposta inesperada.";
        }

        return $e->getMessage();
    }

    public function confirmarPagamento($id)
    {
        $cobranca = $this->ci->cobrancas_model->getById($id);
        if (!$cobranca) {
            throw new \Exception('Cobrança não existe!');
        }

        try {
            $client = new PaymentClient();
            $payment = $client->get($cobranca->charge_id);

            if ($payment->status === 'approved' || $payment->payment_method_id === 'bolbradesco') {
                $this->atualizarDados($id);
                return $payment;
            }

            if ($payment->status === 'authorized') {
                $valor = isset($cobranca->total) ? (float) $cobranca->total : 0;
                if ($valor > 1000) {
                    $valor /= 100;
                }

                $payment = $client->capture(
                    (int) $cobranca->charge_id,
                    (float) $valor
                );
            } else {
                throw new \Exception('O pagamento não está em um estado que permite captura (' . $payment->status . ').');
            }

            $this->atualizarDados($id);
            return $payment;
        } catch (MPApiException $e) {
            throw new \Exception($this->mpErrorMessage($e));
        } catch (MPException $e) {
            throw new \Exception('Erro Mercado Pago: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Erro ao confirmar pagamento: ' . $e->getMessage());
        }
    }

    /**
     * Extrai link/PDF e código de barras / linha digitável do retorno da API
     * verificando tanto o payload JSON bruto (getResponse()->getContent())
     * quanto as propriedades dos objetos mapeados pelo SDK.
     */
    private function extrairDadosBoleto($payment): array
    {
        $raw = [];
        if (is_object($payment) && method_exists($payment, 'getResponse') && $payment->getResponse()) {
            $raw = $payment->getResponse()->getContent() ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }
        }

        // Link / PDF do boleto
        $linkBoleto = '';
        if (! empty($raw['transaction_details']['external_resource_url'])) {
            $linkBoleto = (string) $raw['transaction_details']['external_resource_url'];
        } elseif (! empty($raw['point_of_interaction']['transaction_data']['ticket_url'])) {
            $linkBoleto = (string) $raw['point_of_interaction']['transaction_data']['ticket_url'];
        } elseif (! empty($raw['external_resource_url'])) {
            $linkBoleto = (string) $raw['external_resource_url'];
        } elseif (is_object($payment)) {
            if (! empty($payment->transaction_details->external_resource_url)) {
                $linkBoleto = (string) $payment->transaction_details->external_resource_url;
            } elseif (! empty($payment->point_of_interaction->transaction_data->ticket_url)) {
                $linkBoleto = (string) $payment->point_of_interaction->transaction_data->ticket_url;
            } elseif (! empty($payment->external_resource_url)) {
                $linkBoleto = (string) $payment->external_resource_url;
            }
        }

        // Código de barras / linha digitável (prioriza linha digitável para facilitar cópia no app do banco)
        $barcode = '';
        if (! empty($raw['digitable_line'])) {
            $barcode = (string) $raw['digitable_line'];
        } elseif (! empty($raw['barcode'])) {
            $barcode = is_array($raw['barcode']) ? (string) ($raw['barcode']['content'] ?? '') : (string) $raw['barcode'];
        } elseif (! empty($raw['transaction_details']['digitable_line'])) {
            $barcode = (string) $raw['transaction_details']['digitable_line'];
        } elseif (! empty($raw['transaction_details']['barcode'])) {
            $barcode = is_array($raw['transaction_details']['barcode']) ? (string) ($raw['transaction_details']['barcode']['content'] ?? '') : (string) $raw['transaction_details']['barcode'];
        } elseif (! empty($raw['point_of_interaction']['transaction_data']['barcode_content'])) {
            $barcode = (string) $raw['point_of_interaction']['transaction_data']['barcode_content'];
        } elseif (is_object($payment)) {
            if (! empty($payment->digitable_line)) {
                $barcode = (string) $payment->digitable_line;
            } elseif (! empty($payment->barcode)) {
                $barcode = is_string($payment->barcode) ? $payment->barcode : (string) ($payment->barcode->content ?? '');
            } elseif (! empty($payment->transaction_details->digitable_line)) {
                $barcode = (string) $payment->transaction_details->digitable_line;
            } elseif (! empty($payment->transaction_details->barcode)) {
                $barcode = is_string($payment->transaction_details->barcode) ? $payment->transaction_details->barcode : (string) ($payment->transaction_details->barcode->content ?? '');
            }
        }

        return [
            'link' => trim($linkBoleto),
            'pdf' => trim($linkBoleto),
            'barcode' => trim($barcode),
        ];
    }

    protected function gerarCobrancaBoleto($id, $tipo)
    {
        if (empty($this->mercadoPagoConfig['credentials']['access_token'])) {
            throw new \Exception('As credenciais do Mercado Pago não foram configuradas. Acesse Configurações > Pagamentos para configurar o Access Token.');
        }

        $entity = $this->findEntity($id, $tipo);

        if (empty($entity)) {
            throw new \Exception('OS ou venda não existe!');
        }

        $valor = $this->valorDaCobranca($entity, $tipo);

        if ($valor <= 0) {
            throw new \Exception('Valor inválido!');
        }

        if ($err = $this->errosCadastro($entity)) {
            throw new \Exception($err);
        }

        $clientNameParts = explode(' ', $entity->nomeCliente);
        $documento = preg_replace('/[^0-9]/', '', $entity->documento);
        $expirationDate = (new DateTime())->add(new DateInterval($this->mercadoPagoConfig['boleto_expiration']))->format(DateTime::RFC3339_EXTENDED);

        try {
            $client = new PaymentClient();
            $payment = $client->create([
                "transaction_amount" => $valor,
                "description" => $tipo === PaymentGateway::PAYMENT_TYPE_OS ? "OS #$id" : "Venda #$id",
                "payment_method_id" => "bolbradesco",
                "notification_url" => "https://seusite.com.br/mercadopago/retorno",
                "date_of_expiration" => $expirationDate,
                "payer" => [
                    "email" => $entity->email,
                    "first_name" => $clientNameParts[0],
                    "last_name" => end($clientNameParts),
                    "identification" => [
                        "type" => strlen($documento) == 11 ? "CPF" : "CNPJ",
                        "number" => $documento,
                    ],
                    "address" => [
                        "zip_code" => preg_replace('/[^0-9]/', '', $entity->cep),
                        "street_name" => $entity->rua,
                        "street_number" => $entity->numero,
                        "neighborhood" => $entity->bairro,
                        "city" => $entity->cidade,
                        "federal_unit" => $entity->estado,
                    ],
                ],
            ]);
        } catch (MPApiException $e) {
            throw new \Exception($this->mpErrorMessage($e));
        } catch (MPException $e) {
            throw new \Exception($e->getMessage());
        }

        if (empty($payment) || empty($payment->id)) {
            throw new \Exception('Mercado Pago não retornou identificador para a cobrança gerada.');
        }

        if ($payment->status === 'rejected') {
            $detalhe = $payment->status_detail ?? '';
            $mensagem = 'Mercado Pago recusou a emissão do boleto';
            if ($detalhe !== '') {
                $mensagem .= ' (' . $detalhe . ')';
            }
            $mensagem .= '. Verifique os dados cadastrais do cliente ou a conta do Mercado Pago.';
            throw new \Exception($mensagem);
        }

        $dadosBoleto = $this->extrairDadosBoleto($payment);

        $data = [
            'barcode' => $dadosBoleto['barcode'],
            'link' => $dadosBoleto['link'],
            'pdf' => $dadosBoleto['pdf'],
            'expire_at' => $payment->date_of_expiration ?? $expirationDate,
            'charge_id' => $payment->id,
            'status' => $payment->status,
            'total' => getMoneyAsCents($valor),
            'clientes_id' => $entity->idClientes,
            'payment_method' => 'boleto',
            'payment_gateway' => 'MercadoPago',
        ];

        if ($tipo === PaymentGateway::PAYMENT_TYPE_OS) {
            $data['os_id'] = $id;
        } else {
            $data['vendas_id'] = $id;
        }

        if ($id = $this->ci->cobrancas_model->add('cobrancas', $data, true)) {
            $data['idCobranca'] = $id;
            log_info('Cobrança criada com sucesso. ID: ' . $payment->id);
        } else {
            throw new \Exception('Erro ao salvar cobrança!');
        }

        return $data;
    }

    protected function gerarCobrancaLink($id, $tipo)
    {
        throw new \Exception('Mercado Pago não suporta gerar link pela API, apenas pelo painel!');
    }

}
