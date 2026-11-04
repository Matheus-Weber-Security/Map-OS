<?php
/**
 * Formulário de nova compra manual (#Compras-01).
 *
 * @var list<object> $fornecedores
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
?>
<div class="flex max-w-3xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header>
        <div class="flex items-center gap-2 mb-1">
            <a href="<?= e(site_url('compras')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                <?= icon('chevron-left', ['class' => 'size-4']) ?> Cancelar e voltar
            </a>
        </div>
        <h1 class="font-display text-heading-xl text-text">Nova Compra Manual</h1>
        <p class="text-caption text-muted">Inicie o registro manual de uma compra para dar entrada de produtos no estoque.</p>
    </header>

    <form method="post" action="<?= e(site_url('compras/adicionar')) ?>" class="flex flex-col gap-6">
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-4">Informações da Compra</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="fornecedores_id" class="text-label-sm text-text block mb-1">Fornecedor</label>
                    <select id="fornecedores_id" name="fornecedores_id" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text">
                        <option value="0">-- Selecione um fornecedor (ou avulso) --</option>
                        <?php foreach ($fornecedores as $f) { ?>
                            <option value="<?= e($f->idClientes) ?>">
                                <?= e($f->nomeCliente) ?> <?= $f->documento ? '(' . e($f->documento) . ')' : '' ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="dataCompra" class="text-label-sm text-text block mb-1">Data da Compra</label>
                    <input type="date" id="dataCompra" name="dataCompra" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text" required>
                </div>

                <div>
                    <label for="status" class="text-label-sm text-text block mb-1">Situação Inicial</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text">
                        <option value="Concluída">Concluída</option>
                        <option value="Pendente">Pendente</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="observacoes" class="text-label-sm text-text block mb-1">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3" class="w-full rounded-lg border border-input bg-surface px-3 py-2 text-body-sm text-text" placeholder="Anotações internas sobre esta compra..."></textarea>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-border flex justify-end gap-2">
                <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('compras')]) ?>
                <?= component('button', ['label' => 'Iniciar compra', 'icon' => 'plus', 'type' => 'submit', 'variant' => 'primary']) ?>
            </div>
        </section>
    </form>
</div>
