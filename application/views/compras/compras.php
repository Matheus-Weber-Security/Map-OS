<?php
/**
 * Listagem de compras (#Compras-01).
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var array                 $paginacao
 * @var array{adicionar: bool, editar: bool, excluir: bool} $pode
 */
$temFiltro = $filtros !== [];

$colunas = [
    ['key' => 'idCompras', 'label' => 'Nº', 'align' => 'right', 'nowrap' => true],
    ['label' => 'Fornecedor', 'class' => 'min-w-40', 'render' => fn ($c) => $c->nomeFornecedor !== null
        ? component('link', ['label' => $c->nomeFornecedor, 'href' => site_url('clientes/visualizar/' . (int) $c->fornecedores_id), 'class' => 'font-medium'])
        : 'Fornecedor avulso / removido'],
    ['label' => 'NF-e', 'nowrap' => true, 'hide_until' => 'md', 'render' => fn ($c) => $c->numero_nfe ? 'NF ' . e($c->numero_nfe) : '—'],
    ['label' => 'Data', 'align' => 'right', 'nowrap' => true, 'hide_until' => 'lg', 'render' => fn ($c) => dataBr($c->dataCompra)],
    ['label' => 'Comprador', 'nowrap' => true, 'hide_until' => '2xl', 'render' => fn ($c) => (string) ($c->comprador ?: '—')],
    ['label' => 'Status', 'nowrap' => true, 'render' => fn ($c) => component('pill-status', comprasStatusPill($c->status))],
    ['label' => 'Faturado', 'nowrap' => true, 'hide_until' => 'xl', 'render' => fn ($c) => (int) $c->faturado === 1
        ? component('pill-status', ['label' => 'Pago / Faturado', 'variant' => 'success'])
        : component('pill-status', ['label' => 'Não faturado', 'variant' => 'neutral'])],
    ['label' => 'Total', 'align' => 'right', 'nowrap' => true, 'render' => fn ($c) => dinheiro($c->total)],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($c) use ($pode) {
        $acoes = [
            component('button', ['label' => 'Ver compra ' . $c->idCompras, 'icon' => 'eye', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('compras/visualizar/' . $c->idCompras)]),
        ];
        if ($pode['excluir']) {
            $acoes[] = component('button', ['label' => 'Excluir compra ' . $c->idCompras, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => [
                'data-modal-abrir' => 'excluir-compra',
                'data-valor-id' => (string) $c->idCompras,
                'data-valor-numero' => (string) $c->idCompras,
                'data-valor-fornecedor' => (string) ($c->nomeFornecedor ?? 'Fornecedor avulso'),
            ]]);
        }

        return $acoes;
    }],
];

$vazio = $temFiltro
    ? component('empty-state', [
        'title' => 'Nenhuma compra encontrada',
        'message' => 'Nada corresponde aos filtros. Confira a busca ou limpe os filtros.',
        'icon' => 'search-x',
        'action' => component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => site_url('compras')]),
        'class' => 'border-0',
    ])
    : component('empty-state', [
        'title' => 'Nenhuma compra registrada',
        'message' => 'Você pode registrar uma compra manual ou importar o XML de uma NF-e para alimentar o estoque.',
        'icon' => 'package-plus',
        'action' => $pode['adicionar'] ? component('button', ['label' => 'Importar NF-e', 'icon' => 'upload', 'variant' => 'outline', 'href' => site_url('compras/importarNfe')]) : null,
        'class' => 'border-0',
    ]);
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-heading-xl text-text">Compras e Entrada de Estoque</h1>
            <p class="text-caption text-muted" aria-live="polite">
                <?= e(($total === 1 ? '1 compra' : number_format($total, 0, ',', '.') . ' compras') . ($temFiltro ? ($total === 1 ? ' encontrada' : ' encontradas') . ' com os filtros' : '')) ?>
            </p>
        </div>
        <?php if ($pode['adicionar']) { ?>
            <div class="flex items-center gap-2">
                <?= component('button', ['label' => 'Importar NF-e (XML)', 'icon' => 'upload', 'variant' => 'outline', 'href' => site_url('compras/importarNfe')]) ?>
            </div>
        <?php } ?>
    </header>

    <form method="get" action="<?= e(site_url('compras')) ?>" role="search" aria-label="Filtrar compras" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_12rem_10rem_10rem_auto]">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => 'Nº, fornecedor, CNPJ ou NF-e',
            'attrs' => ['maxlength' => 100],
        ]) ?>
        <?= component('select', [
            'name' => 'status',
            'label' => 'Situação',
            'placeholder' => 'Todas as situações',
            'options' => ['Concluída' => 'Concluída', 'Pendente' => 'Pendente', 'Cancelada' => 'Cancelada'],
            'selected' => $filtros['status'] ?? null,
        ]) ?>
        <?= component('input', [
            'name' => 'de',
            'label' => 'De',
            'type' => 'date',
            'value' => $filtros['de'] ?? null,
        ]) ?>
        <?= component('input', [
            'name' => 'ate',
            'label' => 'Até',
            'type' => 'date',
            'value' => $filtros['ate'] ?? null,
        ]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('compras')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'empty' => $vazio,
        'caption' => 'Compras e entradas de estoque',
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-compra" method="post" action="<?= e(site_url('compras/excluir') . listagemQuery($filtros)) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-compra" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-compra',
        'title' => 'Excluir compra?',
        'message' => [
            new HtmlSeguro('Deseja realmente excluir a compra <strong class="font-semibold text-text" data-modal-valor="numero"></strong> do fornecedor <strong class="font-semibold text-text" data-modal-valor="fornecedor"></strong>? Caso a compra tenha entrado no estoque, a quantidade será estornada automaticamente.'),
        ],
        'confirm_label' => 'Excluir compra',
        'confirm_attrs' => ['form' => 'form-excluir-compra'],
    ]) ?>
<?php } ?>
