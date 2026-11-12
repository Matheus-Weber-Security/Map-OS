<?php

class Servicos_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields);
        $this->db->from($table);
        if ($table === 'servicos') {
            $this->db->where('empresa_id', empresa_ativa_id());
        }
        $this->db->order_by('idServicos', 'desc');
        $this->db->limit($perpage, $start);
        if ($where) {
            $this->db->like('nome', $where);
            $this->db->or_like('descricao', $where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    /**
     * Serviços da listagem (#2841), do mais recente para o mais antigo.
     *
     * @param  array{pesquisa?: string}  $filtros  Saída de listagemFiltros()
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->aplicarFiltros($filtros);

        return $this->db->order_by('idServicos', 'desc')->limit($limite, max(0, $offset))->get('servicos')->result();
    }

    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results('servicos');
    }

    private function aplicarFiltros(array $filtros): void
    {
        $this->db->where('servicos.empresa_id', empresa_ativa_id());

        if (($filtros['pesquisa'] ?? '') !== '') {
            $this->db->group_start()->like('nome', $filtros['pesquisa'])->or_like('descricao', $filtros['pesquisa'])->group_end();
        }
    }

    public function getById($id)
    {
        $this->db->where('idServicos', $id);
        $this->db->where('empresa_id', empresa_ativa_id());
        $this->db->limit(1);

        return $this->db->get('servicos')->row();
    }

    /**
     * Gera o próximo ID sequencial de serviço (1, 2, 3...) para evitar saltos de auto-increment do TiDB.
     */
    public function gerarProximoId(): int
    {
        $ultimo = $this->db->select_max('idServicos')->get('servicos')->row();

        return ($ultimo && (int) $ultimo->idServicos > 0) ? ((int) $ultimo->idServicos + 1) : 1;
    }

    public function add($table, $data)
    {
        if ($table === 'servicos') {
            $data['empresa_id'] = $data['empresa_id'] ?? empresa_ativa_id();
            if (empty($data['idServicos'])) {
                $data['idServicos'] = $this->gerarProximoId();
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
        if ($table === 'servicos') {
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
        if ($table === 'servicos') {
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
        if ($table === 'servicos') {
            return $this->db->where('empresa_id', empresa_ativa_id())->count_all_results('servicos');
        }

        return $this->db->count_all($table);
    }
}
