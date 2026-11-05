<?php
/**
 * Dashboard de Gestão SaaS no padrão oficial Map-OS v5 (DESIGN.md).
 *
 * Utiliza o componente kpi-card, cards padronizados com tokens de tema,
 * e formulários acessíveis integrados à paleta de design do sistema.
 *
 * @var array $metricas
 */
?>
<?= page_data('saas-dados', [
    'mrr' => (float) $metricas['mrr'],
    'pix' => (int) $metricas['forma_pix_percent'],
    'manual' => (int) $metricas['forma_manual_percent'],
]) ?>

<div class="flex flex-col gap-6 pt-2 pb-8" <?= js_module('saas/gerenciar') ?> data-url-alternar="<?= e(site_url('saas/alternarFormaPagamento')) ?>">
    <!-- Cabeçalho (Padrão Oficial Map-OS v5) -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Gerenciar SaaS</h1>
            <p class="text-caption text-muted">Acompanhe métricas financeiras, evolução da receita e precificação da assinatura.</p>
        </div>
        <?php if (! empty($podeAssinantes)) { ?>
            <div>
                <?= component('button', [
                    'label' => 'Ver Todos os Assinantes',
                    'icon' => 'users',
                    'variant' => 'outline',
                    'href' => site_url('saas/assinantes'),
                ]) ?>
            </div>
        <?php } ?>
    </header>

    <!-- KPIs Executivos do Topo (kpi-card) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?= component('kpi-card', [
            'label' => 'Assinantes Ativos',
            'value' => (int) $metricas['assinantes_ativos'],
            'icon' => 'users',
            'accent' => 'info',
            'caption' => html_purificado('<span class="font-semibold text-success-ink">+' . (int) $metricas['novos_assinantes'] . '</span> este mês'),
        ]) ?>

        <?= component('kpi-card', [
            'label' => 'Novos Assinantes',
            'value' => (int) $metricas['novos_assinantes'],
            'icon' => 'circle-user',
            'accent' => 'progress',
            'caption' => 'Últimos 30 dias',
        ]) ?>

        <?= component('kpi-card', [
            'label' => 'Receita Mensal (MRR)',
            'value' => 'R$ ' . number_format($metricas['mrr'], 2, ',', '.'),
            'icon' => 'circle-dollar-sign',
            'accent' => 'success',
            'caption' => html_purificado('<span class="font-semibold text-success-ink">+0%</span> este mês'),
        ]) ?>

        <?= component('kpi-card', [
            'label' => 'Cancelamentos',
            'value' => (int) $metricas['churn_percent'] . '%',
            'icon' => 'triangle-alert',
            'accent' => 'warning',
            'caption' => 'Taxa de churn ' . (int) $metricas['churn_percent'] . '%',
        ]) ?>
    </div>

    <!-- Gráficos Centrais -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Evolução da Receita -->
        <section class="flex flex-col justify-between rounded-xl border border-border bg-surface p-5 shadow-card lg:col-span-2">
            <div class="mb-4 flex items-center justify-between border-b border-border pb-3">
                <h2 class="font-display text-base font-semibold text-text">Evolução da Receita</h2>
                <span class="text-caption text-muted">Últimos 6 meses</span>
            </div>
            <div class="relative flex h-64 w-full items-center justify-center">
                <canvas id="chart-evolucao"></canvas>
            </div>
        </section>

        <!-- Formas de Pagamento -->
        <section class="flex flex-col justify-between rounded-xl border border-border bg-surface p-5 shadow-card">
            <div class="mb-4 flex items-center justify-between border-b border-border pb-3">
                <h2 class="font-display text-base font-semibold text-text">Formas de Pagamento</h2>
                <span class="text-caption text-muted">Distribuição</span>
            </div>
            <div class="relative flex h-64 w-full items-center justify-center">
                <canvas id="chart-pagamento"></canvas>
            </div>
            <div class="mt-4 flex items-center justify-around text-xs font-semibold text-muted">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-primary"></span>
                    <span>Manual (<?= (int) $metricas['forma_manual_percent'] ?>%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full bg-info"></span>
                    <span>PIX (<?= (int) $metricas['forma_pix_percent'] ?>%)</span>
                </div>
            </div>
        </section>
    </div>

    <!-- Rodapé: Últimos Assinantes e Gerenciar Preços -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Tabela: Últimos Assinantes -->
        <section class="flex flex-col justify-between rounded-xl border border-border bg-surface p-5 shadow-card lg:col-span-2">
            <div class="mb-4 flex items-center justify-between border-b border-border pb-3">
                <h2 class="font-display text-base font-semibold text-text">Últimos Assinantes</h2>
                <?php if (! empty($podeAssinantes)) { ?>
                    <?= component('button', [
                        'label' => 'Ver todos os assinantes',
                        'variant' => 'ghost',
                        'size' => 'sm',
                        'href' => site_url('saas/assinantes'),
                    ]) ?>
                <?php } ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-2.5 px-3">Nome</th>
                            <th class="py-2.5 px-3 text-right">Valor</th>
                            <th class="py-2.5 px-3 text-center">Pagamento</th>
                            <th class="py-2.5 px-3 text-right">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php if (empty($metricas['ultimos_assinantes'])) { ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-caption text-muted font-medium">Nenhum pagamento registrado ainda.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($metricas['ultimos_assinantes'] as $ass) { ?>
                                <tr class="hover:bg-surface-subtle transition-colors">
                                    <td class="py-3 px-3 font-semibold text-text"><?= e($ass->nome) ?></td>
                                    <td class="py-3 px-3 text-right font-medium text-success-ink">R$ <?= number_format((float) $ass->valor, 2, ',', '.') ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <?= component('pill-status', [
                                            'label' => $ass->pagamento,
                                            'variant' => $ass->pagamento === 'PIX' ? 'success' : 'neutral',
                                        ]) ?>
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
        </section>

        <!-- Card: Gerenciar Preço da Assinatura -->
        <section class="flex flex-col justify-between rounded-xl border border-border bg-surface p-5 shadow-card">
            <div>
                <div class="mb-4 flex items-center justify-between border-b border-border pb-3">
                    <h2 class="font-display text-base font-semibold text-text">Gerenciar Preço da Assinatura</h2>
                </div>

                <form method="post" action="<?= site_url('saas/salvarPrecos') ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

                    <?= component('input', [
                        'name' => 'valor_assinatura',
                        'label' => 'Valor da Assinatura Mensal (R$)',
                        'type' => 'text',
                        'value' => number_format($metricas['valor_assinatura'] ?? $metricas['valor_essencial'], 2, ',', '.'),
                        'required' => true,
                        'disabled' => empty($podeAlterarPreco),
                        'class' => 'tabular-nums',
                        'attrs' => empty($podeAlterarPreco) ? [] : ['data-mascara' => 'dinheiro', 'inputmode' => 'decimal', 'maxlength' => 20, 'data-msg-vazio' => 'Informe o valor.'],
                    ]) ?>

                    <?php if (! empty($podeAlterarPreco)) { ?>
                        <?= component('button', [
                            'label' => 'Salvar Preço da Assinatura',
                            'icon' => 'save',
                            'type' => 'submit',
                            'class' => 'mt-2 w-full',
                            'attrs' => ['data-rotulo-carregando' => 'Salvando...'],
                        ]) ?>
                    <?php } else { ?>
                        <div class="mt-2 rounded-lg border border-border bg-surface-subtle p-3 text-center text-caption text-muted">
                            Visualização apenas: seu perfil de acesso não possui permissão para alterar o preço da assinatura.
                        </div>
                    <?php } ?>
                </form>

                <p class="text-caption text-muted mt-3 text-center">O novo preço será aplicado para novas contratações e renovações.</p>
            </div>
        </section>
    </div>

    <!-- Card: Formas de Pagamento para Assinatura (+30 dias) -->
    <section class="flex flex-col rounded-xl border border-border bg-surface p-5 shadow-card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
            <div>
                <h2 class="font-display text-base font-semibold text-text flex items-center gap-2">
                    <?= icon('banknote', ['class' => 'size-5 text-primary']) ?>
                    <span>Formas de Pagamento da Assinatura</span>
                </h2>
                <p class="text-caption text-muted">Gerencie os métodos que as empresas utilizam para comprar mais 30 dias na tela de assinaturas.</p>
            </div>
            <div>
                <?php
                $ativasCount = 0;
                if (! empty($formasPagamento)) {
                    foreach ($formasPagamento as $f) {
                        if (! empty($f->ativo)) {
                            $ativasCount++;
                        }
                    }
                }
                ?>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-subtle border border-border px-3 py-1 text-xs font-semibold text-text">
                    <span id="indicador-bolinha" class="size-2 rounded-full <?= $ativasCount > 0 ? 'bg-success' : 'bg-neutral' ?>"></span>
                    <span id="contador-formas-ativas"><?= (int) $ativasCount ?> <?= $ativasCount === 1 ? 'forma ativa' : 'formas ativas' ?></span>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm" id="tabela-formas-pagamento">
                <thead>
                    <tr class="border-b border-border text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-2.5 px-3">Forma de Pagamento</th>
                        <th class="py-2.5 px-3">Provedor / Gateway</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-center">Ativar</th>
                        <th class="py-2.5 px-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php if (empty($formasPagamento)) { ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-caption text-muted font-medium">Nenhuma forma de pagamento cadastrada.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($formasPagamento as $forma) { ?>
                            <tr class="hover:bg-surface-subtle transition-colors" data-forma-id="<?= (int) $forma->id ?>">
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 rounded-lg bg-surface-subtle border border-border text-primary shrink-0">
                                            <?= icon($forma->icone ?: 'banknote', ['class' => 'size-5']) ?>
                                        </div>
                                        <div>
                                            <span class="font-semibold text-text block"><?= e($forma->nome) ?></span>
                                            <span class="text-caption text-muted block"><?= e($forma->descricao) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="font-medium text-text capitalize">
                                        <?= e($forma->provedor === 'mercadopago' ? 'Mercado Pago' : ($forma->provedor === 'gerencianet' ? 'Efí / Gerencianet' : ($forma->provedor === 'asaas' ? 'Asaas' : $forma->provedor))) ?>
                                    </span>
                                    <?php if (! empty($forma->config['usar_env'])) { ?>
                                        <span class="text-caption text-muted block">Credenciais globais (.env)</span>
                                    <?php } ?>
                                </td>
                                <td class="py-3 px-3 text-center status-col">
                                    <?= component('pill-status', [
                                        'label' => ! empty($forma->ativo) ? 'Ativo' : 'Inativo',
                                        'variant' => ! empty($forma->ativo) ? 'success' : 'neutral',
                                    ]) ?>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <label class="inline-flex cursor-pointer items-center justify-center">
                                        <input type="checkbox"
                                               role="switch"
                                               class="forma-toggle peer h-6 w-10 cursor-pointer appearance-none rounded-full bg-input transition-colors checked:bg-primary focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-60"
                                               data-id="<?= (int) $forma->id ?>"
                                               data-nome="<?= e($forma->nome) ?>"
                                               <?= ! empty($forma->ativo) ? 'checked' : '' ?>
                                               <?= empty($podeAlterarPreco) ? 'disabled' : '' ?>>
                                    </label>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <?php if (! empty($podeAlterarPreco)) { ?>
                                        <?= component('button', [
                                            'label' => 'Editar',
                                            'icon' => 'pencil',
                                            'variant' => 'ghost',
                                            'size' => 'sm',
                                            'href' => site_url('saas/editarFormaPagamento/' . $forma->id),
                                        ]) ?>
                                    <?php } else { ?>
                                        <span class="text-caption text-muted">-</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
