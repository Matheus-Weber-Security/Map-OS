<?php
/**
 * Tela de Conferência e De-Para da NF-e (#Compras-01).
 *
 * @var array         $nfe
 * @var list<object>  $produtosSistema
 * @var list<object>  $fornecedores
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$emit = $nfe['emitente'];
$fornecedorJaCadastrado = ! empty($nfe['fornecedor_ja_cadastrado']);
$fornecedorSugerido = $nfe['fornecedor_id_sugerido'] ?? null;
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= e(site_url('compras/importarNfe')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                    <?= icon('chevron-left', ['class' => 'size-4']) ?> Cancelar e voltar
                </a>
            </div>
            <h1 class="font-display text-heading-xl text-text">Conferência da NF-e <?= e($nfe['numero'] ? '#' . $nfe['numero'] : '') ?></h1>
            <p class="text-caption text-muted">Confira o fornecedor, vincule os produtos ao seu estoque e defina os fatores de conversão.</p>
        </div>
        <div class="text-right">
            <span class="text-caption text-muted block">Valor Total da Nota</span>
            <span class="font-display text-heading-lg text-primary"><?= e(dinheiro($nfe['totais']['valor_total'])) ?></span>
        </div>
    </header>

    <form method="post" action="<?= e(site_url('compras/concluirImportacaoNfe')) ?>" class="flex flex-col gap-6">
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <!-- 1. IDENTIFICAÇÃO DO FORNECEDOR E NOTA -->
        <section class="<?= e($caixa) ?>">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border mb-4">
                <div>
                    <h2 class="text-heading-sm text-text">Dados do Fornecedor</h2>
                    <p class="text-caption text-muted">Identificado a partir do CNPJ da nota fiscal.</p>
                </div>
                <div>
                    <?php if ($fornecedorJaCadastrado) { ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-caption font-medium bg-success-soft text-success-ink">
                            <?= icon('check', ['class' => 'size-3.5']) ?> Fornecedor já cadastrado no sistema
                        </span>
                    <?php } else { ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-caption font-medium bg-info-soft text-info-ink">
                            <?= icon('info', ['class' => 'size-3.5']) ?> Novo fornecedor (será cadastrado automaticamente)
                        </span>
                    <?php } ?>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <span class="text-label-sm text-muted block">Razão Social / Nome</span>
                    <strong class="text-body-sm text-text block"><?= e($emit['razao_social'] ?: $emit['nome_fantasia']) ?></strong>
                </div>
                <div>
                    <span class="text-label-sm text-muted block">CNPJ / CPF</span>
                    <strong class="text-body-sm text-text block"><?= e($emit['documento']) ?></strong>
                </div>
                <div>
                    <span class="text-label-sm text-muted block">Cidade / UF</span>
                    <strong class="text-body-sm text-text block"><?= e(($emit['municipio'] ? $emit['municipio'] . ' - ' : '') . $emit['uf']) ?></strong>
                </div>
            </div>

            <?php if (! $fornecedorJaCadastrado) { ?>
                <div class="mt-4 pt-4 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <p class="text-caption text-muted">Deseja associar a um fornecedor já existente na sua lista?</p>
                    <select name="fornecedor_id" class="rounded-lg border border-input bg-surface px-3 py-1.5 text-body-sm text-text max-w-xs">
                        <option value="0">-- Cadastrar automaticamente como novo --</option>
                        <?php foreach ($fornecedores as $f) { ?>
                            <option value="<?= e($f->idClientes) ?>" <?= $f->idClientes == $fornecedorSugerido ? 'selected' : '' ?>>
                                <?= e($f->nomeCliente) ?> (<?= e($f->documento) ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else { ?>
                <input type="hidden" name="fornecedor_id" value="<?= e($fornecedorSugerido) ?>">
            <?php } ?>
        </section>

        <!-- 2. VÍNCULO DOS PRODUTOS (DE-PARA) -->
        <section class="<?= e($caixa) ?>">
            <div class="mb-4">
                <h2 class="text-heading-sm text-text">Produtos da NF-e (Vínculo e Estoque)</h2>
                <p class="text-caption text-muted">Vincule cada item do XML ao produto correspondente no seu estoque. O sistema salvará as relações para reconhecer automaticamente nas próximas notas.</p>
            </div>

            <div class="flex flex-col gap-4">
                <?php foreach ($nfe['itens'] as $idx => $item) { ?>
                    <div class="rounded-xl border border-border bg-surface-subtle/20 p-4 flex flex-col gap-3">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 border-b border-border/60 pb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-label-sm px-2 py-0.5 rounded bg-surface border border-border text-muted">Item <?= e($item['item_num']) ?></span>
                                    <strong class="text-body-md font-medium text-text"><?= e($item['descricao']) ?></strong>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-caption text-muted mt-1">
                                    <span>Cód. Fornecedor: <strong><?= e($item['codigo_fornecedor'] ?: '—') ?></strong></span>
                                    <?php if ($item['ean']) { ?>
                                        <span>EAN/Cód. Barras: <strong><?= e($item['ean']) ?></strong></span>
                                    <?php } ?>
                                    <span>NCM: <?= e($item['ncm'] ?: '—') ?></span>
                                    <span>Faturado: <strong><?= e($item['quantidade']) ?> <?= e($item['unidade']) ?></strong> × <?= e(dinheiro($item['valor_unitario'])) ?></span>
                                </div>
                            </div>
                            <div class="text-right sm:shrink-0">
                                <span class="text-caption text-muted block">Subtotal Item</span>
                                <strong class="text-body-md text-text"><?= e(dinheiro($item['valor_total'])) ?></strong>
                            </div>
                        </div>

                        <!-- Configuração do Vínculo e Fator de Conversão -->
                        <div class="grid gap-4 sm:grid-cols-12 items-end">
                            <div class="sm:col-span-8">
                                <label for="prod_select_<?= e($idx) ?>" class="text-label-sm text-text block mb-1">
                                    Vincular ao Produto do seu Sistema:
                                    <?php if ($item['origem_vinculo'] === 'historico_fornecedor') { ?>
                                        <span class="text-success-ink text-caption font-normal ml-2">✓ Reconhecido pelo histórico do fornecedor</span>
                                    <?php } elseif ($item['origem_vinculo'] === 'codigo_barras') { ?>
                                        <span class="text-primary text-caption font-normal ml-2">✓ Reconhecido pelo código de barras</span>
                                    <?php } ?>
                                </label>
                                <select id="prod_select_<?= e($idx) ?>" name="itens[<?= e($idx) ?>][produto_id]" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text">
                                    <option value="0">-- Cadastrar automaticamente como novo produto --</option>
                                    <?php foreach ($produtosSistema as $p) { ?>
                                        <option value="<?= e($p->idProdutos) ?>" <?= $p->idProdutos == $item['produto_id_vinculado'] ? 'selected' : '' ?>>
                                            <?= e($p->descricao) ?> (Estoque atual: <?= e($p->estoque) ?> <?= e($p->unidade) ?> | Custo: <?= e(dinheiro($p->precoCompra)) ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="sm:col-span-4">
                                <label for="fator_<?= e($idx) ?>" class="text-label-sm text-text block mb-1" title="Exemplo: Se o fornecedor vendeu 1 Caixa com 12 Unidades, informe 12 para entrarem 12 unidades no estoque.">
                                    Fator de Conversão (Embalagem):
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" step="0.0001" min="0.0001" id="fator_<?= e($idx) ?>" name="itens[<?= e($idx) ?>][fator_conversao]" value="<?= e($item['fator_conversao']) ?>" class="w-28 rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text text-right" required>
                                    <span class="text-caption text-muted">unidades p/ estoque</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </section>

        <!-- 3. CONFIGURAÇÕES ADICIONAIS E FINANCEIRO -->
        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-3">Opções de Entrada e Financeiro</h2>
            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="atualizar_custo" value="1" checked class="mt-1 rounded border-border text-primary focus:ring-primary">
                    <div>
                        <strong class="text-body-sm text-text block">Atualizar preço de custo no cadastro dos produtos</strong>
                        <span class="text-caption text-muted">Atualiza o preço de compra dos produtos vinculados com os novos valores unitários desta nota.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer border-t border-border pt-3">
                    <input type="checkbox" name="lancar_financeiro" value="1" checked class="mt-1 rounded border-border text-primary focus:ring-primary">
                    <div>
                        <strong class="text-body-sm text-text block">Lançar no Contas a Pagar do Financeiro</strong>
                        <span class="text-caption text-muted">Gera despesa(s) no módulo financeiro vinculadas ao fornecedor com os vencimentos informados no XML.</span>
                        <?php if (! empty($nfe['duplicatas'])) { ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php foreach ($nfe['duplicatas'] as $dup) { ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-surface border border-border text-caption text-text">
                                        Parc. <?= e($dup['numero']) ?>: <?= e(dinheiro($dup['valor'])) ?> em <?= e(date('d/m/Y', strtotime($dup['vencimento']))) ?>
                                    </span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </label>
            </div>

            <div class="mt-6 pt-4 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="text-caption text-muted">
                    Ao confirmar, o estoque será alimentado e os vínculos serão gravados para as próximas importações.
                </div>
                <div class="flex items-center gap-2">
                    <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('compras')]) ?>
                    <?= component('button', ['label' => 'Concluir entrada de estoque', 'icon' => 'check', 'type' => 'submit', 'variant' => 'primary']) ?>
                </div>
            </div>
        </section>
    </form>
</div>
