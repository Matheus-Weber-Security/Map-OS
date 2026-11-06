<?php
/**
 * Formulário de Configuração de Forma de Pagamento SaaS no padrão oficial Map-OS v5.
 *
 * @var object $forma
 * @var array  $configuracoes
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6 shadow-2xs flex flex-col gap-4';
$grade = 'grid gap-4 sm:grid-cols-2';
$tipoPixAtual = $configuracoes['tipo_pix'] ?? 'dinamico';
$provedorAtual = $forma->provedor ?: 'mercadopago';
?>
<div class="flex max-w-4xl flex-col gap-6 pt-2 pb-8 mx-auto w-full" <?= js_module('saas/forma-pagamento') ?>>
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
            <p class="text-caption text-muted">Ajuste os parâmetros de cobrança, provedores e tokens de API para compra de +30 dias.</p>
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
                        <span>Configurações do PIX</span>
                    </h2>
                    <p class="text-caption text-muted">Escolha entre baixa automática via Gateway de pagamento ou chave PIX fixa para transferência direta.</p>
                </div>

                <div class="w-full">
                    <?= component('select', [
                        'name' => 'tipo_pix',
                        'label' => 'Modelo de Cobrança PIX',
                        'selected' => $tipoPixAtual,
                        'options' => [
                            ['value' => 'dinamico', 'label' => 'PIX Dinâmico (Via Gateway com baixa automática instantânea)'],
                            ['value' => 'manual', 'label' => 'PIX Chave Fixa (Manual com chave bancária da empresa master)'],
                        ],
                        'help' => 'No modelo dinâmico, o gateway gera o QR Code e credita os 30 dias na hora. No modelo manual, o cliente paga na sua chave e você confere o comprovante.',
                    ]) ?>
                </div>

                <!-- Bloco PIX Dinâmico (Via Gateway) -->
                <div id="bloco-pix-dinamico" class="flex flex-col gap-4 <?= $tipoPixAtual === 'manual' ? 'hidden' : '' ?>">
                    <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex items-start gap-3">
                        <?= icon('circle-dollar-sign', ['class' => 'size-5 text-primary shrink-0 mt-0.5']) ?>
                        <div class="text-caption text-muted">
                            <strong class="text-text font-semibold">Baixa Automática Instantânea:</strong> A API do gateway gera um QR Code exclusivo com valor e expiração. Quando a empresa assinante paga em seu banco, o gateway avisa o sistema e +30 dias são creditados automaticamente.
                        </div>
                    </div>

                    <div class="<?= e($grade) ?>">
                        <?= component('select', [
                            'name' => 'provedor',
                            'label' => 'Gateway Processador',
                            'selected' => $provedorAtual,
                            'options' => [
                                ['value' => 'mercadopago', 'label' => 'Mercado Pago'],
                                ['value' => 'asaas', 'label' => 'Asaas'],
                                ['value' => 'gerencianet', 'label' => 'Efí (antiga Gerencianet)'],
                            ],
                        ]) ?>

                        <?= component('input', [
                            'name' => 'expiracao_minutos',
                            'label' => 'Tempo de Expiração do QR Code (Minutos)',
                            'type' => 'number',
                            'value' => (string) ($configuracoes['expiracao_minutos'] ?? 60),
                            'attrs' => ['min' => 5, 'max' => 1440],
                            'help' => 'Tempo que o cliente tem para ler o QR Code e pagar antes de expirar.',
                        ]) ?>
                    </div>

                    <!-- Credenciais Mercado Pago -->
                    <div id="credenciais-mercadopago" class="flex flex-col gap-4 border-t border-border pt-4 <?= $provedorAtual !== 'mercadopago' ? 'hidden' : '' ?>">
                        <div class="flex items-center gap-2">
                            <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                            <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Mercado Pago</h3>
                        </div>
                        <div class="<?= e($grade) ?>">
                            <?= component('input', [
                                'name' => 'mp_access_token',
                                'label' => 'Access Token (Produção)',
                                'type' => 'password',
                                'value' => $configuracoes['access_token'] ?? $configuracoes['mp_access_token'] ?? '',
                                'help' => 'Token de produção (ex: APP_USR-...) obtido no painel de desenvolvedores do Mercado Pago.',
                                'attrs' => ['autocomplete' => 'new-password'],
                            ]) ?>

                            <?= component('input', [
                                'name' => 'mp_public_key',
                                'label' => 'Public Key (Chave Pública)',
                                'type' => 'text',
                                'value' => $configuracoes['public_key'] ?? $configuracoes['mp_public_key'] ?? '',
                                'help' => 'Chave pública da sua aplicação no Mercado Pago.',
                            ]) ?>
                        </div>
                    </div>

                    <!-- Credenciais Asaas -->
                    <div id="credenciais-asaas" class="flex flex-col gap-4 border-t border-border pt-4 <?= $provedorAtual !== 'asaas' ? 'hidden' : '' ?>">
                        <div class="flex items-center gap-2">
                            <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                            <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Asaas</h3>
                        </div>
                        <div class="w-full">
                            <?= component('input', [
                                'name' => 'asaas_api_key',
                                'label' => 'Chave de API Asaas (API Key)',
                                'type' => 'password',
                                'value' => $configuracoes['api_key'] ?? $configuracoes['asaas_api_key'] ?? '',
                                'help' => 'Chave de API gerada no painel Asaas em Minha Conta > Integrações.',
                                'attrs' => ['autocomplete' => 'new-password'],
                            ]) ?>
                        </div>
                    </div>

                    <!-- Credenciais Efí (Gerencianet) -->
                    <div id="credenciais-gerencianet" class="flex flex-col gap-4 border-t border-border pt-4 <?= $provedorAtual !== 'gerencianet' ? 'hidden' : '' ?>">
                        <div class="flex items-center gap-2">
                            <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                            <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Efí (Gerencianet)</h3>
                        </div>
                        <div class="<?= e($grade) ?>">
                            <?= component('input', [
                                'name' => 'efi_client_id',
                                'label' => 'Client ID',
                                'type' => 'text',
                                'value' => $configuracoes['client_id'] ?? $configuracoes['efi_client_id'] ?? '',
                                'help' => 'Client ID obtido na aplicação cadastrada na Efí.',
                            ]) ?>

                            <?= component('input', [
                                'name' => 'efi_client_secret',
                                'label' => 'Client Secret',
                                'type' => 'password',
                                'value' => $configuracoes['client_secret'] ?? $configuracoes['efi_client_secret'] ?? '',
                                'help' => 'Client Secret da aplicação cadastrada na Efí.',
                                'attrs' => ['autocomplete' => 'new-password'],
                            ]) ?>
                        </div>
                        <div class="<?= e($grade) ?>">
                            <?= component('input', [
                                'name' => 'efi_chave_pix',
                                'label' => 'Chave PIX da Conta Efí',
                                'type' => 'text',
                                'value' => $configuracoes['chave_pix_efi'] ?? $configuracoes['efi_chave_pix'] ?? '',
                                'help' => 'A chave PIX vinculada à sua conta Efí onde o valor será recebido.',
                            ]) ?>
                        </div>
                    </div>
                </div>

                <!-- Bloco PIX Manual (Chave Fixa) -->
                <div id="bloco-pix-manual" class="flex flex-col gap-4 <?= $tipoPixAtual === 'dinamico' ? 'hidden' : '' ?>">
                    <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex items-start gap-3">
                        <?= icon('info', ['class' => 'size-5 text-primary shrink-0 mt-0.5']) ?>
                        <div class="text-caption text-muted">
                            <strong class="text-text font-semibold">Chave PIX Fixa:</strong> O sistema gera o QR Code estático padrão do Banco Central com os dados bancários informados abaixo. O assinante paga pelo app do banco dele e você confirma os 30 dias após verificar o comprovante.
                        </div>
                    </div>

                    <div class="<?= e($grade) ?>">
                        <?= component('input', [
                            'name' => 'chave_pix',
                            'label' => 'Chave PIX',
                            'type' => 'text',
                            'value' => $configuracoes['chave_pix'] ?? '',
                            'help' => 'Ex: seu CNPJ, CPF, E-mail, Celular ou Chave Aleatória.',
                        ]) ?>

                        <?= component('select', [
                            'name' => 'tipo_chave',
                            'label' => 'Tipo da Chave PIX',
                            'selected' => $configuracoes['tipo_chave'] ?? 'cnpj',
                            'options' => [
                                ['value' => 'cnpj', 'label' => 'CNPJ'],
                                ['value' => 'cpf', 'label' => 'CPF'],
                                ['value' => 'email', 'label' => 'E-mail'],
                                ['value' => 'telefone', 'label' => 'Telefone / Celular'],
                                ['value' => 'aleatoria', 'label' => 'Chave Aleatória (EVP)'],
                            ],
                        ]) ?>
                    </div>

                    <div class="<?= e($grade) ?>">
                        <?= component('input', [
                            'name' => 'titular',
                            'label' => 'Nome do Titular da Conta / Beneficiário',
                            'type' => 'text',
                            'value' => $configuracoes['titular'] ?? '',
                            'help' => 'Nome da empresa master exatamente como aparece na conta bancária.',
                        ]) ?>

                        <?= component('input', [
                            'name' => 'cidade',
                            'label' => 'Cidade do Titular da Conta',
                            'type' => 'text',
                            'value' => $configuracoes['cidade'] ?? 'Sao Paulo',
                            'help' => 'Cidade da agência bancária (exigida na norma EMV do Banco Central).',
                        ]) ?>
                    </div>
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
                    <p class="text-caption text-muted">Configurações de emissão registrada e tokens de API do gateway emissor.</p>
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
                        'selected' => $forma->provedor ?: 'asaas',
                        'options' => [
                            ['value' => 'asaas', 'label' => 'Asaas (Recomendado para Boletos)'],
                            ['value' => 'gerencianet', 'label' => 'Efí (antiga Gerencianet)'],
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago'],
                        ],
                    ]) ?>

                    <?php
                    $opcoesDiasVencimento = [];
                    for ($d = 1; $d <= 30; $d++) {
                        $rotulo = $d . ($d === 1 ? ' dia corrido' : ' dias corridos') . ($d === 3 ? ' (Padrão)' : '');
                        $opcoesDiasVencimento[] = [
                            'value' => (string) $d,
                            'label' => $rotulo,
                        ];
                    }
                    ?>
                    <?= component('select', [
                        'name' => 'dias_vencimento',
                        'label' => 'Prazo de Vencimento do Boleto',
                        'selected' => (string) ($configuracoes['dias_vencimento'] ?? 3),
                        'options' => $opcoesDiasVencimento,
                        'help' => 'Quantidade de dias corridos após a geração para vencimento do boleto.',
                    ]) ?>
                </div>

                <!-- Credenciais Asaas -->
                <div id="credenciais-asaas" class="flex flex-col gap-4 border-t border-border pt-4 <?= ($forma->provedor ?: 'asaas') !== 'asaas' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Asaas</h3>
                    </div>
                    <div class="w-full">
                        <?= component('input', [
                            'name' => 'asaas_api_key',
                            'label' => 'Chave de API Asaas (API Key)',
                            'type' => 'password',
                            'value' => $configuracoes['api_key'] ?? $configuracoes['asaas_api_key'] ?? '',
                            'help' => 'Chave de API gerada no painel Asaas em Minha Conta > Integrações.',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
                </div>

                <!-- Credenciais Efí (Gerencianet) -->
                <div id="credenciais-gerencianet" class="flex flex-col gap-4 border-t border-border pt-4 <?= $forma->provedor !== 'gerencianet' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Efí (Gerencianet)</h3>
                    </div>
                    <div class="<?= e($grade) ?>">
                        <?= component('input', [
                            'name' => 'efi_client_id',
                            'label' => 'Client ID',
                            'type' => 'text',
                            'value' => $configuracoes['client_id'] ?? $configuracoes['efi_client_id'] ?? '',
                        ]) ?>

                        <?= component('input', [
                            'name' => 'efi_client_secret',
                            'label' => 'Client Secret',
                            'type' => 'password',
                            'value' => $configuracoes['client_secret'] ?? $configuracoes['efi_client_secret'] ?? '',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
                </div>

                <!-- Credenciais Mercado Pago -->
                <div id="credenciais-mercadopago" class="flex flex-col gap-4 border-t border-border pt-4 <?= $forma->provedor !== 'mercadopago' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Mercado Pago</h3>
                    </div>
                    <div class="w-full">
                        <?= component('input', [
                            'name' => 'mp_access_token',
                            'label' => 'Access Token (Produção)',
                            'type' => 'password',
                            'value' => $configuracoes['access_token'] ?? $configuracoes['mp_access_token'] ?? '',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
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
                    <p class="text-caption text-muted">Pagamento com aprovação instantânea e renovação imediata de +30 dias.</p>
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
                        'selected' => $forma->provedor ?: 'mercadopago',
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
                        'help' => 'Normalmente 1x (à vista) para renovação mensal de 30 dias.',
                    ]) ?>
                </div>

                <!-- Credenciais Mercado Pago -->
                <div id="credenciais-mercadopago" class="flex flex-col gap-4 border-t border-border pt-4 <?= ($forma->provedor ?: 'mercadopago') !== 'mercadopago' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Mercado Pago</h3>
                    </div>
                    <div class="<?= e($grade) ?>">
                        <?= component('input', [
                            'name' => 'mp_access_token',
                            'label' => 'Access Token (Produção)',
                            'type' => 'password',
                            'value' => $configuracoes['access_token'] ?? $configuracoes['mp_access_token'] ?? '',
                            'help' => 'Utilizado no backend para efetivar a transação.',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>

                        <?= component('input', [
                            'name' => 'mp_public_key',
                            'label' => 'Public Key (Chave Pública)',
                            'type' => 'text',
                            'value' => $configuracoes['public_key'] ?? $configuracoes['mp_public_key'] ?? '',
                            'help' => 'Necessária para a tokenização segura do cartão no navegador do assinante.',
                        ]) ?>
                    </div>
                </div>

                <!-- Credenciais Asaas -->
                <div id="credenciais-asaas" class="flex flex-col gap-4 border-t border-border pt-4 <?= $forma->provedor !== 'asaas' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Asaas</h3>
                    </div>
                    <div class="w-full">
                        <?= component('input', [
                            'name' => 'asaas_api_key',
                            'label' => 'Chave de API Asaas (API Key)',
                            'type' => 'password',
                            'value' => $configuracoes['api_key'] ?? $configuracoes['asaas_api_key'] ?? '',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
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
                        'selected' => $forma->provedor ?: 'mercadopago',
                        'options' => [
                            ['value' => 'mercadopago', 'label' => 'Mercado Pago Checkout Pro (3DS)'],
                            ['value' => 'asaas', 'label' => 'Asaas Link Seguro'],
                        ],
                    ]) ?>
                </div>

                <!-- Credenciais Mercado Pago -->
                <div id="credenciais-mercadopago" class="flex flex-col gap-4 border-t border-border pt-4 <?= ($forma->provedor ?: 'mercadopago') !== 'mercadopago' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Mercado Pago</h3>
                    </div>
                    <div class="w-full">
                        <?= component('input', [
                            'name' => 'mp_access_token',
                            'label' => 'Access Token (Produção)',
                            'type' => 'password',
                            'value' => $configuracoes['access_token'] ?? $configuracoes['mp_access_token'] ?? '',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
                </div>

                <!-- Credenciais Asaas -->
                <div id="credenciais-asaas" class="flex flex-col gap-4 border-t border-border pt-4 <?= $forma->provedor !== 'asaas' ? 'hidden' : '' ?>">
                    <div class="flex items-center gap-2">
                        <?= icon('key-round', ['class' => 'size-4 text-primary']) ?>
                        <h3 class="font-display text-sm font-semibold text-text">Credenciais de API - Asaas</h3>
                    </div>
                    <div class="w-full">
                        <?= component('input', [
                            'name' => 'asaas_api_key',
                            'label' => 'Chave de API Asaas (API Key)',
                            'type' => 'password',
                            'value' => $configuracoes['api_key'] ?? $configuracoes['asaas_api_key'] ?? '',
                            'attrs' => ['autocomplete' => 'new-password'],
                        ]) ?>
                    </div>
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
