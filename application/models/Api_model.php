<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * User_model class.
 */
class Api_model extends CI_Model
{
    public function lastRow($table, $idColumn)
    {
        return $this->db->select('*')->limit(1)->order_by($idColumn, 'DESC')->get($table)->row();
    }

    public function getRowById($table, $idColumn, $id)
    {
        $this->db->where($idColumn, $id);
        $this->db->limit(1);

        return $this->db->get($table)->row();
    }

    public function getUserByEmail($email)
    {
        $this->db->where('email', $email);
        $this->db->limit(1);

        return $this->db->get('usuarios')->row();
    }

    public function searchUsuario($search)
    {
        $this->db->select('usuarios.*');
        if ($this->db->table_exists('saas_empresa_usuarios')) {
            $this->db->join('saas_empresa_usuarios', 'saas_empresa_usuarios.usuario_id = usuarios.idUsuarios');
            $this->db->where('saas_empresa_usuarios.empresa_id', empresa_ativa_id());
        }
        $this->db->limit(5);
        $this->db->like('usuarios.nome', $search);
        $this->db->where('usuarios.situacao', 1);
        $query = $this->db->get('usuarios');
        if ($query->num_rows() > 0) {
            $usuarios = [];
            foreach ($query->result() as $row) {
                $usuarios[$row->idUsuarios]['label'] = $row->nome . ' | Telefone: ' . $row->telefone;
                $usuarios[$row->idUsuarios]['idUsuarios'] = $row->idUsuarios;
            }

            return (object) $usuarios;
        }

        return (object) [];
    }
}
