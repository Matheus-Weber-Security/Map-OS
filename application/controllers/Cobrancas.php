<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Cobrancas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(['form', 'financeiro']);
        $this->load->model('cobrancas_model');
        $this->data['menuCobrancas'] = 'financeiro';
    }

    public function index()
    {
        $this->cobrancas();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCobranca')) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(403)
                ->set_output(json_encode(['message' => 'Você não tem permissão para adicionar cobrança!']));
        }

        $this->load->library('form_validation');
        if ($this->form_validation->run('cobrancas') == false) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(400)
                ->set_output(json_encode(['message' => validation_errors()]));
        } else {
            $id = $this->input->post('id');
            $tipo = $this->input->post('tipo');
            $formaPagamento = $this->input->post('forma_pagamento');
            $gatewayDePagamento = $this->input->post('gateway_de_pagamento');

            $this->load->model('Os_model');
            $this->load->model('vendas_model');
            $cobranca = $tipo === 'os'
                ? $this->Os_model->getCobrancas($this->input->post('id'))
                : $this->vendas_model->getCobrancas($this->input->post('id'));
            if ($cobranca) {
                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(400)
                    ->set_output(json_encode(['message' => 'Já existe cobrança!']));
            }

            $this->load->config('payment_gateways');
            $gatewaysConfig = (array) $this->config->item('payment_gateways');
            if (! cobrancaGatewayValido($gatewayDePagamento, $gatewaysConfig)) {
                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(400)
                    ->set_output(json_encode(['message' => 'Gateway de pagamento inválido.']));
            }

            $gatewayConfig = $gatewaysConfig[$gatewayDePagamento] ?? [];
            if (! gatewayEstaConfigurado($gatewayConfig)) {
                $nomeGateway = $gatewayConfig['name'] ?? $gatewayDePagamento;
                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(400)
                    ->set_output(json_encode([
                        'message' => "As credenciais do {$nomeGateway} não foram configuradas. Acesse Configurações > Pagamentos para informar os dados de acesso.",
                    ]));
            }

            $this->load->library("Gateways/$gatewayDePagamento", null, 'PaymentGateway');

            try {
                $cobranca = $this->PaymentGateway->gerarCobranca(
                    $id,
                    $tipo,
                    $formaPagamento
                );

                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(200)
                    ->set_output(json_encode($cobranca));
            } catch (\Exception $e) {
                $nomeGateway = $gatewayConfig['name'] ?? $gatewayDePagamento;
                $msgAmigavel = $this->traduzirErroGateway($e->getMessage(), $nomeGateway);

                return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(500)
                    ->set_output(json_encode(['message' => $msgAmigavel]));
            }
        }
    }

    /**
     * Listagem de cobranças da v5 (#2844), no padrão das listagens (#2852):
     * filtros na URL (busca, status do gateway e tipo), paginação que os
     * mantém e as ações de cada linha (atualizar, confirmar, cancelar, enviar
     * por e-mail e excluir) por POST, com confirmação em modal-confirm.
     */
    public function cobrancas()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar cobrancas.');
            redirect(base_url());
        }

        $this->load->config('payment_gateways');

        $filtros = $this->filtrosDaListagem();
        $offset = (int) $this->uri->segment(3);
        $total = $this->cobrancas_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->cobrancas_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('cobrancas/cobrancas'), $total, $offset, null, $filtros);
        $this->data['status_opcoes'] = $this->statusDosGateways();
        $this->data['gateways'] = $this->config->item('payment_gateways');
        $this->data['pode'] = [
            'editar' => $this->permite('eCobranca'),
            'excluir' => $this->permite('dCobranca'),
        ];
        $this->data['view'] = 'cobrancas/cobrancas';

        return $this->layout();
    }

    /**
     * Status que os gateways configurados informam, como lista de valores
     * aceitos no filtro e opções do select.
     *
     * @return array<string, string>
     */
    protected function statusDosGateways(): array
    {
        $this->load->config('payment_gateways');
        $status = [];
        foreach ((array) $this->config->item('payment_gateways') as $gateway) {
            foreach (array_keys((array) ($gateway['transaction_status'] ?? [])) as $chave) {
                $status[(string) $chave] = cobrancaStatusPill((string) $chave)['label'] . ' (' . $chave . ')';
            }
        }
        asort($status);

        return $status;
    }

    /** @return array<string, string> */
    private function filtrosDaListagem(): array
    {
        return listagemFiltros([
            'pesquisa' => 'texto',
            'status' => array_map('strval', array_keys($this->statusDosGateways())),
            'tipo' => ['os', 'venda'],
        ], $this->input->get());
    }

    /**
     * Para onde voltar depois de uma ação: o detalhe da cobrança, quando ela
     * saiu de lá (campo voltar), ou a listagem com os mesmos filtros. Nunca
     * uma URL vinda do navegador (a v4 usava o Referer).
     */
    private function destinoDaAcao(int $id): string
    {
        if ($this->input->post('voltar') === 'detalhe') {
            return site_url('cobrancas/visualizar/' . $id);
        }

        return site_url('cobrancas/cobrancas') . listagemQuery($this->filtrosDaListagem());
    }

    /**
     * Roda uma ação do model sobre a cobrança. Só por POST (com o token CSRF):
     * na v4 atualizar e enviar por e-mail mudavam dados num GET. Erros do
     * gateway voltam como mensagem.
     */
    private function agirSobreCobranca(int $id, string $metodo, string $sucesso, string $erro): void
    {
        $destino = $this->destinoDaAcao($id);

        if ($this->input->method() !== 'post') {
            $this->session->set_flashdata('error', 'Use o botão da tela para esta ação.');
            redirect($destino);
        }

        if ($id <= 0 || ! $this->cobrancas_model->getById($id)) {
            $this->session->set_flashdata('error', 'Cobrança não encontrada.');
            redirect(site_url('cobrancas/cobrancas'));
        }

        try {
            $this->cobrancas_model->{$metodo}($id);
            $this->session->set_flashdata('success', $sucesso);
        } catch (Exception $e) {
            $this->session->set_flashdata('error', $e->getMessage() !== '' ? $e->getMessage() : $erro);
        }

        redirect($destino);
    }

    /** Id da cobrança no POST (formulários dos modais), ou 0. */
    private function idDoPost(): int
    {
        $id = $this->input->post('id');

        return is_scalar($id) && ctype_digit((string) $id) ? (int) $id : 0;
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir cobranças');
            redirect(site_url('cobrancas/cobrancas/'));
        }

        $id = $this->idDoPost();
        $destino = $this->destinoDaAcao($id);

        if ($this->input->method() !== 'post' || $id <= 0 || ! $this->cobrancas_model->getById($id)) {
            $this->session->set_flashdata('error', 'Cobrança não encontrada.');
            redirect(site_url('cobrancas/cobrancas'));
        }

        try {
            $this->cobrancas_model->cancelarPagamento($id);

            if ($this->cobrancas_model->delete('cobrancas', 'idCobranca', $id) == true) {
                log_info('Removeu uma cobrança. ID' . $id);
                $this->session->set_flashdata('success', 'Cobrança excluída.');
                // O detalhe da cobrança excluída não existe mais.
                $destino = site_url('cobrancas/cobrancas') . listagemQuery($this->filtrosDaListagem());
            } else {
                $this->session->set_flashdata('error', 'Não foi possível excluir a cobrança.');
            }
        } catch (Exception $e) {
            $this->session->set_flashdata('error', $e->getMessage());
        }

        redirect($destino);
    }

    public function atualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('mapos');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para atualizar cobrança.');
            redirect(base_url());
        }

        $this->agirSobreCobranca((int) $this->uri->segment(3), 'atualizarStatus', 'Status da cobrança atualizado.', 'Não foi possível atualizar a cobrança.');
    }

    public function confirmarPagamento()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para confirmar pagamento da cobrança.');
            redirect(base_url());
        }

        $this->agirSobreCobranca($this->idDoPost(), 'confirmarPagamento', 'Pagamento confirmado.', 'Não foi possível confirmar o pagamento.');
    }

    public function cancelar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para cancelar cobrança.');
            redirect(base_url());
        }

        $this->agirSobreCobranca($this->idDoPost(), 'cancelarPagamento', 'Cobrança cancelada.', 'Não foi possível cancelar a cobrança.');
    }

    public function visualizar()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('cobrancas');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar cobranças.');
            redirect(base_url());
        }

        $this->load->config('payment_gateways');

        $this->data['result'] = $this->cobrancas_model->getById((int) $this->uri->segment(3));
        if ($this->data['result'] == null) {
            $this->session->set_flashdata('error', 'Cobrança não encontrada.');
            redirect(site_url('cobrancas/'));
        }

        $this->data['gateways'] = $this->config->item('payment_gateways');
        $this->data['pode'] = [
            'editar' => $this->permite('eCobranca'),
            'excluir' => $this->permite('dCobranca'),
            'ver_os' => $this->permite('vOs'),
            'ver_venda' => $this->permite('vVenda'),
            'ver_cliente' => $this->permite('vCliente'),
        ];
        $this->data['view'] = 'cobrancas/visualizarCobranca';

        return $this->layout();
    }

    public function enviarEmail()
    {
        if (! $this->uri->segment(3) || ! is_numeric($this->uri->segment(3))) {
            $this->session->set_flashdata('error', 'Item não pode ser encontrado, parâmetro não foi passado corretamente.');
            redirect('cobrancas');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCobranca')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para enviar cobranças por e-mail.');
            redirect(base_url());
        }

        $this->agirSobreCobranca((int) $this->uri->segment(3), 'enviarEmail', 'E-mail adicionado na fila.', 'Não foi possível enviar o e-mail.');
    }

    /**
     * Traduz erros técnicos de gateways, cURL e bibliotecas para português claro e amigável.
     */
    private function traduzirErroGateway(string $msg, string $nomeGateway): string
    {
        $msgLower = strtolower($msg);

        // Erros de SSL / Certificado OpenSSL / cURL 60 / cURL 35
        if (str_contains($msgLower, 'ssl') || str_contains($msgLower, 'certificate') || str_contains($msgLower, 'openssl') || str_contains($msgLower, 'curl error 60') || str_contains($msgLower, 'curl error 35')) {
            return "Não foi possível estabelecer uma conexão segura (SSL) com os servidores do {$nomeGateway}. Verifique a conexão com a internet e as configurações de certificado do servidor.";
        }

        // Erros de Timeout / Conexão / cURL 28 / Host não encontrado
        if (str_contains($msgLower, 'timed out') || str_contains($msgLower, 'timeout') || str_contains($msgLower, 'curl error 28') || str_contains($msgLower, 'connection refused') || str_contains($msgLower, 'could not resolve host')) {
            return "O servidor do {$nomeGateway} demorou muito para responder ou está indisponível no momento. Tente novamente em instantes.";
        }

        // Erros de Credenciais / Não autorizado / Token inválido
        if (str_contains($msgLower, 'unauthorized') || str_contains($msgLower, 'must provide your access_token') || str_contains($msgLower, 'invalid token') || str_contains($msgLower, 'invalid_token') || str_contains($msgLower, 'invalid_client') || str_contains($msgLower, 'forbidden')) {
            return "As credenciais de acesso informadas para o {$nomeGateway} estão incorretas, incompletas ou expiradas. Por favor, confira os dados em Configurações > Pagamentos.";
        }

        // Erros de Dados cadastrais do cliente
        if (str_contains($msgLower, 'identification.number') || str_contains($msgLower, 'invalid cpf') || str_contains($msgLower, 'invalid cnpj') || str_contains($msgLower, 'cpf inválido') || str_contains($msgLower, 'cnpj inválido')) {
            return 'O CPF/CNPJ do cliente é inválido ou não foi preenchido. Verifique o cadastro do cliente antes de gerar a cobrança.';
        }

        if (str_contains($msgLower, 'payer.email') || str_contains($msgLower, 'email inválido') || str_contains($msgLower, 'invalid email')) {
            return 'O e-mail do cliente é inválido ou não foi informado. Verifique o cadastro do cliente.';
        }

        if (str_contains($msgLower, 'zip_code') || str_contains($msgLower, 'cep inválido') || str_contains($msgLower, 'cep')) {
            return 'O CEP do cliente é obrigatório para emissão de boleto bancário. Atualize o endereço no cadastro do cliente.';
        }

        return $msg;
    }
}
