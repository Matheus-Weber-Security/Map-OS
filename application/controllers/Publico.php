<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Publico extends CI_Controller
{
    public $data = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Os_model');
        $this->load->model('Mapos_model');
        $this->load->helper(['os', 'escape', 'data', 'financeiro', 'emitente', 'url']);
    }

    /**
     * Visualização e download público do PDF da OS pelo cliente via token seguro.
     *
     * @param int|string|null $id
     * @param string|null $token
     */
    public function os($id = null, $token = null)
    {
        $id = (int) $id;
        $token = trim((string) $token);

        if ($id <= 0 || $token === '') {
            show_404();
        }

        $os = $this->Os_model->getById($id);
        if (! $os) {
            show_404();
        }

        $tokenEsperado = osTokenPublico($os);
        if (! hash_equals($tokenEsperado, $token)) {
            show_error('Acesso não autorizado ou link inválido.', 403, 'Acesso Negado');
        }

        $empresaId = (int) ($os->empresa_id ?? 1);

        // Carrega configurações da empresa da OS
        $configuration = [];
        if ($this->db->table_exists('configuracoes')) {
            $configs = $this->db->where('empresa_id', $empresaId)->get('configuracoes')->result();
            foreach ($configs as $c) {
                $configuration[$c->config] = $c->valor;
            }
        }
        $configuration['control_2vias'] = $configuration['control_2vias'] ?? 0;
        $configuration['pix_key'] = $configuration['pix_key'] ?? '';

        // Carrega emitente da empresa
        $emitente = null;
        if ($this->db->table_exists('emitente')) {
            $emitente = $this->db->where('empresa_id', $empresaId)->get('emitente', 1)->row();
        }
        if (! $emitente) {
            $emitente = $this->Mapos_model->getEmitente();
        }

        $this->data['result'] = $os;
        $this->data['produtos'] = $this->Os_model->getProdutos($id);
        $this->data['servicos'] = $this->Os_model->getServicos($id);
        $this->data['anexos'] = $this->Os_model->getAnexos($id);
        $this->data['emitente'] = $emitente;
        $this->data['configuration'] = $configuration;
        $this->data['publico'] = true;

        $pixKey = $emitente->pix_key ?? ($configuration['pix_key'] ?? '');
        if (! empty($pixKey)) {
            $this->data['qrCode'] = $this->Os_model->getQrCode($id, $pixKey, $emitente);
            $this->data['chaveFormatada'] = $this->formatarChavePix($pixKey);
        }

        $imprimirAnexoConfig = $configuration['IMPRIMIR_ANEXOS'] ?? ($_ENV['IMPRIMIR_ANEXOS'] ?? false);
        $this->data['imprimirAnexo'] = filter_var($imprimirAnexoConfig, FILTER_VALIDATE_BOOLEAN);

        // Se solicitado o download direto do PDF via streaming na memória (0 bytes no disco)
        if ($this->input->get('pdf') || $this->input->get('download')) {
            $this->load->helper('mpdf');
            $html = $this->load->view('os/imprimirOs', $this->data, true);
            $codigoOs = $os->codigo ?? $os->idOs;
            $nomeArquivo = 'OS_' . str_pad((string) $codigoOs, 4, '0', STR_PAD_LEFT);
            pdf_create($html, $nomeArquivo, true);
            return;
        }

        $this->load->view('os/imprimirOs', $this->data);
    }

    private function formatarChavePix(string $chave): string
    {
        $soNumeros = preg_replace('/\D/', '', $chave);
        if (strlen($soNumeros) === 11 && (function_exists('validar_cpf') && validar_cpf($soNumeros) || ! str_contains($chave, '@'))) {
            return substr($soNumeros, 0, 3) . '.' . substr($soNumeros, 3, 3) . '.' . substr($soNumeros, 6, 3) . '-' . substr($soNumeros, 9);
        }
        if (strlen($soNumeros) === 14) {
            return substr($soNumeros, 0, 2) . '.' . substr($soNumeros, 2, 3) . '.' . substr($soNumeros, 5, 3) . '/' . substr($soNumeros, 8, 4) . '-' . substr($soNumeros, 12);
        }
        return $chave;
    }
}
