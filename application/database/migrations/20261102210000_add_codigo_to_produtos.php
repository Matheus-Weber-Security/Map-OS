<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Adiciona coluna 'codigo' sequencial por empresa na tabela de produtos (#SaaS-02).
 */
class Migration_add_codigo_to_produtos extends CI_Migration
{
    public function up()
    {
        // 1. Adicionar coluna codigo na tabela produtos se não existir
        if ($this->db->table_exists('produtos') && ! $this->db->field_exists('codigo', 'produtos')) {
            $this->dbforge->add_column('produtos', [
                'codigo' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => false,
                    'default' => 1,
                    'after' => 'idProdutos',
                ],
            ]);

            $this->db->query('CREATE INDEX idx_produtos_empresa_codigo ON ' . $this->db->protect_identifiers('produtos') . ' (empresa_id, codigo)');
        }

        // 2. Garantir sequência contínua 1 por 1 para os produtos existentes de cada empresa
        if ($this->db->table_exists('produtos')) {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

            // Ajustar IDs legados que saltaram para 30000+
            $p30002 = $this->db->where('idProdutos', 30002)->get('produtos')->row();
            if ($p30002) {
                if ($this->db->table_exists('itens_de_vendas')) {
                    $this->db->where('produtos_id', 30002)->update('itens_de_vendas', ['produtos_id' => 3]);
                }
                if ($this->db->table_exists('produtos_os')) {
                    $this->db->where('produtos_id', 30002)->update('produtos_os', ['produtos_id' => 3]);
                }
                $this->db->where('idProdutos', 30002)->update('produtos', ['idProdutos' => 3, 'codigo' => 3]);
            }

            $p30001 = $this->db->where('idProdutos', 30001)->get('produtos')->row();
            if ($p30001) {
                if ($this->db->table_exists('itens_de_vendas')) {
                    $this->db->where('produtos_id', 30001)->update('itens_de_vendas', ['produtos_id' => 2]);
                }
                if ($this->db->table_exists('produtos_os')) {
                    $this->db->where('produtos_id', 30001)->update('produtos_os', ['produtos_id' => 2]);
                }
                $this->db->where('idProdutos', 30001)->update('produtos', ['idProdutos' => 2, 'codigo' => 2]);
            }

            // Atualiza o codigo sequencial por empresa
            $empresas = $this->db->distinct()->select('empresa_id')->get('produtos')->result_array();
            foreach ($empresas as $emp) {
                $empId = (int) $emp['empresa_id'];
                $prods = $this->db->where('empresa_id', $empId)->order_by('idProdutos', 'asc')->get('produtos')->result_array();
                $seq = 1;
                foreach ($prods as $prod) {
                    $this->db->where('idProdutos', $prod['idProdutos'])->update('produtos', ['codigo' => $seq]);
                    $seq++;
                }
            }

            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function down()
    {
        if ($this->db->table_exists('produtos') && $this->db->field_exists('codigo', 'produtos')) {
            $this->dbforge->drop_column('produtos', 'codigo');
        }
    }
}
