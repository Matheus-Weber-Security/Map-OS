<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Adiciona a coluna 'codigo' sequencial por empresa (iniciando do 1) em todas as tabelas principais (#MultiTenant-Sequencial).
 */
class Migration_Add_sequential_codigo_to_multitenant_tables extends CI_Migration
{
    private array $tabelas = [
        'clientes'    => 'idClientes',
        'os'          => 'idOs',
        'vendas'      => 'idVendas',
        'servicos'    => 'idServicos',
        'compras'     => 'idCompras',
        'garantias'   => 'idGarantias',
        'lancamentos' => 'idLancamentos',
        'documentos'  => 'idDocumentos',
        'cobrancas'   => 'idCobranca',
    ];

    public function up()
    {
        foreach ($this->tabelas as $tabela => $pk) {
            if (! $this->db->table_exists($tabela)) {
                continue;
            }

            // 1. Adiciona coluna 'codigo' se não existir
            if (! $this->db->field_exists('codigo', $tabela)) {
                $this->dbforge->add_column($tabela, [
                    'codigo' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'null' => true,
                        'default' => null,
                        'after' => $pk,
                    ],
                ]);

                if ($this->db->field_exists('empresa_id', $tabela)) {
                    $indiceNome = "idx_{$tabela}_empresa_codigo";
                    $this->db->query("CREATE INDEX `{$indiceNome}` ON " . $this->db->protect_identifiers($tabela) . " (`empresa_id`, `codigo`)");
                }
            }

            // 2. Enumera retrospectivamente todos os registros existentes por empresa a partir de 1
            if ($this->db->field_exists('empresa_id', $tabela) && $this->db->field_exists('codigo', $tabela)) {
                $empresas = $this->db->distinct()->select('empresa_id')->get($tabela)->result_array();
                foreach ($empresas as $emp) {
                    $empId = (int) ($emp['empresa_id'] ?? 1);
                    $registros = $this->db->where('empresa_id', $empId)
                        ->order_by($pk, 'asc')
                        ->get($tabela)
                        ->result_array();

                    $seq = 1;
                    foreach ($registros as $reg) {
                        $this->db->where($pk, $reg[$pk])->update($tabela, ['codigo' => $seq]);
                        $seq++;
                    }
                }
            }
        }

        // 3. Garante que produtos também tenha sequência contínua a partir de 1 por empresa
        if ($this->db->table_exists('produtos') && $this->db->field_exists('codigo', 'produtos') && $this->db->field_exists('empresa_id', 'produtos')) {
            $empresas = $this->db->distinct()->select('empresa_id')->get('produtos')->result_array();
            foreach ($empresas as $emp) {
                $empId = (int) ($emp['empresa_id'] ?? 1);
                $registros = $this->db->where('empresa_id', $empId)
                    ->order_by('idProdutos', 'asc')
                    ->get('produtos')
                    ->result_array();

                $seq = 1;
                foreach ($registros as $reg) {
                    $this->db->where('idProdutos', $reg['idProdutos'])->update('produtos', ['codigo' => $seq]);
                    $seq++;
                }
            }
        }
    }

    public function down()
    {
        foreach ($this->tabelas as $tabela => $pk) {
            if ($this->db->table_exists($tabela) && $this->db->field_exists('codigo', $tabela)) {
                $this->dbforge->drop_column($tabela, 'codigo');
            }
        }
    }
}
