<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_gateway_fields_to_saas_assinaturas extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('saas_assinaturas')) {
            $camposAdicionar = [];

            if (! $this->db->field_exists('gateway', 'saas_assinaturas')) {
                $camposAdicionar['gateway'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                    'default' => null,
                    'after' => 'forma_pagamento',
                ];
            }

            if (! $this->db->field_exists('charge_id', 'saas_assinaturas')) {
                $camposAdicionar['charge_id'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'default' => null,
                    'after' => 'txid',
                ];
            }

            if (! empty($camposAdicionar)) {
                $this->dbforge->add_column('saas_assinaturas', $camposAdicionar);
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('saas_assinaturas')) {
            if ($this->db->field_exists('charge_id', 'saas_assinaturas')) {
                $this->dbforge->drop_column('saas_assinaturas', 'charge_id');
            }
            if ($this->db->field_exists('gateway', 'saas_assinaturas')) {
                $this->dbforge->drop_column('saas_assinaturas', 'gateway');
            }
        }
    }
}
