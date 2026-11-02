<div class="bg-[#070d18] text-[#f1f5f9] min-h-screen -m-4 sm:-m-6 p-6 sm:p-10 flex flex-col gap-6 font-sans">
    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-[#00d2ff] tracking-tight">Gerenciar</h1>
            <p class="text-sm text-[#64748b] mt-1 font-medium">Acompanhe métricas financeiras, evolução da receita e precificação dos planos.</p>
        </div>
        <div>
            <a href="<?= site_url('saas/assinantes') ?>" class="inline-flex items-center gap-2 rounded-xl bg-[#1d4ed8] hover:bg-[#2563eb] text-white font-bold px-5 py-2.5 shadow-md transition-colors text-xs tracking-wide">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Ver Todos os Assinantes</span>
            </a>
        </div>
    </div>

    <!-- KPIs Executivos do Topo (Fiel à Imagem 2 do Finance) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Assinantes Ativos -->
        <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-5 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">ASSINANTES ATIVOS</span>
                <span class="p-2 rounded-lg bg-[#070d18] text-[#00d2ff]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-3xl font-extrabold text-[#00d2ff]"><?= (int) $metricas['assinantes_ativos'] ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-[#34d399] font-semibold">
                    <span>↗ +<?= (int) $metricas['novos_assinantes'] ?> Este Mês</span>
                </div>
            </div>
        </div>

        <!-- 2. Novos Assinantes -->
        <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-5 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">NOVOS ASSINANTES</span>
                <span class="p-2 rounded-lg bg-[#070d18] text-[#00d2ff]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-3xl font-extrabold text-[#00d2ff]"><?= (int) $metricas['novos_assinantes'] ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-[#94a3b8] font-medium">
                    <span>Últimos 30 dias</span>
                </div>
            </div>
        </div>

        <!-- 3. Receita Mensal (MRR) -->
        <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-5 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">RECEITA MENSAL (MRR)</span>
                <span class="p-2 rounded-lg bg-[#070d18] text-[#34d399]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-3xl font-extrabold text-[#00d2ff]">
                    R$ <?= number_format($metricas['mrr'], 2, ',', '.') ?>
                </div>
                <div class="mt-1 flex items-center gap-1 text-xs text-[#34d399] font-semibold">
                    <span>+0% Este Mês</span>
                </div>
            </div>
        </div>

        <!-- 4. Cancelamentos (Churn) -->
        <div class="rounded-xl border border-[#1e293b] bg-[#0c1527] p-5 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">CANCELAMENTOS</span>
                <span class="p-2 rounded-lg bg-[#070d18] text-[#f43f5e]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-3xl font-extrabold text-[#00d2ff]"><?= (int) $metricas['churn_percent'] ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-[#64748b] font-semibold">
                    <span>Churn <?= $metricas['churn_percent'] ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Centrais (Fiel à Imagem 2 do Finance) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Evolução da Receita -->
        <div class="lg:col-span-2 rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#1e293b]">
                <h3 class="font-display text-lg font-bold text-[#f1f5f9]">Evolução da Receita</h3>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-evolucao"></canvas>
            </div>
        </div>

        <!-- Formas de Pagamento -->
        <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#1e293b]">
                <h3 class="font-display text-lg font-bold text-[#f1f5f9]">Formas de Pagamento</h3>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-pagamento"></canvas>
            </div>
            <div class="mt-4 flex items-center justify-around text-xs font-semibold text-[#94a3b8]">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-[#1d4ed8]"></span>
                    <span>Manual (100%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-[#00d2ff]"></span>
                    <span>PIX (0%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé: Últimos Assinantes e Gerenciar Preços (Fiel à Imagem 2 do Finance) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tabela: Últimos Assinantes -->
        <div class="lg:col-span-2 rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-xl flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#1e293b]">
                <h3 class="font-display text-lg font-bold text-[#f1f5f9]">Últimos Assinantes</h3>
                <a href="<?= site_url('saas/assinantes') ?>" class="inline-block px-3 py-1 rounded-lg border border-[#1e293b] bg-[#070d18] text-xs font-semibold text-[#00d2ff] hover:text-[#38bdf8] transition-colors">
                    Ver todos os assinantes
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-[#1e293b] text-xs font-bold text-[#64748b]">
                            <th class="py-3 px-4">Nome</th>
                            <th class="py-3 px-4 text-right">Valor</th>
                            <th class="py-3 px-4 text-center">Pagamento</th>
                            <th class="py-3 px-4 text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#1e293b]/60">
                        <?php if (empty($metricas['ultimos_assinantes'])) { ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-[#64748b] font-medium">Nenhum pagamento registrado ainda.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($metricas['ultimos_assinantes'] as $ass) { ?>
                                <tr class="hover:bg-[#0f1d38]/40 transition-colors">
                                    <td class="py-3.5 px-4 font-semibold text-[#00d2ff]"><?= e($ass->nome) ?></td>
                                    <td class="py-3.5 px-4 text-right font-bold text-[#34d399]">R$ <?= number_format((float) $ass->valor, 2, ',', '.') ?></td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-block px-2.5 py-0.5 rounded text-xs font-bold bg-[#1e293b] text-[#cbd5e1] border border-[#334155]">
                                            <?= e($ass->pagamento) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-xs text-[#94a3b8]">
                                        <?= ! empty($ass->data) ? e(date('d/m/Y', strtotime($ass->data))) : e(date('d/m/Y', strtotime($ass->criado_em))) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card: Gerenciar Preço da Assinatura (Fiel à Imagem 2 do Finance) -->
        <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 shadow-xl flex flex-col justify-between">
            <div>
                <div class="mb-4 pb-2 border-b border-[#1e293b]">
                    <h3 class="font-display text-lg font-bold text-[#f1f5f9]">Gerenciar Preço da Assinatura</h3>
                </div>

                <form method="post" action="<?= site_url('saas/salvarPrecos') ?>" class="flex flex-col gap-4">
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                    <div class="flex flex-col gap-1.5">
                        <label for="valor_basico" class="text-[11px] font-bold tracking-wider text-[#00d2ff] uppercase">VALOR DA ASSINATURA BÁSICA:</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-[#64748b] font-bold text-sm">R$</span>
                            <input id="valor_basico" name="valor_basico" type="text" value="<?= number_format($metricas['valor_basico'], 2, '.', '') ?>" required class="w-full rounded-xl border border-[#1e293b] bg-[#070d18] pl-11 pr-4 py-2.5 text-sm font-bold text-[#f1f5f9] focus:outline-none focus:border-[#00d2ff]">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="valor_essencial" class="text-[11px] font-bold tracking-wider text-[#00d2ff] uppercase">⭐ VALOR DA ASSINATURA ESSENCIAL:</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-[#64748b] font-bold text-sm">R$</span>
                            <input id="valor_essencial" name="valor_essencial" type="text" value="<?= number_format($metricas['valor_essencial'], 2, '.', '') ?>" required class="w-full rounded-xl border border-[#1e293b] bg-[#070d18] pl-11 pr-4 py-2.5 text-sm font-bold text-[#f1f5f9] focus:outline-none focus:border-[#00d2ff]">
                        </div>
                    </div>

                    <button type="submit" class="mt-3 w-full flex items-center justify-center gap-2 rounded-xl bg-[#1d4ed8] hover:bg-[#2563eb] text-white font-bold py-3 px-4 shadow-md transition-colors text-xs tracking-wide">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        <span>Salvar Preços</span>
                    </button>
                </form>

                <p class="text-[11px] text-[#64748b] mt-3 text-center">Os novos preços serão aplicados para novas contratações e renovações.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Gráfico de Evolução da Receita
    const ctxEvolucao = document.getElementById('chart-evolucao')?.getContext('2d');
    if (ctxEvolucao) {
        new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: ['Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov'],
                datasets: [{
                    label: 'Receita (R$)',
                    data: [0, 0, 0, 0, 0, <?= (float) $metricas['mrr'] ?>],
                    borderColor: '#1d4ed8',
                    backgroundColor: 'rgba(29, 78, 216, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 3,
                    pointBackgroundColor: '#00d2ff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#64748b' }
                    },
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });
    }

    // 2. Gráfico de Formas de Pagamento
    const ctxPagamento = document.getElementById('chart-pagamento')?.getContext('2d');
    if (ctxPagamento) {
        new Chart(ctxPagamento, {
            type: 'doughnut',
            data: {
                labels: ['Manual', 'PIX'],
                datasets: [{
                    data: [100, 0],
                    backgroundColor: ['#1d4ed8', '#00d2ff'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '70%',
            }
        });
    }
});
</script>
