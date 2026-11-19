<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_ativo_to_clientes extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('ativo', 'clientes')) {
            $this->dbforge->add_column('clientes', [
                'ativo' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => false,
                    'default' => 1,
                ],
            ]);

            // Garante que todos os clientes existentes fiquem ativos por padrão
            $this->db->update('clientes', ['ativo' => 1]);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('ativo', 'clientes')) {
            $this->dbforge->drop_column('clientes', 'ativo');
        }
    }
}
