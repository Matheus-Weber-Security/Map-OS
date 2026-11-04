<?php
/**
 * Digitar código manual de recuperação de senha (#RecuperarSenha).
 *
 * @var string|null $erro
 */
$entrada = [
    'titulo' => 'Código de recuperação',
    'secundaria' => ['label' => 'Voltar ao login', 'href' => site_url('login'), 'icon' => 'log-in'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-6 lg:py-12">
        <section class="superficie-entrada w-full rounded-xxl border border-hairline-violet bg-night p-6 text-text sm:p-8" aria-labelledby="titulo-codigo">
            <h1 id="titulo-codigo" class="font-display text-heading-md text-text">Digite seu código</h1>
            <p class="mt-2 text-body-md text-muted">Cole o código alfanumérico que você recebeu nas instruções de recuperação.</p>

            <?php if (! empty($erro)) { ?>
                <div class="mt-4"><?= component('alert', ['message' => (string) $erro, 'variant' => 'danger']) ?></div>
            <?php } ?>

            <form method="post" action="<?= e(site_url('login/tokenManual')) ?>" novalidate class="mt-6 flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <?= component('input', [
                    'name' => 'token',
                    'label' => 'Código de recuperação',
                    'required' => true,
                    'autocomplete' => 'one-time-code',
                    'class' => 'font-mono',
                    'attrs' => [
                        'autofocus' => true,
                        'maxlength' => 128,
                        'autocapitalize' => 'off',
                        'spellcheck' => 'false',
                        'data-msg-vazio' => 'Cole o código recebido.',
                    ],
                ]) ?>

                <?= component('button', [
                    'label' => 'Continuar',
                    'type' => 'submit',
                    'size' => 'lg',
                    'icon' => 'key-round',
                    'class' => 'mt-2 w-full shadow-elev-3',
                    'attrs' => ['data-rotulo-carregando' => 'Verificando…'],
                ]) ?>
            </form>

            <p class="mt-6 text-body-md">
                <a href="<?= e(site_url('login/resetarSenha')) ?>" class="rounded-xs text-on-dark underline underline-offset-4 hover:decoration-2 focus-visible:outline-3 focus-visible:outline-ring max-sm:inline-flex max-sm:min-h-11 max-sm:items-center">Solicitar novo código</a>
            </p>
        </section>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
