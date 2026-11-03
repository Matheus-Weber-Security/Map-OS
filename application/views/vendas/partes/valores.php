<?php
/**
 * Resumo financeiro da venda (Subtotal, Desconto e Total).
 *
 * @var object $venda
 * @var array{produtos: float, servicos: float, bruto: float, desconto: float, total: float, tipo: string|null, informado: float, descontoItens: float} $totais
 * @var array<string, bool> $pode
 */
$temDesconto = (float) ($totais['desconto'] ?? 0) > 0;
?>
<section class="flex flex-1 flex-col justify-between rounded-xl border border-border bg-surface p-4 sm:p-6 shadow-xs" aria-labelledby="secao-valores">
    <div>
        <div class="flex items-center justify-between gap-2">
            <h2 id="secao-valores" class="text-heading-sm text-text">Valores</h2>
            <?php if ($temDesconto) { ?>
                <?= component('pill-status', ['label' => 'Desconto aplicado', 'variant' => 'success']) ?>
            <?php } ?>
        </div>

        <dl class="mt-4 flex flex-col gap-2.5 text-body-md">
            <div class="flex items-center justify-between">
                <dt class="text-muted">Subtotal</dt>
                <dd class="tabular-nums font-medium text-text"><?= e(dinheiro($totais['produtos'])) ?></dd>
            </div>

            <div class="flex items-center justify-between">
                <dt class="flex items-center gap-1.5 text-muted">
                    <span>Desconto</span>
                    <?php if ($temDesconto && $totais['tipo'] === 'porcento') { ?>
                        <span class="rounded-xs bg-success-soft px-1.5 py-0.5 text-micro-cap font-semibold text-success-ink">
                            <?= e(rtrim(rtrim(number_format((float) $totais['informado'], 2, ',', ''), '0'), ',')) ?>%
                        </span>
                    <?php } ?>
                </dt>
                <dd class="tabular-nums font-medium <?= $temDesconto ? 'text-success-ink' : 'text-muted' ?>">
                    <?= $temDesconto ? '- ' . e(dinheiro($totais['desconto'])) : 'R$ 0,00' ?>
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 border-t border-border pt-3">
        <div class="flex items-baseline justify-between">
            <span class="text-label-md font-semibold text-text">Valor Total</span>
            <span class="font-display text-heading-md font-bold tabular-nums text-text"><?= e(dinheiro($totais['total'])) ?></span>
        </div>
    </div>
</section>
