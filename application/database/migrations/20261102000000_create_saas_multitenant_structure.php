<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Estrutura Multi-Tenant e Módulo de Assinaturas SaaS (#SaaS-01).
 *
 * Cria as tabelas saas_planos, saas_empresas, saas_empresa_usuarios e saas_assinaturas.
 * Adiciona a coluna empresa_id nas tabelas operacionais e is_superadmin em usuarios.
 */
class Migration_create_saas_multitenant_structure extends CI_Migration
{
    public function up()
    {
        // 1. Tabela saas_planos
        if (! $this->db->table_exists('saas_planos')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'nome' => ['type' => 'VARCHAR', 'constraint' => 100],
                'slug' => ['type' => 'VARCHAR', 'constraint' => 50],
                'valor' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
                'limite_usuarios' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'limite_os_mes' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'ativo' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('saas_planos', true, ['ENGINE' => 'InnoDB']);
        }

        // 2. Tabela saas_empresas
        if (! $this->db->table_exists('saas_empresas')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'razao_social' => ['type' => 'VARCHAR', 'constraint' => 255],
                'nome_fantasia' => ['type' => 'VARCHAR', 'constraint' => 255],
                'documento' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'telefone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'email' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'plano_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
                'dias_restantes' => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
                'data_vencimento' => ['type' => 'DATE', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ativa'],
                'desconto_percentual' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'forma_pagamento_preferida' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PIX'],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('status');
            $this->dbforge->create_table('saas_empresas', true, ['ENGINE' => 'InnoDB']);
        }

        // 3. Tabela saas_empresa_usuarios
        if (! $this->db->table_exists('saas_empresa_usuarios')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'empresa_id' => ['type' => 'INT', 'constraint' => 11],
                'usuario_id' => ['type' => 'INT', 'constraint' => 11],
                'papel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'dono'],
                'ativo' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'criado_em' => ['type' => 'DATETIME'],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('empresa_id');
            $this->dbforge->add_key('usuario_id');
            $this->dbforge->create_table('saas_empresa_usuarios', true, ['ENGINE' => 'InnoDB']);
        }

        // 4. Tabela saas_assinaturas
        if (! $this->db->table_exists('saas_assinaturas')) {
            $this->dbforge->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'empresa_id' => ['type' => 'INT', 'constraint' => 11],
                'plano_id' => ['type' => 'INT', 'constraint' => 11],
                'valor' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
                'forma_pagamento' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PIX'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pendente'],
                'dias_adicionados' => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
                'txid' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'qr_code' => ['type' => 'TEXT', 'null' => true],
                'qr_code_image' => ['type' => 'TEXT', 'null' => true],
                'criado_em' => ['type' => 'DATETIME'],
                'pago_em' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('empresa_id');
            $this->dbforge->add_key('status');
            $this->dbforge->create_table('saas_assinaturas', true, ['ENGINE' => 'InnoDB']);
        }

        // 5. Adicionar is_superadmin em usuarios
        if (! $this->db->field_exists('is_superadmin', 'usuarios')) {
            $this->dbforge->add_column('usuarios', [
                'is_superadmin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            ]);
        }

        // 6. Adicionar empresa_id nas tabelas operacionais
        $tabelas = [
            'clientes',
            'os',
            'produtos',
            'servicos',
            'vendas',
            'lancamentos',
            'garantias',
            'arquivos',
            'cobrancas',
            'emitente',
        ];

        foreach ($tabelas as $tab) {
            if ($this->db->table_exists($tab) && ! $this->db->field_exists('empresa_id', $tab)) {
                $this->dbforge->add_column($tab, [
                    'empresa_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1, 'null' => false],
                ]);
                $this->db->query("CREATE INDEX idx_{$tab}_empresa_id ON " . $this->db->protect_identifiers($tab) . ' (empresa_id)');
            }
        }

        // 7. Seed de dados iniciais
        // Planos Padrão
        $qtdPlanos = $this->db->count_all_results('saas_planos');
        if ($qtdPlanos === 0) {
            $this->db->insert_batch('saas_planos', [
                [
                    'id' => 1,
                    'nome' => 'Básico',
                    'slug' => 'basico',
                    'valor' => 10.00,
                    'limite_usuarios' => 2,
                    'limite_os_mes' => 50,
                    'ativo' => 1,
                    'criado_em' => date('Y-m-d H:i:s'),
                ],
                [
                    'id' => 2,
                    'nome' => 'Essencial',
                    'slug' => 'essencial',
                    'valor' => 37.90,
                    'limite_usuarios' => 0,
                    'limite_os_mes' => 0,
                    'ativo' => 1,
                    'criado_em' => date('Y-m-d H:i:s'),
                ],
            ]);
        }

        // Empresa 1 Inicial
        $qtdEmpresas = $this->db->count_all_results('saas_empresas');
        if ($qtdEmpresas === 0) {
            $hojeMaisAno = date('Y-m-d', strtotime('+365 days'));
            $this->db->insert('saas_empresas', [
                'id' => 1,
                'razao_social' => 'Empresa Principal',
                'nome_fantasia' => 'Empresa Principal',
                'email' => 'admin@admin.com',
                'plano_id' => 2,
                'dias_restantes' => 365,
                'data_vencimento' => $hojeMaisAno,
                'status' => 'ativa',
                'desconto_percentual' => 0,
                'forma_pagamento_preferida' => 'PIX',
                'criado_em' => date('Y-m-d H:i:s'),
            ]);
        }

        // Vínculo do usuário 1 como Dono da Empresa 1 e Superadmin
        if ($this->db->table_exists('usuarios')) {
            $this->db->where('idUsuarios', 1)->update('usuarios', ['is_superadmin' => 1]);

            $temVinculo = $this->db->where('empresa_id', 1)->where('usuario_id', 1)->count_all_results('saas_empresa_usuarios') > 0;
            if (! $temVinculo) {
                $this->db->insert('saas_empresa_usuarios', [
                    'empresa_id' => 1,
                    'usuario_id' => 1,
                    'papel' => 'dono',
                    'ativo' => 1,
                    'criado_em' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('saas_assinaturas', true);
        $this->dbforge->drop_table('saas_empresa_usuarios', true);
        $this->dbforge->drop_table('saas_empresas', true);
        $this->dbforge->drop_table('saas_planos', true);

        if ($this->db->field_exists('is_superadmin', 'usuarios')) {
            $this->dbforge->drop_column('usuarios', 'is_superadmin');
        }

        $tabelas = [
            'clientes', 'os', 'produtos', 'servicos', 'vendas',
            'lancamentos', 'garantias', 'arquivos', 'cobrancas', 'emitente',
        ];
        foreach ($tabelas as $tab) {
            if ($this->db->table_exists($tab) && $this->db->field_exists('empresa_id', $tab)) {
                $this->dbforge->drop_column($tab, 'empresa_id');
            }
        }
    }
}
