<?php

$empresaId = 1;
$configsTenant = [];
if (function_exists('get_instance')) {
    $ci = &get_instance();
    if (isset($ci->session) && $ci->session->userdata('empresa_id')) {
        $empresaId = (int) $ci->session->userdata('empresa_id');
    }
    if (isset($ci->data['configuration'])) {
        $configsTenant = $ci->data['configuration'];
    }
}

$envEmail = static function (string $chave, mixed $padrao = null) use ($empresaId, $configsTenant) {
    if (isset($configsTenant[$chave]) && $configsTenant[$chave] !== '') {
        return $configsTenant[$chave];
    }
    if ($empresaId === 1) {
        return ($_ENV[$chave] ?? null) !== null && $_ENV[$chave] !== ''
            ? $_ENV[$chave]
            : (getenv($chave) !== false && getenv($chave) !== '' ? getenv($chave) : $padrao);
    }
    return $padrao;
};

$config['protocol'] = (string) $envEmail('EMAIL_PROTOCOL', 'smtp');
$config['smtp_host'] = (string) $envEmail('EMAIL_SMTP_HOST', 'smtp.gmail.com');
$config['smtp_crypto'] = (string) $envEmail('EMAIL_SMTP_CRYPTO', 'tls'); // tls or ssl
$config['smtp_port'] = (int) $envEmail('EMAIL_SMTP_PORT', 587);
$config['smtp_timeout'] = (int) $envEmail('EMAIL_SMTP_TIMEOUT', 5);
$config['smtp_user'] = (string) $envEmail('EMAIL_SMTP_USER', 'seuemail@gmail.com');
$config['smtp_pass'] = (string) $envEmail('EMAIL_SMTP_PASS', 'senhadoemail');
$config['from_address'] = (string) $envEmail('EMAIL_FROM', '');
$config['from_name'] = (string) $envEmail('EMAIL_FROM_NAME', 'Nexus Ticket');
$config['validate'] = filter_var($envEmail('EMAIL_VALIDATE', 'true'), FILTER_VALIDATE_BOOLEAN);
$config['mailtype'] = (string) $envEmail('EMAIL_MAILTYPE', 'html');
$config['charset'] = (string) $envEmail('EMAIL_CHARSET', 'utf-8');
$config['newline'] = (string) $envEmail('EMAIL_NEWLINE', "\r\n");
$config['crlf'] = (string) $envEmail('EMAIL_CRLF', "\r\n");
$config['bcc_batch_mode'] = filter_var($envEmail('EMAIL_BCC_BATCH_MODE', 'false'), FILTER_VALIDATE_BOOLEAN);
$config['wordwrap'] = filter_var($envEmail('EMAIL_WORDWRAP', 'false'), FILTER_VALIDATE_BOOLEAN);
$config['priority'] = (int) $envEmail('EMAIL_PRIORITY', 3);
