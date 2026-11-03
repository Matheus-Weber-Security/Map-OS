<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Corrige IDs de clientes que saltaram para a faixa de 30000+ devido ao cache de auto-increment do TiDB.
 */
class Migration_fix_sequential_id_clientes extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('clientes')) {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

            // Ajustar cliente 30001 para 2 se existir
            $c30001 = $this->db->where('idClientes', 30001)->get('clientes')->row();
            if ($c30001) {
                if ($this->db->table_exists('os')) {
                    $this->db->where('clientes_id', 30001)->update('os', ['clientes_id' => 2]);
                }
                if ($this->db->table_exists('vendas')) {
                    $this->db->where('clientes_id', 30001)->update('vendas', ['clientes_id' => 2]);
                }
                if ($this->db->table_exists('cobrancas')) {
                    $this->db->where('clientes_id', 30001)->update('cobrancas', ['clientes_id' => 2]);
                }
                if ($this->db->table_exists('lancamentos')) {
                    $this->db->where('clientes_id', 30001)->update('lancamentos', ['clientes_id' => 2]);
                }
                if ($this->db->table_exists('equipamentos')) {
                    $this->db->where('clientes_id', 30001)->update('equipamentos', ['clientes_id' => 2]);
                }
                $this->db->where('idClientes', 30001)->update('clientes', ['idClientes' => 2]);
            }

            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function down()
    {
        // Operação irreversível por manter consistência de chave estrangeira
    }
}
