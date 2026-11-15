<?php
/**
 * Pagamento da OS / Venda na área do cliente (#2848):
 * - PIX: QR Code e código copia e cola.
 * - Boleto, Cartão de Crédito/Débito, Dinheiro: dados e orientações da forma escolhida.
 *
 * @var object|null $os
 * @var array{payload: string, qr: string|null, chave: string}|null $pix
 * @var array $totais
 * @var object|null $cobranca
 */
$os = $os ?? null;
$cobranca = $cobranca ?? null;
$formaPagamento = 'pix';
if ($os !== null) {
    $formaPagamento = strtolower(trim((string) ($os->forma_pagamento ?? 'pix')));
    if (! defined('OS_FORMAS_PAGAMENTO_OPCOES') || ! array_key_exists($formaPagamento, OS_FORMAS_PAGAMENTO_OPCOES)) {
        $formaPagamento = 'pix';
    }
}

if ($formaPagamento === 'pix') {
    if ($pix === null) {
        return;
    }
    ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6" aria-labelledby="secao-pix" <?= js_module('conecte/copiar') ?>>
        <h2 id="secao-pix" class="text-heading-sm text-text">Pagar com PIX</h2>
        <p class="mt-0.5 text-caption text-muted">Leia o QR Code no app do banco ou copie o código.</p>
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
            <?php if (! empty($pix['qr'])) { ?>
                <img src="<?= e($pix['qr']) ?>" alt="<?= e('QR Code do PIX de ' . dinheiro($totais['total'])) ?>" class="size-44 shrink-0 self-center rounded-md border border-border bg-field p-2 sm:self-start">
            <?php } ?>
            <div class="flex min-w-0 flex-1 flex-col gap-3">
                <dl class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-caption text-muted">Valor</dt>
                        <dd class="text-body-md tabular-nums text-text"><?= e(dinheiro($totais['total'])) ?></dd>
                    </div>
                    <div>
                        <dt class="text-caption text-muted">Chave PIX</dt>
                        <dd class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($pix['chave']) ?></dd>
                    </div>
                </dl>
                <?= component('input', [
                    'name' => 'pix_copia_cola',
                    'id' => 'pix-copia-cola',
                    'label' => 'PIX copia e cola',
                    'value' => $pix['payload'],
                    'readonly' => true,
                    'class' => 'font-mono text-caption',
                ]) ?>
                <div>
                    <?= component('button', ['label' => 'Copiar código', 'icon' => 'copy', 'variant' => 'outline', 'attrs' => ['data-copiar' => 'pix-copia-cola']]) ?>
                </div>
            </div>
        </div>
    </section>
    <?php
} else {
    $infoForma = OS_FORMAS_PAGAMENTO_OPCOES[$formaPagamento];
    ?>
    <section class="rounded-xl border border-border bg-surface p-4 sm:p-6" aria-labelledby="secao-forma-pagamento" <?= js_module('conecte/copiar') ?>>
        <h2 id="secao-forma-pagamento" class="text-heading-sm text-text">Forma de pagamento</h2>
        <p class="mt-0.5 text-caption text-muted">Condição para liquidação dos serviços e produtos desta OS.</p>

        <div class="mt-4 rounded-xl border border-border bg-surface-subtle p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface border border-border text-text">
                        <?= icon($infoForma['icone'], ['class' => 'size-5']) ?>
                    </span>
                    <div>
                        <h3 class="text-body-md font-medium text-text"><?= e($infoForma['titulo']) ?></h3>
                        <p class="mt-1 text-body-sm text-text"><?= e($infoForma['descricao']) ?></p>
                        <?php if (! empty($infoForma['detalhe'])) { ?>
                            <p class="mt-1 text-caption text-muted"><?= e($infoForma['detalhe']) ?></p>
                        <?php } ?>
                    </div>
                </div>
                <div class="sm:text-right shrink-0">
                    <span class="text-caption text-muted block">Valor total</span>
                    <span class="text-heading-sm font-semibold tabular-nums text-text"><?= e(dinheiro($totais['total'])) ?></span>
                </div>
            </div>

            <?php if ($formaPagamento === 'boleto' && $cobranca !== null) { ?>
                <?php
                if (! function_exists('cobrancaUrlSegura')) {
                    get_instance()->load->helper('financeiro');
                }
                $linkBoleto = function_exists('cobrancaUrlSegura')
                    ? (cobrancaUrlSegura($cobranca->link ?? null) ?? cobrancaUrlSegura($cobranca->pdf ?? null))
                    : null;
                ?>
                <?php if ($linkBoleto !== null || ! empty($cobranca->barcode)) { ?>
                    <div class="mt-4 border-t border-border pt-4 flex flex-col gap-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-caption font-medium text-text">Boleto emitido via <?= e($cobranca->payment_gateway ?? 'Gateway') ?></span>
                            <?php if ($linkBoleto !== null) { ?>
                                <?= component('button', [
                                    'label' => 'Visualizar / Imprimir Boleto',
                                    'icon' => 'barcode',
                                    'size' => 'sm',
                                    'variant' => 'outline',
                                    'href' => $linkBoleto,
                                    'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                                ]) ?>
                            <?php } ?>
                        </div>
                        <?php if (! empty($cobranca->barcode)) { ?>
                            <?= component('input', [
                                'name' => 'barcode_boleto',
                                'id' => 'conecte-boleto-barcode',
                                'label' => 'Linha digitável do boleto',
                                'value' => $cobranca->barcode,
                                'readonly' => true,
                                'class' => 'font-mono text-caption',
                            ]) ?>
                            <div>
                                <?= component('button', ['label' => 'Copiar linha digitável', 'icon' => 'copy', 'variant' => 'outline', 'attrs' => ['data-copiar' => 'conecte-boleto-barcode']]) ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </section>
    <?php
}
