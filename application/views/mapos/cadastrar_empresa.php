<?php
/**
 * Cadastro de Nova Empresa (Tenant) no painel Map-OS v5 (#SaaS-AutoCadastro).
 *
 * Superfície de entrada (DESIGN.md, views/entrada/inicio.php e fim.php):
 * Fundo escuro com estrelas e formulário enxuto e direto ao ponto
 * com 10 dias de degustação gratuita.
 *
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 */
$erroGeral = $erros['_geral'] ?? null;
$errosDeCampo = array_diff_key($erros, ['_geral' => true]);

$entrada = [
    'titulo' => 'Cadastrar Empresa',
    'secundaria' => ['label' => 'Já tenho conta', 'href' => site_url('login'), 'icon' => 'log-in'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-6 lg:py-10">
        <header class="mb-6">
            <h1 class="font-display text-[35px] font-bold tracking-tight text-on-dark sm:text-[41px] md:text-[50px] whitespace-nowrap">
                Cadastre sua <span class="rounded-xs bg-accent-lime px-2.5 py-0.5 text-ink">empresa</span>
            </h1>
            <p class="mt-2 text-sm text-on-dark-muted sm:text-base">
                Crie sua conta para gerenciar ordens de serviço, vendas e financeiro.
            </p>
        </header>

        <div class="superficie-entrada flex flex-col gap-4 rounded-xxl border border-hairline-violet bg-night p-4 text-text sm:p-8">
            <?php if ($erroGeral !== null) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => $erroGeral]) ?>
            <?php } elseif ($errosDeCampo !== []) { ?>
                <?= component('alert', ['variant' => 'danger', 'message' => count($errosDeCampo) === 1 ? 'Confira o campo destacado abaixo.' : 'Confira os ' . count($errosDeCampo) . ' campos destacados abaixo.']) ?>
            <?php } ?>

            <form method="post" action="<?= e(site_url('login/cadastrar')) ?>" novalidate class="flex flex-col gap-6" <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                <!-- Seção 1: Identificação e Acesso -->
                <section class="flex flex-col gap-4" aria-labelledby="secao-acesso">
                    <h2 id="secao-acesso" class="text-heading-sm text-text">Dados de Acesso</h2>

                    <?= component('input', [
                        'name' => 'nome',
                        'id' => 'nome',
                        'label' => 'Nome Completo ou Razão Social',
                        'required' => true,
                        'value' => $valores['nome'] ?? null,
                        'error' => $errosDeCampo['nome'] ?? null,
                        'placeholder' => 'Ex: Carlos da Silva ou Oficina Mecânica',
                        'help' => 'Seu nome ou como seu negócio é conhecido.',
                        'attrs' => [
                            'maxlength' => 100,
                            'autofocus' => true,
                            'data-msg-vazio' => 'Informe seu nome ou o nome do seu negócio.',
                        ],
                    ]) ?>

                    <?= component('input', [
                        'name' => 'email',
                        'id' => 'email',
                        'label' => 'E-mail para Login',
                        'type' => 'email',
                        'required' => true,
                        'value' => $valores['email'] ?? null,
                        'error' => $errosDeCampo['email'] ?? null,
                        'placeholder' => 'seu.email@empresa.com.br',
                        'help' => 'Este será seu login principal de acesso ao sistema.',
                        'attrs' => [
                            'maxlength' => 80,
                            'inputmode' => 'email',
                            'data-msg-vazio' => 'Informe o seu e-mail de acesso.',
                        ],
                    ]) ?>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= component('input', [
                            'name' => 'senha',
                            'id' => 'senha',
                            'label' => 'Criar Senha',
                            'type' => 'password',
                            'required' => true,
                            'error' => $errosDeCampo['senha'] ?? null,
                            'help' => 'Mínimo de 8 caracteres.',
                            'attrs' => [
                                'minlength' => 8,
                                'maxlength' => 72,
                                'data-msg-vazio' => 'Crie uma senha de acesso.',
                                'data-msg-invalido' => 'A senha precisa de pelo menos 8 caracteres.',
                            ],
                        ]) ?>

                        <?= component('input', [
                            'name' => 'senha_confirmacao',
                            'id' => 'senha_confirmacao',
                            'label' => 'Confirmar Senha',
                            'type' => 'password',
                            'required' => true,
                            'error' => $errosDeCampo['senha_confirmacao'] ?? null,
                            'attrs' => [
                                'maxlength' => 72,
                                'data-msg-vazio' => 'Repita a senha para confirmar.',
                            ],
                        ]) ?>
                    </div>
                </section>

                <!-- Seção 2: Teste de Robô (Captcha) -->
                <section class="border-t border-border pt-4" aria-labelledby="secao-captcha">
                    <h2 id="secao-captcha" class="text-heading-sm text-text">Confirme que você não é um robô</h2>
                    <div class="mt-3 grid items-start gap-4 sm:grid-cols-2">
                        <img src="<?= e(site_url('login/captcha') . '?' . bin2hex(random_bytes(4))) ?>" alt="Imagem com os caracteres a digitar" width="180" height="50" class="h-[50px] w-[180px] rounded-md border border-border">
                        <?= component('input', [
                            'name' => 'captcha',
                            'label' => 'Caracteres da imagem',
                            'required' => true,
                            'autocomplete' => 'off',
                            'help' => 'Não diferencia maiúsculas de minúsculas.',
                            'error' => $errosDeCampo['captcha'] ?? null,
                            'attrs' => [
                                'maxlength' => 10,
                                'autocapitalize' => 'off',
                                'spellcheck' => 'false',
                                'data-msg-vazio' => 'Digite os caracteres da imagem.',
                            ],
                        ]) ?>
                    </div>
                </section>

                <div class="flex flex-col items-center justify-end pt-2 sm:flex-row">
                    <?= component('button', [
                        'label' => 'Criar conta',
                        'type' => 'submit',
                        'size' => 'lg',
                        'icon' => 'check',
                        'class' => 'w-full shadow-elev-3 sm:w-auto',
                        'attrs' => ['data-rotulo-carregando' => 'Criando seu ambiente…'],
                    ]) ?>
                </div>
            </form>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
