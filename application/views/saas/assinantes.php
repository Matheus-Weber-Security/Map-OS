<div class="bg-[#070d18] text-[#f1f5f9] min-h-screen -m-4 sm:-m-6 p-6 sm:p-10 flex flex-col gap-6 font-sans">
    <!-- Breadcrumb e Voltar -->
    <div>
        <a href="<?= site_url('saas/gerenciar') ?>" class="inline-flex items-center gap-2 text-xs font-semibold text-[#00d2ff] hover:text-[#38bdf8] transition-colors">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Voltar para Gerenciar</span>
        </a>
    </div>

    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-[#00d2ff] tracking-tight">Assinantes</h1>
            <p class="text-sm text-[#64748b] mt-1 font-medium">Gerencie todos os clientes, altere planos, adicione dias e configure descontos personalizados.</p>
        </div>
        <div>
            <span class="inline-block px-4 py-2 rounded-xl border border-[#1e293b] bg-[#0c1527] text-xs font-bold text-[#cbd5e1] tracking-wide shadow-sm">
                TOTAL EXIBIDO: <span class="text-[#00d2ff]"><?= count($empresas) ?></span>
            </span>
        </div>
    </div>

    <!-- Barra de Filtros (Fiel ao Finance - Imagem 1) -->
    <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-5 shadow-xl">
        <form method="get" action="<?= site_url('saas/assinantes') ?>" id="form-filtros" class="flex flex-col lg:flex-row items-stretch lg:items-end gap-5 justify-between">
            <!-- 1. Buscar Assinante -->
            <div class="flex-1 flex flex-col gap-2">
                <label for="busca" class="text-[11px] font-bold tracking-wider text-[#64748b] uppercase">BUSCAR ASSINANTE</label>
                <div class="relative flex items-center">
                    <input id="busca" name="busca" type="text" value="<?= e($buscaAtual) ?>" placeholder="Buscar por nome ou email..." onkeydown="if(event.key === 'Enter') this.form.submit()" class="w-full rounded-xl border border-[#1e293b] bg-[#070d18] px-4 py-2.5 text-sm text-[#cbd5e1] placeholder-[#475569] focus:border-[#00d2ff] focus:outline-none transition-colors">
                </div>
            </div>

            <!-- 2. Status Segmented Buttons -->
            <div class="flex flex-col gap-2 shrink-0">
                <span class="text-[11px] font-bold tracking-wider text-[#64748b] uppercase">STATUS</span>
                <div class="inline-flex rounded-xl border border-[#1e293b] bg-[#070d18] p-1 gap-1">
                    <button type="submit" name="status" value="ativos" class="px-4 py-2 text-xs font-bold rounded-lg transition-all <?= $statusAtual === 'ativos' ? 'bg-[#1d4ed8] text-white shadow-md' : 'text-[#94a3b8] hover:text-white' ?>">
                        Ativos
                    </button>
                    <button type="submit" name="status" value="inativos" class="px-4 py-2 text-xs font-bold rounded-lg transition-all <?= $statusAtual === 'inativos' ? 'bg-[#1d4ed8] text-white shadow-md' : 'text-[#94a3b8] hover:text-white' ?>">
                        Inativos
                    </button>
                    <button type="submit" name="status" value="todos" class="px-4 py-2 text-xs font-bold rounded-lg transition-all <?= $statusAtual === 'todos' ? 'bg-[#1d4ed8] text-white shadow-md' : 'text-[#94a3b8] hover:text-white' ?>">
                        Todos
                    </button>
                </div>
            </div>

            <!-- 3. Período de Pagamento -->
            <div class="flex flex-col gap-2 shrink-0">
                <span class="text-[11px] font-bold tracking-wider text-[#64748b] uppercase">PERÍODO DE PAGAMENTO</span>
                <div class="flex items-center gap-2 rounded-xl border border-[#1e293b] bg-[#070d18] px-3.5 py-2.5 text-xs text-[#cbd5e1]">
                    <span class="font-medium text-[#cbd5e1]">01/01/2026</span>
                    <svg class="size-4 text-[#64748b]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span class="text-[#64748b] font-medium mx-1">até</span>
                    <span class="font-medium text-[#cbd5e1]">31/12/2026</span>
                    <svg class="size-4 text-[#64748b]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabela de Assinantes (Fiel ao Finance - Imagem 1) -->
    <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-[#1e293b] text-xs font-bold text-[#00d2ff]">
                        <th class="py-4 px-6 text-left">Nome</th>
                        <th class="py-4 px-6 text-left">Email</th>
                        <th class="py-4 px-6 text-center">Pagamento</th>
                        <th class="py-4 px-6 text-center">Data</th>
                        <th class="py-4 px-6 text-center">Dias</th>
                        <th class="py-4 px-6 text-center">Assinatura</th>
                        <th class="py-4 px-6 text-center">Desconto</th>
                        <th class="py-4 px-6 text-center">Adicionar Dias</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#1e293b]/60">
                    <?php if (empty($empresas)) { ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-[#64748b] font-medium">Nenhum assinante encontrado com os filtros selecionados.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($empresas as $emp) { 
                            $dias = (int) $emp->dias_restantes;
                            $desc = (int) ($emp->desconto_percentual ?? 0);
                        ?>
                            <tr class="hover:bg-[#0f1d38]/50 transition-colors">
                                <!-- Nome -->
                                <td class="py-4 px-6 text-left font-semibold text-[#00d2ff]">
                                    <div class="flex items-center gap-2">
                                        <span><?= e($emp->nome_fantasia ?: $emp->razao_social) ?></span>
                                        <a href="<?= site_url('saas/trocarEmpresa/' . $emp->id) ?>" title="Acessar painel desta empresa" class="text-xs text-[#64748b] hover:text-[#00d2ff] font-normal transition-colors">
                                            (Acessar)
                                        </a>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td class="py-4 px-6 text-left text-[#94a3b8] font-medium">
                                    <?= e($emp->email ?: 'matheusrweber@gmail.com') ?>
                                </td>

                                <!-- Pagamento -->
                                <td class="py-4 px-6 text-center">
                                    <?php if ($emp->forma_pagamento_preferida === 'PIX') { ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-[#064e3b]/50 border border-[#059669]/40 text-[#34d399]">
                                            PIX
                                        </span>
                                    <?php } else { ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-[#1e293b] border border-[#334155] text-[#cbd5e1]">
                                            Manual
                                        </span>
                                    <?php } ?>
                                </td>

                                <!-- Data -->
                                <td class="py-4 px-6 text-center text-[#94a3b8] font-medium">
                                    <?= ! empty($emp->data_vencimento) ? e(date('d/m/Y', strtotime($emp->data_vencimento))) : '-' ?>
                                </td>

                                <!-- Dias -->
                                <td class="py-4 px-6 text-center font-bold text-sm <?= $dias > 0 ? 'text-[#34d399]' : 'text-[#f43f5e]' ?>">
                                    <?= $dias ?> dias
                                </td>

                                <!-- Assinatura (Dropdown com chevron) -->
                                <td class="py-4 px-6 text-center">
                                    <form method="post" action="<?= site_url('saas/alterarPlano') ?>" class="inline-block">
                                        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                                        <input type="hidden" name="empresa_id" value="<?= (int) $emp->id ?>">
                                        <div class="relative inline-block">
                                            <select name="plano_id" onchange="this.form.submit()" class="appearance-none bg-[#070d18] border border-[#1e293b] rounded-lg text-xs font-semibold text-[#f1f5f9] pr-8 pl-3 py-1.5 focus:border-[#00d2ff] focus:outline-none cursor-pointer transition-colors">
                                                <?php foreach ($planos as $pl) { ?>
                                                    <option value="<?= (int) $pl->id ?>" <?= (int) $pl->id === (int) $emp->plano_id ? 'selected' : '' ?>>
                                                        <?= e($pl->nome) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-3.5 text-[#64748b]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </div>
                                    </form>
                                </td>

                                <!-- Desconto -->
                                <td class="py-4 px-6 text-center text-xs">
                                    <?php if ($desc > 0) { ?>
                                        <span class="text-[#34d399] font-bold mr-2"><?= $desc ?>% OFF</span>
                                    <?php } else { ?>
                                        <span class="text-[#64748b] mr-2">Sem desconto</span>
                                    <?php } ?>
                                    <button type="button" onclick="abrirModalDesconto(<?= (int) $emp->id ?>, <?= $desc ?>, '<?= e($emp->nome_fantasia ?: $emp->razao_social) ?>')" class="text-[#00d2ff] hover:text-[#38bdf8] font-semibold hover:underline">
                                        Ajustar
                                    </button>
                                </td>

                                <!-- Adicionar Dias -->
                                <td class="py-4 px-6 text-center text-xs">
                                    <button type="button" onclick="abrirModalDias(<?= (int) $emp->id ?>, '<?= e($emp->nome_fantasia ?: $emp->razao_social) ?>')" class="text-[#00d2ff] hover:text-[#38bdf8] font-semibold hover:underline">
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

<!-- Modal Adicionar Dias (Estilo Finance Dark) -->
<div id="modal-dias" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-sm rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-2xl flex flex-col gap-4 text-white">
        <button onclick="fecharModais()" class="absolute top-4 right-4 text-[#64748b] hover:text-white">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <h3 class="font-display text-xl font-bold text-[#00d2ff]">Adicionar Dias</h3>
        <p class="text-xs text-[#94a3b8]">Adicione dias de acesso à empresa: <strong id="modal-dias-empresa-nome" class="text-white"></strong></p>

        <form method="post" action="<?= site_url('saas/adicionarDias') ?>" class="flex flex-col gap-4 mt-2">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" id="modal-dias-empresa-id" name="empresa_id" value="">

            <div class="flex flex-col gap-1.5">
                <label for="dias" class="text-[11px] font-bold text-[#64748b] uppercase">Quantidade de Dias:</label>
                <input id="dias" name="dias" type="number" min="1" max="1000" value="30" required class="w-full rounded-xl border border-[#1e293b] bg-[#070d18] px-3.5 py-2.5 text-sm font-bold text-white focus:outline-none focus:border-[#00d2ff]">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="fecharModais()" class="flex-1 rounded-xl border border-[#1e293b] bg-[#070d18] py-2.5 text-xs font-bold text-[#cbd5e1] hover:bg-[#1e293b] transition-colors">Cancelar</button>
                <button type="submit" class="flex-1 rounded-xl bg-[#1d4ed8] hover:bg-[#2563eb] text-white py-2.5 text-xs font-bold shadow-md transition-colors">Salvar Dias</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ajustar Desconto (Estilo Finance Dark) -->
<div id="modal-desconto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-sm rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-2xl flex flex-col gap-4 text-white">
        <button onclick="fecharModais()" class="absolute top-4 right-4 text-[#64748b] hover:text-white">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
        <h3 class="font-display text-xl font-bold text-[#00d2ff]">Ajustar Desconto</h3>
        <p class="text-xs text-[#94a3b8]">Defina a porcentagem de desconto para: <strong id="modal-desc-empresa-nome" class="text-white"></strong></p>

        <form method="post" action="<?= site_url('saas/ajustarDesconto') ?>" class="flex flex-col gap-4 mt-2">
            <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
            <input type="hidden" id="modal-desc-empresa-id" name="empresa_id" value="">

            <div class="flex flex-col gap-1.5">
                <label for="desconto_percentual" class="text-[11px] font-bold text-[#64748b] uppercase">Porcentagem de Desconto (%):</label>
                <input id="desconto_percentual" name="desconto_percentual" type="number" min="0" max="100" value="0" required class="w-full rounded-xl border border-[#1e293b] bg-[#070d18] px-3.5 py-2.5 text-sm font-bold text-white focus:outline-none focus:border-[#00d2ff]">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="fecharModais()" class="flex-1 rounded-xl border border-[#1e293b] bg-[#070d18] py-2.5 text-xs font-bold text-[#cbd5e1] hover:bg-[#1e293b] transition-colors">Cancelar</button>
                <button type="submit" class="flex-1 rounded-xl bg-[#1d4ed8] hover:bg-[#2563eb] text-white py-2.5 text-xs font-bold shadow-md transition-colors">Salvar Desconto</button>
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
