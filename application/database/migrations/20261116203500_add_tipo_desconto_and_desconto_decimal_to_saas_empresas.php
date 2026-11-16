<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_tipo_desconto_and_desconto_decimal_to_saas_empresas extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('saas_empresas')) {
            if (! $this->db->field_exists('tipo_desconto', 'saas_empresas')) {
                $this->dbforge->add_column('saas_empresas', [
                    'tipo_desconto' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'default' => 'porcentagem',
                    ],
                ]);
            }

            if (! $this->db->field_exists('desconto_valor', 'saas_empresas')) {
                $this->dbforge->add_column('saas_empresas', [
                    'desconto_valor' => [
                        'type' => 'DECIMAL',
                        'constraint' => '10,2',
                        'default' => 0.00,
                    ],
                ]);
            }

            if ($this->db->field_exists('desconto_percentual', 'saas_empresas')) {
                $this->db->query('ALTER TABLE `saas_empresas` MODIFY `desconto_percentual` DECIMAL(5,2) NOT NULL DEFAULT 0.00');
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('saas_empresas')) {
            if ($this->db->field_exists('tipo_desconto', 'saas_empresas')) {
                $this->dbforge->drop_column('saas_empresas', 'tipo_desconto');
            }

            if ($this->db->field_exists('desconto_valor', 'saas_empresas')) {
                $this->dbforge->drop_column('saas_empresas', 'desconto_valor');
            }

            if ($this->db->field_exists('desconto_percentual', 'saas_empresas')) {
                $this->db->query('ALTER TABLE `saas_empresas` MODIFY `desconto_percentual` INT(11) NOT NULL DEFAULT 0');
            }
        }
    }
}
