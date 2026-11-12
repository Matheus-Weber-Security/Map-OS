<?php
/**
 * Definição de nova senha após validação pelo autenticador (ou token legado).
 *
 * @var string                $token  Token em claro (sessão ou URL)
 * @var array<string, string> $erros  Erros por campo
 */
$erros ??= [];
$entrada = [
    'titulo' => 'Nova senha',
    'secundaria' => ['label' => 'Voltar ao login', 'href' => site_url('login'), 'icon' => 'log-in'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-6 lg:py-12">
        <section class="superficie-entrada w-full rounded-xxl border border-hairline-violet bg-night p-6 text-text sm:p-8" aria-labelledby="titulo-nova-senha">
            <h1 id="titulo-nova-senha" class="font-display text-heading-md text-text">Crie uma nova senha</h1>
            <p class="mt-2 text-body-md text-muted">Use no mínimo 8 caracteres com letra maiúscula, minúscula, número e caractere especial.</p>

            <?php if (isset($erros['_geral'])) { ?>
                <div class="mt-4"><?= component('alert', ['message' => $erros['_geral'], 'variant' => 'danger']) ?></div>
            <?php } ?>

            <form method="post" action="<?= e(site_url('login/salvarNovaSenha')) ?>" novalidate class="mt-6 flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <?= component('input', [
                    'name' => 'senha',
                    'label' => 'Nova Senha',
                    'type' => 'password',
                    'required' => true,
                    'alternar_senha' => true,
                    'autocomplete' => 'new-password',
                    'help' => 'Mínimo de 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.',
                    'error' => $erros['senha'] ?? null,
                    'attrs' => [
                        'autofocus' => true,
                        'minlength' => 8,
                        'maxlength' => 72,
                        'pattern' => '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).{8,}',
                        'data-msg-vazio' => 'Digite a nova senha.',
                        'data-msg-invalido' => 'Mínimo de 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.',
                    ],
                ]) ?>

                <?= component('input', [
                    'name' => 'senha_confirmacao',
                    'label' => 'Confirmar Nova Senha',
                    'type' => 'password',
                    'required' => true,
                    'alternar_senha' => true,
                    'autocomplete' => 'new-password',
                    'error' => $erros['senha_confirmacao'] ?? null,
                    'attrs' => [
                        'minlength' => 8,
                        'maxlength' => 72,
                        'data-msg-vazio' => 'Confirme a nova senha.',
                    ],
                ]) ?>

                <?= component('button', [
                    'label' => 'Salvar nova senha',
                    'type' => 'submit',
                    'size' => 'lg',
                    'icon' => 'save',
                    'class' => 'mt-2 w-full shadow-elev-3',
                    'attrs' => ['data-rotulo-carregando' => 'Salvando senha…'],
                ]) ?>
            </form>
        </section>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
