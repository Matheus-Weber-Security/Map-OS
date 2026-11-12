<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Expande a coluna descricao da tabela servicos de VARCHAR(45) para VARCHAR(100)
 * para comportar descrições de serviços mais completas sem erro no MySQL.
 */
class Migration_expand_servicos_descricao_column extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('servicos') && $this->db->field_exists('descricao', 'servicos')) {
            $this->db->query('ALTER TABLE `servicos` MODIFY `descricao` VARCHAR(100) NULL');
        }
    }

    public function down()
    {
        if ($this->db->table_exists('servicos') && $this->db->field_exists('descricao', 'servicos')) {
            $this->db->query('ALTER TABLE `servicos` MODIFY `descricao` VARCHAR(45) NULL');
        }
    }
}
