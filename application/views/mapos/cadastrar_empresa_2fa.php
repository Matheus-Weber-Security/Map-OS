<?php
/**
 * Cadastro de Nova Empresa - Passo 2 de 2: Vinculação do Aplicativo Autenticador (2FA).
 *
 * O segredo TOTP é gerado e guardado apenas na sessão até o usuário confirmar o
 * código de 6 dígitos; só então a empresa e o usuário são criados no banco.
 *
 * @var bool        $ja_tem_2fa
 * @var string      $email
 * @var string      $nome
 * @var string|null $qr
 * @var string|null $segredo
 * @var string|null $segredo_puro
 * @var string|null $otpauth_uri
 * @var string|null $erro
 */
$entrada = [
    'titulo' => 'Configurar autenticador',
    'secundaria' => ['label' => 'Voltar ao início', 'href' => site_url('login/cadastrar?voltar=1'), 'icon' => 'chevron-left'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-6 lg:py-8" <?= js_module('login/dois-fatores-cadastro') ?>>
        <header class="mb-5">
            <span class="mb-2 inline-block rounded-xs bg-night px-2 py-1 text-caption text-on-dark">Passo 2 de 2 · Segurança e recuperação</span>
            <h1 class="font-display font-bold tracking-tight text-on-dark titulo-cadastrar-empresa">
                Configure seu <span class="rounded-xs bg-accent-lime px-2 py-1 text-ink">autenticador</span>
            </h1>
            <p class="mt-1.5 text-sm text-on-dark-muted">
                <?= e($ja_tem_2fa
                    ? 'Sua conta já usa um aplicativo autenticador. Confirme com o código atual de 6 dígitos para criar a nova empresa.'
                    : 'Se você esquecer sua senha no futuro, usará o código do aplicativo autenticador para redefini-la na hora, sem depender de e-mail.') ?>
            </p>
        </header>

        <div class="superficie-entrada flex flex-col gap-4 rounded-xxl border border-hairline-violet bg-night p-5 text-text sm:p-6">
            <?php if (! empty($erro)) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => (string) $erro]) ?>
            <?php } ?>

            <?php if (! $ja_tem_2fa) { ?>
                <?php include APPPATH . 'views/mapos/partes/configurar_2fa_qr.php'; ?>
            <?php } else { ?>
                <div class="rounded-md border border-border bg-surface-subtle p-3 text-sm text-text">
                    Vinculando nova empresa à conta <strong class="font-semibold"><?= e($email) ?></strong>.
                </div>
            <?php } ?>

            <form method="post" action="<?= e(site_url('login/cadastrar2fa')) ?>" novalidate class="flex flex-col gap-4 border-t border-border pt-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <?= component('input', [
                    'name' => 'codigo',
                    'id' => 'codigo',
                    'label' => 'Código de 6 dígitos do aplicativo',
                    'required' => true,
                    'autocomplete' => 'one-time-code',
                    'placeholder' => '000000',
                    'help' => 'O código muda automaticamente a cada 30 segundos.',
                    'attrs' => [
                        'autofocus' => true,
                        'inputmode' => 'numeric',
                        'maxlength' => 7,
                        'pattern' => '[0-9 ]{6,7}',
                        'data-msg-vazio' => 'Digite o código de 6 dígitos mostrado no aplicativo.',
                    ],
                ]) ?>

                <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:items-center sm:justify-between">
                    <?= component('button', [
                        'label' => 'Voltar e editar dados',
                        'href' => site_url('login/cadastrar?voltar=1'),
                        'variant' => 'outline',
                        'size' => 'lg',
                        'icon' => 'chevron-left',
                        'class' => 'w-full sm:w-auto',
                    ]) ?>
                    <?= component('button', [
                        'label' => $ja_tem_2fa ? 'Confirmar e criar empresa' : 'Concluir cadastro',
                        'type' => 'submit',
                        'size' => 'lg',
                        'icon' => 'shield-check',
                        'class' => 'w-full shadow-elev-3 sm:w-auto',
                        'attrs' => ['data-rotulo-carregando' => 'Criando sua conta…'],
                    ]) ?>
                </div>
            </form>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
