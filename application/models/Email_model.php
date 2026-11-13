<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Email_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function empresaIdSessao(): int
    {
        return (int) ($this->session->userdata('empresa_id') ?: 1);
    }

    public function get($table, $fields, $where = '', $perpage = 0, $start = 0, $one = false, $array = 'array')
    {
        $this->db->select($fields);
        $this->db->from($table);
        if ($table === 'email_queue' && $this->db->field_exists('empresa_id', 'email_queue')) {
            $this->db->where('empresa_id', $this->empresaIdSessao());
        }
        $this->db->order_by('id', 'desc');
        $this->db->limit($perpage, $start);
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        return ! $one ? $query->result() : $query->row();
    }

    /**
     * Histórico e fila de e-mails da empresa ativa, os mais recentes primeiro.
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->aplicarFiltros($filtros);

        $campos = 'id, to, status, date, headers';
        foreach (['empresa_id', 'enviado_em', 'lido_em', 'ultima_leitura_em', 'total_leituras', 'erro_mensagem'] as $col) {
            if ($this->db->field_exists($col, 'email_queue')) {
                $campos .= ', ' . $col;
            }
        }

        return $this->db
            ->select($campos)
            ->order_by('id', 'DESC')
            ->limit($limite, max(0, $offset))
            ->get()
            ->result();
    }

    public function contar(array $filtros): int
    {
        $this->aplicarFiltros($filtros);

        return (int) $this->db->count_all_results();
    }

    private function aplicarFiltros(array $filtros): void
    {
        $this->db->from('email_queue');
        if ($this->db->field_exists('empresa_id', 'email_queue')) {
            $this->db->where('empresa_id', $this->empresaIdSessao());
        }
        if (($filtros['status'] ?? '') !== '') {
            $this->db->where('status', $filtros['status']);
        }
    }

    public function getById($id)
    {
        $this->db->where('id', (int) $id);
        if ($this->db->field_exists('empresa_id', 'email_queue')) {
            $this->db->where('empresa_id', $this->empresaIdSessao());
        }
        $this->db->limit(1);

        return $this->db->get('email_queue')->row();
    }

    public function logsDoEmail(int $emailId): array
    {
        if (! $this->db->table_exists('email_logs')) {
            return [];
        }

        return $this->db
            ->where('email_id', $emailId)
            ->where('empresa_id', $this->empresaIdSessao())
            ->order_by('criado_em', 'DESC')
            ->order_by('id', 'DESC')
            ->get('email_logs')
            ->result();
    }

    public function add($table, $data)
    {
        if ($table === 'email_queue' && is_array($data)) {
            return $this->enviarImediato($data)['inserido'];
        }

        $this->db->insert($table, $data);

        return $this->db->affected_rows() == 1;
    }

    /**
     * Registra o e-mail na tabela email_queue isolado por empresa, injeta o
     * pixel de rastreamento de abertura e dispara imediatamente via SMTP.
     *
     * @return array{inserido: bool, enviado: bool, id: int, erro: string|null}
     */
    public function enviarImediato(array $data): array
    {
        $empresaId = isset($data['empresa_id']) && (int) $data['empresa_id'] > 0
            ? (int) $data['empresa_id']
            : $this->empresaIdSessao();

        $rawHeaders = (string) ($data['headers'] ?? '');
        $headers = function_exists('json_decode_legacy')
            ? (json_decode_legacy($rawHeaders) ?: [])
            : (json_decode($rawHeaders, true) ?: []);

        $assunto = $this->decodificarAssunto((string) ($headers['Subject'] ?? 'Notificação'));
        [$fromNomeOriginal, $fromEmailOriginal] = $this->extrairRemetente((string) ($headers['From'] ?? ''));

        $temRastreamento = $this->db->field_exists('tracking_token', 'email_queue');
        $token = $temRastreamento ? bin2hex(random_bytes(16)) : null;

        $mensagemOriginal = (string) ($data['message'] ?? '');
        $mensagemComPixel = $token !== null ? $this->injetarPixel($mensagemOriginal, $token) : $mensagemOriginal;

        $registro = [
            'to' => (string) ($data['to'] ?? ''),
            'cc' => isset($data['cc']) ? (string) $data['cc'] : null,
            'bcc' => isset($data['bcc']) ? (string) $data['bcc'] : null,
            'message' => $mensagemOriginal,
            'status' => 'sending',
            'date' => (string) ($data['date'] ?? date('Y-m-d H:i:s')),
            'headers' => is_string($data['headers'] ?? null) ? $data['headers'] : json_encode($headers),
        ];

        if ($this->db->field_exists('empresa_id', 'email_queue')) {
            $registro['empresa_id'] = $empresaId;
        }
        if ($temRastreamento) {
            $registro['tracking_token'] = $token;
        }

        $this->db->insert('email_queue', $registro);
        $emailId = (int) $this->db->insert_id();

        if ($emailId <= 0) {
            return ['inserido' => false, 'enviado' => false, 'id' => 0, 'erro' => 'Falha ao gravar registro do e-mail.'];
        }

        $resultado = $this->despacharViaBiblioteca(
            $registro['to'],
            $registro['cc'],
            $registro['bcc'],
            $assunto,
            $mensagemComPixel,
            $fromNomeOriginal,
            $fromEmailOriginal
        );

        $agora = date('Y-m-d H:i:s');
        $statusFinal = $resultado['enviado'] ? 'sent' : 'failed';

        $update = ['status' => $statusFinal];
        if ($resultado['enviado'] && $this->db->field_exists('enviado_em', 'email_queue')) {
            $update['enviado_em'] = $agora;
        }
        if (! $resultado['enviado'] && $this->db->field_exists('erro_mensagem', 'email_queue')) {
            $update['erro_mensagem'] = $resultado['erro'];
        }
        $this->db->where('id', $emailId)->update('email_queue', $update);

        $this->registrarEventoLog(
            $emailId,
            $empresaId,
            $resultado['enviado'] ? 'sent' : 'failed',
            $resultado['enviado'] ? 'E-mail disparado pelo servidor SMTP.' : ($resultado['erro'] ?: 'Falha desconhecida no disparo.'),
            $this->input->ip_address(),
            null
        );

        return [
            'inserido' => true,
            'enviado' => $resultado['enviado'],
            'id' => $emailId,
            'erro' => $resultado['erro'],
        ];
    }

    /**
     * Registra abertura via pixel 1x1 quando o destinatário abre o e-mail.
     */
    public function registrarAbertura(string $token, ?string $ip, ?string $userAgent): bool
    {
        if (! preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return false;
        }

        if (! $this->db->field_exists('tracking_token', 'email_queue')) {
            return false;
        }

        $email = $this->db
            ->select('id, empresa_id, lido_em')
            ->where('tracking_token', $token)
            ->limit(1)
            ->get('email_queue')
            ->row();

        if (! $email) {
            return false;
        }

        $agora = date('Y-m-d H:i:s');
        $this->db->set('ultima_leitura_em', $agora);
        $this->db->set('total_leituras', 'total_leituras + 1', false);
        if (empty($email->lido_em)) {
            $this->db->set('lido_em', $agora);
        }
        $this->db->where('id', (int) $email->id)->update('email_queue');

        $this->registrarEventoLog(
            (int) $email->id,
            (int) ($email->empresa_id ?? 1),
            'opened',
            $this->descreverClienteLeitura($userAgent),
            $ip,
            $userAgent
        );

        return true;
    }

    private function registrarEventoLog(int $emailId, int $empresaId, string $evento, ?string $detalhes, ?string $ip, ?string $userAgent): void
    {
        if (! $this->db->table_exists('email_logs')) {
            return;
        }

        $this->db->insert('email_logs', [
            'email_id' => $emailId,
            'empresa_id' => $empresaId,
            'evento' => substr($evento, 0, 30),
            'detalhes' => $detalhes,
            'ip' => $ip !== null ? substr($ip, 0, 45) : null,
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
            'criado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{enviado: bool, erro: string|null}
     */
    private function despacharViaBiblioteca(
        string $to,
        ?string $cc,
        ?string $bcc,
        string $assunto,
        string $mensagemHtml,
        string $fromNomeOriginal,
        string $fromEmailOriginal
    ): array {
        $this->config->load('email', true);
        $emailConfig = (array) $this->config->item('email');

        $protocolo = (string) ($emailConfig['protocol'] ?? 'smtp');
        $smtpHost = trim((string) ($emailConfig['smtp_host'] ?? ''));
        $smtpUser = trim((string) ($emailConfig['smtp_user'] ?? ''));
        $smtpPass = (string) ($emailConfig['smtp_pass'] ?? '');

        if ($protocolo === 'smtp' && ($smtpHost === '' || $smtpUser === '' || $smtpUser === 'seuemail@gmail.com' || $smtpPass === 'senhadoemail')) {
            return [
                'enviado' => false,
                'erro' => 'Servidor SMTP não configurado. Defina EMAIL_SMTP_HOST, EMAIL_SMTP_USER e EMAIL_SMTP_PASS nas variáveis de ambiente (ou em Configurações > E-mail).',
            ];
        }

        $remetenteSistema = trim((string) ($emailConfig['from_address'] ?? ''));
        if ($remetenteSistema === '' || ! filter_var($remetenteSistema, FILTER_VALIDATE_EMAIL)) {
            $remetenteSistema = filter_var($smtpUser, FILTER_VALIDATE_EMAIL)
                ? $smtpUser
                : (filter_var($fromEmailOriginal, FILTER_VALIDATE_EMAIL) ? $fromEmailOriginal : 'nao-responder@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost'));
        }

        $nomeSistema = trim((string) ($emailConfig['from_name'] ?? 'Nexus Ticket')) ?: 'Nexus Ticket';
        $nomeRemetente = $fromNomeOriginal !== '' && strcasecmp($fromNomeOriginal, $nomeSistema) !== 0
            ? "{$fromNomeOriginal} (via {$nomeSistema})"
            : $nomeSistema;

        $this->load->library('email');
        $this->email->initialize($emailConfig);
        $this->email->clear(true);
        $this->email->from($remetenteSistema, $nomeRemetente);

        if ($fromEmailOriginal !== '' && filter_var($fromEmailOriginal, FILTER_VALIDATE_EMAIL) && strcasecmp($fromEmailOriginal, $remetenteSistema) !== 0) {
            $this->email->reply_to($fromEmailOriginal, $fromNomeOriginal !== '' ? $fromNomeOriginal : $fromEmailOriginal);
        }

        $destinatarios = array_values(array_filter(array_map('trim', explode(',', $to))));
        if ($destinatarios === []) {
            return ['enviado' => false, 'erro' => 'Nenhum destinatário válido informado.'];
        }

        $this->email->to($destinatarios);
        if (! empty($cc)) {
            $this->email->cc(array_values(array_filter(array_map('trim', explode(',', $cc)))));
        }
        if (! empty($bcc)) {
            $this->email->bcc(array_values(array_filter(array_map('trim', explode(',', $bcc)))));
        }
        $this->email->subject($assunto);
        $this->email->message($mensagemHtml);

        $capturado = '';
        set_error_handler(static function (int $severity, string $message) use (&$capturado): bool {
            $capturado = $message;

            return true;
        });

        try {
            $enviado = (bool) $this->email->send(true);
            $erro = null;
            if (! $enviado) {
                $debug = trim(strip_tags((string) $this->email->print_debugger(['headers'])));
                $erro = $debug !== '' ? $debug : ($capturado !== '' ? $capturado : 'Erro ao enviar e-mail via ' . $protocolo);
                log_message('error', 'Email_model::despacharViaBiblioteca falhou: ' . $erro);
            }

            return ['enviado' => $enviado, 'erro' => $erro];
        } catch (Throwable $e) {
            log_message('error', 'Exceção ao enviar e-mail: ' . $e->getMessage());

            return ['enviado' => false, 'erro' => $e->getMessage()];
        } finally {
            restore_error_handler();
        }
    }

    private function injetarPixel(string $html, string $token): string
    {
        $this->load->helper('url');
        $url = rtrim(base_url(), '/') . '/index.php/email/rastrear/' . $token;
        $pixel = '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" border="0" alt="" style="width:1px;height:1px;border:0;opacity:0;" />';

        if (stripos($html, '</body>') !== false) {
            return (string) preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1);
        }

        return $html . $pixel;
    }

    /**
     * @return array{0: string, 1: string} [nome, email]
     */
    private function extrairRemetente(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', ''];
        }

        if (preg_match('/^(?:"?([^"<]*)"?\s*)?<([^>]+)>$/', $raw, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return ['', $raw];
        }

        return [$raw, ''];
    }

    private function decodificarAssunto(string $assunto): string
    {
        if ($assunto === '') {
            return 'Notificação';
        }
        if (! str_contains($assunto, '=?')) {
            return trim($assunto);
        }

        $dec = function_exists('iconv_mime_decode')
            ? @iconv_mime_decode($assunto, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8')
            : mb_decode_mimeheader($assunto);

        return trim($dec !== false ? $dec : $assunto);
    }

    private function descreverClienteLeitura(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if ($ua === '') {
            return 'Abertura registrada (cliente não identificado)';
        }
        if (stripos($ua, 'GoogleImageProxy') !== false) {
            return 'Aberto no Gmail (Google Image Proxy)';
        }
        if (stripos($ua, 'AppleMail') !== false || (stripos($ua, 'Macintosh') !== false && stripos($ua, 'AppleWebKit') !== false && stripos($ua, 'Chrome') === false)) {
            return 'Aberto no Apple Mail / iOS (pode incluir pré-carregamento automático)';
        }
        if (stripos($ua, 'Outlook') !== false || stripos($ua, 'Microsoft') !== false) {
            return 'Aberto no Microsoft Outlook';
        }
        if (stripos($ua, 'Thunderbird') !== false) {
            return 'Aberto no Mozilla Thunderbird';
        }
        if (stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Mobile') !== false) {
            return 'Aberto em dispositivo móvel';
        }

        return 'Aberto em navegador / cliente web';
    }

    public function delete($table, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        if ($table === 'email_queue' && $this->db->field_exists('empresa_id', 'email_queue')) {
            $this->db->where('empresa_id', $this->empresaIdSessao());
        }
        $this->db->delete($table);

        return $this->db->affected_rows() == 1;
    }

    public function count($table)
    {
        if ($table === 'email_queue' && $this->db->field_exists('empresa_id', 'email_queue')) {
            return (int) $this->db->where('empresa_id', $this->empresaIdSessao())->count_all_results($table);
        }

        return $this->db->count_all($table);
    }
}
