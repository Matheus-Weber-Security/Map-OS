<?php
/**
 * Formulário de Configuração de Forma de Pagamento SaaS no padrão oficial Map-OS v5.
 *
 * @var object $forma
 * @var array  $configuracoes
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6 shadow-2xs flex flex-col gap-4';
$grade = 'grid gap-4 sm:grid-cols-2';
$usarEnv = ! empty($configuracoes['usar_env']);
?>
<div class="flex max-w-4xl flex-col gap-6 pt-2 pb-8 mx-auto w-full">
    <!-- Cabeçalho -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <?= component('button', [
                    'label' => 'Voltar',
                    'icon' => 'chevron-left',
                    'variant' => 'ghost',
                    'size' => 'sm',
                    'href' => site_url('saas/gerenciar'),
                ]) ?>
                <span class="text-caption text-muted">/ Configurar Forma de Pagamento</span>
            </div>
            <h1 class="font-display text-heading-xl text-text flex items-center gap-2">
                <?= icon($forma->icone ?: 'banknote', ['class' => 'size-6 text-primary']) ?>
                <span><?= e($forma->nome) ?></span>
            </h1>
            <p class="text-caption text-muted">Ajuste os parâmetros de cobrança, provedores e credenciais para compra de +30 dias.</p>
        </div>
        <div>
            <?= component('pill-status', [
                'label' => ! empty($forma->ativo) ? 'Ativo no Sistema' : 'Inativo no Sistema',
                'variant' => ! empty($forma->ativo) ? 'success' : 'neutral',
            ]) ?>
        </div>
    </header>

    <form method="post" action="<?= e(site_url('saas/salvarFormaPagamento/' . $forma->id)) ?>" novalidate class="flex flex-col gap-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <!-- Seção 1: Informações Gerais -->
        <section class="<?= e($caixa) ?>">
            <div class="border-b border-border pb-3">
                <h2 class="font-display text-base font-semibold text-text">Informações Gerais</h2>
                <p class="text-caption text-muted">Nome exibido na tela de assinaturas e status de disponibilidade.</p>
            </div>

            <div class="<?= e($grade) ?>">
                <?= component('input', [
                    'name' => 'nome',
                    'label' => 'Nome da Forma de Pagamento',
                    'type' => 'text',
                    'value' => $forma->nome,
                    'required' => true,
                    'attrs' => ['maxlength' => 100, 'data-msg-vazio' => 'Informe o nome.'],
                ]) ?>

                <?= component('input', [
                    'name' => 'descricao',
                    'label' => 'Descrição Curta / Subtítulo',
                    'type' => 'text',
                    'value' => $forma->descricao,
                    'attrs' => ['maxlength' => 255],
                ]) ?>
            </div>

            <div class="pt-2">
                <?= component('switch', [
                    'name' => 'ativo',
                    'label' => 'Ativar esta forma de pagamento para os assinantes',
                    'value' => '1',
                    'checked' => ! empty($forma->ativo),
                    'help' => 'Quando ativada, as empresas poderão selecionar esta opção na tela de assinaturas para renovar +30 dias.',
                ]) ?>
            </div>
        </section>

        <!-- Seção 2: Configurações Específicas por Método -->
        <?php if ($forma->codigo === 'pix') { ?>
            <!-- Configurações do PIX -->
            <section class="<?= e($caixa) ?>">
                <div class="border-b border-border pb-3">
                    <h2 class="font-display text-base font-semibold text-text flex items-center gap-2">
                        <?= icon('qr-code', ['class' => 'size-5 text-primary']) ?>
                        <span>Opções do PIX</span>
                    </h2>
                    <p class="text-caption text-muted">Defina como o QR Code será gerado para o cliente.</p>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('select', [
                        'name' => 'tipo_pix',
                        'label' => 'Modelo de Cobrança PIX',
                        'selected' => $configuracoes['tipo_pix'] ?? 'dinamico',
                        'options' => [
                            ['value' => 'dinamico', 'label' => 'PIX Dinâmico (Via Gateway com baixa automática)'],
                            ['value' => 'manual', 'label' => 'PIX Chave Fixa (Manual com chave da empresa)'],
                        ],
                    ]) ?>

                    <?= component('select', [
                        'name' => 'provedor',
                        'label' => 'Gateway Processador',
                        'selected' => $forma->provedor,
                        'options' => [
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago'],
                            ['value' => 'asaas', 'label' => 'Asaas'],
                            ['value' => 'gerencianet', 'label' => 'Efí (antiga Gerencianet)'],
                        ],
                    ]) ?>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('input', [
                        'name' => 'chave_pix',
                        'label' => 'Chave PIX (Para modo manual)',
                        'type' => 'text',
                        'value' => $configuracoes['chave_pix'] ?? '',
                        'help' => 'Preencha se estiver usando o modelo Chave Fixa.',
                    ]) ?>

                    <?= component('select', [
                        'name' => 'tipo_chave',
                        'label' => 'Tipo da Chave PIX',
                        'selected' => $configuracoes['tipo_chave'] ?? 'aleatoria',
                        'options' => [
                            ['value' => 'cnpj', 'label' => 'CNPJ'],
                            ['value' => 'cpf', 'label' => 'CPF'],
                            ['value' => 'email', 'label' => 'E-mail'],
                            ['value' => 'telefone', 'label' => 'Telefone'],
                            ['value' => 'aleatoria', 'label' => 'Chave Aleatória (EVP)'],
                        ],
                    ]) ?>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('input', [
                        'name' => 'titular',
                        'label' => 'Nome do Titular da Conta',
                        'type' => 'text',
                        'value' => $configuracoes['titular'] ?? '',
                    ]) ?>

                    <?= component('input', [
                        'name' => 'expiracao_minutos',
                        'label' => 'Tempo de Expiração do QR Code (Minutos)',
                        'type' => 'number',
                        'value' => (string) ($configuracoes['expiracao_minutos'] ?? 60),
                        'attrs' => ['min' => 5, 'max' => 1440],
                    ]) ?>
                </div>

                <div class="pt-2 border-t border-border">
                    <?= component('switch', [
                        'name' => 'usar_env',
                        'label' => 'Usar credenciais de gateway padrão do sistema (.env)',
                        'value' => '1',
                        'checked' => $usarEnv,
                        'help' => 'Aproveita automaticamente as chaves do Mercado Pago, Efí ou Asaas configuradas no arquivo .env do Map-OS.',
                    ]) ?>
                </div>
            </section>

        <?php } elseif ($forma->codigo === 'boleto') { ?>
            <!-- Configurações do Boleto Bancário -->
            <section class="<?= e($caixa) ?>">
                <div class="border-b border-border pb-3">
                    <h2 class="font-display text-base font-semibold text-text flex items-center gap-2">
                        <?= icon('barcode', ['class' => 'size-5 text-primary']) ?>
                        <span>Opções do Boleto Bancário</span>
                    </h2>
                    <p class="text-caption text-muted">Configurações de emissão e prazos de compensação bancária.</p>
                </div>

                <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex items-start gap-3">
                    <?= icon('info', ['class' => 'size-5 text-primary shrink-0 mt-0.5']) ?>
                    <div class="text-caption text-muted">
                        <strong class="text-text font-semibold">Exigência do Banco Central:</strong> Para emitir boleto bancário registrado, a empresa assinante precisa ter Razão Social, CNPJ/CPF e Endereço com CEP cadastrados no sistema.
                    </div>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('select', [
                        'name' => 'provedor',
                        'label' => 'Gateway Emissor do Boleto',
                        'selected' => $forma->provedor,
                        'options' => [
                            ['value' => 'asaas', 'label' => 'Asaas (Recomendado para Boletos)'],
                            ['value' => 'gerencianet', 'label' => 'Efí (antiga Gerencianet)'],
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago'],
                        ],
                    ]) ?>

                    <?= component('input', [
                        'name' => 'dias_vencimento',
                        'label' => 'Prazo de Vencimento do Boleto (Dias)',
                        'type' => 'number',
                        'value' => (string) ($configuracoes['dias_vencimento'] ?? 3),
                        'required' => true,
                        'attrs' => ['min' => 1, 'max' => 30],
                        'help' => 'Quantidade de dias corridos após a geração para vencimento do boleto.',
                    ]) ?>
                </div>

                <div class="pt-2 border-t border-border">
                    <?= component('switch', [
                        'name' => 'usar_env',
                        'label' => 'Usar credenciais padrão do sistema (.env)',
                        'value' => '1',
                        'checked' => $usarEnv,
                        'help' => 'Utiliza as chaves de API e credenciais bancárias já preenchidas no .env do Map-OS.',
                    ]) ?>
                </div>
            </section>

        <?php } elseif ($forma->codigo === 'cartao_credito') { ?>
            <!-- Configurações do Cartão de Crédito -->
            <section class="<?= e($caixa) ?>">
                <div class="border-b border-border pb-3">
                    <h2 class="font-display text-base font-semibold text-text flex items-center gap-2">
                        <?= icon('banknote', ['class' => 'size-5 text-primary']) ?>
                        <span>Opções do Cartão de Crédito</span>
                    </h2>
                    <p class="text-caption text-muted">Pagamento à vista com aprovação instantânea e renovação de +30 dias.</p>
                </div>

                <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex items-start gap-3">
                    <?= icon('shield-check', ['class' => 'size-5 text-success-ink shrink-0 mt-0.5']) ?>
                    <div class="text-caption text-muted">
                        <strong class="text-text font-semibold">Segurança & Conformidade PCI-DSS:</strong> Os dados do cartão são tokenizados diretamente no gateway através de conexão criptografada (HTTPS/TLS). O servidor nunca armazena dados de cartão.
                    </div>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('select', [
                        'name' => 'provedor',
                        'label' => 'Gateway Processador',
                        'selected' => $forma->provedor,
                        'options' => [
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago (Recomendado)'],
                            ['value' => 'asaas', 'label' => 'Asaas'],
                        ],
                    ]) ?>

                    <?= component('select', [
                        'name' => 'modo',
                        'label' => 'Modo de Cobrança',
                        'selected' => $configuracoes['modo'] ?? 'transparente',
                        'options' => [
                            ['value' => 'transparente', 'label' => 'Checkout Transparente (Formulário integrado)'],
                            ['value' => 'link', 'label' => 'Link Seguro / Checkout Hospedado'],
                        ],
                    ]) ?>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('input', [
                        'name' => 'max_parcelas',
                        'label' => 'Parcelamento Máximo',
                        'type' => 'number',
                        'value' => (string) ($configuracoes['max_parcelas'] ?? 1),
                        'attrs' => ['min' => 1, 'max' => 12],
                        'help' => 'Normalmente 1x (à vista) para plano mensal de 30 dias.',
                    ]) ?>
                </div>

                <div class="pt-2 border-t border-border">
                    <?= component('switch', [
                        'name' => 'usar_env',
                        'label' => 'Usar credenciais padrão do sistema (.env)',
                        'value' => '1',
                        'checked' => $usarEnv,
                        'help' => 'Utiliza a Public Key e Access Token configurados no .env.',
                    ]) ?>
                </div>
            </section>

        <?php } elseif ($forma->codigo === 'cartao_debito') { ?>
            <!-- Configurações do Cartão de Débito -->
            <section class="<?= e($caixa) ?>">
                <div class="border-b border-border pb-3">
                    <h2 class="font-display text-base font-semibold text-text flex items-center gap-2">
                        <?= icon('receipt', ['class' => 'size-5 text-primary']) ?>
                        <span>Opções do Cartão de Débito</span>
                    </h2>
                    <p class="text-caption text-muted">Débito em conta com autenticação bancária 3D Secure (3DS).</p>
                </div>

                <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex items-start gap-3">
                    <?= icon('info', ['class' => 'size-5 text-primary shrink-0 mt-0.5']) ?>
                    <div class="text-caption text-muted">
                        <strong class="text-text font-semibold">Autenticação 3D Secure Obrigatória:</strong> No Brasil, pagamentos em débito online exigem redirecionamento ou desafio 3DS no aplicativo do banco do cliente. O sistema utiliza a sessão segura do gateway para garantir a conformidade.
                    </div>
                </div>

                <div class="<?= e($grade) ?>">
                    <?= component('select', [
                        'name' => 'provedor',
                        'label' => 'Gateway Processador',
                        'selected' => $forma->provedor,
                        'options' => [
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago Checkout Pro (3DS)'],
                            ['value' => 'asaas', 'label' => 'Asaas Link Seguro'],
                        ],
                    ]) ?>
                </div>

                <div class="pt-2 border-t border-border">
                    <?= component('switch', [
                        'name' => 'usar_env',
                        'label' => 'Usar credenciais padrão do sistema (.env)',
                        'value' => '1',
                        'checked' => $usarEnv,
                    ]) ?>
                </div>
            </section>
        <?php } ?>

        <!-- Seção 3: Instruções ao Assinante -->
        <section class="<?= e($caixa) ?>">
            <div class="border-b border-border pb-3">
                <h2 class="font-display text-base font-semibold text-text">Instruções aos Assinantes</h2>
                <p class="text-caption text-muted">Texto exibido para a empresa no momento da escolha ou realização do pagamento.</p>
            </div>

            <div>
                <?= component('textarea', [
                    'name' => 'instrucoes',
                    'label' => 'Instruções de Pagamento',
                    'value' => $forma->instrucoes,
                    'rows' => 3,
                    'attrs' => ['maxlength' => 500],
                ]) ?>
            </div>
        </section>

        <!-- Barra de Ações -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <?= component('button', [
                'label' => 'Cancelar',
                'variant' => 'ghost',
                'href' => site_url('saas/gerenciar'),
            ]) ?>

            <?= component('button', [
                'label' => 'Salvar Configurações',
                'icon' => 'save',
                'type' => 'submit',
                'attrs' => ['data-rotulo-carregando' => 'Salvando...'],
            ]) ?>
        </div>
    </form>
</div>
