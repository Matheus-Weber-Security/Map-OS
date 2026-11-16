<?php
/**
 * Resultado detalhado da importação de clientes e fornecedores via CSV (Etapa 3).
 *
 * @var array{
 *     total: int,
 *     importados: int,
 *     atualizados: int,
 *     substituidos: int,
 *     sucesso_total: int,
 *     ignorados: int,
 *     erros: int,
 *     ocorrencias: list<array{
 *         linha: int,
 *         item: string,
 *         identificador: string,
 *         tipo: 'duplicado'|'ignorado'|'erro',
 *         motivo: string
 *     }>
 * } $resultado
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$total = (int) ($resultado['total'] ?? 0);
$sucessoTotal = (int) ($resultado['sucesso_total'] ?? 0);
$importados = (int) ($resultado['importados'] ?? 0);
$atualizados = (int) ($resultado['atualizados'] ?? 0);
$substituidos = (int) ($resultado['substituidos'] ?? 0);
$ignorados = (int) ($resultado['ignorados'] ?? 0);
$erros = (int) ($resultado['erros'] ?? 0);
$ocorrencias = (array) ($resultado['ocorrencias'] ?? []);

$legendaSucesso = "{$importados} novo(s) cadastro(s)";
if ($atualizados > 0) {
    $legendaSucesso .= " · {$atualizados} mesclado(s)";
}
if ($substituidos > 0) {
    $legendaSucesso .= " · {$substituidos} substituído(s)";
}
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= e(site_url('clientes')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                    <?= icon('chevron-left', ['class' => 'size-4']) ?> Ir para Clientes
                </a>
            </div>
            <h1 class="font-display text-heading-xl text-text">Resultado da importação</h1>
            <p class="text-caption text-muted">
                <?= e(number_format($total, 0, ',', '.')) ?> linha(s) processada(s) do arquivo CSV.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?= component('button', [
                'label' => 'Importar outro arquivo',
                'icon' => 'upload',
                'variant' => 'outline',
                'href' => site_url('clientes/importar'),
            ]) ?>
            <?= component('button', [
                'label' => 'Ver clientes cadastrados',
                'icon' => 'chevron-right',
                'variant' => 'primary',
                'href' => site_url('clientes'),
            ]) ?>
        </div>
    </header>

    <!-- 3 Cards de Resumo -->
    <div class="grid gap-4 sm:grid-cols-3">
        <?= component('kpi-card', [
            'label' => 'Importados com sucesso',
            'value' => $sucessoTotal,
            'icon' => 'circle-check',
            'accent' => 'success',
            'caption' => $legendaSucesso,
        ]) ?>

        <?= component('kpi-card', [
            'label' => 'Repetidos / Ignorados',
            'value' => $ignorados,
            'icon' => 'copy',
            'accent' => $ignorados > 0 ? 'warning' : 'neutral',
            'caption' => 'Regra de duplicidade ou filtro',
        ]) ?>

        <?= component('kpi-card', [
            'label' => 'Falhas / Erros',
            'value' => $erros,
            'icon' => 'circle-alert',
            'accent' => $erros > 0 ? 'warning' : 'neutral',
            'caption' => 'Campos vazios ou inválidos',
        ]) ?>
    </div>

    <!-- Tabela Detalhada de Ocorrências e Erros -->
    <?php if ($ocorrencias !== []) { ?>
        <section class="<?= e($caixa) ?>">
            <div class="mb-4">
                <h2 class="text-heading-sm text-text">Registros com ocorrências ou não importados (<?= e(count($ocorrencias)) ?>)</h2>
                <p class="text-caption text-muted">Confira abaixo os registros que não puderam ser importados como novos e o motivo exato de cada um.</p>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-body-sm text-text">
                    <thead class="bg-surface-subtle text-caption font-medium uppercase text-muted">
                        <tr>
                            <th class="p-3 border-b border-border w-16 text-center">Linha</th>
                            <th class="p-3 border-b border-border">Nome / Razão Social</th>
                            <th class="p-3 border-b border-border whitespace-nowrap">Documento / E-mail</th>
                            <th class="p-3 border-b border-border whitespace-nowrap">Status</th>
                            <th class="p-3 border-b border-border">Motivo detalhado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($ocorrencias as $oc) {
                            $variantePill = $oc['tipo'] === 'erro' ? 'danger' : ($oc['tipo'] === 'duplicado' ? 'warning' : 'neutral');
                            $rotuloPill = $oc['tipo'] === 'erro' ? 'Erro' : ($oc['tipo'] === 'duplicado' ? 'Duplicado' : 'Ignorado');
                        ?>
                            <tr class="hover:bg-surface-subtle/50">
                                <td class="p-3 text-center text-muted font-mono font-medium">#<?= e($oc['linha']) ?></td>
                                <td class="p-3 font-medium text-text"><?= e($oc['item']) ?></td>
                                <td class="p-3 text-muted font-mono whitespace-nowrap"><?= e($oc['identificador']) ?></td>
                                <td class="p-3 whitespace-nowrap">
                                    <?= component('pill-status', ['label' => $rotuloPill, 'variant' => $variantePill]) ?>
                                </td>
                                <td class="p-3 text-caption text-muted leading-relaxed"><?= e($oc['motivo']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } else { ?>
        <?= component('empty-state', [
            'title' => 'Importação 100% concluída!',
            'message' => 'Todos os registros da planilha foram processados com sucesso. Nenhum erro ou cadastro duplicado foi encontrado.',
            'icon' => 'circle-check',
            'action' => component('button', ['label' => 'Ver clientes cadastrados', 'href' => site_url('clientes')]),
            'class' => $caixa,
        ]) ?>
    <?php } ?>

    <div class="flex justify-end gap-2">
        <?= component('button', [
            'label' => 'Importar outro arquivo',
            'icon' => 'upload',
            'variant' => 'outline',
            'href' => site_url('clientes/importar'),
        ]) ?>
        <?= component('button', [
            'label' => 'Ver clientes cadastrados',
            'icon' => 'chevron-right',
            'variant' => 'primary',
            'href' => site_url('clientes'),
        ]) ?>
    </div>
</div>
