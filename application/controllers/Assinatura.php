<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Assinatura extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
    }

    public function index()
    {
        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            $this->session->set_flashdata('error', 'Empresa não encontrada.');
            redirect('mapos');
        }

        // Recalcula dias restantes com base na data de vencimento
        $hoje = strtotime('today');
        $vencimento = $empresa->data_vencimento ? strtotime($empresa->data_vencimento) : $hoje;
        $diasRestantes = (int) ceil(($vencimento - $hoje) / 86400);
        if ($diasRestantes < 0) {
            $diasRestantes = 0;
        }

        // Obtém a assinatura/plano configurado para a empresa ou o plano padrão
        $plano = null;
        if (! empty($empresa->plano_id)) {
            $plano = $this->saas_model->getPlanoById((int) $empresa->plano_id);
        }
        if (! $plano) {
            $planos = $this->saas_model->getPlanos();
            $plano = ! empty($planos) ? $planos[0] : null;
        }

        // Aplica desconto da empresa se houver e estiver válido
        $calculo = $this->calcularValoresPlano($empresa, $plano);
        $valorOriginal = $calculo['valorOriginal'];
        $valorFinal = $calculo['valorFinal'];
        $tipoDesconto = $calculo['tipoDesconto'];
        $descontoPercentual = $calculo['descontoPercentual'];
        $descontoValor = $calculo['descontoValor'];
        $descontoValidoAte = $calculo['descontoValidoAte'];
        $descontoExpirado = $calculo['descontoExpirado'];

        $this->data['empresa'] = $empresa;
        $this->data['ehMaster'] = $ehMaster;
        $this->data['planoAtual'] = $plano;
        $this->data['diasRestantes'] = $diasRestantes;
        $this->data['valorOriginal'] = $valorOriginal;
        $this->data['valorFinal'] = $valorFinal;
        $situacaoAssinatura = $this->obterSituacaoAssinaturaEmpresa();
        $this->data['situacaoAssinatura'] = $situacaoAssinatura;
        $this->data['tipoDesconto'] = $tipoDesconto;
        $this->data['descontoPercentual'] = $descontoPercentual;
        $this->data['descontoValor'] = $descontoValor;
        $this->data['descontoValidoAte'] = $descontoValidoAte;
        $this->data['descontoExpirado'] = $descontoExpirado;
        $this->data['formasPagamento'] = $this->saas_model->getFormasPagamento(true);
        $this->data['view'] = 'assinatura/index';

        return $this->layout();
    }

    /**
     * Endpoint AJAX para gerar cobrança PIX (+30 dias).
     */
    public function gerarPix()
    {
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido']));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Empresa não encontrada']));
        }

        $planoId = (int) ($empresa->plano_id ?: 2);
        $plano = $this->saas_model->getPlanoById($planoId);
        if (! $plano) {
            $planos = $this->saas_model->getPlanos();
            $plano = ! empty($planos) ? $planos[0] : (object) ['id' => 2, 'valor' => 37.90];
        }

        $calculo = $this->calcularValoresPlano($empresa, $plano);
        $valorFinal = $calculo['valorFinal'];

        try {
            $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, 'PIX');
            $assinatura = $this->saas_model->getAssinaturaById($assinaturaId);

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => true,
                    'assinatura_id' => $assinaturaId,
                    'txid' => $assinatura->txid,
                    'qr_code' => $assinatura->qr_code,
                    'qr_code_image' => $assinatura->qr_code_image ?? null,
                    'valor' => number_format($valorFinal, 2, ',', '.'),
                ]));
        } catch (\Throwable $e) {
            log_message('error', 'Assinatura::gerarPix: ' . $e->getMessage());

            return $this->output
                ->set_status_header(422)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Polling AJAX para verificar se a assinatura foi paga.
     */
    public function verificarPix()
    {
        $id = (int) $this->input->get('id');
        $assinatura = $this->saas_model->getAssinaturaById($id);

        if (! $assinatura) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'pago' => false]));
        }

        if ($assinatura->status === 'pendente') {
            $this->saas_model->consultarStatusGateway($assinatura);
            $assinatura = $this->saas_model->getAssinaturaById($id);
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'pago' => ($assinatura->status === 'aprovado'),
            ]));
    }

    /**
     * Webhook unificado para notificação dos gateways (Mercado Pago, Asaas e Efí).
     */
    public function webhook(string $gateway = '')
    {
        $gateway = strtolower(trim($gateway));
        $corpo = (string) file_get_contents('php://input');
        $dados = json_decode($corpo, true) ?: [];

        log_message('info', 'Webhook Assinatura PIX [' . $gateway . ']: ' . substr($corpo, 0, 500));

        if ($gateway === 'mercadopago') {
            $chargeId = $this->input->get('data_id') ?: ($dados['data']['id'] ?? ($dados['id'] ?? null));
            if ($chargeId) {
                $assinatura = $this->db->where('charge_id', (string) $chargeId)->get('saas_assinaturas')->row();
                if ($assinatura && $assinatura->status === 'pendente') {
                    $this->saas_model->consultarStatusGateway($assinatura);
                }
            }
        } elseif ($gateway === 'asaas') {
            $evento = $dados['event'] ?? '';
            $paymentId = $dados['payment']['id'] ?? null;
            if ($paymentId && in_array($evento, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
                $assinatura = $this->db->where('charge_id', (string) $paymentId)->get('saas_assinaturas')->row();
                if ($assinatura && $assinatura->status === 'pendente') {
                    $this->saas_model->confirmarPagamento((int) $assinatura->id);
                }
            }
        } elseif ($gateway === 'gerencianet') {
            $pixArray = $dados['pix'] ?? [];
            if (is_array($pixArray)) {
                foreach ($pixArray as $pixItem) {
                    $txid = $pixItem['txid'] ?? '';
                    if ($txid) {
                        $assinatura = $this->db->where('txid', (string) $txid)->get('saas_assinaturas')->row();
                        if ($assinatura && $assinatura->status === 'pendente') {
                            $this->saas_model->confirmarPagamento((int) $assinatura->id);
                        }
                    }
                }
            }
        }

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'ok']));
    }

    /**
     * Endpoint AJAX para gerar cobrança de Boleto (+30 dias).
     */
    public function gerarBoleto()
    {
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido']));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Empresa não encontrada']));
        }

        $planoId = (int) ($empresa->plano_id ?: 2);
        $plano = $this->saas_model->getPlanoById($planoId);
        if (! $plano) {
            $planos = $this->saas_model->getPlanos();
            $plano = ! empty($planos) ? $planos[0] : (object) ['id' => 2, 'valor' => 37.90];
        }

        $calculo = $this->calcularValoresPlano($empresa, $plano);
        $valorFinal = $calculo['valorFinal'];

        // Obtém configuração do boleto
        $formaBoleto = $this->saas_model->getFormaPagamentoByCodigo('boleto');
        if (! $formaBoleto || empty($formaBoleto->ativo)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'A emissão de boleto bancário está inativa no sistema.']));
        }

        $configBoleto = $formaBoleto->config ?? [];
        if (! isset($configBoleto['provedor']) && ! empty($formaBoleto->provedor)) {
            $configBoleto['provedor'] = $formaBoleto->provedor;
        }

        $diasVencimento = max(1, min(30, (int) ($configBoleto['dias_vencimento'] ?? 3)));

        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, 'BOLETO');

        $this->load->library('SaasPaymentService');

        try {
            $resultado = $this->saaspaymentservice->processarBoleto($configBoleto, $valorFinal, $empresa, $plano, $diasVencimento, $assinaturaId);

            $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                'gateway' => $resultado['gateway'],
                'charge_id' => $resultado['charge_id'],
                'linha_digitavel' => $resultado['linha_digitavel'],
                'pdf_url' => $resultado['pdf_url'],
                'data_vencimento' => $resultado['vencimento_db'],
            ]);

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => true,
                    'assinatura_id' => $assinaturaId,
                    'linha_digitavel' => $resultado['linha_digitavel'],
                    'pdf_url' => $resultado['pdf_url'],
                    'vencimento' => $resultado['vencimento'],
                    'valor' => number_format($valorFinal, 2, ',', '.'),
                    'instrucoes' => $formaBoleto->instrucoes ?? 'Pagar até a data de vencimento.',
                ]));
        } catch (\Throwable $e) {
            $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                'status' => 'cancelado',
            ]);

            return $this->output
                ->set_status_header(422)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Endpoint AJAX para processar Cartão de Crédito ou Débito (+30 dias).
     */
    public function gerarCartao()
    {
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido']));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Empresa não encontrada']));
        }

        $planoId = (int) ($empresa->plano_id ?: 2);
        $plano = $this->saas_model->getPlanoById($planoId);
        if (! $plano) {
            $planos = $this->saas_model->getPlanos();
            $plano = ! empty($planos) ? $planos[0] : (object) ['id' => 2, 'valor' => 37.90];
        }

        $calculo = $this->calcularValoresPlano($empresa, $plano);
        $valorFinal = $calculo['valorFinal'];

        $isDebito = $this->input->post('tipo') === 'debito';
        $codigoForma = $isDebito ? 'cartao_debito' : 'cartao_credito';
        $tipoBanco = $isDebito ? 'CARTAO_DEBITO' : 'CARTAO_CREDITO';

        $formaDb = $this->saas_model->getFormaPagamentoByCodigo($codigoForma);
        if (! $formaDb || empty($formaDb->ativo)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => 'A forma de pagamento ' . ($isDebito ? 'Cartão de Débito' : 'Cartão de Crédito') . ' está inativa no sistema.',
                ]));
        }

        $configForma = $formaDb->config ?? [];
        if (! isset($configForma['provedor']) && ! empty($formaDb->provedor)) {
            $configForma['provedor'] = $formaDb->provedor;
        }

        $this->load->library('SaasPaymentService');

        if ($isDebito) {
            $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, $tipoBanco);
            try {
                $resultado = $this->saaspaymentservice->processarCartaoDebito($configForma, $valorFinal, $empresa, $plano, $assinaturaId);

                $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                    'charge_id' => $resultado['charge_id'] ?? null,
                    'gateway' => $resultado['gateway'] ?? 'mercadopago',
                ]);

                return $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'result' => true,
                        'modo' => 'redirecionar',
                        'checkout_url' => $resultado['checkout_url'],
                        'assinatura_id' => $assinaturaId,
                        'message' => 'Redirecionando para autenticação bancária segura (3DS)...',
                    ]));
            } catch (\Throwable $e) {
                $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                    'status' => 'cancelado',
                ]);

                return $this->output
                    ->set_status_header(422)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'result' => false,
                        'message' => $e->getMessage(),
                    ]));
            }
        }

        // Cartão de Crédito
        $dadosPost = [
            'token' => trim((string) $this->input->post('token')),
            'titular' => trim((string) $this->input->post('titular')),
            'doc_titular' => trim((string) $this->input->post('doc_titular')),
            'parcelas' => max(1, min(12, (int) ($this->input->post('parcelas') ?: 1))),
            'numero_cartao' => trim((string) $this->input->post('numero_cartao')),
            'mes_expiracao' => trim((string) $this->input->post('mes_expiracao')),
            'ano_expiracao' => trim((string) $this->input->post('ano_expiracao')),
            'cvv' => trim((string) $this->input->post('cvv')),
        ];

        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, $tipoBanco);

        try {
            $resultado = $this->saaspaymentservice->processarCartaoCredito($configForma, $valorFinal, $empresa, $plano, $dadosPost, $assinaturaId);

            $chargeId = $resultado['charge_id'] ?? null;
            $gateway = $resultado['gateway'] ?? ($configForma['provedor'] ?? 'mercadopago');

            $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                'charge_id' => $chargeId,
                'gateway' => $gateway,
            ]);

            if ($resultado['status'] === 'aprovado') {
                $this->saas_model->confirmarPagamento($assinaturaId);

                return $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'result' => true,
                        'assinatura_id' => $assinaturaId,
                        'message' => $resultado['mensagem'] ?? 'Pagamento no cartão aprovado com sucesso! Mais 30 dias foram adicionados ao seu plano.',
                        'valor' => number_format($valorFinal, 2, ',', '.'),
                    ]));
            }

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => true,
                    'pendente' => true,
                    'assinatura_id' => $assinaturaId,
                    'message' => $resultado['mensagem'] ?? 'O pagamento foi recebido e está em análise pela operadora.',
                ]));
        } catch (\Throwable $e) {
            $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                'status' => 'cancelado',
            ]);

            return $this->output
                ->set_status_header(422)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Retorno do checkout externo com autenticação 3DS (Mercado Pago).
     */
    public function retornoCheckout()
    {
        $status = strtolower(trim((string) $this->input->get('status')));
        $collectionStatus = strtolower(trim((string) $this->input->get('collection_status')));
        $assinaturaId = (int) $this->input->get('assinatura_id');
        $paymentId = $this->input->get('payment_id') ?: $this->input->get('collection_id');

        if ($assinaturaId > 0) {
            $assinatura = $this->saas_model->getAssinaturaById($assinaturaId);
            if ($assinatura && $assinatura->status === 'pendente') {
                if ($paymentId) {
                    $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
                        'charge_id' => (string) $paymentId,
                    ]);
                    $assinatura->charge_id = (string) $paymentId;
                }

                if ($status === 'success' || $collectionStatus === 'approved') {
                    $this->saas_model->confirmarPagamento($assinaturaId);
                    $this->session->set_flashdata('success', 'Pagamento aprovado com sucesso! Mais 30 dias adicionados ao seu plano.');
                } else {
                    $this->saas_model->consultarStatusGateway($assinatura);
                }
            }
        }

        return redirect('assinatura');
    }

    /**
     * Calcula os valores do plano aplicando desconto apenas se ativo e dentro da validade.
     *
     * @return array{valorOriginal: float, valorFinal: float, tipoDesconto: string, descontoPercentual: float, descontoValor: float, descontoValidoAte: ?string, descontoExpirado: bool}
     */
    protected function calcularValoresPlano(object $empresa, ?object $plano): array
    {
        $valorOriginal = (float) ($plano->valor ?? 37.90);
        $tipoDesconto = $empresa->tipo_desconto ?? 'porcentagem';
        $descontoPercentual = (float) ($empresa->desconto_percentual ?? 0);
        $descontoValor = (float) ($empresa->desconto_valor ?? 0);
        $descontoValidoAte = ! empty($empresa->desconto_valido_ate) ? (string) $empresa->desconto_valido_ate : null;
        $descontoExpirado = false;

        $temDesconto = ($tipoDesconto === 'valor' && $descontoValor > 0) || ($tipoDesconto === 'porcentagem' && $descontoPercentual > 0);

        if ($temDesconto && $descontoValidoAte !== null) {
            if ($descontoValidoAte < date('Y-m-d')) {
                $descontoExpirado = true;
                $temDesconto = false;
                $descontoPercentual = 0.0;
                $descontoValor = 0.0;
            }
        }

        if (! $temDesconto) {
            $valorFinal = $valorOriginal;
        } elseif ($tipoDesconto === 'valor') {
            $valorFinal = max(0.0, $valorOriginal - $descontoValor);
        } else {
            $valorFinal = max(0.0, $valorOriginal * (1 - ($descontoPercentual / 100)));
        }

        return [
            'valorOriginal' => $valorOriginal,
            'valorFinal' => round($valorFinal, 2),
            'tipoDesconto' => $tipoDesconto,
            'descontoPercentual' => $descontoPercentual,
            'descontoValor' => $descontoValor,
            'descontoValidoAte' => $descontoValidoAte,
            'descontoExpirado' => $descontoExpirado,
        ];
    }
/**
     * Tela de visualização e gerenciamento de boletos da assinatura da empresa.
     */
    public function boletos()
    {
        $formaBoleto = $this->saas_model->getFormaPagamentoByCodigo('boleto');
        if (! $formaBoleto || empty($formaBoleto->ativo)) {
            $this->session->set_flashdata('error', 'A opção de pagamento por boleto não está disponível no momento.');
            return redirect('assinatura');
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            $this->session->set_flashdata('error', 'Empresa não encontrada.');
            return redirect('mapos');
        }

        $this->data['empresa'] = $empresa;
        $this->data['boletos'] = $this->saas_model->getBoletosByEmpresa($empresaId);
        $this->data['formaBoleto'] = $formaBoleto;
        $this->data['view'] = 'assinatura/boletos';

        return $this->layout();
    }

    /**
     * Cancela um boleto pendente gerado pela empresa assinante.
     */
    public function cancelarBoleto()
    {
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido']));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $id = (int) $this->input->post('id');

        $assinatura = $this->saas_model->getAssinaturaById($id);
        if (! $assinatura || (int) $assinatura->empresa_id !== $empresaId) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Boleto não encontrado.']));
        }

        if ($assinatura->status !== 'pendente') {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Apenas boletos pendentes podem ser cancelados.']));
        }

        $formaBoleto = $this->saas_model->getFormaPagamentoByCodigo('boleto');
        $configBoleto = $formaBoleto->config ?? [];
        if (! isset($configBoleto['provedor']) && ! empty($formaBoleto->provedor)) {
            $configBoleto['provedor'] = $formaBoleto->provedor;
        }

        $this->load->library('SaasPaymentService');
        if (! empty($assinatura->charge_id) && ! empty($assinatura->gateway)) {
            $this->saaspaymentservice->cancelarBoleto($configBoleto, (string) $assinatura->charge_id, (string) $assinatura->gateway);
        }

        $this->saas_model->cancelarAssinatura($id);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'message' => 'Boleto cancelado com sucesso.',
            ]));
    }
}
