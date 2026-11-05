<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Adiciona configuração para dar baixa automática no financeiro ao pagar boleto/cobrança.
 */
class Migration_add_baixa_automatica_cobranca extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('configuracoes')) {
            // Garante tamanho suficiente para chaves de configuração
            $this->db->query('ALTER TABLE `configuracoes` MODIFY `config` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');

            $existe = $this->db->where('config', 'baixa_auto_cobranca')->count_all_results('configuracoes') > 0;
            if (! $existe) {
                $this->db->insert('configuracoes', [
                    'config' => 'baixa_auto_cobranca',
                    'valor' => '0',
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('configuracoes')) {
            $this->db->where('config', 'baixa_auto_cobranca')->delete('configuracoes');
        }
    }
}
