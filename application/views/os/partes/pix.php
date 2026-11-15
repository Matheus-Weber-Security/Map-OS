<?php
/**
 * Forma de pagamento da OS (#2842): exibe PIX (QR Code e código copia e cola),
 * Boleto Bancário, Cartão de Crédito/Débito ou Dinheiro, permitindo alteração rápida.
 *
 * @var object $os
 * @var array $totais
 * @var array{payload: string, qr: string|null, chave: string}|null $pix
 * @var array{telefone: string, texto: string}|null $whatsapp
 * @var object|null $cobranca
 * @var array $pode
 */
$formaPagamento = strtolower(trim((string) ($os->forma_pagamento ?? 'pix')));
if (! array_key_exists($formaPagamento, OS_FORMAS_PAGAMENTO_OPCOES)) {
    $formaPagamento = 'pix';
}
$infoForma = OS_FORMAS_PAGAMENTO_OPCOES[$formaPagamento];
$podeEditar = ($pode['editar'] ?? false) && ((int) ($os->faturado ?? 0) === 0) && (($os->status ?? '') !== 'Cancelado');
?>
<section class="rounded-xl border border-border bg-surface p-4 sm:p-6" aria-labelledby="secao-forma-pagamento">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="secao-forma-pagamento" class="text-heading-sm text-text">Forma de pagamento</h2>
            <p class="text-caption text-muted">Condição combinada para liquidação dos serviços e produtos desta OS.</p>
        </div>

        <?php if ($podeEditar) { ?>
            <form method="post" action="<?= e(site_url('os/alterarFormaPagamento')) ?>" novalidate
                  class="flex items-center gap-2" data-os-acao="pagamento">
                <input type="hidden" name="idOs" value="<?= e((int) $os->idOs) ?>">
                <div class="w-48 sm:w-52">
                    <?= component('select', [
                        'name' => 'forma_pagamento',
                        'label' => 'Forma de pagamento',
                        'hide_label' => true,
                        'options' => array_combine(
                            array_keys(OS_FORMAS_PAGAMENTO_OPCOES),
                            array_column(OS_FORMAS_PAGAMENTO_OPCOES, 'label')
                        ),
                        'selected' => $formaPagamento,
                    ]) ?>
                </div>
                <?= component('button', [
                    'label' => 'Alterar',
                    'size' => 'sm',
                    'variant' => 'outline',
                    'type' => 'submit',
                ]) ?>
            </form>
        <?php } ?>
    </div>

    <div class="mt-4 border-t border-border pt-4">
        <?php if ($formaPagamento === 'pix') { ?>
            <?php if ($pix !== null) { ?>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <?php if (! empty($pix['qr'])) { ?>
                        <img src="<?= e($pix['qr']) ?>" alt="<?= e('QR Code do PIX de ' . dinheiro($totais['total'])) ?>" class="size-44 shrink-0 rounded-md border border-border bg-field p-2">
                    <?php } ?>
                    <div class="flex min-w-0 flex-1 flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <span class="flex size-7 items-center justify-center rounded-lg bg-surface-subtle text-text">
                                <?= icon($infoForma['icone'], ['class' => 'size-4']) ?>
                            </span>
                            <span class="text-body-md font-medium text-text"><?= e($infoForma['titulo']) ?></span>
                        </div>
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-caption text-muted">Chave PIX</dt>
                                <dd class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($pix['chave']) ?></dd>
                            </div>
                            <div>
                                <dt class="text-caption text-muted">Valor</dt>
                                <dd class="text-body-md tabular-nums text-text"><?= e(dinheiro($totais['total'])) ?></dd>
                            </div>
                        </dl>
                        <?= component('input', [
                            'name' => 'pix_copia_cola',
                            'id' => 'os-pix-payload',
                            'label' => 'PIX copia e cola',
                            'value' => $pix['payload'],
                            'readonly' => true,
                            'help' => 'O cliente cola este código no app do banco.',
                            'class' => 'font-mono text-caption',
                        ]) ?>
                        <div class="flex flex-wrap gap-2">
                            <?= component('button', ['label' => 'Copiar código', 'icon' => 'copy', 'variant' => 'outline', 'attrs' => ['data-copiar' => 'os-pix-payload']]) ?>
                            <?php if ($whatsapp !== null) { ?>
                                <?= component('button', [
                                    'label' => 'Enviar pelo WhatsApp',
                                    'icon' => 'message-circle',
                                    'variant' => 'ghost',
                                    'href' => 'https://wa.me/' . $whatsapp['telefone'] . '?text=' . rawurlencode($pix['payload']),
                                    'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                                ]) ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="rounded-lg border border-border bg-surface-subtle p-4">
                    <p class="text-body-md font-medium text-text">PIX não configurado</p>
                    <p class="mt-1 text-body-sm text-muted">Configure sua Chave PIX em Configurações > Emitente ou Sistema para gerar o QR Code nesta OS.</p>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="rounded-xl border border-border bg-surface-subtle p-4 sm:p-5">
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
                                    'id' => 'os-boleto-barcode',
                                    'label' => 'Linha digitável do boleto',
                                    'value' => $cobranca->barcode,
                                    'readonly' => true,
                                    'class' => 'font-mono text-caption',
                                ]) ?>
                                <div>
                                    <?= component('button', ['label' => 'Copiar linha digitável', 'icon' => 'copy', 'variant' => 'outline', 'attrs' => ['data-copiar' => 'os-boleto-barcode']]) ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>
