<?php
/**
 * Solicitação de recuperação de senha do painel Map-OS v5 (#RecuperarSenha).
 *
 * Superfície de entrada (views/entrada/inicio.php e fim.php).
 *
 * @var string|null $erro
 */
$entrada = [
    'titulo' => 'Recuperar senha',
    'secundaria' => ['label' => 'Voltar ao login', 'href' => site_url('login'), 'icon' => 'log-in'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-6 lg:py-12">
        <section class="superficie-entrada w-full rounded-xxl border border-hairline-violet bg-night p-6 text-text sm:p-8" aria-labelledby="titulo-recuperar">
            <h1 id="titulo-recuperar" class="font-display text-heading-md text-text">Esqueceu sua senha?</h1>
            <p class="mt-2 text-body-md text-muted">Informe o e-mail cadastrado da sua conta. Você receberá as instruções para criar uma nova senha com validade de 1 hora.</p>

            <?php if (! empty($erro)) { ?>
                <div class="mt-4"><?= component('alert', ['message' => (string) $erro, 'variant' => 'danger']) ?></div>
            <?php } ?>

            <form method="post" action="<?= e(site_url('login/gerarTokenResetarSenha')) ?>" novalidate class="mt-6 flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <?= component('input', [
                    'name' => 'email',
                    'label' => 'E-mail cadastrado',
                    'type' => 'email',
                    'required' => true,
                    'autocomplete' => 'email',
                    'placeholder' => 'seu.email@empresa.com.br',
                    'attrs' => [
                        'autofocus' => true,
                        'inputmode' => 'email',
                        'maxlength' => 100,
                        'data-msg-vazio' => 'Informe o seu e-mail.',
                        'data-msg-invalido' => 'Informe um e-mail válido.',
                    ],
                ]) ?>

                <?= component('button', [
                    'label' => 'Enviar instruções',
                    'type' => 'submit',
                    'size' => 'lg',
                    'icon' => 'mail',
                    'class' => 'mt-2 w-full shadow-elev-3',
                    'attrs' => ['data-rotulo-carregando' => 'Enviando…'],
                ]) ?>
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-body-md">
                <a href="<?= e(site_url('login/tokenManual')) ?>" class="rounded-xs text-on-dark underline underline-offset-4 hover:decoration-2 focus-visible:outline-3 focus-visible:outline-ring max-sm:inline-flex max-sm:min-h-11 max-sm:items-center">Já possuo um código</a>
                <a href="<?= e(site_url('login')) ?>" class="rounded-xs text-on-dark underline underline-offset-4 hover:decoration-2 focus-visible:outline-3 focus-visible:outline-ring max-sm:inline-flex max-sm:min-h-11 max-sm:items-center">Lembrei minha senha</a>
            </div>
        </section>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
