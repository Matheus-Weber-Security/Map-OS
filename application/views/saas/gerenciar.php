<div class="flex flex-col gap-6 pt-2 pb-8">
    <!-- Cabeçalho -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Gerenciar SaaS</h1>
            <p class="text-caption text-muted">Acompanhe métricas financeiras, assinantes ativos e precificação dos planos.</p>
        </div>
        <div>
            <?= component('button', [
                'label' => 'Ver todos os assinantes',
                'icon' => 'users',
                'variant' => 'outline',
                'href' => site_url('saas/assinantes'),
            ]) ?>
        </div>
    </header>

    <!-- KPIs Executivos do Topo -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Assinantes Ativos -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Assinantes Ativos</span>
                <span class="p-2 rounded-lg bg-surface-subtle text-primary">
                    <?= icon('users', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-heading-xl font-bold text-text"><?= (int) $metricas['assinantes_ativos'] ?></div>
                <div class="mt-1 flex items-center gap-1">
                    <?= component('pill-status', ['label' => '+' . (int) $metricas['novos_assinantes'] . ' este mês', 'variant' => 'success']) ?>
                </div>
            </div>
        </div>

        <!-- 2. Novos Assinantes -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Novos Assinantes</span>
                <span class="p-2 rounded-lg bg-surface-subtle text-muted">
                    <?= icon('plus', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-heading-xl font-bold text-text"><?= (int) $metricas['novos_assinantes'] ?></div>
                <div class="mt-1 text-caption text-muted">
                    <span>Últimos 30 dias</span>
                </div>
            </div>
        </div>

        <!-- 3. Receita Mensal (MRR) -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Receita Mensal (MRR)</span>
                <span class="p-2 rounded-lg bg-surface-subtle text-success-ink">
                    <?= icon('chart-column', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-heading-xl font-bold text-text">
                    R$ <?= number_format($metricas['mrr'], 2, ',', '.') ?>
                </div>
                <div class="mt-1 flex items-center gap-1">
                    <?= component('pill-status', ['label' => 'Ativa', 'variant' => 'success']) ?>
                </div>
            </div>
        </div>

        <!-- 4. Cancelamentos (Churn) -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Cancelamentos</span>
                <span class="p-2 rounded-lg bg-surface-subtle text-danger-ink">
                    <?= icon('user', ['class' => 'size-5']) ?>
                </span>
            </div>
            <div class="mt-4">
                <div class="font-display text-heading-xl font-bold text-text"><?= (int) $metricas['churn_percent'] ?>%</div>
                <div class="mt-1 text-caption text-muted font-medium">
                    <span>Taxa de churn</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Centrais -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Evolução da Receita -->
        <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Evolução da Receita</h3>
                <span class="text-caption text-muted">Últimos meses</span>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-evolucao"></canvas>
            </div>
        </div>

        <!-- Formas de Pagamento -->
        <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Formas de Pagamento</h3>
                <span class="text-caption text-muted">Distribuição</span>
            </div>
            <div class="h-64 relative w-full flex items-center justify-center">
                <canvas id="chart-pagamento"></canvas>
            </div>
            <div class="mt-4 flex items-center justify-around text-caption font-semibold">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-primary"></span>
                    <span class="text-text">Manual (100%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-text">PIX (0%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé: Últimos Assinantes e Gerenciar Preços -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tabela: Últimos Assinantes -->
        <div class="lg:col-span-2 rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-border">
                <h3 class="font-display text-heading-sm font-bold text-text">Últimos Assinantes</h3>
                <?= component('button', [
                    'label' => 'Ver todos',
                    'icon' => 'arrow-right',
                    'variant' => 'link',
                    'href' => site_url('saas/assinantes'),
                ]) ?>
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
                    <tbody class="divide-y divide-border">
                        <?php if (empty($metricas['ultimos_assinantes'])) { ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted">Nenhum pagamento registrado ainda.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($metricas['ultimos_assinantes'] as $ass) { ?>
                                <tr class="hover:bg-surface-subtle transition-colors">
                                    <td class="py-3 px-3 font-medium text-text"><?= e($ass->nome) ?></td>
                                    <td class="py-3 px-3 text-right font-bold text-text">R$ <?= number_format((float) $ass->valor, 2, ',', '.') ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <?= component('pill-status', ['label' => $ass->pagamento, 'variant' => 'neutral']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-right text-caption text-muted">
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
        <div class="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-2 mb-4 pb-2 border-b border-border">
                    <?= icon('settings', ['class' => 'size-5 text-muted']) ?>
                    <h3 class="font-display text-heading-sm font-bold text-text">Preço dos Planos</h3>
                </div>

                <form method="post" action="<?= site_url('saas/salvarPrecos') ?>" class="flex flex-col gap-4">
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                    <?= component('input', [
                        'name' => 'valor_basico',
                        'label' => 'Valor da Assinatura Básica (R$)',
                        'type' => 'text',
                        'value' => number_format($metricas['valor_basico'], 2, '.', ''),
                        'required' => true,
                    ]) ?>

                    <?= component('input', [
                        'name' => 'valor_essencial',
                        'label' => 'Valor da Assinatura Essencial (R$)',
                        'type' => 'text',
                        'value' => number_format($metricas['valor_essencial'], 2, '.', ''),
                        'required' => true,
                    ]) ?>

                    <p class="text-caption text-muted">Os novos preços serão aplicados para novas contratações e renovações.</p>

                    <?= component('button', [
                        'label' => 'Salvar Preços',
                        'icon' => 'save',
                        'type' => 'submit',
                        'class' => 'w-full',
                    ]) ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctxEvolucao = document.getElementById('chart-evolucao')?.getContext('2d');
    if (ctxEvolucao) {
        new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: ['Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov'],
                datasets: [{
                    label: 'Receita (R$)',
                    data: [0, 0, 0, 0, 0, <?= (float) $metricas['mrr'] ?>],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: 4,
                    pointBackgroundColor: '#f97316',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    const ctxPagamento = document.getElementById('chart-pagamento')?.getContext('2d');
    if (ctxPagamento) {
        new Chart(ctxPagamento, {
            type: 'doughnut',
            data: {
                labels: ['Manual', 'PIX'],
                datasets: [{
                    data: [100, 0],
                    backgroundColor: ['#f97316', '#10b981'],
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
