<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Gerador de Backup Multi-Tenant para o Nexus Ticket / Map-OS (#SaaS-Backup).
 *
 * - Superadmin: pode gerar o backup completo de todas as tabelas (disaster recovery).
 * - Empresa (Tenant): gera um dump SQL isolado contendo estritamente os dados
 *   da empresa ativa (clientes, produtos, serviços, OS, vendas, financeiro, etc.),
 *   protegendo dados confidenciais de outros assinantes do SaaS e omitindo
 *   tabelas internas da plataforma (saas_empresas, saas_planos, saas_assinaturas, ci_sessions).
 */
class Backup_tenant
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
    }

    /**
     * Gera o backup em ZIP contendo o SQL apenas com os dados da empresa informada.
     */
    public function gerarBackupEmpresa(int $empresaId): string
    {
        $sql = $this->gerarSqlEmpresa($empresaId);
        $nomeSql = 'backup-empresa-' . $empresaId . '-' . date('Y-m-d') . '.sql';

        $this->CI->load->library('zip');
        $this->CI->zip->clear_data();
        $this->CI->zip->add_data($nomeSql, $sql);

        $zipData = $this->CI->zip->get_zip();
        $this->CI->zip->clear_data();

        return (string) $zipData;
    }

    /**
     * Gera o backup completo de todo o banco (para o SuperAdmin).
     */
    public function gerarBackupCompleto(): string
    {
        $this->CI->load->dbutil();
        $prefs = [
            'format' => 'zip',
            'foreign_key_checks' => false,
            'filename' => 'backup-completo-' . date('Y-m-d') . '.sql',
            'ignore' => [
                'ci_sessions',
                'login_attempts',
                'api_refresh_tokens',
                'api_rate_limit',
                'resets_de_senha',
                'usuarios_codigos_recuperacao',
                'email_queue',
            ],
        ];

        return $this->CI->dbutil->backup($prefs);
    }

    /**
     * Monta o SQL isolado com os registros pertencentes à empresa.
     */
    public function gerarSqlEmpresa(int $empresaId): string
    {
        $empresa = $this->CI->db->select('razao_social, nome_fantasia')->get_where('saas_empresas', ['id' => $empresaId])->row();
        $nomeEmpresa = $empresa ? ($empresa->nome_fantasia ?: $empresa->razao_social) : "Empresa #{$empresaId}";

        $output = "-- =============================================================\n";
        $output .= "-- Backup Nexus Ticket - Dados da Empresa\n";
        $output .= "-- Empresa: " . str_replace(["\r", "\n"], ' ', $nomeEmpresa) . " (ID: {$empresaId})\n";
        $output .= "-- Data de Geracao: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- =============================================================\n\n";
        $output .= "SET NAMES utf8mb4;\n";
        $output .= "SET foreign_key_checks = 0;\n";
        $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

        $tabelas = [
            // 1. Estrutura e Tabelas Globais de Apoio
            'migrations' => 'SELECT * FROM migrations',
            'configuracoes' => "SELECT * FROM configuracoes WHERE config NOT IN ('saas_master_key', 'jwt_secret')",
            'permissoes' => 'SELECT * FROM permissoes',
            'categorias' => 'SELECT * FROM categorias',
            'contas' => 'SELECT * FROM contas',
            'documentos' => 'SELECT * FROM documentos',
            'marcas' => 'SELECT * FROM marcas',
            'equipamentos' => 'SELECT * FROM equipamentos',

            // 2. Usuários da empresa
            'usuarios' => "SELECT * FROM usuarios WHERE idUsuarios IN (SELECT usuario_id FROM saas_empresa_usuarios WHERE empresa_id = {$empresaId}) OR idUsuarios IN (SELECT DISTINCT usuarios_id FROM os WHERE empresa_id = {$empresaId}) OR idUsuarios IN (SELECT DISTINCT usuarios_id FROM vendas WHERE empresa_id = {$empresaId})",

            // 3. Cadastros Operacionais da Empresa
            'emitente' => "SELECT * FROM emitente WHERE empresa_id = {$empresaId}",
            'clientes' => "SELECT * FROM clientes WHERE empresa_id = {$empresaId}",
            'produtos' => "SELECT * FROM produtos WHERE empresa_id = {$empresaId}",
            'servicos' => "SELECT * FROM servicos WHERE empresa_id = {$empresaId}",
            'garantias' => "SELECT * FROM garantias WHERE empresa_id = {$empresaId}",
            'os' => "SELECT * FROM os WHERE empresa_id = {$empresaId}",
            'vendas' => "SELECT * FROM vendas WHERE empresa_id = {$empresaId}",
            'lancamentos' => "SELECT * FROM lancamentos WHERE empresa_id = {$empresaId}",
            'cobrancas' => "SELECT * FROM cobrancas WHERE empresa_id = {$empresaId}",
            'arquivos' => "SELECT * FROM arquivos WHERE empresa_id = {$empresaId}",

            // 4. Filhas de OS
            'produtos_os' => "SELECT * FROM produtos_os WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",
            'servicos_os' => "SELECT * FROM servicos_os WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",
            'anotacoes_os' => "SELECT * FROM anotacoes_os WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",
            'anexos' => "SELECT * FROM anexos WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",
            'equipamentos_os' => "SELECT * FROM equipamentos_os WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",
            'os_historico' => "SELECT * FROM os_historico WHERE os_id IN (SELECT idOs FROM os WHERE empresa_id = {$empresaId})",

            // 5. Filhas de Vendas
            'itens_de_vendas' => "SELECT * FROM itens_de_vendas WHERE vendas_id IN (SELECT idVendas FROM vendas WHERE empresa_id = {$empresaId})",
        ];

        foreach ($tabelas as $tabela => $querySql) {
            if (! $this->CI->db->table_exists($tabela)) {
                continue;
            }

            // Estrutura
            $cRes = $this->CI->db->query('SHOW CREATE TABLE ' . $this->CI->db->protect_identifiers($tabela));
            if (! $cRes || $cRes->num_rows() === 0) {
                continue;
            }
            $createRow = $cRes->row_array();
            $createTable = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;

            $output .= "# -------------------------------------------------------------\n";
            $output .= "# Table: {$tabela}\n";
            $output .= "# -------------------------------------------------------------\n";
            $output .= 'DROP TABLE IF EXISTS ' . $this->CI->db->protect_identifiers($tabela) . ";\n";
            if ($createTable) {
                $output .= $createTable . ";\n\n";
            }

            // Dados
            $dataRes = $this->CI->db->query($querySql);
            if ($dataRes && $dataRes->num_rows() > 0) {
                $fields = $dataRes->list_fields();
                $fieldStr = implode(', ', array_map([$this->CI->db, 'protect_identifiers'], $fields));

                foreach ($dataRes->result_array() as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } elseif (is_numeric($v) && ! str_starts_with((string) $v, '0') && ! str_contains((string) $v, '-')) {
                            // Inteiro ou decimal sem leading zero
                            $vals[] = $v;
                        } else {
                            $vals[] = $this->CI->db->escape($v);
                        }
                    }
                    $output .= 'INSERT INTO ' . $this->CI->db->protect_identifiers($tabela) . " ({$fieldStr}) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $output .= "\n";
            }
        }

        $output .= "SET foreign_key_checks = 1;\n";

        return $output;
    }
}
