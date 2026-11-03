<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Adiciona configurações para permitir/impedir editar e excluir lançamentos financeiros já pagos.
 */
class Migration_add_controle_edicao_exclusao_financeiro extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('configuracoes')) {
            $configs = [
                'control_edit_pago' => '0',
                'control_del_pago' => '0',
            ];

            foreach ($configs as $config => $valor) {
                $existe = $this->db->where('config', $config)->count_all_results('configuracoes') > 0;
                if (! $existe) {
                    $this->db->insert('configuracoes', [
                        'config' => $config,
                        'valor' => $valor,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('configuracoes')) {
            $this->db->where_in('config', ['control_edit_pago', 'control_del_pago'])->delete('configuracoes');
        }
    }
}
