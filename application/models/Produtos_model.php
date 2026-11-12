<?php

class Produtos_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields);
        $this->db->from($table);
        $this->db->order_by('idProdutos', 'desc');
        $this->db->limit($perpage, $start);
        if ($table === 'produtos' || $this->db->field_exists('empresa_id', $table)) {
            $this->db->where("{$table}.empresa_id", empresa_ativa_id());
        }
        if ($where) {
            $this->db->group_start();
            $this->db->like('codDeBarra', $where);
            $this->db->or_like('descricao', $where);
            $this->db->group_end();
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    /**
     * Produtos da listagem (#2841), do mais recente para o mais antigo.
     *
     * @param  array{pesquisa?: string, estoque?: 'baixo'}  $filtros  Saída de listagemFiltros()
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->aplicarFiltros($filtros);

        return $this->db->order_by('codigo', 'desc')->order_by('idProdutos', 'desc')->limit($limite, max(0, $offset))->get('produtos')->result();
    }

    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results('produtos');
    }

    /**
     * Gera o próximo código sequencial de produto para a empresa (1, 2, 3...).
     */
    public function gerarProximoCodigo(int $empresaId): int
    {
        $ultimo = $this->db->select_max('codigo')
            ->where('empresa_id', $empresaId)
            ->get('produtos')
            ->row();

        return ($ultimo && (int) $ultimo->codigo > 0) ? ((int) $ultimo->codigo + 1) : 1;
    }

    /**
     * pesquisa procura na descrição, código de barras e código interno; estoque=baixo
     * mostra quem está no mínimo ou abaixo dele (com mínimo definido).
     * Filtra sempre pela empresa ativa do usuário.
     */
    private function aplicarFiltros(array $filtros): void
    {
        $this->db->where('empresa_id', empresa_ativa_id());

        if (($filtros['pesquisa'] ?? '') !== '') {
            $termo = $filtros['pesquisa'];
            $this->db->group_start()
                ->like('descricao', $termo)
                ->or_like('codDeBarra', $termo);
            if (is_numeric($termo)) {
                $this->db->or_where('codigo', (int) $termo);
            }
            $this->db->group_end();
        }

        if (($filtros['estoque'] ?? '') === 'baixo') {
            $this->db->where('estoqueMinimo >', 0)->where('estoque <= estoqueMinimo', null, false);
        }
    }

    public function getById($id)
    {
        $this->db->where('idProdutos', $id);
        $this->db->where('empresa_id', empresa_ativa_id());
        $this->db->limit(1);

        return $this->db->get('produtos')->row();
    }

    /**
     * Gera o próximo ID sequencial de produto (1, 2, 3...) para evitar saltos de auto-increment do TiDB.
     */
    public function gerarProximoId(): int
    {
        $ultimo = $this->db->select_max('idProdutos')->get('produtos')->row();

        return ($ultimo && (int) $ultimo->idProdutos > 0) ? ((int) $ultimo->idProdutos + 1) : 1;
    }

    public function add($table, $data)
    {
        if ($table === 'produtos') {
            if (empty($data['idProdutos'])) {
                $data['idProdutos'] = $this->gerarProximoId();
            }
            if (empty($data['empresa_id'])) {
                $data['empresa_id'] = empresa_ativa_id();
            }
        }

        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
            return true;
        }

        return false;
    }

    public function edit($table, $data, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        if ($table === 'produtos' || $this->db->field_exists('empresa_id', $table)) {
            $this->db->where('empresa_id', empresa_ativa_id());
        }
        $this->db->update($table, $data);

        if ($this->db->affected_rows() >= 0) {
            return true;
        }

        return false;
    }

    public function delete($table, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        if ($table === 'produtos' || $this->db->field_exists('empresa_id', $table)) {
            $this->db->where('empresa_id', empresa_ativa_id());
        }
        $this->db->delete($table);
        if ($this->db->affected_rows() == '1') {
            return true;
        }

        return false;
    }

    public function count($table)
    {
        if ($table === 'produtos' || $this->db->field_exists('empresa_id', $table)) {
            $this->db->where('empresa_id', empresa_ativa_id());
        }

        return $this->db->count_all_results($table);
    }

    public function updateEstoque($produto, $quantidade, $operacao = '-')
    {
        $sql = "UPDATE produtos set estoque = estoque $operacao ? WHERE idProdutos = ? AND empresa_id = ?";

        return $this->db->query($sql, [$quantidade, $produto, empresa_ativa_id()]);
    }
}
