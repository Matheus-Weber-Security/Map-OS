<?php
/**
 * Desconto da venda (#2843), em R$ ou %. O servidor calcula o total com
 * desconto (Vendas::adicionarDesconto(), vendaCalcularDesconto()); o módulo
 * vendas/tela envia sem recarregar e troca este trecho e os totais.
 *
 * Só aparece com a venda editável: depois de faturada, o desconto fica no
 * kpi-card dos totais.
 *
 * @var object $venda
 * @var array  $totais
 * @var array<string, bool> $pode
 */
if (! $pode['editar']) {
    return;
}

$valorAtual = $totais['desconto'] > 0 ? rtrim(rtrim(number_format($totais['informado'], 2, ',', ''), '0'), ',') : null;
?>
<section class="flex h-full flex-col justify-between rounded-xl border border-border bg-surface p-4 sm:p-6 shadow-xs" aria-labelledby="secao-desconto">
    <div>
        <h2 id="secao-desconto" class="text-heading-sm text-text">Desconto</h2>
        <p class="mt-1 text-caption text-muted">
            <?= e($totais['desconto'] > 0
                ? 'Desconto de ' . dinheiro($totais['desconto']) . ' aplicado. Informe 0 para remover.'
                : 'Informe o desconto por valor (R$) ou porcentagem (%) sobre o total.') ?>
        </p>
        <form method="post" action="<?= e(site_url('vendas/adicionarDesconto')) ?>" novalidate class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-[9.5rem_minmax(0,1fr)_auto] items-start" data-os-acao="desconto">
            <input type="hidden" name="idVendas" value="<?= e((int) $venda->idVendas) ?>">
            <?= component('select', [
                'name' => 'tipoDesconto',
                'id' => 'desconto-tipo',
                'label' => 'Tipo',
                'options' => ['real' => 'Em reais (R$)', 'porcento' => 'Em porcentagem (%)'],
                'selected' => $totais['tipo'] ?? 'real',
                'required' => true,
            ]) ?>
            <?= component('input', [
                'name' => 'desconto',
                'id' => 'desconto-valor',
                'label' => 'Desconto',
                'value' => $valorAtual,
                'placeholder' => '0,00',
                'required' => true,
                'help' => 'Informe 0 para tirar o desconto.',
                'attrs' => [
                    'inputmode' => 'decimal',
                    'pattern' => '^\d+([.,]\d{1,2})?$',
                    'data-msg-vazio' => 'Informe o desconto, ou 0 para tirar.',
                    'data-msg-invalido' => 'Use só números, como 10 ou 10,50.',
                ],
            ]) ?>
            <div class="sm:col-span-2 xl:col-span-1 xl:pt-7">
                <?= component('button', ['label' => 'Aplicar desconto', 'icon' => 'percent', 'variant' => 'outline', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Aplicando…']]) ?>
            </div>
        </form>
    </div>
</section>
