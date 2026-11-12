<?php
/**
 * Bloco reutilizável de vinculação de aplicativo autenticador (QR Code + chave manual + deep-link).
 *
 * @var string      $qr
 * @var string      $segredo
 * @var string      $segredo_puro
 * @var string|null $otpauth_uri
 */
?>
<section class="flex flex-col gap-3" aria-labelledby="secao-instrucoes-2fa">
    <h2 id="secao-instrucoes-2fa" class="text-base font-semibold text-text">Como vincular em 3 passos</h2>
    <ol class="list-decimal space-y-1 pl-5 text-caption text-muted">
        <li>Instale um app autenticador no celular (<strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou <strong>Authy</strong>).</li>
        <li>Escaneie o QR Code abaixo, toque em <strong>Abrir no app</strong> (se estiver no celular) ou copie a chave manualmente.</li>
        <li>Digite abaixo o código de <strong>6 dígitos</strong> exibido no app para confirmar.</li>
    </ol>

    <div class="mt-2 flex flex-col items-center gap-4 rounded-md border border-border bg-surface-subtle p-4 sm:flex-row sm:items-start">
        <div class="shrink-0 rounded-md border border-border bg-field p-2">
            <img src="<?= e((string) $qr) ?>" alt="QR Code para o aplicativo autenticador" width="200" height="200" class="size-[200px]">
        </div>
        <div class="min-w-0 flex-1 flex flex-col gap-2">
            <p class="text-label-md text-text">Chave manual (se preferir digitar)</p>
            <p class="rounded-md border border-border bg-field px-3 py-2 font-mono text-xs text-text [overflow-wrap:anywhere]" data-segredo><?php foreach (explode(' ', (string) $segredo) as $grupo) { ?><span class="inline-block whitespace-nowrap"><?= e($grupo) ?></span> <?php } ?></p>
            <div class="flex flex-wrap gap-2 pt-1">
                <?= component('button', [
                    'label' => 'Copiar chave',
                    'variant' => 'outline',
                    'size' => 'sm',
                    'icon' => 'copy',
                    'attrs' => ['data-copiar-segredo' => (string) $segredo_puro],
                ]) ?>
                <?php if (! empty($otpauth_uri)) { ?>
                    <?= component('button', [
                        'label' => 'Abrir no app',
                        'href' => (string) $otpauth_uri,
                        'variant' => 'outline',
                        'size' => 'sm',
                        'icon' => 'external-link',
                    ]) ?>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
