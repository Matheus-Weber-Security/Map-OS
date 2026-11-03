<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Garante que o nome do sistema na tabela configuracoes seja 'Nexus Ticket'.
 */
class Migration_set_app_name_nexus_ticket extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('configuracoes')) {
            $existe = $this->db->where('config', 'app_name')->count_all_results('configuracoes') > 0;
            if ($existe) {
                $this->db->where('config', 'app_name')->update('configuracoes', ['valor' => 'Nexus Ticket']);
            } else {
                $this->db->insert('configuracoes', ['config' => 'app_name', 'valor' => 'Nexus Ticket']);
            }
        }
    }

    public function down()
    {
    }
}
