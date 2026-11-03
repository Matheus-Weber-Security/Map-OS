<?php
/**
 * Produtos da venda (#2843). Excluir abre o modal-confirm "excluir-produto" da
 * tela (vendas/visualizar), que leva o id do item por data-valor-id.
 *
 * @var list<object> $produtos
 * @var array        $totais
 * @var array<string, bool> $pode
 */
$colunas = [
    ['label' => 'Produto', 'class' => 'min-w-48 [overflow-wrap:anywhere]', 'render' => fn ($p) => (string) $p->descricao],
    ['label' => 'Qtd.', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => (string) (0 + $p->quantidade)],
    ['label' => 'Preço', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => dinheiro($p->preco ?: $p->precoVenda)],
];

// Desconto individual do item (#2880): a coluna só aparece quando algum item tem.
if (array_filter($produtos, static fn ($p) => (float) ($p->desconto ?? 0) > 0) !== []) {
    $colunas[] = osColunaDescontoDoItem();
}
$colunas[] = ['label' => 'Subtotal', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => dinheiro($p->subTotal)];

if ($pode['itens']) {
    $colunas[] = ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => fn ($p) => [
        osBotaoDescontoDoItem($p, 'produto', (int) $p->idItens, (string) $p->descricao, '!size-7'),
        component('button', [
            'label' => 'Excluir ' . $p->descricao,
            'icon' => 'trash-2',
            'icon_only' => true,
            'variant' => 'ghost',
            'size' => 'sm',
            'class' => '!size-7',
            'attrs' => ['data-modal-abrir' => 'excluir-produto', 'data-valor-id' => (string) $p->idItens, 'data-valor-nome' => (string) $p->descricao],
        ]),
    ]];
}
?>
<div class="flex flex-col gap-2">
    <?= component('data-table', [
        'caption' => 'Produtos da venda',
        'rows' => $produtos,
        'columns' => $colunas,
        'compact' => true,
        'empty' => component('empty-state', [
            'title' => 'Nenhum produto na venda',
            'message' => $pode['itens'] ? 'Adicione os produtos pelo formulário acima.' : 'Esta venda não tem produtos.',
            'icon' => 'package',
            'class' => 'border-0',
        ]),
    ]) ?>
</div>
