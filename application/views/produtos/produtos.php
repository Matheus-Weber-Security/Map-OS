<?php
/**
 * Listagem de produtos (#2841), no padrão das listagens (#2852).
 *
 * - Busca (descrição ou código de barras) e filtro "estoque baixo" na URL.
 * - Estoque com a segunda pista quando está no mínimo: pill "Baixo".
 * - Ações por linha (a descrição leva à ficha): entrada de estoque (modal com formulário),
 *   editar e excluir (modal-confirm). Os dois modais são preenchidos pelo
 *   modal.js a partir dos data-valor-* do botão da linha.
 * - "Etiquetas" abre um modal com o formulário do relatório de etiquetas.
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var array                 $paginacao
 * @var array{adicionar: bool, editar: bool, excluir: bool, etiquetas: bool} $pode
 */
$temFiltro = $filtros !== [];
$csrf = [$this->security->get_csrf_token_name(), $this->security->get_csrf_hash()];

$colunas = [
    ['label' => 'Cód.', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => (string) ($p->codigo ?? $p->idProdutos)],
    ['key' => 'codDeBarra', 'label' => 'Cód. de barras', 'nowrap' => true, 'truncate' => true, 'hide_until' => 'xl'],
    ['label' => 'Descrição', 'class' => 'min-w-32 [overflow-wrap:anywhere]', 'render' => fn ($p) => component('link', ['label' => $p->descricao, 'href' => site_url('produtos/visualizar/' . $p->idProdutos), 'class' => 'font-medium'])],
    ['label' => 'Estoque', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => produtoEstoqueBaixo($p)
        ? [component('pill-status', ['label' => 'Baixo', 'variant' => 'warning']), (string) (int) $p->estoque]
        : (string) (int) $p->estoque],
    ['label' => 'Preço', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => dinheiro($p->precoVenda)],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($p) use ($pode) {
        // A descrição já leva à ficha: mantendo no menu só as ações existentes.
        $itens = [];
        if ($pode['editar']) {
            $itens[] = ['label' => 'Entrada de estoque', 'icon' => 'package-plus', 'attrs' => [
                'data-modal-abrir' => 'estoque-produto',
                'data-valor-id' => (string) $p->idProdutos,
                'data-valor-nome' => $p->descricao,
                'data-valor-atual' => (int) $p->estoque . ' ' . $p->unidade,
            ]];
            $itens[] = ['label' => 'Editar produto', 'icon' => 'pencil', 'href' => site_url('produtos/editar/' . $p->idProdutos)];
        }
        if ($pode['excluir']) {
            $itens[] = ['separador' => true];
            $itens[] = ['label' => 'Excluir produto', 'icon' => 'trash-2', 'danger' => true, 'attrs' => [
                'data-modal-abrir' => 'excluir-produto',
                'data-valor-id' => (string) $p->idProdutos,
                'data-valor-nome' => $p->descricao,
            ]];
        }

        if ($itens === []) {
            return '';
        }

        return component('menu-acoes', ['label' => 'Ações de ' . $p->descricao, 'items' => $itens]);
    }],
];

$vazio = $temFiltro
    ? component('empty-state', [
        'title' => 'Nenhum produto encontrado',
        'message' => 'Nada corresponde aos filtros. Confira a busca ou limpe os filtros.',
        'icon' => 'search-x',
        'action' => component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => site_url('produtos')]),
        'class' => 'border-0',
    ])
    : component('empty-state', [
        'title' => 'Nenhum produto cadastrado',
        'message' => 'Os produtos cadastrados aparecem aqui e podem ser usados nas ordens de serviço e nas vendas.',
        'icon' => 'shopping-basket',
        'action' => $pode['adicionar'] ? component('button', ['label' => 'Cadastrar produto', 'icon' => 'plus', 'variant' => 'outline', 'href' => site_url('produtos/adicionar')]) : null,
        'class' => 'border-0',
    ]);
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Produtos</h1>
            <p class="text-caption text-muted" aria-live="polite">
                <?= e(($total === 1 ? '1 produto' : number_format($total, 0, ',', '.') . ' produtos') . ($temFiltro ? ($total === 1 ? ' encontrado' : ' encontrados') . ' com os filtros' : '')) ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($pode['adicionar']) { ?>
                <?= component('button', ['label' => 'Importar produtos', 'icon' => 'upload', 'variant' => 'outline', 'href' => site_url('produtos/importar')]) ?>
            <?php } ?>
            <?php if ($pode['etiquetas']) { ?>
                <?= component('button', ['label' => 'Etiquetas', 'icon' => 'barcode', 'variant' => 'outline', 'attrs' => ['data-modal-abrir' => 'etiquetas-produtos']]) ?>
            <?php } ?>
        </div>
    </header>

    <form method="get" action="<?= e(site_url('produtos')) ?>" role="search" aria-label="Filtrar produtos" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => 'Descrição ou código de barras',
            'attrs' => ['maxlength' => 100],
        ]) ?>
        <?= component('select', [
            'name' => 'estoque',
            'label' => 'Estoque',
            'placeholder' => 'Todos',
            'options' => ['baixo' => 'No mínimo ou abaixo'],
            'selected' => $filtros['estoque'] ?? null,
        ]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('produtos')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'empty' => $vazio,
        'caption' => 'Produtos',
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?php if ($pode['editar']) { ?>
    <?= component('modal', [
        'id' => 'estoque-produto',
        'title' => 'Entrada de estoque',
        'body' => [
            new HtmlSeguro('<p class="mb-4 text-caption text-muted"><strong class="font-semibold text-text" data-modal-valor="nome"></strong> · em estoque: <span data-modal-valor="atual"></span></p>'),
            new HtmlSeguro('<form id="form-estoque-produto" method="post" action="' . e(site_url('produtos/atualizar_estoque') . listagemQuery($filtros)) . '" novalidate ' . js_module('formulario/padrao') . '>'),
            new HtmlSeguro('<input type="hidden" name="' . e($csrf[0]) . '" value="' . e($csrf[1]) . '"><input type="hidden" name="voltar" value="lista">'),
            new HtmlSeguro('<input type="hidden" name="id" value="" data-modal-valor="id">'),
            component('input', [
                'name' => 'quantidade',
                'label' => 'Quantidade',
                'type' => 'number',
                'required' => true,
                'help' => 'Somada ao estoque. Use um número negativo para retirar.',
                'attrs' => ['step' => 1, 'min' => -1000000, 'max' => 1000000, 'inputmode' => 'numeric', 'data-msg-vazio' => 'Informe a quantidade.'],
            ]),
            new HtmlSeguro('</form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Atualizar estoque', 'icon' => 'package-plus', 'type' => 'submit', 'attrs' => ['form' => 'form-estoque-produto', 'data-rotulo-carregando' => 'Salvando…']]),
        ],
    ]) ?>
<?php } ?>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-produto" method="post" action="<?= e(site_url('produtos/excluir') . listagemQuery($filtros)) ?>" hidden>
        <input type="hidden" name="<?= e($csrf[0]) ?>" value="<?= e($csrf[1]) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-produto" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-produto',
        'title' => 'Excluir produto?',
        'message' => [
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="nome"></strong>'),
            ' será removido do catálogo e também das ordens de serviço e das vendas em que foi usado. Essa ação não pode ser desfeita.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-produto'],
    ]) ?>
<?php } ?>

<?php if ($pode['etiquetas']) { ?>
    <?= component('modal', [
        'id' => 'etiquetas-produtos',
        'title' => 'Etiquetas com código de barras',
        'body' => [
            new HtmlSeguro('<form id="form-etiquetas" method="get" action="' . e(site_url('relatorios/produtosEtiquetas')) . '" target="_blank" class="grid gap-4 sm:grid-cols-2">'),
            new HtmlSeguro('<p class="text-caption text-muted sm:col-span-2">Escolha o intervalo de códigos dos produtos. As etiquetas abrem em outra aba, prontas para imprimir.</p>'),
            component('input', ['name' => 'de_id', 'label' => 'Do código', 'type' => 'number', 'attrs' => ['min' => 1, 'step' => 1, 'inputmode' => 'numeric']]),
            component('input', ['name' => 'ate_id', 'label' => 'Até o código', 'type' => 'number', 'attrs' => ['min' => 1, 'step' => 1, 'inputmode' => 'numeric']]),
            component('select', ['name' => 'etiquetaCode', 'label' => 'Formato', 'options' => ['EAN13' => 'EAN-13', 'UPCA' => 'UPC-A', 'C93' => 'Code 93', 'C128A' => 'Code 128', 'CODABAR' => 'Codabar', 'QR' => 'QR Code'], 'selected' => 'EAN13']),
            new HtmlSeguro('<div class="self-end">'),
            component('checkbox', ['name' => 'qtdEtiqueta', 'value' => 'true', 'label' => 'Uma etiqueta por unidade em estoque']),
            new HtmlSeguro('</div></form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Gerar etiquetas', 'icon' => 'barcode', 'type' => 'submit', 'attrs' => ['form' => 'form-etiquetas']]),
        ],
    ]) ?>
<?php } ?>
