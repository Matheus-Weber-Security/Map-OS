<?php
/**
 * Primeiro acesso: vinculação do aplicativo autenticador (2FA).
 *
 * Exibido quando um usuário ou funcionário entra sem TOTP vinculado.
 * Quando o 2FA no login está desativado, exibe o botão "Cadastrar depois".
 *
 * @var string      $nome
 * @var string      $email
 * @var string      $qr
 * @var string      $segredo
 * @var string      $segredo_puro
 * @var string      $otpauth_uri
 * @var bool        $pode_adiar
 * @var string|null $erro
 */
$entrada = [
    'titulo' => 'Primeiro acesso · Configurar 2FA',
    'secundaria' => ['label' => 'Sair', 'href' => site_url('login/sair'), 'icon' => 'log-out'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-6 lg:py-8" <?= js_module('login/dois-fatores-cadastro') ?>>
        <header class="mb-5">
            <span class="mb-2 inline-block rounded-xs bg-night px-2 py-1 text-caption text-on-dark">Primeiro acesso · Segurança</span>
            <h1 class="font-display font-bold tracking-tight text-on-dark titulo-cadastrar-empresa">
                Proteja sua <span class="rounded-xs bg-accent-lime px-2 py-1 text-ink">conta</span>
            </h1>
            <p class="mt-1.5 text-sm text-on-dark-muted">
                Olá, <strong class="font-medium text-on-dark"><?= e($nome) ?></strong>. Vincule um aplicativo autenticador. Ele será usado caso precise recuperar sua senha sem depender de e-mail.
            </p>
        </header>

        <div class="superficie-entrada flex flex-col gap-4 rounded-xxl border border-hairline-violet bg-night p-5 text-text sm:p-6">
            <?php if (! empty($erro)) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => (string) $erro]) ?>
            <?php } ?>

            <?php include APPPATH . 'views/mapos/partes/configurar_2fa_qr.php'; ?>

            <form method="post" action="<?= e(site_url('login/salvarDoisFatoresPrimeiroAcesso')) ?>" novalidate class="flex flex-col gap-4 border-t border-border pt-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <?= component('input', [
                    'name' => 'codigo',
                    'id' => 'codigo',
                    'label' => 'Código de 6 dígitos do aplicativo',
                    'required' => true,
                    'autocomplete' => 'one-time-code',
                    'placeholder' => '000000',
                    'help' => 'Digite os 6 dígitos mostrados no seu celular para concluir.',
                    'attrs' => [
                        'autofocus' => true,
                        'inputmode' => 'numeric',
                        'maxlength' => 7,
                        'pattern' => '[0-9 ]{6,7}',
                        'data-msg-vazio' => 'Digite o código de 6 dígitos mostrado no aplicativo.',
                    ],
                ]) ?>

                <div class="pt-1">
                    <?= component('button', [
                        'label' => 'Ativar e entrar no sistema',
                        'type' => 'submit',
                        'size' => 'lg',
                        'icon' => 'shield-check',
                        'class' => 'w-full shadow-elev-3',
                        'attrs' => ['data-rotulo-carregando' => 'Ativando…'],
                    ]) ?>
                </div>
            </form>

            <?php if (! empty($pode_adiar)) { ?>
                <form method="post" action="<?= e(site_url('login/adiarDoisFatores')) ?>" class="border-t border-border pt-3 text-center">
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                    <?= component('button', [
                        'label' => 'Cadastrar depois',
                        'type' => 'submit',
                        'variant' => 'ghost',
                        'size' => 'sm',
                        'class' => 'w-full',
                    ]) ?>
                    <p class="mt-1.5 text-caption text-muted">Você poderá ativar depois em Configurações &gt; Geral ou Minha Conta.</p>
                </form>
            <?php } ?>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
