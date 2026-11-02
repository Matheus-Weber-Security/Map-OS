<div class="flex flex-col gap-6 p-4 sm:p-6 max-w-7xl mx-auto">
    <!-- Cabeçalho -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-heading-lg font-bold text-text">Gerenciar SaaS</h1>
            <p class="text-body-md text-muted mt-1">Acompanhe métricas financeiras, assinantes ativos e precificação dos planos.</p>
        </div>
        <div>
            <a href="<?= site_url('saas/assinantes') ?>" class="inline-flex items-center gap-2 rounded-lg bg-primary hover:bg-primary/90 text-on-primary font-bold px-4 py-2.5 shadow-sm transition-colors text-label-md">
                <?= icon('users', ['class' => 'size-4']) ?>
                <span>Ver Todos os Assinantes</span>
            </a>
        </div>
    </div>

    <!-- KPIs Executivos do Topo (Imagem 2) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Assinantes Ativos -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Assinantes Ativos</span>
                <span class="p-2 rounded-lg bg-primary/10 text-primary">
                    <?= icon('users', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-display-small font-bold text-text"><?= (int) $metricas['assinantes_ativos'] ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-emerald-500 font-semibold">
                    <span>↗ +<?= (int) $metricas['novos_assinantes'] ?> este mês</span>
                </div>
            </div>
        </div>

        <!-- 2. Novos Assinantes -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Novos Assinantes</span>
                <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-500">
                    <?= icon('plus', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-display-small font-bold text-text"><?= (int) $metricas['novos_assinantes'] ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-muted font-medium">
                    <span>Últimos 30 dias</span>
                </div>
            </div>
        </div>

        <!-- 3. Receita Mensal (MRR) -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Receita Mensal (MRR)</span>
                <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-500">
                    <?= icon('chart-column', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-display-small font-bold text-emerald-500">
                    R$ <?= number_format($metricas['mrr'], 2, ',', '.') ?>
                </div>
                <div class="mt-1 flex items-center gap-1 text-xs text-emerald-500 font-semibold">
                    <span>Recorrente ativa</span>
                </div>
            </div>
        </div>

        <!-- 4. Cancelamentos (Churn) -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Cancelamentos</span>
                <span class="p-2 rounded-lg bg-red-500/10 text-red-500">
                    <?= icon('user', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-display-small font-bold text-text"><?= $metricas['churn_percent'] > 0 ? (int) $metricas['churn_percent'] . '%' : '0' ?></div>
                <div class="mt-1 flex items-center gap-1 text-xs text-muted font-semibold">
                    <span>Churn <?= $metricas['churn_percent'] ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Centrais (Imagem 2) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Evolução da Receita -->
        <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Evolução da Receita</h3>
                <span class="text-caption text-muted">Últimos meses</span>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-evolucao"></canvas>
            </div>
        </div>

        <!-- Formas de Pagamento -->
        <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Formas de Pagamento</h3>
                <span class="text-caption text-muted">Distribuição</span>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-pagamento"></canvas>
            </div>
            <div class="mt-4 flex items-center justify-around text-caption font-semibold">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-emerald-500"></span>
                    <span class="text-text">PIX (<?= $metricas['forma_pix_percent'] ?>%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-sky-500"></span>
                    <span class="text-text">Manual (<?= $metricas['forma_manual_percent'] ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé: Últimos Assinantes e Gerenciar Preços (Imagem 2) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tabela: Últimos Assinantes -->
        <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Últimos Assinantes</h3>
                <a href="<?= site_url('saas/assinantes') ?>" class="text-caption font-bold text-primary hover:underline">Ver todos os assinantes</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm">
                    <thead>
                        <tr class="border-b border-border text-caption font-bold uppercase text-muted">
                            <th class="py-2.5 px-3">Nome</th>
                            <th class="py-2.5 px-3 text-right">Valor</th>
                            <th class="py-2.5 px-3 text-center">Pagamento</th>
                            <th class="py-2.5 px-3 text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <?php if (empty($metricas['ultimos_assinantes'])) { ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted">Nenhum pagamento registrado ainda.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($metricas['ultimos_assinantes'] as $ass) { ?>
                                <tr class="hover:bg-surface-subtle/50 transition-colors">
                                    <td class="py-3 px-3 font-semibold text-text"><?= e($ass->nome) ?></td>
                                    <td class="py-3 px-3 text-right font-bold text-emerald-500">R$ <?= number_format((float) $ass->valor, 2, ',', '.') ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-surface-subtle border border-border text-text">
                                            <?= e($ass->pagamento) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right text-muted">
                                        <?= ! empty($ass->data) ? e(date('d/m/Y', strtotime($ass->data))) : e(date('d/m/Y', strtotime($ass->criado_em))) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card: Gerenciar Preço da Assinatura -->
        <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 mb-4 pb-2 border-b border-border">
                    <?= icon('settings', ['class' => 'size-5 text-primary']) ?>
                    <h3 class="font-display text-heading-sm font-bold text-text">Gerenciar Preço da Assinatura</h3>
                </div>

                <form method="post" action="<?= site_url('saas/salvarPrecos') ?>" class="flex flex-col gap-4">
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                    <div class="flex flex-col gap-1.5">
                        <label for="valor_basico" class="text-caption font-bold uppercase tracking-wider text-muted">Valor da Assinatura Básica:</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-muted font-bold text-label-md">R$</span>
                            <input id="valor_basico" name="valor_basico" type="text" value="<?= number_format($metricas['valor_basico'], 2, '.', '') ?>" required class="w-full rounded-md border border-input bg-surface pl-10 pr-3 py-2.5 text-label-md font-bold text-text focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="valor_essencial" class="text-caption font-bold uppercase tracking-wider text-muted">⭐ Valor da Assinatura Essencial:</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-muted font-bold text-label-md">R$</span>
                            <input id="valor_essencial" name="valor_essencial" type="text" value="<?= number_format($metricas['valor_essencial'], 2, '.', '') ?>" required class="w-full rounded-md border border-input bg-surface pl-10 pr-3 py-2.5 text-label-md font-bold text-text focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <p class="text-xs text-muted mt-1">Os novos preços serão aplicados para novas contratações e renovações.</p>

                    <button type="submit" class="mt-2 w-full flex items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary/90 text-on-primary font-bold py-3 px-4 shadow-sm transition-colors text-label-md">
                        <?= icon('save', ['class' => 'size-4']) ?>
                        <span>Salvar Preços</span>
                    </button>
                </form>
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
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
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
                labels: ['PIX', 'Manual'],
                datasets: [{
                    data: [<?= max(1, (int)$metricas['forma_pix_percent']) ?>, <?= (int)$metricas['forma_manual_percent'] ?>],
                    backgroundColor: ['#10b981', '#0ea5e9'],
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
