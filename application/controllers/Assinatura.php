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

        // Aplica desconto da empresa se houver
        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) ($plano->valor ?? 37.90);
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        $this->data['empresa'] = $empresa;
        $this->data['ehMaster'] = $ehMaster;
        $this->data['planoAtual'] = $plano;
        $this->data['diasRestantes'] = $diasRestantes;
        $this->data['valorOriginal'] = $valorOriginal;
        $this->data['valorFinal'] = $valorFinal;
        $situacaoAssinatura = $this->obterSituacaoAssinaturaEmpresa();
        $this->data['situacaoAssinatura'] = $situacaoAssinatura;
        $this->data['descontoPercentual'] = $descontoPercentual;
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

        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) $plano->valor;
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, 'PIX');
        $assinatura = $this->saas_model->getAssinaturaById($assinaturaId);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'assinatura_id' => $assinaturaId,
                'txid' => $assinatura->txid,
                'qr_code' => $assinatura->qr_code,
                'valor' => number_format($valorFinal, 2, ',', '.'),
            ]));
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

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'pago' => ($assinatura->status === 'aprovado'),
            ]));
    }

    /**
     * Confirmação imediata para testes e demonstração do fluxo.
     */
    public function confirmarPixTeste()
    {
        $id = (int) $this->input->post('id');
        $ok = $this->saas_model->confirmarPagamento($id);

        if ($ok) {
            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => true,
                    'message' => 'Pagamento confirmado com sucesso! 30 dias foram adicionados à sua assinatura.',
                ]));
        }

        return $this->output
            ->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode(['result' => false, 'message' => 'Não foi possível confirmar o pagamento.']));
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

        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) $plano->valor;
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        // Obtém configuração do boleto
        $formaBoleto = $this->saas_model->getFormaPagamentoByCodigo('boleto');
        $diasVencimento = (int) ($formaBoleto->config['dias_vencimento'] ?? 3);
        $vencimentoData = date('d/m/Y', strtotime("+{$diasVencimento} days"));

        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, 'BOLETO');
        $assinatura = $this->saas_model->getAssinaturaById($assinaturaId);

        // Linha digitável simulada/padrão Febraban
        $linhaDigitavel = '34191.79001 01043.510047 91020.150008 4 ' . date('Ymd') . '0000' . str_pad((string) (int) ($valorFinal * 100), 6, '0', STR_PAD_LEFT);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'assinatura_id' => $assinaturaId,
                'linha_digitavel' => $linhaDigitavel,
                'vencimento' => $vencimentoData,
                'valor' => number_format($valorFinal, 2, ',', '.'),
                'instrucoes' => $formaBoleto->instrucoes ?? 'Pagar até a data de vencimento.',
            ]));
    }

    /**
     * Endpoint AJAX para processar Cartão de Crédito (+30 dias).
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

        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) $plano->valor;
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        $tipo = $this->input->post('tipo') === 'debito' ? 'CARTAO_DEBITO' : 'CARTAO_CREDITO';
        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, (int) $plano->id, $valorFinal, $tipo);

        // Confirmação do pagamento e adição imediata de +30 dias
        $this->saas_model->confirmarPagamento($assinaturaId);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'assinatura_id' => $assinaturaId,
                'message' => 'Pagamento no cartão aprovado com sucesso! Mais 30 dias foram adicionados ao seu plano.',
                'valor' => number_format($valorFinal, 2, ',', '.'),
            ]));
    }
}
