<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Model de Compras e Importação de NF-e (#Compras-01).
 */
class Compras_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retorna a lista de compras filtrada e paginada.
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->db->select('compras.*, clientes.nomeCliente as nomeFornecedor, clientes.documento as docFornecedor, usuarios.nome as comprador');
        $this->aplicarFiltros($filtros);

        $linhas = $this->db
            ->order_by('compras.idCompras', 'desc')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();

        foreach ($linhas as $linha) {
            $linha->total = (float) $linha->valorTotal;
        }

        return $linhas;
    }

    /**
     * Total da listagem de compras para paginação.
     */
    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results();
    }

    /**
     * Aplica filtros e isolamento multi-tenant da empresa ativa.
     */
    private function aplicarFiltros(array $filtros): void
    {
        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        $this->db->from('compras');
        $this->db->join('clientes', 'clientes.idClientes = compras.fornecedores_id', 'left');
        $this->db->join('usuarios', 'usuarios.idUsuarios = compras.usuarios_id', 'left');
        $this->db->where('compras.empresa_id', $empresaId);

        $pesquisa = trim((string) ($filtros['pesquisa'] ?? ''));
        if ($pesquisa !== '') {
            $this->db->group_start()
                ->like('clientes.nomeCliente', $pesquisa)
                ->or_like('clientes.documento', $pesquisa)
                ->or_like('compras.numero_nfe', $pesquisa)
                ->or_like('compras.chave_nfe', $pesquisa);
            if (ctype_digit($pesquisa)) {
                $this->db->or_where('compras.codigo', (int) $pesquisa);
                $this->db->or_where('compras.idCompras', (int) $pesquisa);
            }
            $this->db->group_end();
        }

        if (($filtros['status'] ?? '') !== '') {
            $this->db->where('compras.status', $filtros['status']);
        }

        if (($filtros['fornecedor'] ?? '') !== '') {
            $this->db->where('compras.fornecedores_id', (int) $filtros['fornecedor']);
        }

        if (($filtros['de'] ?? '') !== '') {
            $this->db->where('compras.dataCompra >=', $filtros['de']);
        }
        if (($filtros['ate'] ?? '') !== '') {
            $this->db->where('compras.dataCompra <=', $filtros['ate']);
        }
    }

    /**
     * Busca uma compra pelo ID (conferindo a empresa do usuário).
     */
    public function getById(int $id): ?object
    {
        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        return $this->db
            ->select('compras.*, clientes.nomeCliente as nomeFornecedor, clientes.documento as docFornecedor, clientes.telefone, clientes.celular, clientes.email, usuarios.nome as comprador')
            ->from('compras')
            ->join('clientes', 'clientes.idClientes = compras.fornecedores_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = compras.usuarios_id', 'left')
            ->where('compras.idCompras', $id)
            ->where('compras.empresa_id', $empresaId)
            ->limit(1)
            ->get()
            ->row();
    }

    /**
     * Retorna os itens de uma compra com detalhes dos produtos.
     */
    public function getItens(int $idCompra): array
    {
        return $this->db
            ->select('itens_de_compras.*, produtos.descricao as nomeProduto, produtos.unidade, produtos.codDeBarra, produtos.estoque as estoqueAtual')
            ->from('itens_de_compras')
            ->join('produtos', 'produtos.idProdutos = itens_de_compras.produtos_id', 'left')
            ->where('itens_de_compras.compras_id', $idCompra)
            ->get()
            ->result();
    }

    /**
     * Busca vínculo De-Para na tabela produtos_fornecedores.
     */
    public function buscarVinculoFornecedor(int $empresaId, int $fornecedorId, string $codigoFornecedor): ?object
    {
        return $this->db
            ->where('empresa_id', $empresaId)
            ->where('fornecedor_id', $fornecedorId)
            ->where('codigo_fornecedor', trim($codigoFornecedor))
            ->limit(1)
            ->get('produtos_fornecedores')
            ->row();
    }

    /**
     * Salva ou atualiza a memória do vínculo De-Para.
     */
    public function salvarVinculoFornecedor(int $empresaId, int $fornecedorId, string $codigoFornecedor, int $produtoId, float $fatorConversao): void
    {
        $existente = $this->buscarVinculoFornecedor($empresaId, $fornecedorId, $codigoFornecedor);
        $fator = $fatorConversao > 0 ? $fatorConversao : 1.0000;

        if ($existente) {
            $this->db->where('id', $existente->id)->update('produtos_fornecedores', [
                'produto_id' => $produtoId,
                'fator_conversao' => $fator,
            ]);
        } else {
            $this->db->insert('produtos_fornecedores', [
                'empresa_id' => $empresaId,
                'fornecedor_id' => $fornecedorId,
                'codigo_fornecedor' => trim($codigoFornecedor),
                'produto_id' => $produtoId,
                'fator_conversao' => $fator,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Verifica se uma NF-e já foi importada por sua chave de acesso (44 dígitos).
     */
    public function buscarPorChaveNfe(int $empresaId, string $chaveNfe): ?object
    {
        $chave = preg_replace('/\D/', '', $chaveNfe);
        if ($chave === '') {
            return null;
        }

        return $this->db
            ->where('empresa_id', $empresaId)
            ->where('chave_nfe', $chave)
            ->limit(1)
            ->get('compras')
            ->row();
    }

    /**
     * Localiza fornecedor cadastrado pelo CNPJ ou CPF.
     */
    public function buscarFornecedorPorDocumento(int $empresaId, string $documento): ?object
    {
        $limpo = preg_replace('/\D/', '', $documento);
        if ($limpo === '') {
            return null;
        }

        return $this->db
            ->where('empresa_id', $empresaId)
            ->group_start()
            ->where('REPLACE(REPLACE(REPLACE(documento, ".", ""), "-", ""), "/", "") =', $limpo)
            ->or_where('documento', $documento)
            ->group_end()
            ->limit(1)
            ->get('clientes')
            ->row();
    }

    /**
     * Cadastra fornecedor automaticamente a partir do XML da NF-e.
     */
    public function cadastrarFornecedorNfe(int $empresaId, array $d): int
    {
        $doc = preg_replace('/\D/', '', (string) ($d['documento'] ?? ''));
        $dados = [
            'empresa_id' => $empresaId,
            'nomeCliente' => substr((string) ($d['razao_social'] ?: $d['nome_fantasia'] ?? 'Fornecedor sem nome'), 0, 255),
            'documento' => $doc,
            'telefone' => substr((string) ($d['telefone'] ?? ''), 0, 20),
            'celular' => substr((string) ($d['telefone'] ?? ''), 0, 20),
            'email' => '',
            'rua' => substr((string) ($d['logradouro'] ?? ''), 0, 100),
            'numero' => substr((string) ($d['numero'] ?? ''), 0, 20),
            'bairro' => substr((string) ($d['bairro'] ?? ''), 0, 50),
            'cidade' => substr((string) ($d['municipio'] ?? ''), 0, 50),
            'estado' => substr((string) ($d['uf'] ?? ''), 0, 2),
            'cep' => substr((string) ($d['cep'] ?? ''), 0, 10),
            'complemento' => substr((string) ($d['complemento'] ?? ''), 0, 100),
            'senha' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'fornecedor' => 1,
            'pessoa_fisica' => strlen($doc) === 11 ? 1 : 0,
            'dataCadastro' => date('Y-m-d'),
        ];

        $this->db->insert('clientes', $dados);

        return (int) $this->db->insert_id();
    }

    /**
     * Cadastra um novo produto rapidamente direto da tela de conferência da NF-e.
     */
    public function cadastrarProdutoRapido(int $empresaId, array $p): int
    {
        $dados = [
            'empresa_id' => $empresaId,
            'descricao' => substr(trim((string) ($p['descricao'] ?? 'Novo produto')), 0, 80),
            'unidade' => substr(trim((string) ($p['unidade'] ?: 'UN')), 0, 10),
            'precoCompra' => (float) ($p['precoCompra'] ?? 0.0),
            'precoVenda' => (float) ($p['precoVenda'] ?? 0.0) ?: (float) ($p['precoCompra'] ?? 0.0) * 1.5,
            'estoque' => 0, // Será alimentado pela conclusão da compra
            'estoqueMinimo' => 1,
            'codDeBarra' => substr(trim((string) ($p['codDeBarra'] ?? ($p['codBarra'] ?? ''))), 0, 50),
            'saida' => 1,
            'entrada' => 0,
        ];

        $this->db->insert('produtos', $dados);

        return (int) $this->db->insert_id();
    }

    /**
     * Movimenta o estoque para todos os produtos da compra (+ para entrada, - para estorno).
     */
    public function movimentarEstoque(int $idCompra, string $operacao, string $motivo): void
    {
        $this->load->model('produtos_model');
        $itens = $this->getItens($idCompra);

        foreach ($itens as $item) {
            $this->produtos_model->updateEstoque($item->produtos_id, $item->quantidade, $operacao);
            log_info('ESTOQUE COMPRA: Produto id ' . $item->produtos_id . ($operacao === '+' ? ' entrada de estoque' : ' estorno de estoque') . '. Quantidade: ' . $item->quantidade . '. Motivo: ' . $motivo);
        }
    }

    /**
     * Lança uma ou mais parcelas no Financeiro (Despesa) vinculadas à compra.
     */
    public function faturarCompra(int $idCompra, array $dadosFaturamento): int
    {
        $compra = $this->getById($idCompra);
        if (! $compra) {
            return 0;
        }

        $empresaId = (int) $compra->empresa_id;
        $fornecedorNome = $compra->nomeFornecedor ?: 'Fornecedor #' . $compra->fornecedores_id;

        $parcelas = $dadosFaturamento['parcelas'] ?? [
            [
                'vencimento' => $dadosFaturamento['vencimento'] ?? date('Y-m-d'),
                'valor' => (float) $compra->valorTotal,
                'numero' => '1',
            ],
        ];

        $totalParcelas = count($parcelas);
        $primeiroId = 0;

        foreach ($parcelas as $idx => $parc) {
            $num = $parc['numero'] ?? ($idx + 1);
            $desc = 'Compra #' . $idCompra . ($totalParcelas > 1 ? " ({$num}/{$totalParcelas})" : '');
            if (! empty($compra->numero_nfe)) {
                $desc .= ' - NF-e ' . $compra->numero_nfe;
            }

            $dadosLancamento = [
                'empresa_id' => $empresaId,
                'descricao' => $desc,
                'valor' => (float) $parc['valor'],
                'data_vencimento' => $parc['vencimento'],
                'data_pagamento' => ! empty($dadosFaturamento['baixado']) ? date('Y-m-d') : null,
                'baixado' => ! empty($dadosFaturamento['baixado']) ? 1 : 0,
                'cliente_fornecedor' => $fornecedorNome,
                'forma_pgto' => $dadosFaturamento['forma_pgto'] ?? 'Boleto',
                'tipo' => 'despesa',
                'clientes_id' => $compra->fornecedores_id,
                'observacoes' => 'Lançamento gerado a partir da Compra #' . $idCompra,
                'usuarios_id' => (int) $this->session->userdata('id_usuarios'),
            ];

            $this->db->insert('lancamentos', $dadosLancamento);
            $lancId = (int) $this->db->insert_id();
            if ($primeiroId === 0) {
                $primeiroId = $lancId;
            }
        }

        $this->db->where('idCompras', $idCompra)->update('compras', ['faturado' => 1]);

        return $primeiroId;
    }

    public function gerarProximoCodigo(int $empresaId): int
    {
        $ultimo = $this->db->select_max('codigo')
            ->where('empresa_id', $empresaId)
            ->get('compras')
            ->row();

        return ($ultimo && (int) $ultimo->codigo > 0) ? ((int) $ultimo->codigo + 1) : 1;
    }

    public function add(string $table, array $data, bool $returnId = false)
    {
        if ($table === 'compras') {
            $empresaId = (int) ($data['empresa_id'] ?? empresa_ativa_id());
            $data['empresa_id'] = $empresaId;
            if (empty($data['codigo'])) {
                $data['codigo'] = $this->gerarProximoCodigo($empresaId);
            }
        }

        $this->db->insert($table, $data);
        if ($this->db->affected_rows() === '1') {
            return $returnId ? $this->db->insert_id() : true;
        }

        return false;
    }

    public function edit(string $table, array $data, string $fieldID, $ID): bool
    {
        $this->db->where($fieldID, $ID);
        $this->db->update($table, $data);

        return $this->db->affected_rows() >= 0;
    }

    public function delete(string $table, string $fieldID, $ID): bool
    {
        $this->db->where($fieldID, $ID);
        $this->db->delete($table);

        return $this->db->affected_rows() > 0;
    }
}
