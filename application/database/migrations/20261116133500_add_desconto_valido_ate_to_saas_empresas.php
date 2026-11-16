<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_desconto_valido_ate_to_saas_empresas extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('saas_empresas') && ! $this->db->field_exists('desconto_valido_ate', 'saas_empresas')) {
            $this->dbforge->add_column('saas_empresas', [
                'desconto_valido_ate' => [
                    'type' => 'DATE',
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->table_exists('saas_empresas') && $this->db->field_exists('desconto_valido_ate', 'saas_empresas')) {
            $this->dbforge->drop_column('saas_empresas', 'desconto_valido_ate');
        }
    }
}
