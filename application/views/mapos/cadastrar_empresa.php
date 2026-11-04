<?php
/**
 * Cadastro de Nova Empresa (Tenant) no painel Map-OS v5 (#SaaS-AutoCadastro).
 *
 * Superfície de entrada (DESIGN.md, views/entrada/inicio.php e fim.php):
 * Fundo escuro com estrelas e formulário centralizado com 10 dias de degustação.
 * Formulário com suporte dinâmico a CPF/CNPJ, auto-preenchimento de endereço
 * por CEP e dados da Receita por CNPJ via js_module('clientes/formulario').
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
    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 lg:py-10" <?= js_module('clientes/formulario') ?>>
        <header class="mb-6">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-hairline-violet bg-night px-3.5 py-1 text-caption font-semibold text-accent-lime">
                <?= icon('sparkles', ['class' => 'size-4']) ?> 10 dias grátis de assinatura sem compromisso
            </div>
            <h1 class="font-display text-display-hero-sm font-bold text-on-dark sm:text-display-large">
                Cadastre sua <span class="rounded-xs bg-accent-lime px-3 text-ink">empresa</span>.
            </h1>
            <p class="mt-2 max-w-xl text-body-lg text-on-dark-muted">
                Comece agora mesmo com ordens de serviço, vendas, compras, controle de estoque e financeiro completo.
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

                <!-- Seção 1: Identificação da Empresa -->
                <section aria-labelledby="secao-empresa">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="secao-empresa" class="text-heading-sm text-text">Identificação da Empresa</h2>
                        <span class="text-caption text-muted">Pessoa Física (CPF) ou Jurídica (CNPJ)</span>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <?= component('input', [
                                    'name' => 'documento',
                                    'id' => 'documento',
                                    'label' => 'CPF ou CNPJ',
                                    'help' => 'Digite o CPF (11 dígitos) ou CNPJ (14 dígitos).',
                                    'required' => true,
                                    'value' => $valores['documento'] ?? null,
                                    'error' => $errosDeCampo['documento'] ?? null,
                                    'attrs' => [
                                        'data-mascara' => 'documento',
                                        'maxlength' => 20,
                                        'inputmode' => 'numeric',
                                        'autocomplete' => 'off',
                                        'autofocus' => true,
                                        'data-msg-vazio' => 'Informe o CPF ou CNPJ.',
                                    ],
                                ]) ?>
                                <div class="mb-0.5">
                                    <?= component('button', [
                                        'label' => 'Buscar CNPJ',
                                        'icon' => 'search',
                                        'variant' => 'outline',
                                        'type' => 'button',
                                        'class' => 'w-full sm:w-auto',
                                        'attrs' => ['data-buscar-cnpj' => true],
                                    ]) ?>
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <?= component('input', [
                                'name' => 'nome_empresa',
                                'id' => 'nomeCliente',
                                'label' => 'Razão Social ou Nome do Negócio',
                                'required' => true,
                                'value' => $valores['nome_empresa'] ?? null,
                                'error' => $errosDeCampo['nome_empresa'] ?? null,
                                'placeholder' => 'Ex: Minha Oficina Mecânica LTDA',
                                'attrs' => ['maxlength' => 255, 'data-msg-vazio' => 'Informe o nome da sua empresa.'],
                            ]) ?>
                        </div>

                        <div class="sm:col-span-2">
                            <?= component('input', [
                                'name' => 'nome_fantasia',
                                'id' => 'contato',
                                'label' => 'Nome Fantasia',
                                'help' => 'Como seus clientes conhecem a empresa (opcional).',
                                'value' => $valores['nome_fantasia'] ?? null,
                                'error' => $errosDeCampo['nome_fantasia'] ?? null,
                                'placeholder' => 'Ex: Oficina do João',
                                'attrs' => ['maxlength' => 255],
                            ]) ?>
                        </div>
                    </div>
                </section>

                <!-- Seção 2: Responsável e Acesso -->
                <section class="border-t border-border pt-6" aria-labelledby="secao-responsavel">
                    <h2 id="secao-responsavel" class="text-heading-sm text-text">Responsável e Acesso</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <?= component('input', [
                            'name' => 'nome_responsavel',
                            'label' => 'Nome Completo do Responsável',
                            'required' => true,
                            'value' => $valores['nome_responsavel'] ?? null,
                            'error' => $errosDeCampo['nome_responsavel'] ?? null,
                            'placeholder' => 'Ex: Carlos da Silva',
                            'attrs' => ['maxlength' => 80, 'data-msg-vazio' => 'Informe o nome do responsável.'],
                        ]) ?>

                        <?= component('input', [
                            'name' => 'telefone',
                            'label' => 'WhatsApp ou Celular',
                            'type' => 'tel',
                            'required' => true,
                            'value' => $valores['telefone'] ?? null,
                            'error' => $errosDeCampo['telefone'] ?? null,
                            'attrs' => ['data-mascara' => 'telefone', 'maxlength' => 20, 'data-msg-vazio' => 'Informe um WhatsApp ou telefone de contato.'],
                        ]) ?>

                        <div class="sm:col-span-2">
                            <?= component('input', [
                                'name' => 'email',
                                'label' => 'E-mail para Login',
                                'type' => 'email',
                                'required' => true,
                                'help' => 'Este será seu login principal de acesso ao sistema.',
                                'value' => $valores['email'] ?? null,
                                'error' => $errosDeCampo['email'] ?? null,
                                'placeholder' => 'seu.email@empresa.com.br',
                                'attrs' => ['maxlength' => 80, 'inputmode' => 'email', 'data-msg-vazio' => 'Informe o seu e-mail de acesso.'],
                            ]) ?>
                        </div>
                    </div>
                </section>

                <!-- Seção 3: Endereço (Opcional) -->
                <section class="border-t border-border pt-6" aria-labelledby="secao-endereco">
                    <h2 id="secao-endereco" class="text-heading-sm text-text">Endereço da Empresa</h2>
                    <p class="mt-0.5 text-caption text-muted">Opcional. Digite o CEP para preenchimento automático.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <?= component('input', [
                            'name' => 'cep',
                            'label' => 'CEP',
                            'value' => $valores['cep'] ?? null,
                            'error' => $errosDeCampo['cep'] ?? null,
                            'attrs' => ['data-mascara' => 'cep', 'inputmode' => 'numeric', 'maxlength' => 9],
                        ]) ?>
                        <div class="hidden sm:block" aria-hidden="true"></div>

                        <?= component('input', [
                            'name' => 'rua',
                            'label' => 'Rua / Logradouro',
                            'value' => $valores['rua'] ?? null,
                            'attrs' => ['maxlength' => 70],
                        ]) ?>

                        <div class="grid grid-cols-2 gap-4">
                            <?= component('input', [
                                'name' => 'numero',
                                'label' => 'Número',
                                'value' => $valores['numero'] ?? null,
                                'attrs' => ['maxlength' => 15],
                            ]) ?>
                            <?= component('input', [
                                'name' => 'bairro',
                                'label' => 'Bairro',
                                'value' => $valores['bairro'] ?? null,
                                'attrs' => ['maxlength' => 45],
                            ]) ?>
                        </div>

                        <?= component('input', [
                            'name' => 'cidade',
                            'label' => 'Cidade',
                            'value' => $valores['cidade'] ?? null,
                            'attrs' => ['maxlength' => 45],
                        ]) ?>

                        <?= component('select', [
                            'name' => 'estado',
                            'label' => 'UF',
                            'placeholder' => '—',
                            'options' => array_combine(array_keys(ufsDoBrasil()), array_keys(ufsDoBrasil())),
                            'selected' => $valores['estado'] ?? null,
                            'error' => $errosDeCampo['estado'] ?? null,
                        ]) ?>
                    </div>
                </section>

                <!-- Seção 4: Senha -->
                <section class="border-t border-border pt-6" aria-labelledby="secao-senha">
                    <h2 id="secao-senha" class="text-heading-sm text-text">Senha de Acesso</h2>
                    <p class="mt-0.5 text-caption text-muted">Pelo menos 8 caracteres para proteger a sua conta.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <?= component('input', [
                            'name' => 'senha',
                            'label' => 'Criar Senha',
                            'type' => 'password',
                            'required' => true,
                            'error' => $errosDeCampo['senha'] ?? null,
                            'attrs' => ['minlength' => 8, 'maxlength' => 72, 'data-msg-vazio' => 'Crie uma senha de acesso.', 'data-msg-invalido' => 'A senha precisa de pelo menos 8 caracteres.'],
                        ]) ?>

                        <?= component('input', [
                            'name' => 'senha_confirmacao',
                            'label' => 'Confirmar Senha',
                            'type' => 'password',
                            'required' => true,
                            'error' => $errosDeCampo['senha_confirmacao'] ?? null,
                            'attrs' => ['maxlength' => 72, 'data-msg-vazio' => 'Repita a senha para confirmar.'],
                        ]) ?>
                    </div>
                </section>

                <!-- Seção 5: Captcha -->
                <section class="border-t border-border pt-6" aria-labelledby="secao-captcha">
                    <h2 id="secao-captcha" class="text-heading-sm text-text">Confirme que você não é um robô</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <img src="<?= e(site_url('login/captcha') . '?' . bin2hex(random_bytes(4))) ?>" alt="Imagem com os caracteres a digitar" width="180" height="50" class="h-[50px] w-[180px] rounded-md border border-border">
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

                <div class="flex flex-col items-center justify-between gap-4 pt-4 sm:flex-row">
                    <p class="text-caption text-muted">
                        Ao se cadastrar, você concorda em iniciar seu período gratuito de 10 dias.
                    </p>
                    <?= component('button', [
                        'label' => 'Criar empresa e iniciar teste grátis',
                        'type' => 'submit',
                        'size' => 'lg',
                        'icon' => 'sparkles',
                        'class' => 'w-full shadow-elev-3 sm:w-auto',
                        'attrs' => ['data-rotulo-carregando' => 'Criando ambiente da empresa…'],
                    ]) ?>
                </div>
            </form>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
