<?php
/**
 * Visualização da Compra (#Compras-01).
 *
 * @var object       $compra
 * @var list<object> $itens
 * @var array{editar: bool, excluir: bool} $pode
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$faturada = (int) $compra->faturado === 1;
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= e(site_url('compras')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                    <?= icon('chevron-left', ['class' => 'size-4']) ?> Voltar para Compras
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="font-display text-heading-xl text-text">Compra #<?= e($compra->codigo ?? $compra->idCompras) ?></h1>
                <?= component('pill-status', comprasStatusPill($compra->status)) ?>
                <?php if ($faturada) { ?>
                    <?= component('pill-status', ['label' => 'Faturado no Financeiro', 'variant' => 'success']) ?>
                <?php } ?>
            </div>
            <p class="text-caption text-muted mt-1">
                Registrada em <?= e(dataBr($compra->dataCompra)) ?> por <?= e($compra->comprador ?: 'Sistema') ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (! $faturada && strtolower((string) $compra->status) !== 'cancelada') { ?>
                <?= component('button', ['label' => 'Lançar no Financeiro', 'icon' => 'circle-dollar-sign', 'variant' => 'outline', 'attrs' => ['data-modal-abrir' => 'modal-faturar']]) ?>
            <?php } ?>
            <?php if ($pode['excluir']) { ?>
                <?= component('button', ['label' => 'Excluir', 'icon' => 'trash-2', 'variant' => 'ghost', 'class' => 'hover:text-danger-ink', 'attrs' => [
                    'data-modal-abrir' => 'excluir-compra',
                    'data-valor-id' => (string) $compra->idCompras,
                    'data-valor-numero' => (string) $compra->idCompras,
                    'data-valor-fornecedor' => (string) ($compra->nomeFornecedor ?? 'Fornecedor avulso'),
                ]]) ?>
            <?php } ?>
        </div>
    </header>

    <div class="grid gap-6 sm:grid-cols-3">
        <!-- Fornecedor -->
        <section class="<?= e($caixa) ?> sm:col-span-2">
            <h2 class="text-heading-sm text-text mb-3">Dados do Fornecedor</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <span class="text-label-sm text-muted block">Nome / Razão Social</span>
                    <?php if ($compra->fornecedores_id) { ?>
                        <a href="<?= e(site_url('clientes/visualizar/' . (int) $compra->fornecedores_id)) ?>" class="text-body-sm font-medium text-primary hover:underline">
                            <?= e($compra->nomeFornecedor) ?>
                        </a>
                    <?php } else { ?>
                        <strong class="text-body-sm text-text"><?= e($compra->nomeFornecedor ?: 'Não informado') ?></strong>
                    <?php } ?>
                </div>
                <div>
                    <span class="text-label-sm text-muted block">CNPJ / CPF</span>
                    <span class="text-body-sm text-text"><?= e($compra->docFornecedor ?: '—') ?></span>
                </div>
                <div>
                    <span class="text-label-sm text-muted block">Contato / Telefone</span>
                    <span class="text-body-sm text-text"><?= e($compra->celular ?: ($compra->telefone ?: '—')) ?></span>
                </div>
                <div>
                    <span class="text-label-sm text-muted block">E-mail</span>
                    <span class="text-body-sm text-text"><?= e($compra->email ?: '—') ?></span>
                </div>
            </div>
        </section>

        <!-- Dados Fiscais / Totais -->
        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-3">Totais e Fiscal</h2>
            <div class="flex flex-col gap-3">
                <div>
                    <span class="text-label-sm text-muted block">Total da Compra</span>
                    <strong class="text-heading-md text-primary font-display"><?= e(dinheiro($compra->valorTotal)) ?></strong>
                </div>
                <?php if ($compra->numero_nfe) { ?>
                    <div class="pt-2 border-t border-border">
                        <span class="text-label-sm text-muted block">NF-e Número / Série</span>
                        <span class="text-body-sm font-medium text-text"><?= e($compra->numero_nfe) ?> / Série <?= e($compra->serie_nfe ?: '1') ?></span>
                    </div>
                <?php } ?>
                <?php if ($compra->chave_nfe) { ?>
                    <div class="pt-2 border-t border-border">
                        <span class="text-label-sm text-muted block">Chave de Acesso</span>
                        <span class="text-caption font-mono break-all text-text"><?= e($compra->chave_nfe) ?></span>
                    </div>
                <?php } ?>
            </div>
        </section>
    </div>

    <!-- Tabela de Itens Comprados -->
    <section class="<?= e($caixa) ?>">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-heading-sm text-text">Produtos Comprados</h2>
                <p class="text-caption text-muted">Itens que deram entrada no estoque a partir desta compra.</p>
            </div>
            <span class="text-caption font-medium text-muted"><?= count($itens) ?> item(ns)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-body-sm">
                <thead>
                    <tr class="border-b border-border text-label-sm text-muted">
                        <th class="py-2.5 px-3">Produto</th>
                        <th class="py-2.5 px-3 text-right">Qtd. Entrada</th>
                        <th class="py-2.5 px-3 text-right">Preço Unit.</th>
                        <th class="py-2.5 px-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <?php if (empty($itens)) { ?>
                        <tr>
                            <td colspan="4" class="py-6 text-center text-muted text-caption">Nenhum item registrado nesta compra.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($itens as $item) { ?>
                            <tr>
                                <td class="py-3 px-3">
                                    <div class="font-medium text-text"><?= e($item->nomeProduto ?: 'Produto #' . $item->produtos_id) ?></div>
                                    <?php if ($item->codigo_fornecedor) { ?>
                                        <span class="text-caption text-muted block">Cód. Fornecedor: <?= e($item->codigo_fornecedor) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-text"><?= e($item->quantidade) ?> <?= e($item->unidade ?: 'UN') ?></td>
                                <td class="py-3 px-3 text-right text-text"><?= e(dinheiro($item->precoUnitario)) ?></td>
                                <td class="py-3 px-3 text-right font-medium text-text"><?= e(dinheiro($item->subTotal)) ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Modal de Lançamento Financeiro -->
<?php if (! $faturada) { ?>
    <div id="modal-faturar" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
        <div class="w-full max-w-md rounded-xl border border-border bg-surface p-6 shadow-xl">
            <h3 class="text-heading-sm text-text mb-2">Lançar no Contas a Pagar</h3>
            <p class="text-caption text-muted mb-4">Gera um lançamento de despesa no valor de <?= e(dinheiro($compra->valorTotal)) ?> vinculado a este fornecedor.</p>
            <form id="form-faturar" method="post" action="<?= e(site_url('compras/faturar')) ?>" class="flex flex-col gap-4">
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <input type="hidden" name="idCompras" value="<?= e($compra->idCompras) ?>">

                <div>
                    <label class="text-label-sm text-text block mb-1">Data de Vencimento</label>
                    <input type="date" name="vencimento" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text" required>
                </div>

                <div>
                    <label class="text-label-sm text-text block mb-1">Forma de Pagamento</label>
                    <select name="forma_pgto" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text">
                        <option value="Boleto">Boleto</option>
                        <option value="PIX">PIX</option>
                        <option value="Transferência Bancária">Transferência Bancária</option>
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Cartão de Crédito">Cartão de Crédito</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 cursor-pointer mt-1">
                    <input type="checkbox" name="pago" value="1" class="rounded border-border text-primary">
                    <span class="text-caption text-text">Já foi pago (baixar lançamento agora)</span>
                </label>

                <div class="flex justify-end gap-2 mt-4">
                    <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => 'modal-faturar']]) ?>
                    <?= component('button', ['label' => 'Confirmar lançamento', 'type' => 'submit', 'variant' => 'primary']) ?>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-compra" method="post" action="<?= e(site_url('compras/excluir')) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-compra" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-compra',
        'title' => 'Excluir compra?',
        'message' => [
            new HtmlSeguro('Deseja realmente excluir a compra <strong class="font-semibold text-text" data-modal-valor="numero"></strong>? Se a compra entrou no estoque, o saldo dos produtos será estornado automaticamente.'),
        ],
        'confirm_label' => 'Excluir compra',
        'confirm_attrs' => ['form' => 'form-excluir-compra'],
    ]) ?>
<?php } ?>
