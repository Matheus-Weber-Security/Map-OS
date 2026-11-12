<?php
/**
 * Autenticação em dois fatores da própria conta (#2876).
 *
 * Reutiliza a partial mapos/partes/painel_2fa_conta.php para ativação,
 * exibição dos códigos de recuperação e desativação/troca do autenticador.
 *
 * @var bool                  $ativo
 * @var bool                  $exigido
 * @var bool                  $exigir_2fa_login
 * @var int                   $restantes
 * @var list<string>|null     $codigos
 * @var array<string, string> $erros
 * @var string                $email
 * @var string|null           $qr
 * @var string|null           $segredo
 * @var string|null           $segredo_puro
 * @var string|null           $otpauth_uri
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$tfa = [
    'ativo' => $ativo,
    'exigido' => $exigido,
    'exigir_2fa_login' => ! empty($exigir_2fa_login),
    'restantes' => $restantes,
    'codigos' => $codigos,
    'erros' => $erros,
    'email' => $email ?? '',
    'qr' => $qr,
    'segredo' => $segredo,
    'segredo_puro' => $segredo_puro ?? null,
    'otpauth_uri' => $otpauth_uri ?? null,
];
$origem = 'doisFatores';
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8 mx-auto w-full">
    <header>
        <h1 class="font-display text-heading-xl text-text">Autenticação em dois fatores</h1>
        <p class="text-caption text-muted">O aplicativo autenticador protege sua conta e permite redefinir a senha sem depender de e-mail.</p>
    </header>

    <?php include APPPATH . 'views/mapos/partes/painel_2fa_conta.php'; ?>
</div>
