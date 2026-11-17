<div class="flex flex-col gap-6 pt-2 pb-8 max-w-6xl mx-auto">
    <!-- Cabeçalho -->
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <?= component('button', [
                    'label' => 'Voltar ao SaaS',
                    'icon' => 'chevron-left',
                    'variant' => 'ghost',
                    'size' => 'sm',
                    'href' => site_url('saas/gerenciar'),
                ]) ?>
            </div>
            <h1 class="font-display text-heading-xl text-text">Cobranças de Assinaturas</h1>
            <p class="text-caption text-muted">Audite, confirme ou cancele os boletos e cobranças gerados pelas empresas assinantes.</p>
        </div>
        <div class="flex items-center gap-2">
            <?= component('button', [
                'label' => 'Assinantes',
                'icon' => 'users',
                'variant' => 'outline',
                'size' => 'sm',
                'href' => site_url('saas/assinantes'),
            ]) ?>
        </div>
    </header>

    <!-- Filtros -->
    <div class="rounded-xl border border-border bg-surface p-4 shadow-2xs">
        <form method="get" action="<?= site_url('saas/cobrancas') ?>" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
            <div>
                <label class="text-caption font-semibold text-text block mb-1">Buscar Empresa / ID</label>
                <input type="text" name="termo" value="<?= e($filtros['termo'] ?? '') ?>" placeholder="Nome, razão social ou ID..." class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary">
            </div>

            <div>
                <label class="text-caption font-semibold text-text block mb-1">Forma de Pagamento</label>
                <select name="forma" class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary">
                    <option value="">Todas as Formas</option>
                    <option value="BOLETO" <?= ($filtros['forma'] ?? '') === 'BOLETO' ? 'selected' : '' ?>>Boleto Bancário</option>
                    <option value="PIX" <?= ($filtros['forma'] ?? '') === 'PIX' ? 'selected' : '' ?>>PIX Instantâneo</option>
                    <option value="CARTAO_CREDITO" <?= ($filtros['forma'] ?? '') === 'CARTAO_CREDITO' ? 'selected' : '' ?>>Cartão de Crédito</option>
                    <option value="CARTAO_DEBITO" <?= ($filtros['forma'] ?? '') === 'CARTAO_DEBITO' ? 'selected' : '' ?>>Cartão de Débito</option>
                </select>
            </div>

            <div>
                <label class="text-caption font-semibold text-text block mb-1">Status da Cobrança</label>
                <select name="status" class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary">
                    <option value="">Todos os Status</option>
                    <option value="pendente" <?= ($filtros['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="pago" <?= ($filtros['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="cancelado" <?= ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-on-primary font-semibold text-sm transition-colors shadow-2xs">
                    <?= icon('search', ['class' => 'size-4']) ?>
                    <span>Filtrar</span>
                </button>
                <?php if (! empty($filtros['termo']) || ! empty($filtros['forma']) || ! empty($filtros['status'])) { ?>
                    <a href="<?= site_url('saas/cobrancas') ?>" class="p-2 rounded-lg bg-surface-subtle border border-input hover:bg-border text-muted hover:text-text transition-colors" title="Limpar filtros">
                        <?= icon('x', ['class' => 'size-4']) ?>
                    </a>
                <?php } ?>
            </div>
        </form>
    </div>

    <!-- Tabela de Cobranças -->
    <div class="rounded-xl border border-border bg-surface p-6 shadow-2xs flex flex-col gap-4">
        <div class="flex items-center justify-between border-b border-border pb-4">
            <div class="flex items-center gap-2">
                <?= icon('receipt', ['class' => 'size-5 text-primary']) ?>
                <h2 class="font-display text-heading-sm font-semibold text-text">Todas as Cobranças</h2>
            </div>
            <span class="text-caption text-muted font-medium"><?= (int) $total ?> <?= $total === 1 ? 'cobrança encontrada' : 'cobranças encontradas' ?></span>
        </div>

        <?php if (empty($cobrancas)) { ?>
            <div class="py-12 flex flex-col items-center justify-center text-center gap-3">
                <div class="size-12 rounded-full bg-surface-subtle flex items-center justify-center text-muted">
                    <?= icon('receipt', ['class' => 'size-6']) ?>
                </div>
                <h3 class="font-display text-base font-semibold text-text">Nenhuma cobrança encontrada</h3>
                <p class="text-caption text-muted max-w-sm">Nenhum registro encontrado para os filtros selecionados.</p>
            </div>
        <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-3">ID / Emissão</th>
                            <th class="py-3 px-3">Empresa Assinante</th>
                            <th class="py-3 px-3">Plano / Valor</th>
                            <th class="py-3 px-3">Forma / Gateway</th>
                            <th class="py-3 px-3">Vencimento</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($cobrancas as $c) {
                            $isVencido = ($c->status === 'pendente' && ! empty($c->data_vencimento) && strtotime($c->data_vencimento) < strtotime('today'));
                        ?>
                            <tr class="hover:bg-surface-subtle transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-text">#<?= (int) $c->id ?></span>
                                        <span class="text-caption text-muted"><?= date('d/m/Y H:i', strtotime($c->criado_em)) ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-text"><?= e($c->nome_fantasia ?: $c->razao_social ?: 'Empresa #' . $c->empresa_id) ?></span>
                                        <span class="text-caption text-muted">ID Empresa: <?= (int) $c->empresa_id ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-text">R$ <?= number_format($c->valor, 2, ',', '.') ?></span>
                                        <span class="text-caption text-muted"><?= e($c->plano_nome ?: 'Plano') ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-text uppercase"><?= e($c->forma_pagamento) ?></span>
                                        <span class="text-caption text-muted capitalize"><?= e($c->gateway ?: 'Manual') ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    <?php if (! empty($c->data_vencimento)) { ?>
                                        <span class="<?= $isVencido ? 'text-danger-ink font-bold' : 'text-text' ?>">
                                            <?= date('d/m/Y', strtotime($c->data_vencimento)) ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted">-</span>
                                    <?php } ?>
                                </td>
                                <td class="py-3.5 px-3">
                                    <?php if ($c->status === 'pago') { ?>
                                        <?= component('pill-status', ['label' => 'Pago', 'variant' => 'success']) ?>
                                    <?php } elseif ($c->status === 'cancelado') { ?>
                                        <?= component('pill-status', ['label' => 'Cancelado', 'variant' => 'neutral']) ?>
                                    <?php } elseif ($isVencido) { ?>
                                        <?= component('pill-status', ['label' => 'Vencido', 'variant' => 'danger']) ?>
                                    <?php } else { ?>
                                        <?= component('pill-status', ['label' => 'Pendente', 'variant' => 'warning']) ?>
                                    <?php } ?>
                                </td>
                                <td class="py-3.5 px-3 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <?php if (! empty($c->linha_digitavel)) { ?>
                                            <button type="button" 
                                                    class="btn-copiar-linha p-2 rounded-lg text-muted hover:text-text hover:bg-surface border border-transparent hover:border-border transition-colors"
                                                    data-linha="<?= e($c->linha_digitavel) ?>"
                                                    title="Copiar Linha Digitável">
                                                <?= icon('copy', ['class' => 'size-4']) ?>
                                            </button>
                                        <?php } ?>

                                        <?php if (! empty($c->pdf_url)) { ?>
                                            <a href="<?= e($c->pdf_url) ?>" 
                                               target="_blank" 
                                               class="p-2 rounded-lg text-primary hover:text-primary-hover hover:bg-primary/10 transition-colors"
                                               title="Imprimir / Visualizar Documento">
                                                <?= icon('printer', ['class' => 'size-4']) ?>
                                            </a>
                                        <?php } ?>

                                        <?php if ($c->status === 'pendente') { ?>
                                            <button type="button" 
                                                    class="btn-confirmar-cobranca p-2 rounded-lg text-success-ink hover:text-success hover:bg-success/10 transition-colors"
                                                    data-id="<?= (int) $c->id ?>"
                                                    data-empresa="<?= e($c->nome_fantasia ?: $c->razao_social) ?>"
                                                    title="Confirmar Pagamento e Liberar +30 dias">
                                                <?= icon('check', ['class' => 'size-4']) ?>
                                            </button>

                                            <button type="button" 
                                                    class="btn-cancelar-cobranca p-2 rounded-lg text-danger-ink hover:text-danger hover:bg-danger/10 transition-colors"
                                                    data-id="<?= (int) $c->id ?>"
                                                    title="Cancelar Cobrança">
                                                <?= icon('trash-2', ['class' => 'size-4']) ?>
                                            </button>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPages > 1) { ?>
                <div class="flex items-center justify-between border-t border-border pt-4">
                    <span class="text-caption text-muted">Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                    <div class="flex items-center gap-1">
                        <?php for ($p = 1; $p <= $totalPages; $p++) {
                            $queryParams = array_merge($filtros, ['page' => $p]);
                            $url = site_url('saas/cobrancas?' . http_build_query($queryParams));
                        ?>
                            <a href="<?= $url ?>" class="size-8 flex items-center justify-center rounded-lg text-sm font-semibold <?= $p === (int) $page ? 'bg-primary text-on-primary' : 'bg-surface-subtle border border-border text-text hover:bg-border' ?>">
                                <?= $p ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Copiar Linha
    document.querySelectorAll('.btn-copiar-linha').forEach(btn => {
        btn.addEventListener('click', async () => {
            const linha = btn.getAttribute('data-linha');
            if (!linha) return;
            try {
                await navigator.clipboard.writeText(linha);
                alert('Linha digitável copiada com sucesso!');
            } catch (e) {
                prompt('Copie a linha digitável abaixo:', linha);
            }
        });
    });

    // Confirmar Cobrança Manualmente
    document.querySelectorAll('.btn-confirmar-cobranca').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            const empresa = btn.getAttribute('data-empresa');
            if (!id) return;
            if (!confirm(`Deseja confirmar o pagamento da cobrança #${id} (${empresa}) e adicionar +30 dias ao plano?`)) {
                return;
            }

            btn.disabled = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf_token"]')?.content || '';
                const formData = new FormData();
                formData.append('id', id);
                if (csrfToken) formData.append('ci_csrf_token', csrfToken);

                const res = await fetch('<?= site_url('saas/confirmarCobranca') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                const data = await res.json().catch(() => null);
                if (data && data.result) {
                    alert('🎉 ' + data.message);
                    window.location.reload();
                } else {
                    alert(data?.message || 'Erro ao confirmar cobrança.');
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Erro na comunicação com o servidor.');
                btn.disabled = false;
            }
        });
    });

    // Cancelar Cobrança
    document.querySelectorAll('.btn-cancelar-cobranca').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            if (!id) return;
            if (!confirm(`Deseja realmente cancelar a cobrança #${id}? Esta ação não pode ser desfeita.`)) {
                return;
            }

            btn.disabled = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf_token"]')?.content || '';
                const formData = new FormData();
                formData.append('id', id);
                if (csrfToken) formData.append('ci_csrf_token', csrfToken);

                const res = await fetch('<?= site_url('saas/cancelarCobranca') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                const data = await res.json().catch(() => null);
                if (data && data.result) {
                    alert('Cobrança cancelada com sucesso.');
                    window.location.reload();
                } else {
                    alert(data?.message || 'Erro ao cancelar cobrança.');
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Erro na comunicação com o servidor.');
                btn.disabled = false;
            }
        });
    });
});
</script>
