<?php
/**
 * Listagem de Assinantes SaaS no padrão oficial Map-OS v5 (DESIGN.md).
 *
 * Utiliza o componente data-table, filtros padronizados e modais nativos,
 * com o mesmo design elegante da tela de Produtos.
 *
 * @var list<object>          $empresas
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var list<object>          $planos
 * @var bool                  $podeDias
 * @var bool                  $podeDesconto
 * @var bool                  $podeAcessar
 * @var bool                  $podeStatus
 * @var bool                  $podeCadastrar
 */
$podeDias = ! empty($podeDias);
$podeDesconto = ! empty($podeDesconto);
$podeAcessar = ! empty($podeAcessar);
$podeStatus = ! empty($podeStatus);
$podeCadastrar = ! empty($podeCadastrar);
$temAcoes = $podeDias || $podeDesconto || $podeStatus || $podeAcessar;

$temFiltro = ($filtros['busca'] ?? '') !== '' || (($filtros['status'] ?? '') !== '' && ($filtros['status'] ?? '') !== 'todos');
$csrf = [$this->security->get_csrf_token_name(), $this->security->get_csrf_hash()];

$colunas = [
    ['key' => 'id', 'label' => 'Cód.', 'align' => 'right', 'nowrap' => true],
    [
        'label' => 'Nome da Empresa',
        'class' => 'min-w-44 [overflow-wrap:anywhere]',
        'render' => function ($e) use ($podeAcessar) {
            $nome = (string) ($e->nome_fantasia ?: $e->razao_social);
            if ($podeAcessar) {
                return component('link', [
                    'label' => $nome,
                    'href' => site_url('saas/trocarEmpresa/' . $e->id),
                    'class' => 'font-semibold text-text hover:text-primary',
                    'attrs' => ['title' => 'Acessar painel desta empresa'],
                ]);
            }

            return html_purificado('<span class="font-semibold text-text">' . e($nome) . '</span>');
        },
    ],
    [
        'key' => 'email',
        'label' => 'E-mail',
        'nowrap' => true,
        'truncate' => true,
        'hide_until' => 'lg',
        'render' => fn ($e) => e($e->email ?: 'Sem e-mail'),
    ],
    [
        'label' => 'Status',
        'align' => 'center',
        'nowrap' => true,
        'render' => function ($e) {
            if ($e->status === 'suspensa') {
                return component('pill-status', ['label' => 'Suspensa', 'variant' => 'danger']);
            }
            if ((int) $e->dias_restantes <= 0) {
                return component('pill-status', ['label' => 'Vencida', 'variant' => 'warning']);
            }

            return component('pill-status', ['label' => 'Ativa', 'variant' => 'success']);
        },
    ],
    [
        'label' => 'Pagamento',
        'align' => 'center',
        'nowrap' => true,
        'render' => fn ($e) => component('pill-status', [
            'label' => $e->forma_pagamento_preferida,
            'variant' => $e->forma_pagamento_preferida === 'PIX' ? 'success' : 'neutral',
        ]),
    ],
    [
        'label' => 'Vencimento',
        'align' => 'right',
        'nowrap' => true,
        'render' => fn ($e) => ! empty($e->data_vencimento) ? date('d/m/Y', strtotime($e->data_vencimento)) : '-',
    ],
    [
        'label' => 'Dias',
        'align' => 'right',
        'nowrap' => true,
        'render' => fn ($e) => (int) $e->dias_restantes > 0
            ? component('pill-status', ['label' => (int) $e->dias_restantes . ' dias', 'variant' => 'success'])
            : component('pill-status', ['label' => '0 dias', 'variant' => 'danger']),
    ],
    [
        'label' => 'Valor Mensal',
        'align' => 'right',
        'nowrap' => true,
        'render' => function ($e) {
            $valor = (float) ($e->plano_valor ?? 37.90);
            $desc = (int) ($e->desconto_percentual ?? 0);
            $final = $valor * (1 - ($desc / 100));

            return html_purificado('<span class="font-semibold text-text">R$ ' . number_format($final, 2, ',', '.') . '</span>' .
                ($desc > 0 ? ' <span class="text-caption text-muted line-through">R$ ' . number_format($valor, 2, ',', '.') . '</span>' : ''));
        },
    ],
    [
        'label' => 'Desconto',
        'align' => 'center',
        'nowrap' => true,
        'render' => function ($e) {
            $desc = (int) ($e->desconto_percentual ?? 0);

            return $desc > 0
                ? component('pill-status', ['label' => $desc . '% OFF', 'variant' => 'warning'])
                : component('pill-status', ['label' => 'Sem desconto', 'variant' => 'neutral']);
        },
    ],
];

if ($temAcoes) {
    $colunas[] = [
        'label' => 'Ações',
        'align' => 'right',
        'nowrap' => true,
        'render' => function ($e) use ($podeDias, $podeDesconto, $podeStatus, $podeAcessar) {
            $nome = (string) ($e->nome_fantasia ?: $e->razao_social);
            $acoes = [];

            if ($podeDias) {
                $acoes[] = component('button', [
                    'label' => 'Adicionar dias para ' . $nome,
                    'icon' => 'plus',
                    'icon_only' => true,
                    'variant' => 'outline',
                    'size' => 'sm',
                    'class' => 'border-border text-muted hover:text-success-ink hover:border-success-soft hover:bg-success-soft/20 shadow-2xs',
                    'attrs' => [
                        'data-modal-abrir' => 'modal-dias',
                        'data-valor-id' => (string) $e->id,
                        'data-valor-nome' => $nome,
                        'title' => 'Adicionar dias de acesso',
                    ],
                ]);
            }

            if ($podeDesconto) {
                $acoes[] = component('button', [
                    'label' => 'Ajustar desconto de ' . $nome,
                    'icon' => 'percent',
                    'icon_only' => true,
                    'variant' => 'outline',
                    'size' => 'sm',
                    'class' => 'border-border text-muted hover:text-warning-ink hover:border-warning-soft hover:bg-warning-soft/20 shadow-2xs',
                    'attrs' => [
                        'data-modal-abrir' => 'modal-desconto',
                        'data-valor-id' => (string) $e->id,
                        'data-valor-nome' => $nome,
                        'data-valor-desconto' => (string) ($e->desconto_percentual ?? 0),
                        'title' => 'Ajustar desconto comercial (%)',
                    ],
                ]);
            }

            if ($podeStatus && (int) $e->id > 1) {
                $isSuspensa = ($e->status === 'suspensa');
                $acoes[] = component('button', [
                    'label' => ($isSuspensa ? 'Ativar empresa ' : 'Suspender empresa ') . $nome,
                    'icon' => $isSuspensa ? 'shield-check' : 'shield-off',
                    'icon_only' => true,
                    'variant' => 'outline',
                    'size' => 'sm',
                    'class' => 'border-border text-muted ' . ($isSuspensa ? 'hover:text-success-ink hover:border-success-soft hover:bg-success-soft/20' : 'hover:text-danger-ink hover:border-danger-soft hover:bg-danger-soft/20') . ' shadow-2xs',
                    'attrs' => [
                        'data-modal-abrir' => 'modal-status',
                        'data-valor-id' => (string) $e->id,
                        'data-valor-nome' => $nome,
                        'data-valor-acao' => $isSuspensa ? 'ativar' : 'suspender',
                        'data-valor-status' => $isSuspensa ? 'ativa' : 'suspensa',
                        'title' => $isSuspensa ? 'Ativar empresa' : 'Suspender empresa',
                    ],
                ]);
            }

            if ($podeAcessar) {
                $acoes[] = component('button', [
                    'label' => 'Acessar painel de ' . $nome,
                    'icon' => 'log-in',
                    'icon_only' => true,
                    'variant' => 'outline',
                    'size' => 'sm',
                    'class' => 'border-border text-muted hover:text-primary hover:border-primary/40 hover:bg-primary-tint/20 shadow-2xs',
                    'href' => site_url('saas/trocarEmpresa/' . $e->id),
                    'attrs' => [
                        'title' => 'Acessar painel desta empresa',
                    ],
                ]);
            }

            return $acoes;
        },
    ];
}

$vazio = component('empty-state', [
    'title' => $temFiltro ? 'Nenhum assinante encontrado' : 'Nenhuma empresa cadastrada',
    'message' => $temFiltro ? 'Nada corresponde aos filtros aplicados. Tente buscar por outro termo.' : 'As empresas cadastradas no SaaS aparecerão listadas aqui.',
    'icon' => 'users',
    'action' => $temFiltro ? component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => site_url('saas/assinantes')]) : null,
    'class' => 'border-0',
]);
?>

<div class="flex flex-col gap-4 pt-2 pb-8">
    <!-- Cabeçalho (Idêntico ao padrão de Produtos) -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="mb-1">
                <?= component('button', [
                    'label' => 'Voltar para Gerenciar',
                    'icon' => 'chevron-left',
                    'variant' => 'link',
                    'href' => site_url('saas/gerenciar'),
                ]) ?>
            </div>
            <h1 class="font-display text-heading-xl text-text">Assinantes</h1>
            <p class="text-caption text-muted" aria-live="polite">
                <?= e(($total === 1 ? '1 assinante' : number_format($total, 0, ',', '.') . ' assinantes') . ($temFiltro ? ' encontrados com os filtros' : '')) ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-caption font-bold text-text shadow-2xs">
                TOTAL EXIBIDO: <?= (int) $total ?>
            </span>
            <?php if ($podeCadastrar) { ?>
                <?= component('button', [
                    'label' => 'Nova Empresa',
                    'icon' => 'plus',
                    'attrs' => [
                        'data-modal-abrir' => 'modal-nova-empresa',
                    ],
                ]) ?>
            <?php } ?>
        </div>
    </header>

    <!-- Formulário de Filtro (Idêntico ao padrão de Produtos) -->
    <form method="get" action="<?= e(site_url('saas/assinantes')) ?>" role="search" aria-label="Filtrar assinantes" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
        <?= component('input', [
            'name' => 'busca',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['busca'] ?? null,
            'placeholder' => 'Buscar por nome, e-mail ou documento',
            'attrs' => ['maxlength' => 100],
        ]) ?>

        <?= component('select', [
            'name' => 'status',
            'label' => 'Status',
            'placeholder' => 'Todos',
            'options' => [
                'ativos' => 'Ativos (com dias)',
                'inativos' => 'Inativos (vencidos)',
                'suspensas' => 'Suspensas',
            ],
            'selected' => $filtros['status'] ?? null,
        ]) ?>

        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('saas/assinantes')]) ?>
            <?php } ?>
        </div>
    </form>

    <!-- Tabela Oficial Map-OS v5 (data-table) -->
    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $empresas,
        'empty' => $vazio,
        'caption' => 'Lista de Assinantes SaaS',
    ]) ?>
</div>

<?php if ($podeDias) { ?>
<!-- Modal Oficial: Adicionar Dias -->
<?= component('modal', [
    'id' => 'modal-dias',
    'title' => 'Adicionar dias de acesso',
    'body' => [
        new HtmlSeguro('<p class="mb-4 text-caption text-muted">Empresa: <strong class="font-semibold text-text" data-modal-valor="nome"></strong></p>'),
        new HtmlSeguro('<form id="form-adicionar-dias" method="post" action="' . e(site_url('saas/adicionarDias')) . '" novalidate ' . js_module('formulario/padrao') . '>'),
        new HtmlSeguro('<input type="hidden" name="' . e($csrf[0]) . '" value="' . e($csrf[1]) . '">'),
        new HtmlSeguro('<input type="hidden" name="empresa_id" value="" data-modal-valor="id">'),
        component('input', [
            'name' => 'dias',
            'label' => 'Quantidade de Dias',
            'type' => 'number',
            'value' => '30',
            'required' => true,
            'help' => 'Quantidade de dias que será adicionada ao vencimento atual.',
            'attrs' => ['step' => 1, 'min' => 1, 'max' => 1000, 'inputmode' => 'numeric', 'data-msg-vazio' => 'Informe a quantidade de dias.'],
        ]),
        new HtmlSeguro('</form>'),
    ],
    'footer' => [
        component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
        component('button', ['label' => 'Salvar dias', 'icon' => 'plus', 'type' => 'submit', 'attrs' => ['form' => 'form-adicionar-dias', 'data-rotulo-carregando' => 'Salvando…']]),
    ],
]) ?>
<?php } ?>

<?php if ($podeDesconto) { ?>
<!-- Modal Oficial: Ajustar Desconto -->
<?= component('modal', [
    'id' => 'modal-desconto',
    'title' => 'Ajustar desconto do assinante',
    'body' => [
        new HtmlSeguro('<p class="mb-4 text-caption text-muted">Empresa: <strong class="font-semibold text-text" data-modal-valor="nome"></strong></p>'),
        new HtmlSeguro('<form id="form-ajustar-desconto" method="post" action="' . e(site_url('saas/ajustarDesconto')) . '" novalidate ' . js_module('formulario/padrao') . '>'),
        new HtmlSeguro('<input type="hidden" name="' . e($csrf[0]) . '" value="' . e($csrf[1]) . '">'),
        new HtmlSeguro('<input type="hidden" name="empresa_id" value="" data-modal-valor="id">'),
        component('input', [
            'name' => 'desconto_percentual',
            'label' => 'Desconto Percentual (%)',
            'type' => 'number',
            'value' => '0',
            'required' => true,
            'help' => 'Porcentagem de desconto aplicada nas faturas mensais (de 0 a 100%).',
            'attrs' => ['step' => 1, 'min' => 0, 'max' => 100, 'inputmode' => 'numeric', 'data-modal-valor' => 'desconto', 'data-msg-vazio' => 'Informe o desconto.'],
        ]),
        new HtmlSeguro('</form>'),
    ],
    'footer' => [
        component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
        component('button', ['label' => 'Salvar desconto', 'icon' => 'percent', 'type' => 'submit', 'attrs' => ['form' => 'form-ajustar-desconto', 'data-rotulo-carregando' => 'Salvando…']]),
    ],
]) ?>
<?php } ?>

<?php if ($podeStatus) { ?>
<!-- Modal Oficial: Alterar Status da Empresa -->
<?= component('modal', [
    'id' => 'modal-status',
    'title' => 'Alterar status da empresa',
    'body' => [
        new HtmlSeguro('<p class="mb-4 text-body text-text">Deseja realmente <strong class="font-semibold text-text" data-modal-valor="acao"></strong> o acesso da empresa <strong class="font-semibold text-text" data-modal-valor="nome"></strong>?</p>'),
        new HtmlSeguro('<form id="form-alterar-status" method="post" action="' . e(site_url('saas/alterarStatus')) . '" novalidate ' . js_module('formulario/padrao') . '>'),
        new HtmlSeguro('<input type="hidden" name="' . e($csrf[0]) . '" value="' . e($csrf[1]) . '">'),
        new HtmlSeguro('<input type="hidden" name="empresa_id" value="" data-modal-valor="id">'),
        new HtmlSeguro('<input type="hidden" name="status" value="" data-modal-valor="status">'),
        new HtmlSeguro('</form>'),
    ],
    'footer' => [
        component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
        component('button', ['label' => 'Confirmar', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['form' => 'form-alterar-status', 'data-rotulo-carregando' => 'Salvando…']]),
    ],
]) ?>
<?php } ?>

<?php if ($podeCadastrar) { ?>
<!-- Modal Oficial: Cadastrar Nova Empresa -->
<?= component('modal', [
    'id' => 'modal-nova-empresa',
    'title' => 'Cadastrar Nova Empresa',
    'size' => 'lg',
    'body' => [
        new HtmlSeguro('<form id="form-cadastrar-empresa" method="post" action="' . e(site_url('saas/cadastrarEmpresa')) . '" novalidate ' . js_module('formulario/padrao') . ' class="space-y-4">'),
        new HtmlSeguro('<input type="hidden" name="' . e($csrf[0]) . '" value="' . e($csrf[1]) . '">'),
        new HtmlSeguro('<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">'),
        component('input', [
            'name' => 'razao_social',
            'label' => 'Razão Social',
            'type' => 'text',
            'required' => true,
            'placeholder' => 'Ex: Minha Empresa LTDA',
            'attrs' => ['maxlength' => 150, 'data-msg-vazio' => 'Informe a Razão Social.'],
        ]),
        component('input', [
            'name' => 'nome_fantasia',
            'label' => 'Nome Fantasia',
            'type' => 'text',
            'placeholder' => 'Ex: Oficina do João',
            'attrs' => ['maxlength' => 150],
        ]),
        component('input', [
            'name' => 'documento',
            'label' => 'CNPJ ou CPF',
            'type' => 'text',
            'attrs' => ['data-mascara' => 'documento', 'maxlength' => 20],
        ]),
        component('input', [
            'name' => 'telefone',
            'label' => 'Telefone / WhatsApp',
            'type' => 'tel',
            'attrs' => ['data-mascara' => 'telefone', 'maxlength' => 20],
        ]),
        component('input', [
            'name' => 'email',
            'label' => 'E-mail do Administrador (Login)',
            'type' => 'email',
            'required' => true,
            'placeholder' => 'admin@empresa.com',
            'attrs' => ['maxlength' => 100, 'data-msg-vazio' => 'Informe o e-mail de acesso.'],
        ]),
        component('input', [
            'name' => 'nome_dono',
            'label' => 'Nome do Administrador',
            'type' => 'text',
            'placeholder' => 'Ex: João da Silva',
            'attrs' => ['maxlength' => 100],
        ]),
        component('input', [
            'name' => 'senha',
            'label' => 'Senha Inicial de Acesso',
            'type' => 'password',
            'required' => true,
            'attrs' => ['minlength' => 6, 'maxlength' => 50, 'data-msg-vazio' => 'Informe uma senha provisória.'],
        ]),
        component('input', [
            'name' => 'dias_trial',
            'label' => 'Dias de Degustação / Validade',
            'type' => 'number',
            'value' => '10',
            'required' => true,
            'attrs' => ['step' => 1, 'min' => 1, 'max' => 365, 'inputmode' => 'numeric', 'data-msg-vazio' => 'Informe a quantidade de dias.'],
        ]),
        new HtmlSeguro('</div>'),
        new HtmlSeguro('</form>'),
    ],
    'footer' => [
        component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
        component('button', ['label' => 'Cadastrar Empresa', 'icon' => 'plus', 'type' => 'submit', 'attrs' => ['form' => 'form-cadastrar-empresa', 'data-rotulo-carregando' => 'Cadastrando…']]),
    ],
]) ?>
<?php } ?>
