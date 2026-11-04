<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Módulo de Compras e Importação de NF-e (#Compras-01).
 *
 * Cria as tabelas compras, itens_de_compras e produtos_fornecedores (De-Para).
 * Adiciona as novas permissões de compras aos perfis existentes.
 */
class Migration_create_compras_structure extends CI_Migration
{
    public function up()
    {
        // 1. Tabela compras
        if (! $this->db->table_exists('compras')) {
            $this->dbforge->add_field([
                'idCompras' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'empresa_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 1,
                    'null' => false,
                ],
                'fornecedores_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'usuarios_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'dataCompra' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'valorTotal' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0.00,
                ],
                'desconto' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0.00,
                ],
                'valor_desconto' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0.00,
                ],
                'tipo_desconto' => [
                    'type' => 'VARCHAR',
                    'constraint' => 8,
                    'default' => 'real',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'Concluída',
                ],
                'faturado' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'observacoes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'chave_nfe' => [
                    'type' => 'VARCHAR',
                    'constraint' => 44,
                    'null' => true,
                ],
                'numero_nfe' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                ],
                'serie_nfe' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'null' => true,
                ],
                'data_emissao_nfe' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'criado_em' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->dbforge->add_key('idCompras', true);
            $this->dbforge->add_key('empresa_id');
            $this->dbforge->add_key('fornecedores_id');
            $this->dbforge->add_key('usuarios_id');
            $this->dbforge->add_key('chave_nfe');
            $this->dbforge->create_table('compras', true, ['ENGINE' => 'InnoDB']);
        }

        // 2. Tabela itens_de_compras
        if (! $this->db->table_exists('itens_de_compras')) {
            $this->dbforge->add_field([
                'idItensCompras' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'compras_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'produtos_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'quantidade' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 1.00,
                ],
                'precoUnitario' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0.00,
                ],
                'subTotal' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0.00,
                ],
                'codigo_fornecedor' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
            ]);
            $this->dbforge->add_key('idItensCompras', true);
            $this->dbforge->add_key('compras_id');
            $this->dbforge->add_key('produtos_id');
            $this->dbforge->create_table('itens_de_compras', true, ['ENGINE' => 'InnoDB']);
        }

        // 3. Tabela produtos_fornecedores (De-Para inteligente)
        if (! $this->db->table_exists('produtos_fornecedores')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'empresa_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 1,
                    'null' => false,
                ],
                'fornecedor_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'codigo_fornecedor' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => false,
                ],
                'produto_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                ],
                'fator_conversao' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                    'default' => 1.0000,
                ],
                'criado_em' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key(['empresa_id', 'fornecedor_id', 'codigo_fornecedor']);
            $this->dbforge->add_key('produto_id');
            $this->dbforge->create_table('produtos_fornecedores', true, ['ENGINE' => 'InnoDB']);
        }

        // 4. Atualizar permissões existentes
        $perfis = $this->db->get('permissoes')->result_array();
        foreach ($perfis as $perfil) {
            $raw = (string) ($perfil['permissoes'] ?? '');
            if (trim($raw) === '') {
                continue;
            }
            $dados = json_decode($raw, true);
            if (! is_array($dados)) {
                $dados = @unserialize($raw);
            }
            if (is_array($dados)) {
                // Se o perfil já tinha permissão de Vendas, concede permissão de Compras
                $temVenda = ! empty($dados['vVenda']);
                $dados['vCompra'] = $temVenda ? 1 : 0;
                $dados['aCompra'] = $temVenda ? 1 : 0;
                $dados['eCompra'] = $temVenda ? 1 : 0;
                $dados['dCompra'] = $temVenda ? 1 : 0;
                $dados['rCompra'] = $temVenda ? 1 : 0;

                $this->db->where('idPermissao', $perfil['idPermissao'])->update('permissoes', [
                    'permissoes' => json_encode($dados),
                ]);
            }
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('produtos_fornecedores', true);
        $this->dbforge->drop_table('itens_de_compras', true);
        $this->dbforge->drop_table('compras', true);
    }
}
