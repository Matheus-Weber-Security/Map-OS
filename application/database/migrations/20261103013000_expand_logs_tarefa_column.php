<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Expande a coluna tarefa da tabela logs de VARCHAR(100) para VARCHAR(255)
 * para comportar mensagens detalhadas de auditoria sem estourar o limite no MySQL (Erro 1406).
 */
class Migration_expand_logs_tarefa_column extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('logs') && $this->db->field_exists('tarefa', 'logs')) {
            $this->db->query('ALTER TABLE `logs` MODIFY `tarefa` VARCHAR(255) NULL');
        }
    }

    public function down()
    {
        if ($this->db->table_exists('logs') && $this->db->field_exists('tarefa', 'logs')) {
            $this->db->query('ALTER TABLE `logs` MODIFY `tarefa` VARCHAR(100) NULL');
        }
    }
}
