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

        $planos = $this->saas_model->getPlanos();

        // Determina plano atual
        $planoAtual = null;
        foreach ($planos as $p) {
            if ((int) $p->id === (int) $empresa->plano_id) {
                $planoAtual = $p;
                break;
            }
        }
        if (! $planoAtual && ! empty($planos)) {
            $planoAtual = $planos[0];
        }

        // Aplica desconto da empresa se houver
        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) ($planoAtual->valor ?? 37.90);
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        $this->data['empresa'] = $empresa;
        $this->data['planos'] = $planos;
        $this->data['planoAtual'] = $planoAtual;
        $this->data['diasRestantes'] = $diasRestantes;
        $this->data['valorOriginal'] = $valorOriginal;
        $this->data['valorFinal'] = $valorFinal;
        $this->data['descontoPercentual'] = $descontoPercentual;
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
        $planoId = (int) ($this->input->post('plano_id') ?: 2);

        $plano = $this->saas_model->getPlanoById($planoId);
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $plano || ! $empresa) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Plano ou empresa não encontrados']));
        }

        $descontoPercentual = (int) ($empresa->desconto_percentual ?? 0);
        $valorOriginal = (float) $plano->valor;
        $valorFinal = $valorOriginal * (1 - ($descontoPercentual / 100));

        $assinaturaId = $this->saas_model->criarAssinatura($empresaId, $planoId, $valorFinal, 'PIX');
        $assinatura = $this->saas_model->getAssinaturaById($assinaturaId);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'result' => true,
                'assinatura_id' => $assinaturaId,
                'txid' => $assinatura->txid,
                'qr_code' => $assinatura->qr_code,
                'valor' => number_format($valorFinal, 2, ',', '.'),
                'plano_nome' => $plano->nome,
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
}
