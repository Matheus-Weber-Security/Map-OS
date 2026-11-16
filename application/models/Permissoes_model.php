<?php

class Permissoes_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields);
        $this->db->from($table);
        $this->db->order_by('idPermissao', 'desc');
        $this->db->limit($perpage, $start);
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    /**
     * Grupos de permissão com quantos usuários usam cada um, para a listagem
     * da v5 (#2846). São poucos: sem paginação.
     */
    public function listarComUsuarios(): array
    {
        $subqueryUsuarios = '(SELECT COUNT(*) FROM usuarios WHERE usuarios.permissoes_id = permissoes.idPermissao)';

        if ($this->db->table_exists('saas_empresa_usuarios')) {
            $empresaId = (int) (function_exists('empresa_ativa_id') ? empresa_ativa_id() : 1);
            $subqueryUsuarios = "(SELECT COUNT(*) FROM usuarios 
                INNER JOIN saas_empresa_usuarios ON saas_empresa_usuarios.usuario_id = usuarios.idUsuarios 
                WHERE usuarios.permissoes_id = permissoes.idPermissao 
                  AND saas_empresa_usuarios.empresa_id = {$empresaId})";
        }

        return $this->db
            ->select('permissoes.idPermissao, permissoes.nome, permissoes.data, permissoes.situacao')
            ->select("{$subqueryUsuarios} AS usuarios", false)
            ->from('permissoes')
            ->order_by('permissoes.nome', 'ASC')
            ->get()
            ->result();
    }

    public function getActive($table, $fields)
    {
        $this->db->select($fields);
        $this->db->from($table);
        $this->db->where('situacao', 1);
        $query = $this->db->get();

        return $query->result();

    }

    public function getById($id)
    {
        $this->db->where('idPermissao', $id);
        $this->db->limit(1);

        return $this->db->get('permissoes')->row();
    }

    public function add($table, $data)
    {
        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
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
}

/* End of file permissoes_model.php */
/* Location: ./application/models/permissoes_model.php */
