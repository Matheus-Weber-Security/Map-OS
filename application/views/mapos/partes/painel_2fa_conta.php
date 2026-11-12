<?php
/**
 * Card/seção reutilizável de cadastro e gerenciamento do 2FA da conta logada.
 * Usada na aba Geral de Configurações do Sistema e na tela Minha Conta > 2FA.
 *
 * @var array<string, mixed> $tfa
 * @var string               $origem  'geral' | 'doisFatores'
 * @var string               $caixa
 */
$ativo = ! empty($tfa['ativo']);
$exigirLogin = ! empty($tfa['exigir_2fa_login']);
$restantes = (int) ($tfa['restantes'] ?? 0);
$codigos = $tfa['codigos'] ?? null;
$erros = (array) ($tfa['erros'] ?? []);
$email = (string) ($tfa['email'] ?? '');
$qr = $tfa['qr'] ?? null;
$segredo = $tfa['segredo'] ?? null;
$segredo_puro = $tfa['segredo_puro'] ?? null;
$otpauth_uri = $tfa['otpauth_uri'] ?? null;
$ehGeral = ($origem === 'geral');
?>
<div id="secao-2fa-conta" class="flex flex-col gap-4" <?= js_module('login/dois-fatores-cadastro') ?>>
    <?php if (isset($erros['_geral'])) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => (string) $erros['_geral']]) ?>
    <?php } ?>

    <?php if (! $ativo && $exigirLogin) { ?>
        <?= component('alert', ['variant' => 'warning', 'message' => 'A exigência de 2FA no login está ativa. Vincule seu aplicativo autenticador abaixo para liberar o acesso ao sistema.']) ?>
    <?php } ?>

    <?php if ($codigos !== null) { ?>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-codigos-2fa">
            <h2 id="secao-codigos-2fa" class="text-heading-sm text-text">Códigos de recuperação</h2>
            <p class="mt-1 text-caption text-muted">Guarde estes códigos em um lugar seguro. Cada um vale uma vez e substitui o aplicativo se você perder o celular. <strong class="font-medium text-text">Eles não aparecem de novo.</strong></p>
            <ul class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3" data-codigos>
                <?php foreach ($codigos as $codigo) { ?>
                    <li class="rounded-md border border-border bg-surface-subtle px-3 py-2 text-center font-mono text-sm text-text"><?= e($codigo) ?></li>
                <?php } ?>
            </ul>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap gap-2">
                    <?= component('button', ['label' => 'Copiar todos', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'copy', 'attrs' => ['data-copiar-codigos' => true]]) ?>
                    <?= component('button', ['label' => 'Baixar (.txt)', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'download', 'attrs' => ['data-baixar-codigos' => true, 'data-conta-email' => $email]]) ?>
                </div>
                <?= component('button', [
                    'label' => 'Concluir',
                    'href' => $ehGeral ? site_url('mapos/configurar') : site_url('mapos'),
                    'variant' => $ehGeral ? 'outline' : 'primary',
                    'icon' => 'check',
                ]) ?>
            </div>
        </section>
    <?php } elseif (! $ativo) { ?>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-ativar-2fa">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h2 id="secao-ativar-2fa" class="text-heading-sm text-text">Seu aplicativo autenticador (2FA)</h2>
                <?= component('pill-status', ['label' => 'Não configurado', 'variant' => 'neutral']) ?>
            </div>

            <?php include APPPATH . 'views/mapos/partes/configurar_2fa_qr.php'; ?>

            <form method="post" action="<?= e(site_url('mapos/ativarDoisFatores')) ?>" novalidate class="mt-4 flex flex-col gap-3 border-t border-border pt-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <input type="hidden" name="origem" value="<?= e($origem) ?>">

                <div class="grid gap-3 sm:grid-cols-2">
                    <?= component('input', [
                        'name' => 'senha',
                        'id' => $ehGeral ? 'tfa-senha-ativar' : 'senha',
                        'label' => 'Sua senha atual',
                        'type' => 'password',
                        'required' => true,
                        'alternar_senha' => true,
                        'autocomplete' => 'current-password',
                        'error' => $erros['senha_ativar'] ?? null,
                        'attrs' => ['data-msg-vazio' => 'Informe a senha.'],
                    ]) ?>
                    <?= component('input', [
                        'name' => 'codigo',
                        'id' => $ehGeral ? 'tfa-codigo-ativar' : 'codigo',
                        'label' => 'Código de 6 dígitos do aplicativo',
                        'required' => true,
                        'autocomplete' => 'one-time-code',
                        'placeholder' => '000000',
                        'error' => $erros['codigo'] ?? null,
                        'attrs' => ['inputmode' => 'numeric', 'maxlength' => 7, 'pattern' => '[0-9 ]{6,7}', 'data-msg-vazio' => 'Digite o código de 6 dígitos.'],
                    ]) ?>
                </div>
                <div>
                    <?= component('button', [
                        'label' => 'Ativar 2FA na minha conta',
                        'type' => 'submit',
                        'variant' => $ehGeral ? 'outline' : 'primary',
                        'icon' => 'shield-check',
                        'attrs' => ['data-rotulo-carregando' => 'Verificando…'],
                    ]) ?>
                </div>
            </form>
        </section>
    <?php } else { ?>
        <section class="<?= e($caixa) ?>" aria-labelledby="secao-estado-2fa">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="secao-estado-2fa" class="text-heading-sm text-text">Seu aplicativo autenticador (2FA)</h2>
                <?= component('pill-status', ['label' => $exigirLogin ? 'Ativo no login e recuperação' : 'Ativo na recuperação de senha', 'variant' => 'success']) ?>
            </div>
            <p class="mt-1 text-caption text-muted">Códigos de recuperação restantes: <strong class="font-medium text-text"><?= e((string) $restantes) ?></strong>. <?= $restantes <= 2 ? 'Gere novos códigos em breve.' : '' ?></p>

            <div class="mt-5 grid gap-5 border-t border-border pt-4 sm:grid-cols-2">
                <form method="post" action="<?= e(site_url('mapos/novosCodigosDoisFatores')) ?>" novalidate class="flex flex-col gap-3" <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                    <input type="hidden" name="origem" value="<?= e($origem) ?>">
                    <div>
                        <h3 class="text-sm font-semibold text-text">Novos códigos de recuperação</h3>
                        <p class="text-caption text-muted">Gera 10 códigos novos e invalida os anteriores.</p>
                    </div>
                    <?= component('input', [
                        'name' => 'senha',
                        'id' => $ehGeral ? 'tfa-senha-codigos' : 'senha-codigos',
                        'label' => 'Confirme sua senha',
                        'type' => 'password',
                        'required' => true,
                        'alternar_senha' => true,
                        'autocomplete' => 'current-password',
                        'error' => $erros['senha_codigos'] ?? null,
                        'attrs' => ['data-msg-vazio' => 'Informe a senha.'],
                    ]) ?>
                    <div><?= component('button', ['label' => 'Gerar novos códigos', 'type' => 'submit', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'key-round']) ?></div>
                </form>

                <form method="post" action="<?= e(site_url('mapos/desativarDoisFatores')) ?>" novalidate class="flex flex-col gap-3" <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                    <input type="hidden" name="origem" value="<?= e($origem) ?>">
                    <div>
                        <h3 class="text-sm font-semibold text-text"><?= e($exigirLogin ? 'Trocar aplicativo autenticador' : 'Desativar ou trocar autenticador') ?></h3>
                        <p class="text-caption text-muted"><?= e($exigirLogin ? 'Remove o vínculo atual para cadastrar um novo aparelho.' : 'Desvincula o aplicativo atual da sua conta.') ?></p>
                    </div>
                    <?= component('input', [
                        'name' => 'senha',
                        'id' => $ehGeral ? 'tfa-senha-desativar' : 'senha-desativar',
                        'label' => 'Confirme sua senha',
                        'type' => 'password',
                        'required' => true,
                        'alternar_senha' => true,
                        'autocomplete' => 'current-password',
                        'error' => $erros['senha_desativar'] ?? null,
                        'attrs' => ['data-msg-vazio' => 'Informe a senha.'],
                    ]) ?>
                    <div class="flex flex-wrap gap-2">
                        <?php if (! $exigirLogin) { ?>
                            <?= component('button', ['label' => 'Desativar 2FA', 'type' => 'submit', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'shield-off']) ?>
                        <?php } else { ?>
                            <?= component('button', ['label' => 'Trocar aplicativo', 'type' => 'submit', 'name' => 'modo', 'value' => 'trocar', 'variant' => 'outline', 'size' => 'sm', 'icon' => 'refresh-cw']) ?>
                        <?php } ?>
                    </div>
                </form>
            </div>
        </section>
    <?php } ?>
</div>
