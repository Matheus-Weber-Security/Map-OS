<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Isolamento multi-tenant da fila de e-mails, rastreamento de abertura/logs e
 * flag global para exigir 2FA também no login (por padrão apenas recuperação).
 */
class Migration_upgrade_emails_and_2fa_config extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('email_queue')) {
            $colunas = [
                'empresa_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1, 'null' => false],
                'tracking_token' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'enviado_em' => ['type' => 'DATETIME', 'null' => true],
                'lido_em' => ['type' => 'DATETIME', 'null' => true],
                'ultima_leitura_em' => ['type' => 'DATETIME', 'null' => true],
                'total_leituras' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'null' => false],
                'erro_mensagem' => ['type' => 'TEXT', 'null' => true],
            ];

            foreach ($colunas as $nome => $def) {
                if (! $this->db->field_exists($nome, 'email_queue')) {
                    $this->dbforge->add_column('email_queue', [$nome => $def]);
                }
            }

            if (! $this->indiceExiste('email_queue', 'idx_email_queue_empresa_id')) {
                $this->db->query('CREATE INDEX idx_email_queue_empresa_id ON ' . $this->db->protect_identifiers('email_queue') . ' (empresa_id)');
            }

            if (! $this->indiceExiste('email_queue', 'idx_email_queue_tracking_token')) {
                $this->db->query('CREATE UNIQUE INDEX idx_email_queue_tracking_token ON ' . $this->db->protect_identifiers('email_queue') . ' (tracking_token)');
            }
        }

        if (! $this->db->table_exists('email_logs')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'email_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
                'empresa_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1, 'null' => false],
                'evento' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
                'detalhes' => ['type' => 'TEXT', 'null' => true],
                'ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'criado_em' => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('email_id');
            $this->dbforge->add_key('empresa_id');
            $this->dbforge->create_table('email_logs', true, ['ENGINE' => 'InnoDB']);
        }

        if ($this->db->table_exists('configuracoes')) {
            $existe = $this->db->where('config', 'exigir_2fa_login')->count_all_results('configuracoes') > 0;
            if (! $existe) {
                $this->db->insert('configuracoes', [
                    'config' => 'exigir_2fa_login',
                    'valor' => '0',
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('email_logs')) {
            $this->dbforge->drop_table('email_logs', true);
        }

        if ($this->db->table_exists('email_queue')) {
            if ($this->indiceExiste('email_queue', 'idx_email_queue_tracking_token')) {
                $this->db->query('DROP INDEX idx_email_queue_tracking_token ON ' . $this->db->protect_identifiers('email_queue'));
            }
            if ($this->indiceExiste('email_queue', 'idx_email_queue_empresa_id')) {
                $this->db->query('DROP INDEX idx_email_queue_empresa_id ON ' . $this->db->protect_identifiers('email_queue'));
            }

            foreach (['erro_mensagem', 'total_leituras', 'ultima_leitura_em', 'lido_em', 'enviado_em', 'tracking_token', 'empresa_id'] as $col) {
                if ($this->db->field_exists($col, 'email_queue')) {
                    $this->dbforge->drop_column('email_queue', $col);
                }
            }
        }

        if ($this->db->table_exists('configuracoes')) {
            $this->db->where('config', 'exigir_2fa_login')->delete('configuracoes');
        }
    }

    private function indiceExiste(string $tabela, string $nomeIndice): bool
    {
        $res = $this->db->query('SHOW INDEX FROM ' . $this->db->protect_identifiers($tabela) . ' WHERE Key_name = ?', [$nomeIndice]);

        return $res && $res->num_rows() > 0;
    }
}
