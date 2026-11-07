<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Saas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        $temPermissao = $this->permite('vSaasDashboard')
            || $this->permite('vSaasAssinantes')
            || $this->permite('eSaasPreco')
            || $this->permite('cSaasAcessarEmpresa')
            || $this->permite('eSaasDias')
            || $this->permite('eSaasDesconto')
            || $this->permite('aSaasEmpresa')
            || $this->permite('eSaasStatusEmpresa');

        // Acesso restrito ao Superadmin ou usuários da Empresa Master com permissões de suporte
        if (! $isSuper && (! ($empresaId === 1 && $temPermissao))) {
            $this->session->set_flashdata('error', 'Acesso restrito ao Administrador Geral ou Equipe de Suporte do Sistema.');
            redirect('mapos');
        }
    }

    /**
     * Dashboard Geral do SaaS.
     */
    public function gerenciar()
    {
        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);

        if (! $isSuper && ! $this->permite('vSaasDashboard')) {
            if ($this->permite('vSaasAssinantes')) {
                return redirect('saas/assinantes');
            }
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar o dashboard master.');

            return redirect('mapos');
        }

        $metricas = $this->saas_model->getMetricasDashboard();

        $this->data['metricas'] = $metricas;
        $this->data['podeAlterarPreco'] = $isSuper || $this->permite('eSaasPreco');
        $this->data['podeAssinantes'] = $isSuper || $this->permite('vSaasAssinantes');
        $this->data['formasPagamento'] = $this->saas_model->getFormasPagamento();
        $this->data['podeGerenciarPagamentos'] = $isSuper || $this->permite('eSaasPreco');
        $this->data['view'] = 'saas/gerenciar';

        return $this->layout();
    }

    /**
     * Salvar Preço da Assinatura.
     */
    public function salvarPrecos()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/gerenciar');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasPreco')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar o preço da assinatura.');

            return redirect('saas/gerenciar');
        }

        $limparValor = static function ($v) {
            $v = trim(str_replace(['R$', ' ', ' '], '', (string) $v));
            if (strpos($v, ',') !== false) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }

            return (float) $v;
        };

        $valor = $limparValor($this->input->post('valor_assinatura') ?: $this->input->post('valor_essencial'));

        $this->saas_model->atualizarPrecoAssinatura($valor);

        $this->session->set_flashdata('success', 'Preço da assinatura atualizado com sucesso!');
        redirect('saas/gerenciar');
    }

    /**
     * Lista Completa de Assinantes.
     */
    public function assinantes()
    {
        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);

        if (! $isSuper && ! $this->permite('vSaasAssinantes')) {
            if ($this->permite('vSaasDashboard')) {
                return redirect('saas/gerenciar');
            }
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar a lista de assinantes.');

            return redirect('mapos');
        }

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
        $this->data['podeDias'] = $isSuper || $this->permite('eSaasDias');
        $this->data['podeDesconto'] = $isSuper || $this->permite('eSaasDesconto');
        $this->data['podeAcessar'] = $isSuper || $this->permite('cSaasAcessarEmpresa');
        $this->data['podeStatus'] = $isSuper || $this->permite('eSaasStatusEmpresa');
        $this->data['podeCadastrar'] = $isSuper || $this->permite('aSaasEmpresa');
        $this->data['view'] = 'saas/assinantes';

        return $this->layout();
    }

    /**
     * Adicionar Dias a um Assinante.
     */
    public function adicionarDias()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasDias')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar dias às empresas.');

            return redirect('saas/assinantes');
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
     * Ajustar Desconto de um Assinante.
     */
    public function ajustarDesconto()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasDesconto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para ajustar descontos.');

            return redirect('saas/assinantes');
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
     * Alterar Status (Ativa/Suspensa) de um Assinante.
     */
    public function alterarStatus()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasStatusEmpresa')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar o status de empresas.');

            return redirect('saas/assinantes');
        }

        $empresaId = (int) $this->input->post('empresa_id');
        $status = $this->input->post('status') === 'ativa' ? 'ativa' : 'suspensa';

        if ($empresaId > 1) {
            $this->saas_model->alterarStatusEmpresa($empresaId, $status);
            $msg = $status === 'ativa' ? 'Empresa ativada com sucesso!' : 'Empresa suspensa com sucesso!';
            $this->session->set_flashdata('success', $msg);
        } else {
            $this->session->set_flashdata('error', 'Não é possível alterar o status da Empresa Principal.');
        }

        redirect('saas/assinantes');
    }

    /**
     * Cadastrar Nova Empresa pelo Painel Master.
     */
    public function cadastrarEmpresa()
    {
        if ($this->input->method() !== 'post') {
            redirect('saas/assinantes');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('aSaasEmpresa')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para cadastrar novas empresas.');

            return redirect('saas/assinantes');
        }

        $razaoSocial = trim((string) $this->input->post('razao_social'));
        $nomeFantasia = trim((string) $this->input->post('nome_fantasia')) ?: $razaoSocial;
        $documento = trim((string) $this->input->post('documento'));
        $telefone = trim((string) $this->input->post('telefone'));
        $email = strtolower(trim((string) $this->input->post('email')));
        $nomeDono = trim((string) $this->input->post('nome_dono')) ?: $razaoSocial;
        $senha = (string) $this->input->post('senha');
        $diasTrial = (int) ($this->input->post('dias_trial') ?: 10);

        if ($razaoSocial === '' || $email === '' || $senha === '') {
            $this->session->set_flashdata('error', 'Preencha ao menos Razão Social, E-mail e Senha de acesso.');

            return redirect('saas/assinantes');
        }

        if (strlen($senha) < 8 || ! preg_match('/[A-Z]/', $senha) || ! preg_match('/[a-z]/', $senha) || ! preg_match('/[0-9]/', $senha) || ! preg_match('/[^a-zA-Z0-9]/', $senha)) {
            $this->session->set_flashdata('error', 'A senha precisa ter no mínimo 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.');

            return redirect('saas/assinantes');
        }

        $dadosEmpresa = [
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia,
            'documento' => $documento,
            'telefone' => $telefone,
            'email' => $email,
        ];

        $dadosUsuario = [
            'nome' => $nomeDono,
            'email' => $email,
            'senha_plana' => $senha,
            'telefone' => $telefone,
        ];

        $res = $this->saas_model->cadastrarEmpresaComDono($dadosEmpresa, $dadosUsuario, $diasTrial);

        if ($res['sucesso']) {
            $this->session->set_flashdata('success', 'Nova empresa cadastrada com sucesso!');
        } else {
            $this->session->set_flashdata('error', $res['mensagem'] ?? 'Erro ao cadastrar empresa.');
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

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasStatusEmpresa')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para alterar o plano de empresas.');

            return redirect('saas/assinantes');
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
     * Superadmin/Suporte entra no contexto de uma empresa específica.
     */
    public function trocarEmpresa(int $empresaId)
    {
        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('cSaasAcessarEmpresa')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para acessar o painel de outras empresas.');

            return redirect('saas/assinantes');
        }

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

    /**
     * Alternar status (Ativar/Desativar) da Forma de Pagamento via AJAX.
     */
    public function alternarFormaPagamento()
    {
        if ($this->input->method() !== 'post') {
            return $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Método não permitido']));
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasPreco')) {
            return $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Você não tem permissão para alterar formas de pagamento.']));
        }

        $id = (int) $this->input->post('id');
        $ativo = (bool) $this->input->post('ativo');

        $forma = $this->saas_model->getFormaPagamentoById($id);
        if (! $forma) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['result' => false, 'message' => 'Forma de pagamento não encontrada.']));
        }

        $sucesso = $this->saas_model->alternarStatusFormaPagamento($id, $ativo);

        if ($sucesso) {
            $msg = $ativo
                ? "Forma de pagamento '{$forma->nome}' ativada com sucesso!"
                : "Forma de pagamento '{$forma->nome}' desativada.";

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'result' => true,
                    'message' => $msg,
                    'ativo' => $ativo,
                ]));
        }

        return $this->output
            ->set_status_header(500)
            ->set_content_type('application/json')
            ->set_output(json_encode(['result' => false, 'message' => 'Erro ao atualizar status.']));
    }

    /**
     * Tela de Edição e Configurações de uma Forma de Pagamento.
     */
    public function editarFormaPagamento(int $id)
    {
        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasPreco')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar formas de pagamento.');

            return redirect('saas/gerenciar');
        }

        $forma = $this->saas_model->getFormaPagamentoById($id);
        if (! $forma) {
            $this->session->set_flashdata('error', 'Forma de pagamento não encontrada.');

            return redirect('saas/gerenciar');
        }

        $this->data['forma'] = $forma;
        $this->data['configuracoes'] = $forma->config ?? [];
        $this->data['view'] = 'saas/forma_pagamento_formulario';

        return $this->layout();
    }

    /**
     * Salvar Configurações da Forma de Pagamento.
     */
    public function salvarFormaPagamento(int $id)
    {
        if ($this->input->method() !== 'post') {
            return redirect('saas/gerenciar');
        }

        $isSuper = (bool) ($this->session->userdata('is_superadmin') ?? false);
        if (! $isSuper && ! $this->permite('eSaasPreco')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para salvar formas de pagamento.');

            return redirect('saas/gerenciar');
        }

        $forma = $this->saas_model->getFormaPagamentoById($id);
        if (! $forma) {
            $this->session->set_flashdata('error', 'Forma de pagamento não encontrada.');

            return redirect('saas/gerenciar');
        }

        $nome = trim((string) $this->input->post('nome'));
        $descricao = trim((string) $this->input->post('descricao'));
        $provedor = trim((string) $this->input->post('provedor'));
        $ativo = (int) $this->input->post('ativo') === 1 ? 1 : 0;
        $instrucoes = trim((string) $this->input->post('instrucoes'));

        // Monta o array de configurações de acordo com o tipo
        $configuracoes = $forma->config ?? [];
        unset($configuracoes['usar_env']);

        if ($forma->codigo === 'pix') {
            $configuracoes['tipo_pix'] = $this->input->post('tipo_pix') ?: 'dinamico';
            $configuracoes['chave_pix'] = trim((string) $this->input->post('chave_pix'));
            $configuracoes['tipo_chave'] = trim((string) $this->input->post('tipo_chave'));
            $configuracoes['titular'] = trim((string) $this->input->post('titular'));
            $configuracoes['cidade'] = trim((string) $this->input->post('cidade')) ?: 'Sao Paulo';
            $configuracoes['expiracao_minutos'] = max(5, (int) ($this->input->post('expiracao_minutos') ?: 60));

            // Tokens da API digitados manualmente pelo Master
            $mpToken = trim((string) $this->input->post('mp_access_token'));
            if ($mpToken !== '') {
                $configuracoes['access_token'] = $mpToken;
                $configuracoes['mp_access_token'] = $mpToken;
            }
            $mpPublic = trim((string) $this->input->post('mp_public_key'));
            if ($mpPublic !== '') {
                $configuracoes['public_key'] = $mpPublic;
                $configuracoes['mp_public_key'] = $mpPublic;
            }

            $asaasKey = trim((string) $this->input->post('asaas_api_key'));
            if ($asaasKey !== '') {
                $configuracoes['api_key'] = $asaasKey;
                $configuracoes['asaas_api_key'] = $asaasKey;
            }

            $efiClient = trim((string) $this->input->post('efi_client_id'));
            if ($efiClient !== '') {
                $configuracoes['client_id'] = $efiClient;
                $configuracoes['efi_client_id'] = $efiClient;
            }
            $efiSecret = trim((string) $this->input->post('efi_client_secret'));
            if ($efiSecret !== '') {
                $configuracoes['client_secret'] = $efiSecret;
                $configuracoes['efi_client_secret'] = $efiSecret;
            }
            $efiChave = trim((string) $this->input->post('efi_chave_pix'));
            if ($efiChave !== '') {
                $configuracoes['chave_pix_efi'] = $efiChave;
                $configuracoes['efi_chave_pix'] = $efiChave;
            }
        } elseif ($forma->codigo === 'boleto') {
            $configuracoes['dias_vencimento'] = max(1, min(30, (int) ($this->input->post('dias_vencimento') ?: 3)));

            $asaasKey = trim((string) $this->input->post('asaas_api_key'));
            if ($asaasKey !== '') {
                $configuracoes['api_key'] = $asaasKey;
                $configuracoes['asaas_api_key'] = $asaasKey;
            }

            $efiClient = trim((string) $this->input->post('efi_client_id'));
            if ($efiClient !== '') {
                $configuracoes['client_id'] = $efiClient;
                $configuracoes['efi_client_id'] = $efiClient;
            }
            $efiSecret = trim((string) $this->input->post('efi_client_secret'));
            if ($efiSecret !== '') {
                $configuracoes['client_secret'] = $efiSecret;
                $configuracoes['efi_client_secret'] = $efiSecret;
            }

            $mpToken = trim((string) $this->input->post('mp_access_token'));
            if ($mpToken !== '') {
                $configuracoes['access_token'] = $mpToken;
                $configuracoes['mp_access_token'] = $mpToken;
            }
        } elseif ($forma->codigo === 'cartao_credito') {
            $configuracoes['modo'] = $this->input->post('modo') ?: 'transparente';
            $configuracoes['max_parcelas'] = max(1, min(12, (int) ($this->input->post('max_parcelas') ?: 1)));

            $mpToken = trim((string) $this->input->post('mp_access_token'));
            if ($mpToken !== '') {
                $configuracoes['access_token'] = $mpToken;
                $configuracoes['mp_access_token'] = $mpToken;
            }
            $mpPublic = trim((string) $this->input->post('mp_public_key'));
            if ($mpPublic !== '') {
                $configuracoes['public_key'] = $mpPublic;
                $configuracoes['mp_public_key'] = $mpPublic;
            }

            $asaasKey = trim((string) $this->input->post('asaas_api_key'));
            if ($asaasKey !== '') {
                $configuracoes['api_key'] = $asaasKey;
                $configuracoes['asaas_api_key'] = $asaasKey;
            }
        } elseif ($forma->codigo === 'cartao_debito') {
            $configuracoes['modo'] = 'link';

            $mpToken = trim((string) $this->input->post('mp_access_token'));
            if ($mpToken !== '') {
                $configuracoes['access_token'] = $mpToken;
                $configuracoes['mp_access_token'] = $mpToken;
            }

            $asaasKey = trim((string) $this->input->post('asaas_api_key'));
            if ($asaasKey !== '') {
                $configuracoes['api_key'] = $asaasKey;
                $configuracoes['asaas_api_key'] = $asaasKey;
            }
        }

        $dadosAtualizar = [
            'nome' => $nome ?: $forma->nome,
            'descricao' => $descricao,
            'provedor' => $provedor ?: $forma->provedor,
            'ativo' => $ativo,
            'instrucoes' => $instrucoes,
            'configuracoes' => json_encode($configuracoes),
        ];

        $this->saas_model->salvarFormaPagamento($id, $dadosAtualizar);

        $this->session->set_flashdata('success', "Configurações de '{$forma->nome}' salvas com sucesso!");
        return redirect('saas/gerenciar');
    }
}
