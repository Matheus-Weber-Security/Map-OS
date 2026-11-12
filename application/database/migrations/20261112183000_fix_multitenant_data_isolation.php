<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Fix_multitenant_data_isolation extends CI_Migration
{
    public function up()
    {
        // 1. Garantir que a tabela documentos possua empresa_id
        if ($this->db->table_exists('documentos') && ! $this->db->field_exists('empresa_id', 'documentos')) {
            $this->dbforge->add_column('documentos', [
                'empresa_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1, 'null' => false],
            ]);
            $this->db->query('CREATE INDEX idx_documentos_empresa_id ON ' . $this->db->protect_identifiers('documentos') . ' (empresa_id)');
        }

        // 2. Garantir que a tabela logs possua empresa_id
        if ($this->db->table_exists('logs') && ! $this->db->field_exists('empresa_id', 'logs')) {
            $this->dbforge->add_column('logs', [
                'empresa_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1, 'null' => false],
            ]);
            $this->db->query('CREATE INDEX idx_logs_empresa_id ON ' . $this->db->protect_identifiers('logs') . ' (empresa_id)');
        }

        // 3. Realocação de dados criados por usuários de outras empresas gravados com empresa_id = 1
        if ($this->db->table_exists('saas_empresa_usuarios')) {
            $vinculos = $this->db->select('empresa_id, usuario_id')
                ->where('empresa_id >', 1)
                ->get('saas_empresa_usuarios')
                ->result();

            foreach ($vinculos as $v) {
                $empresaId = (int) $v->empresa_id;
                $usuarioId = (int) $v->usuario_id;

                // Corrige OS criadas por este usuário
                if ($this->db->table_exists('os') && $this->db->field_exists('empresa_id', 'os')) {
                    $this->db->where('usuarios_id', $usuarioId)->where('empresa_id', 1)->update('os', ['empresa_id' => $empresaId]);
                }

                // Corrige Vendas criadas por este usuário
                if ($this->db->table_exists('vendas') && $this->db->field_exists('empresa_id', 'vendas')) {
                    $this->db->where('usuarios_id', $usuarioId)->where('empresa_id', 1)->update('vendas', ['empresa_id' => $empresaId]);
                }

                // Corrige Lançamentos financeiros deste usuário
                if ($this->db->table_exists('lancamentos') && $this->db->field_exists('empresa_id', 'lancamentos')) {
                    $this->db->where('usuarios_id', $usuarioId)->where('empresa_id', 1)->update('lancamentos', ['empresa_id' => $empresaId]);
                }

                // Corrige Termos de Garantia deste usuário
                if ($this->db->table_exists('garantias') && $this->db->field_exists('empresa_id', 'garantias')) {
                    $this->db->where('usuarios_id', $usuarioId)->where('empresa_id', 1)->update('garantias', ['empresa_id' => $empresaId]);
                }

                // Corrige Logs deste usuário
                if ($this->db->table_exists('logs') && $this->db->field_exists('empresa_id', 'logs')) {
                    $user = $this->db->select('nome')->where('idUsuarios', $usuarioId)->get('usuarios')->row();
                    if ($user && ! empty($user->nome)) {
                        $this->db->where('usuario', $user->nome)->where('empresa_id', 1)->update('logs', ['empresa_id' => $empresaId]);
                    }
                }

                // Corrige Clientes vinculados exclusivamente a OS ou Vendas desta empresa
                if ($this->db->table_exists('clientes') && $this->db->field_exists('empresa_id', 'clientes')) {
                    $this->db->query("
                        UPDATE clientes 
                        SET empresa_id = {$empresaId} 
                        WHERE empresa_id = 1 
                        AND idClientes IN (SELECT clientes_id FROM os WHERE empresa_id = {$empresaId})
                        AND idClientes NOT IN (SELECT clientes_id FROM os WHERE empresa_id != {$empresaId})
                    ");
                }

                // Corrige Cobranças geradas para OS ou Vendas desta empresa
                if ($this->db->table_exists('cobrancas') && $this->db->field_exists('empresa_id', 'cobrancas')) {
                    $this->db->query("
                        UPDATE cobrancas 
                        SET empresa_id = {$empresaId} 
                        WHERE empresa_id = 1 
                        AND os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})
                    ");
                    $this->db->query("
                        UPDATE cobrancas 
                        SET empresa_id = {$empresaId} 
                        WHERE empresa_id = 1 
                        AND vendas_id IN (SELECT idVendas FROM vendas WHERE empresa_id = {$empresaId})
                    ");
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('documentos') && $this->db->field_exists('empresa_id', 'documentos')) {
            $this->dbforge->drop_column('documentos', 'empresa_id');
        }

        if ($this->db->table_exists('logs') && $this->db->field_exists('empresa_id', 'logs')) {
            $this->dbforge->drop_column('logs', 'empresa_id');
        }
    }
}
