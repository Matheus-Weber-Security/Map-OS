<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Make_configuracoes_multitenant extends CI_Migration
{
    public function up()
    {
        if (! $this->db->table_exists('configuracoes')) {
            return;
        }

        // 1. Adiciona coluna empresa_id se não existir
        if (! $this->db->field_exists('empresa_id', 'configuracoes')) {
            $this->dbforge->add_column('configuracoes', [
                'empresa_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 1,
                    'null' => false,
                    'after' => 'idConfig',
                ],
            ]);
        }

        // 2. Garante que todos os registros existentes pertençam à empresa 1
        $this->db->query('UPDATE ' . $this->db->protect_identifiers('configuracoes') . ' SET `empresa_id` = 1 WHERE `empresa_id` IS NULL OR `empresa_id` = 0');

        // 3. Expande a coluna config para suportar chaves longas de gateways e integrações (até 120 caracteres)
        $this->db->query('ALTER TABLE ' . $this->db->protect_identifiers('configuracoes') . ' MODIFY COLUMN `config` VARCHAR(120) NOT NULL');

        // 4. Remove índices únicos antigos na coluna config (ex: unique_valor ou config)
        $indices = $this->db->query('SHOW INDEX FROM ' . $this->db->protect_identifiers('configuracoes'))->result_array();
        foreach ($indices as $idx) {
            $keyName = $idx['Key_name'] ?? '';
            $columnName = $idx['Column_name'] ?? '';
            $nonUnique = (int) ($idx['Non_unique'] ?? 1);

            if ($keyName !== 'PRIMARY' && $nonUnique === 0 && ($keyName === 'unique_valor' || $columnName === 'config')) {
                $this->db->query('ALTER TABLE ' . $this->db->protect_identifiers('configuracoes') . ' DROP INDEX ' . $this->db->escape_identifiers($keyName));
                break;
            }
        }

        // 5. Cria índice único composto (empresa_id, config) se não existir
        $indicesAtualizados = $this->db->query('SHOW INDEX FROM ' . $this->db->protect_identifiers('configuracoes'))->result_array();
        $temIndiceComposto = false;
        foreach ($indicesAtualizados as $idx) {
            if (($idx['Key_name'] ?? '') === 'idx_configuracoes_empresa_config') {
                $temIndiceComposto = true;
                break;
            }
        }

        if (! $temIndiceComposto) {
            $this->db->query('ALTER TABLE ' . $this->db->protect_identifiers('configuracoes') . ' ADD UNIQUE INDEX `idx_configuracoes_empresa_config` (`empresa_id`, `config`)');
        }
    }

    public function down()
    {
        if (! $this->db->table_exists('configuracoes')) {
            return;
        }

        if ($this->db->field_exists('empresa_id', 'configuracoes')) {
            $indices = $this->db->query('SHOW INDEX FROM ' . $this->db->protect_identifiers('configuracoes'))->result_array();
            foreach ($indices as $idx) {
                if (($idx['Key_name'] ?? '') === 'idx_configuracoes_empresa_config') {
                    $this->db->query('ALTER TABLE ' . $this->db->protect_identifiers('configuracoes') . ' DROP INDEX `idx_configuracoes_empresa_config`');
                    break;
                }
            }

            // Remove configurações criadas por outras empresas antes de restaurar unicidade
            $this->db->where('empresa_id >', 1)->delete('configuracoes');

            $this->dbforge->drop_column('configuracoes', 'empresa_id');
            $this->db->query('ALTER TABLE ' . $this->db->protect_identifiers('configuracoes') . ' ADD UNIQUE INDEX `unique_valor` (`config`)');
        }
    }
}
