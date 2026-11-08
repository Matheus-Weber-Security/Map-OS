<?php
/**
 * Cadastro pela área do cliente (#2848), na superfície de entrada (DESIGN.md,
 * views/entrada/): o formulário fica num card .superficie-entrada, com os
 * campos claros. Padrão dos formulários (#2851): POST comum, validação no
 * navegador e no servidor, erro em cada campo; o captcha vale para uma
 * tentativa e a imagem é nova a cada carga.
 *
 * @var array<string, string|bool> $valores
 * @var array<string, string>      $erros  Por campo; _geral para falha ao salvar
 */
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$entrada = [
    'titulo' => 'Criar conta',
    'secundaria' => ['label' => 'Já tenho conta', 'href' => site_url('mine'), 'icon' => 'log-in'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto w-full max-w-2xl flex-1 px-4 py-6 lg:py-8" <?= js_module('clientes/formulario') ?>>
        <header class="mb-5">
            <h1 class="font-display text-heading-lg sm:text-heading-xl font-bold tracking-tight text-on-dark">Crie sua <span class="rounded-xs bg-accent-lime px-2 py-0.5 text-ink">conta</span></h1>
            <p class="mt-1.5 text-sm text-on-dark-muted">Acompanhe as ordens de serviço, as compras e as cobranças pela área do cliente.</p>
        </header>

        <div class="superficie-entrada flex flex-col gap-4 rounded-xxl border border-hairline-violet bg-night p-5 text-text sm:p-6">
            <?php if ($erroGeral !== null) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
            <?php } elseif ($errosDeCampo !== []) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado abaixo.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados abaixo.']) ?>
            <?php } ?>

            <form method="post" action="<?= e(site_url('mine/cadastrar')) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <?php $this->load->view('conecte/partes/campos_cliente', ['erros' => $errosDeCampo, 'novo' => true, 'caixa' => 'border-t border-border pt-4', 'caixa_primeira' => '']); ?>

                <section class="border-t border-border pt-4" aria-labelledby="secao-captcha">
                    <h2 id="secao-captcha" class="text-heading-sm text-text">Confirme que você não é um robô</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1">
                            <span class="text-sm font-medium invisible select-none max-sm:hidden" aria-hidden="true">&nbsp;</span>
                            <div class="flex items-center gap-2" data-captcha-container>
                                <img src="<?= e(site_url('mine/captcha') . '?' . bin2hex(random_bytes(4))) ?>" alt="Imagem com os caracteres a digitar" width="160" height="40" class="h-10 w-[160px] shrink-0 rounded-md border border-border" data-captcha-imagem>
                                <button type="button"
                                        class="flex size-10 shrink-0 items-center justify-center rounded-md border border-hairline-violet bg-surface-subtle text-muted hover:bg-surface hover:text-text focus-visible:outline-3 focus-visible:outline-ring cursor-pointer transition-colors"
                                        data-recarregar-captcha
                                        title="Recarregar outra imagem"
                                        aria-label="Recarregar outra imagem">
                                    <?= icon('refresh-cw', ['class' => 'size-5']) ?>
                                </button>
                            </div>
                        </div>
                        <?= component('input', [
                            'name' => 'captcha',
                            'label' => 'Caracteres da imagem',
                            'required' => true,
                            'autocomplete' => 'off',
                            'help' => 'Não diferencia maiúsculas de minúsculas.',
                            'error' => $errosDeCampo['captcha'] ?? null,
                            'attrs' => ['maxlength' => 10, 'autocapitalize' => 'off', 'spellcheck' => 'false', 'data-msg-vazio' => 'Digite os caracteres da imagem.'],
                        ]) ?>
                    </div>
                </section>

                <div class="flex justify-end pt-2">
                    <?= component('button', [
                        'label' => 'Criar conta',
                        'type' => 'submit',
                        'size' => 'lg',
                        'icon' => 'circle-user',
                        // DESIGN.md: na superfície de entrada o button-primary leva o halo nível 3.
                        'class' => 'w-full shadow-elev-3 sm:w-auto',
                        'attrs' => ['data-rotulo-carregando' => 'Criando…'],
                    ]) ?>
                </div>
            </form>
        </div>

        <div class="mt-4 text-center text-sm text-on-dark-muted">
            <a href="<?= e(site_url('mine')) ?>" class="text-on-dark underline underline-offset-4 hover:decoration-2">Já tem uma conta? Acesse aqui</a>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
