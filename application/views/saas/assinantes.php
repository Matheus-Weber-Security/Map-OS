<div class="flex flex-col gap-6 p-4 sm:p-6 max-w-7xl mx-auto">
    <!-- Breadcrumb e Voltar -->
    <div>
        <a href="<?= site_url('saas/gerenciar') ?>" class="inline-flex items-center gap-1.5 text-caption font-bold text-muted hover:text-text transition-colors">
            <?= icon('chevron-left', ['class' => 'size-4']) ?>
            <span>Voltar para Gerenciar</span>
        </a>
    </div>

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-heading-lg font-bold text-text">Assinantes</h1>
            <p class="text-body-md text-muted mt-1">Gerencie todos os clientes, altere planos, adicione dias e configure descontos personalizados.</p>
        </div>
        <div>
            <span class="inline-block px-3 py-1.5 rounded-lg border border-border bg-surface-subtle text-caption font-bold text-text">
                TOTAL EXIBIDO: <?= count($empresas) ?>
            </span>
        </div>
    </div>

    <!-- Barra de Filtros (Imagem 3) -->
    <div class="rounded-xl border border-border bg-surface p-4 sm:p-5 shadow-xs">
        <form method="get" action="<?= site_url('saas/assinantes') ?>" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <!-- Buscar Assinante -->
            <div class="md:col-span-5 flex flex-col gap-1.5">
                <label for="busca" class="text-caption font-bold uppercase tracking-wider text-muted">Buscar Assinante</label>
                <div class="relative flex items-center">
                    <span class="absolute left-3 text-muted">
                        <?= icon('search', ['class' => 'size-4']) ?>
                    </span>
                    <input id="busca" name="busca" type="text" value="<?= e($buscaAtual) ?>" placeholder="Buscar por nome ou email..." class="w-full rounded-md border border-input bg-surface pl-9 pr-3 py-2 text-label-md text-text focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <!-- Status Buttons -->
            <div class="md:col-span-4 flex flex-col gap-1.5">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Status</span>
                <div class="inline-flex rounded-md border border-border bg-surface-subtle p-0.5">
                    <button type="submit" name="status" value="ativos" class="px-3 py-1.5 text-caption font-bold rounded <?= $statusAtual === 'ativos' ? 'bg-primary text-on-primary shadow-xs' : 'text-muted hover:text-text' ?>">
                        Ativos
                    </button>
                    <button type="submit" name="status" value="inativos" class="px-3 py-1.5 text-caption font-bold rounded <?= $statusAtual === 'inativos' ? 'bg-primary text-on-primary shadow-xs' : 'text-muted hover:text-text' ?>">
                        Inativos
                    </button>
                    <button type="submit" name="status" value="todos" class="px-3 py-1.5 text-caption font-bold rounded <?= $statusAtual === 'todos' ? 'bg-primary text-on-primary shadow-xs' : 'text-muted hover:text-text' ?>">
                        Todos
                    </button>
                </div>
            </div>

            <!-- Botão Filtrar -->
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md bg-surface-subtle hover:bg-border border border-border px-4 py-2 text-label-md font-bold text-text transition-colors">
                    <?= icon('search', ['class' => 'size-4']) ?>
                    <span>Filtrar</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Tabela de Assinantes (Imagem 3) -->
    <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-body-sm">
                <thead>
                    <tr class="border-b border-border bg-surface-subtle text-caption font-bold uppercase text-muted">
                        <th class="py-3 px-4">Nome</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4 text-center">Pagamento</th>
                        <th class="py-3 px-4 text-center">Data Vencimento</th>
                        <th class="py-3 px-4 text-center">Dias</th>
                        <th class="py-3 px-4 text-center">Assinatura</th>
                        <th class="py-3 px-4 text-center">Desconto</th>
                        <th class="py-3 px-4 text-center">Adicionar Dias</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    <?php if (empty($empresas)) { ?>
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted">Nenhum assinante encontrado com os filtros selecionados.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($empresas as $emp) { 
                            $dias = (int) $emp->dias_restantes;
                            $desc = (int) ($emp->desconto_percentual ?? 0);
                        ?>
                            <tr class="hover:bg-surface-subtle/50 transition-colors">
                                <!-- Nome -->
                                <td class="py-3.5 px-4 font-semibold text-text">
                                    <div class="flex items-center gap-2">
                                        <span><?= e($emp->nome_fantasia ?: $emp->razao_social) ?></span>
                                        <a href="<?= site_url('saas/trocarEmpresa/' . $emp->id) ?>" title="Acessar ambiente desta empresa" class="text-xs text-primary hover:underline font-normal">
                                            (Acessar)
                                        </a>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td class="py-3.5 px-4 text-muted"><?= e($emp->email ?: 'Sem email') ?></td>

                                <!-- Pagamento -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold <?= $emp->forma_pagamento_preferida === 'PIX' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-surface-subtle text-muted border border-border' ?>">
                                        <?= e($emp->forma_pagamento_preferida) ?>
                                    </span>
                                </td>

                                <!-- Data Vencimento -->
                                <td class="py-3.5 px-4 text-center text-muted">
                                    <?= ! empty($emp->data_vencimento) ? e(date('d/m/Y', strtotime($emp->data_vencimento))) : '-' ?>
                                </td>

                                <!-- Dias -->
                                <td class="py-3.5 px-4 text-center font-bold <?= $dias > 0 ? 'text-emerald-500' : 'text-red-500' ?>">
                                    <?= $dias ?> dias
                                </td>

                                <!-- Assinatura (Plano Dropdown) -->
                                <td class="py-3.5 px-4 text-center">
                                    <form method="post" action="<?= site_url('saas/alterarPlano') ?>" class="inline-block">
                                        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                                        <input type="hidden" name="empresa_id" value="<?= (int) $emp->id ?>">
                                        <select name="plano_id" onchange="this.form.submit()" class="rounded border border-input bg-surface px-2 py-1 text-xs font-semibold text-text focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer">
                                            <?php foreach ($planos as $pl) { ?>
                                                <option value="<?= (int) $pl->id ?>" <?= (int) $pl->id === (int) $emp->plano_id ? 'selected' : '' ?>>
                                                    <?= e($pl->nome) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </form>
                                </td>

                                <!-- Desconto -->
                                <td class="py-3.5 px-4 text-center text-xs">
                                    <span class="<?= $desc > 0 ? 'text-emerald-500 font-bold' : 'text-muted' ?>">
                                        <?= $desc > 0 ? "{$desc}% OFF" : 'Sem desconto' ?>
                                    </span>
                                    <button type="button" onclick="abrirModalDesconto(<?= (int) $emp->id ?>, <?= $desc ?>, '<?= e($emp->nome_fantasia) ?>')" class="text-primary hover:underline font-bold ml-1">
                                        Ajustar
                                    </button>
                                </td>

                                <!-- Adicionar Dias -->
                                <td class="py-3.5 px-4 text-center text-xs">
                                    <button type="button" onclick="abrirModalDias(<?= (int) $emp->id ?>, '<?= e($emp->nome_fantasia) ?>')" class="text-primary hover:underline font-bold">
                                        Ajustar
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Adicionar Dias -->
<div id="modal-dias" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-sm rounded-2xl border border-border bg-surface p-6 shadow-2xl flex flex-col gap-4">
        <button onclick="fecharModais()" class="absolute top-4 right-4 text-muted hover:text-text">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>
        <h3 class="font-display text-heading-md font-bold text-text">Adicionar Dias</h3>
        <p class="text-caption text-muted">Adicione dias de acesso à empresa: <strong id="modal-dias-empresa-nome" class="text-text"></strong></p>

        <form method="post" action="<?= site_url('saas/adicionarDias') ?>" class="flex flex-col gap-4">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" id="modal-dias-empresa-id" name="empresa_id" value="">

            <div class="flex flex-col gap-1.5">
                <label for="dias" class="text-caption font-bold text-muted uppercase">Quantidade de Dias:</label>
                <input id="dias" name="dias" type="number" min="1" max="1000" value="30" required class="w-full rounded-md border border-input bg-surface px-3 py-2 text-label-md font-bold text-text focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="fecharModais()" class="flex-1 rounded-md border border-border py-2 text-caption font-bold text-text hover:bg-surface-subtle">Cancelar</button>
                <button type="submit" class="flex-1 rounded-md bg-primary hover:bg-primary/90 text-on-primary py-2 text-caption font-bold">Salvar Dias</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ajustar Desconto -->
<div id="modal-desconto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-sm rounded-2xl border border-border bg-surface p-6 shadow-2xl flex flex-col gap-4">
        <button onclick="fecharModais()" class="absolute top-4 right-4 text-muted hover:text-text">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>
        <h3 class="font-display text-heading-md font-bold text-text">Ajustar Desconto</h3>
        <p class="text-caption text-muted">Defina a porcentagem de desconto para: <strong id="modal-desc-empresa-nome" class="text-text"></strong></p>

        <form method="post" action="<?= site_url('saas/ajustarDesconto') ?>" class="flex flex-col gap-4">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" id="modal-desc-empresa-id" name="empresa_id" value="">

            <div class="flex flex-col gap-1.5">
                <label for="desconto_percentual" class="text-caption font-bold text-muted uppercase">Porcentagem de Desconto (%):</label>
                <input id="desconto_percentual" name="desconto_percentual" type="number" min="0" max="100" value="0" required class="w-full rounded-md border border-input bg-surface px-3 py-2 text-label-md font-bold text-text focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="fecharModais()" class="flex-1 rounded-md border border-border py-2 text-caption font-bold text-text hover:bg-surface-subtle">Cancelar</button>
                <button type="submit" class="flex-1 rounded-md bg-primary hover:bg-primary/90 text-on-primary py-2 text-caption font-bold">Salvar Desconto</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalDias(empresaId, empresaNome) {
    document.getElementById('modal-dias-empresa-id').value = empresaId;
    document.getElementById('modal-dias-empresa-nome').textContent = empresaNome;
    document.getElementById('modal-dias').classList.remove('hidden');
}

function abrirModalDesconto(empresaId, descontoAtual, empresaNome) {
    document.getElementById('modal-desc-empresa-id').value = empresaId;
    document.getElementById('modal-desc-empresa-nome').textContent = empresaNome;
    document.getElementById('desconto_percentual').value = descontoAtual;
    document.getElementById('modal-desconto').classList.remove('hidden');
}

function fecharModais() {
    document.getElementById('modal-dias').classList.add('hidden');
    document.getElementById('modal-desconto').classList.add('hidden');
}
</script>
