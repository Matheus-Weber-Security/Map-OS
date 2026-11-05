<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Cobrancas_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Listagem de cobranças da v5 (#2844), no padrão das listagens (#2852): os
     * mesmos filtros em listar() e contar(). Mais recentes primeiro, com o
     * nome do cliente.
     *
     * @param  array<string, string>  $filtros  pesquisa, status, tipo (os ou venda)
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->db->select('cobrancas.*, clientes.nomeCliente');
        $this->aplicarFiltros($filtros);

        return $this->db
            ->join('clientes', 'clientes.idClientes = cobrancas.clientes_id', 'left')
            ->order_by('cobrancas.idCobranca', 'desc')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();
    }

    /** Total da listagem com os mesmos filtros de listar(). */
    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);
        $this->db->join('clientes', 'clientes.idClientes = cobrancas.clientes_id', 'left');

        return (int) $this->db->count_all_results();
    }

    /**
     * pesquisa procura no nome do cliente e, se for número, no Nº da cobrança
     * e no id do gateway (o OR fica entre parênteses para não anular os outros
     * filtros); status é o do gateway; tipo separa as de OS das de venda.
     */
    private function aplicarFiltros(array $filtros): void
    {
        $this->db->from('cobrancas');

        $pesquisa = $filtros['pesquisa'] ?? '';
        if ($pesquisa !== '') {
            $this->db->group_start()->like('clientes.nomeCliente', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('cobrancas.idCobranca', (int) $pesquisa)->or_where('cobrancas.charge_id', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        if (($filtros['status'] ?? '') !== '') {
            $this->db->where('cobrancas.status', $filtros['status']);
        }

        if (($filtros['tipo'] ?? '') === 'os') {
            $this->db->where('cobrancas.os_id IS NOT NULL', null, false);
        } elseif (($filtros['tipo'] ?? '') === 'venda') {
            $this->db->where('cobrancas.vendas_id IS NOT NULL', null, false);
        }
    }

    public function getById($id)
    {
        $this->db->select('cobrancas.*, clientes.*');
        $this->db->from('cobrancas');
        $this->db->where('cobrancas.idCobranca', $id);
        $this->db->join('clientes', 'clientes.idClientes = cobrancas.clientes_id', 'left');
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function getByOs($id)
    {
        $this->db->select('cobrancas.*, clientes.*, os.*');
        $this->db->distinct();
        $this->db->from('cobrancas');
        $this->db->join('os', 'os.idOs = cobrancas.os_id');
        $this->db->join('clientes', 'clientes.idClientes = cobrancas.clientes_id', 'left');
        $this->db->where('cobrancas.charge_id', $id);

        return $this->db->get()->row();
    }

    public function getByVendas($id)
    {
        $this->db->select('cobrancas.*, clientes.*, vendas.*');
        $this->db->distinct();
        $this->db->from('cobrancas');
        $this->db->join('vendas', 'vendas.idVendas = cobrancas.vendas_id');
        $this->db->join('clientes', 'clientes.idClientes = cobrancas.clientes_id', 'left');
        $this->db->where('cobrancas.charge_id', $id);

        return $this->db->get()->row();
    }

    public function add($table, $data, $returnId = false)
    {
        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
            if ($returnId == true) {
                return $this->db->insert_id($table);
            }

            return true;
        }

        return false;
    }

    public function edit($table, $data, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        $this->db->update($table, $data);

        if ($this->db->affected_rows() >= 0) {
            return true;
        }

        return false;
    }

    public function delete($table, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        $this->db->delete($table);
        if ($this->db->affected_rows() == '1') {
            return true;
        }

        return false;
    }

    public function count($table)
    {
        return $this->db->count_all($table);
    }

    public function atualizarStatus($idCobranca)
    {
        $cobranca = $this->getById($idCobranca);
        if (empty($cobranca)) {
            return $this->session->set_flashdata('error', 'Cobrança não existe!');
        }

        $gatewayDePagamento = $cobranca->payment_gateway;
        $this->carregarGateway($gatewayDePagamento);

        $result = $this->PaymentGateway->atualizarDados($cobranca->idCobranca);

        $this->darBaixaAutomatica($cobranca->idCobranca);

        return $result;
    }

    public function confirmarPagamento($idCobranca)
    {
        $cobranca = $this->getById($idCobranca);
        if (empty($cobranca)) {
            return $this->session->set_flashdata('error', 'Cobrança não existe!');
        }

        $gatewayDePagamento = $cobranca->payment_gateway;
        $this->carregarGateway($gatewayDePagamento);

        $result = $this->PaymentGateway->confirmarPagamento($cobranca->idCobranca);

        $this->darBaixaAutomatica($cobranca->idCobranca);

        return $result;
    }

    public function cancelarPagamento($idCobranca)
    {
        $cobranca = $this->getById($idCobranca);
        if (empty($cobranca)) {
            return $this->session->set_flashdata('error', 'Cobrança não existe!');
        }

        $gatewayDePagamento = $cobranca->payment_gateway;
        $this->carregarGateway($gatewayDePagamento);

        $result = $this->PaymentGateway->cancelar($cobranca->idCobranca);

        return $result;
    }

    public function enviarEmail($idCobranca)
    {
        $cobranca = $this->getById($idCobranca);
        if (empty($cobranca)) {
            return $this->session->set_flashdata('error', 'Cobrança não existe!');
        }

        $gatewayDePagamento = $cobranca->payment_gateway;
        $this->carregarGateway($gatewayDePagamento);

        $result = $this->PaymentGateway->enviarPorEmail($cobranca->idCobranca);

        return $result;
    }

    /**
     * Carrega a biblioteca do gateway da cobrança como PaymentGateway. O nome
     * vem do banco e tem de ser um dos gateways configurados; senão (gateway
     * removido da configuração, registro adulterado) é uma exceção que a tela
     * mostra como aviso, e não um erro 500 do carregador de bibliotecas.
     *
     * @throws Exception
     */
    private function carregarGateway($nome): void
    {
        $this->load->config('payment_gateways');
        if (! cobrancaGatewayValido($nome, $this->config->item('payment_gateways'))) {
            throw new Exception('O gateway de pagamento desta cobrança (' . (is_scalar($nome) && $nome !== '' ? $nome : 'não informado') . ') não está configurado.');
        }

        $this->load->library("Gateways/$nome", null, 'PaymentGateway');
    }

    /**
     * Dá baixa automática no financeiro se a configuração baixa_auto_cobranca estiver ativa
     * e a cobrança tiver sido quitada/paga.
     * Caso a venda ou OS ainda não esteja faturada, cria o lançamento e fatura automaticamente.
     *
     * @param int|object $idCobranca
     * @param string|null $novoStatus
     * @return bool
     */
    public function darBaixaAutomatica($idCobranca, ?string $novoStatus = null): bool
    {
        $id = is_object($idCobranca) ? (int) ($idCobranca->idCobranca ?? 0) : (int) $idCobranca;
        if ($id <= 0) {
            return false;
        }

        // Verifica se a flag está ativada nas configurações
        $config = $this->db->where('config', 'baixa_auto_cobranca')->get('configuracoes')->row();
        if (! $config || (string) $config->valor !== '1') {
            return false;
        }

        $cobranca = is_object($idCobranca) && isset($idCobranca->total) ? $idCobranca : $this->getById($id);
        if (! $cobranca) {
            return false;
        }

        // Verifica se o status atual da cobrança é de pagamento confirmado
        $statusPagos = ['paid', 'approved', 'settled', 'received', 'confirmed', 'received_in_cash', 'dunning_received'];
        $statusAtual = strtolower(trim((string) ($novoStatus ?? $cobranca->status ?? '')));
        if (! in_array($statusAtual, $statusPagos, true)) {
            return false;
        }

        // Identifica forma de pagamento para o Contas a Receber
        $metodo = strtolower(trim((string) ($cobranca->payment_method ?: ($cobranca->payment ?? ''))));
        $formaPgto = match (true) {
            str_contains($metodo, 'pix') => 'Pix',
            str_contains($metodo, 'cred') || str_contains($metodo, 'card') => 'Cartão de Crédito',
            str_contains($metodo, 'deb') => 'Cartão de Débito',
            default => 'Boleto',
        };

        $hoje = date('Y-m-d');
        $alterou = false;

        // Venda vinculada
        if (! empty($cobranca->vendas_id)) {
            $idVenda = (int) $cobranca->vendas_id;
            $venda = $this->db->where('idVendas', $idVenda)->get('vendas')->row();

            if ($venda && $venda->status !== 'Cancelado') {
                $this->load->helper('vendas');
                $this->load->helper('os');
                $this->load->model('vendas_model');

                // Procura lançamento existente
                $lancamentos = [];
                if (! empty($venda->lancamentos_id)) {
                    $l = $this->db->where('idLancamentos', (int) $venda->lancamentos_id)->get('lancamentos')->row();
                    if ($l) {
                        $lancamentos[] = $l;
                    }
                }
                if (empty($lancamentos)) {
                    $lancamentos = $this->db->where('vendas_id', $idVenda)->get('lancamentos')->result();
                }
                if (empty($lancamentos)) {
                    $descricoes = vendaDescricoesDaFatura($idVenda);
                    $lancamentos = $this->db->where_in('descricao', $descricoes)->get('lancamentos')->result();
                }

                if (! empty($lancamentos)) {
                    foreach ($lancamentos as $lanc) {
                        if ((int) ($lanc->baixado ?? 0) === 0) {
                            $this->db->where('idLancamentos', $lanc->idLancamentos)->update('lancamentos', [
                                'baixado' => 1,
                                'data_pagamento' => $hoje,
                                'forma_pgto' => $formaPgto,
                            ]);
                            $alterou = true;
                            log_info("Baixa automática realizada no lançamento #{$lanc->idLancamentos} da Venda #{$idVenda} via cobrança #{$id}.");
                        }
                    }
                    if ((int) $venda->faturado === 0) {
                        $this->db->where('idVendas', $idVenda)->update('vendas', [
                            'faturado' => 1,
                            'status' => 'Faturado',
                            'lancamentos_id' => $lancamentos[0]->idLancamentos,
                        ]);
                    }
                } else {
                    // Não possui lançamento ainda: fatura automaticamente
                    $totais = $this->vendas_model->totais($venda);
                    $valorTotal = (float) ($totais['total'] ?? 0);
                    if ($valorTotal <= 0 && ! empty($cobranca->total)) {
                        $valorTotal = (float) $cobranca->total / 100;
                    }
                    $valorBruto = (float) ($totais['bruto'] ?? 0);
                    if ($valorBruto <= 0) {
                        $valorBruto = $valorTotal;
                    }
                    $desconto = (float) ($totais['desconto'] ?? 0);

                    $cliente = $this->db->select('nomeCliente')->where('idClientes', $venda->clientes_id)->get('clientes')->row();
                    $nomeCliente = $cliente->nomeCliente ?? ($venda->nomeCliente ?? '');
                    $usuarioId = $venda->usuarios_id ?? $this->session->userdata('id_usuarios');

                    $dadosLancamento = [
                        'descricao' => "Fatura de Venda Nº: {$idVenda}",
                        'valor' => $valorBruto,
                        'tipo_desconto' => 'real',
                        'desconto' => $desconto,
                        'valor_desconto' => $valorTotal,
                        'clientes_id' => $venda->clientes_id,
                        'cliente_fornecedor' => $nomeCliente,
                        'data_vencimento' => ! empty($cobranca->expire_at) ? $cobranca->expire_at : $hoje,
                        'data_pagamento' => $hoje,
                        'baixado' => 1,
                        'forma_pgto' => $formaPgto,
                        'tipo' => 'receita',
                        'vendas_id' => $idVenda,
                        'observacoes' => 'Baixa automática via cobrança #' . $id,
                        'usuarios_id' => $usuarioId,
                    ];

                    $this->db->insert('lancamentos', $dadosLancamento);
                    $idNovoLancamento = $this->db->insert_id();

                    $this->db->where('idVendas', $idVenda)->update('vendas', [
                        'faturado' => 1,
                        'valorTotal' => $valorBruto,
                        'valor_desconto' => $valorTotal,
                        'lancamentos_id' => $idNovoLancamento,
                        'status' => 'Faturado',
                    ]);
                    $alterou = true;
                    log_info("Venda #{$idVenda} faturada automaticamente e baixada no financeiro via cobrança #{$id}.");
                }
            }
        }

        // OS vinculada
        if (! empty($cobranca->os_id)) {
            $idOs = (int) $cobranca->os_id;
            $os = $this->db->where('idOs', $idOs)->get('os')->row();

            if ($os && $os->status !== 'Cancelado') {
                $this->load->helper('os');
                $this->load->model('os_model');

                // Procura lançamento existente
                $lancamentos = [];
                if (! empty($os->lancamento)) {
                    $l = $this->db->where('idLancamentos', (int) $os->lancamento)->get('lancamentos')->row();
                    if ($l) {
                        $lancamentos[] = $l;
                    }
                }
                if (empty($lancamentos)) {
                    $descricoes = osDescricoesDaFatura($idOs);
                    $lancamentos = $this->db->where_in('descricao', $descricoes)->get('lancamentos')->result();
                }

                if (! empty($lancamentos)) {
                    foreach ($lancamentos as $lanc) {
                        if ((int) ($lanc->baixado ?? 0) === 0) {
                            $this->db->where('idLancamentos', $lanc->idLancamentos)->update('lancamentos', [
                                'baixado' => 1,
                                'data_pagamento' => $hoje,
                                'forma_pgto' => $formaPgto,
                            ]);
                            $alterou = true;
                            log_info("Baixa automática realizada no lançamento #{$lanc->idLancamentos} da OS #{$idOs} via cobrança #{$id}.");
                        }
                    }
                    if ((int) $os->faturado === 0) {
                        $this->db->where('idOs', $idOs)->update('os', [
                            'faturado' => 1,
                            'status' => 'Faturado',
                            'lancamento' => $lancamentos[0]->idLancamentos,
                        ]);
                        $this->os_model->registrarStatus($idOs, (string) $os->status, 'Faturado', $os->usuarios_id);
                    }
                } else {
                    // Não possui lançamento ainda: fatura automaticamente
                    $totais = $this->os_model->totais($os);
                    $valorTotal = (float) ($totais['total'] ?? 0);
                    if ($valorTotal <= 0 && ! empty($cobranca->total)) {
                        $valorTotal = (float) $cobranca->total / 100;
                    }
                    $valorBruto = (float) ($totais['bruto'] ?? 0);
                    if ($valorBruto <= 0) {
                        $valorBruto = $valorTotal;
                    }
                    $desconto = (float) ($totais['desconto'] ?? 0);

                    $cliente = $this->db->select('nomeCliente')->where('idClientes', $os->clientes_id)->get('clientes')->row();
                    $nomeCliente = $cliente->nomeCliente ?? ($os->nomeCliente ?? '');
                    $usuarioId = $os->usuarios_id ?? $this->session->userdata('id_usuarios');

                    $dadosLancamento = [
                        'descricao' => "Fatura de OS Nº: {$idOs}",
                        'valor' => $valorBruto,
                        'tipo_desconto' => 'real',
                        'desconto' => $desconto,
                        'valor_desconto' => $valorTotal,
                        'clientes_id' => $os->clientes_id,
                        'cliente_fornecedor' => $nomeCliente,
                        'data_vencimento' => ! empty($cobranca->expire_at) ? $cobranca->expire_at : $hoje,
                        'data_pagamento' => $hoje,
                        'baixado' => 1,
                        'forma_pgto' => $formaPgto,
                        'tipo' => 'receita',
                        'observacoes' => 'Baixa automática via cobrança #' . $id,
                        'usuarios_id' => $usuarioId,
                    ];

                    $this->db->insert('lancamentos', $dadosLancamento);
                    $idNovoLancamento = $this->db->insert_id();

                    $this->db->where('idOs', $idOs)->update('os', [
                        'faturado' => 1,
                        'valorTotal' => $valorBruto,
                        'desconto' => $desconto,
                        'valor_desconto' => $valorTotal,
                        'lancamento' => $idNovoLancamento,
                        'status' => 'Faturado',
                    ]);
                    $this->os_model->registrarStatus($idOs, (string) $os->status, 'Faturado', $usuarioId);
                    $alterou = true;
                    log_info("OS #{$idOs} faturada automaticamente e baixada no financeiro via cobrança #{$id}.");
                }
            }
        }

        return $alterou;
    }
}
