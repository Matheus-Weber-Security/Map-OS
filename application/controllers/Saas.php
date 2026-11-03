<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Saas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');

        // Apenas Super Administradores podem acessar o módulo de gestão SaaS
        if (! $this->session->userdata('is_superadmin')) {
            $this->session->set_flashdata('error', 'Acesso restrito ao Administrador Geral do Sistema.');
            redirect('mapos');
        }
    }

    /**
     * Dashboard Geral do SaaS (Conforme Imagem 2).
     */
    public function gerenciar()
    {
        $metricas = $this->saas_model->getMetricasDashboard();

        $this->data['metricas'] = $metricas;
        $this->data['view'] = 'saas/gerenciar';

        return $this->layout();
    }

    /**
     * Salvar Preços dos Planos (Card da Imagem 2).
     */
    public function salvarPrecos()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/gerenciar');
        }

        $limparValor = static function ($v) {
            $v = trim(str_replace(['R$', ' ', ' '], '', (string) $v));
            if (strpos($v, ',') !== false) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }

            return (float) $v;
        };

        $valorBasico = $limparValor($this->input->post('valor_basico'));
        $valorEssencial = $limparValor($this->input->post('valor_essencial'));

        $this->saas_model->atualizarPrecosPlanos($valorBasico, $valorEssencial);

        $this->session->set_flashdata('success', 'Preços das assinaturas atualizados com sucesso!');
        redirect('saas/gerenciar');
    }

    /**
     * Lista Completa de Assinantes (Conforme Imagem 3).
     */
    public function assinantes()
    {
        $status = $this->input->get('status') ?: 'todos';
        $busca = trim((string) $this->input->get('busca'));

        $filtros = [
            'status' => $status,
            'busca' => $busca,
        ];

        $empresas = $this->saas_model->getEmpresas($filtros);
        $planos = $this->saas_model->getPlanos(false);

        $this->data['empresas'] = $empresas;
        $this->data['total'] = count($empresas);
        $this->data['filtros'] = $filtros;
        $this->data['planos'] = $planos;
        $this->data['statusAtual'] = $status;
        $this->data['buscaAtual'] = $busca;
        $this->data['view'] = 'saas/assinantes';

        return $this->layout();
    }

    /**
     * Adicionar Dias a um Assinante (Ação [Ajustar] da Imagem 3).
     */
    public function adicionarDias()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $empresaId = (int) $this->input->post('empresa_id');
        $dias = (int) $this->input->post('dias');

        if ($empresaId > 0 && $dias > 0) {
            $this->saas_model->adicionarDias($empresaId, $dias);
            $this->session->set_flashdata('success', "Foram adicionados {$dias} dias à empresa com sucesso!");
        } else {
            $this->session->set_flashdata('error', 'Dados inválidos para adicionar dias.');
        }

        redirect('saas/assinantes');
    }

    /**
     * Ajustar Desconto de um Assinante (Ação [Ajustar] da Imagem 3).
     */
    public function ajustarDesconto()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $empresaId = (int) $this->input->post('empresa_id');
        $desconto = (int) $this->input->post('desconto_percentual');

        if ($empresaId > 0) {
            $this->saas_model->atualizarDesconto($empresaId, $desconto);
            $this->session->set_flashdata('success', 'Desconto do assinante atualizado com sucesso!');
        }

        redirect('saas/assinantes');
    }

    /**
     * Alterar Plano do Assinante.
     */
    public function alterarPlano()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $empresaId = (int) $this->input->post('empresa_id');
        $planoId = (int) $this->input->post('plano_id');

        if ($empresaId > 0 && $planoId > 0) {
            $this->saas_model->alterarPlanoEmpresa($empresaId, $planoId);
            $this->session->set_flashdata('success', 'Plano da empresa alterado com sucesso!');
        }

        redirect('saas/assinantes');
    }

    /**
     * Superadmin entra no contexto de uma empresa específica.
     */
    public function trocarEmpresa(int $empresaId)
    {
        $empresa = $this->saas_model->getEmpresaById($empresaId);
        if ($empresa) {
            $this->session->set_userdata([
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome_fantasia,
            ]);
            $this->session->set_flashdata('success', "Ambiente alternado para: {$empresa->nome_fantasia}");
        }

        redirect('mapos');
    }
}
