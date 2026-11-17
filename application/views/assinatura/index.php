<?php
$formasAtivas = ! empty($formasPagamento) ? $formasPagamento : [];
if (empty($formasAtivas)) {
    $formasAtivas = [
        (object) [
            'codigo' => 'pix',
            'nome' => 'PIX Instantâneo',
            'icone' => 'qr-code',
            'descricao' => 'Compensação automática e liberação imediata',
        ],
    ];
}
$primeiraForma = $formasAtivas[0];

$formaCartaoCredito = null;
$formaCartaoDebito = null;
foreach ($formasAtivas as $f) {
    if ($f->codigo === 'cartao_credito') {
        $formaCartaoCredito = $f;
    } elseif ($f->codigo === 'cartao_debito') {
        $formaCartaoDebito = $f;
    }
}
$provedorCartao = $formaCartaoCredito ? ($formaCartaoCredito->provedor ?: 'mercadopago') : 'mercadopago';
$mpPublicKey = $formaCartaoCredito ? ($formaCartaoCredito->config['public_key'] ?? $formaCartaoCredito->config['mp_public_key'] ?? '') : '';
$efiPayeeCode = $formaCartaoCredito ? ($formaCartaoCredito->config['payee_code'] ?? $formaCartaoCredito->config['efi_payee_code'] ?? '') : '';
$efiAmbiente = $formaCartaoCredito ? ($formaCartaoCredito->config['ambiente'] ?? $formaCartaoCredito->config['efi_ambiente'] ?? 'producao') : 'producao';
$maxParcelas = $formaCartaoCredito ? (int) ($formaCartaoCredito->config['max_parcelas'] ?? 1) : 1;
?>
<?php if ($formaCartaoCredito && ! empty($formaCartaoCredito->ativo)) { ?>
    <?php if ($provedorCartao === 'mercadopago' && ! empty($mpPublicKey)) { ?>
        <script src="https://sdk.mercadopago.com/js/v2"></script>
    <?php } elseif ($provedorCartao === 'gerencianet' && ! empty($efiPayeeCode)) { ?>
        <script src="https://interfaces.efipay.com.br/token/api.min.js"></script>
    <?php } ?>
<?php } ?>
<div class="flex flex-col gap-6 pt-2 pb-8 max-w-4xl mx-auto">
    <!-- Cabeçalho -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-heading-xl text-text">Assinatura</h1>
            <p class="text-caption text-muted">Gerencie sua assinatura, vencimento e renovação.</p>
        </div>
    </header>

    <!-- Card Principal: Status da Assinatura -->
    <div class="rounded-xl border border-border bg-surface p-6 sm:p-8 shadow-2xs flex flex-col gap-6">
        <div class="flex items-center gap-2 pb-4 border-b border-border">
            <?= icon('banknote', ['class' => 'size-5 text-primary']) ?>
            <h2 class="font-display text-heading-sm font-semibold text-text">Status da Assinatura</h2>
        </div>

        <!-- Grade de Indicadores (4 Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Status -->
            <div class="rounded-lg border border-border bg-surface-subtle p-4 flex flex-col justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Status</span>
                <div class="mt-2">
                    <?php if (! empty($ehMaster)) { ?>
                        <?= component('pill-status', ['label' => 'Ativa (Master)', 'variant' => 'success']) ?>
                    <?php } elseif (! empty($situacaoAssinatura) && $situacaoAssinatura['status'] === 'carencia') { ?>
                        <?= component('pill-status', ['label' => 'Carência (' . $situacaoAssinatura['dias_carencia'] . 'd)', 'variant' => 'warning']) ?>
                    <?php } elseif (! empty($situacaoAssinatura) && $situacaoAssinatura['status'] === 'somente_leitura') { ?>
                        <?= component('pill-status', ['label' => 'Modo Consulta', 'variant' => 'danger']) ?>
                    <?php } elseif ($diasRestantes > 0) { ?>
                        <?= component('pill-status', ['label' => 'Ativa', 'variant' => 'success']) ?>
                    <?php } else { ?>
                        <?= component('pill-status', ['label' => 'Vencida', 'variant' => 'danger']) ?>
                    <?php } ?>
                </div>
            </div>

            <!-- 2. Dias Restantes -->
            <div class="rounded-lg border border-border bg-surface-subtle p-4 flex flex-col justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Dias Restantes</span>
                <div class="mt-2">
                    <?php if (! empty($ehMaster)) { ?>
                        <span class="font-display text-display-small font-bold text-success-ink">
                            Ilimitado
                        </span>
                    <?php } elseif (! empty($situacaoAssinatura) && $situacaoAssinatura['status'] === 'carencia') { ?>
                        <span class="font-display text-display-small font-bold text-warning-ink">
                            <?= (int) $situacaoAssinatura['dias_carencia'] ?> <?= $situacaoAssinatura['dias_carencia'] === 1 ? 'dia' : 'dias' ?> <span class="text-xs font-normal text-muted">(carência)</span>
                        </span>
                    <?php } else { ?>
                        <span class="font-display text-display-small font-bold <?= $diasRestantes > 0 ? 'text-success-ink' : 'text-danger-ink' ?>">
                            <?= (int) $diasRestantes ?> dias
                        </span>
                    <?php } ?>
                </div>
            </div>

            <!-- 3. Próximo Vencimento -->
            <div class="rounded-lg border border-border bg-surface-subtle p-4 flex flex-col justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Próximo Vencimento</span>
                <div class="mt-2">
                    <span class="font-display text-heading-sm font-bold text-text">
                        <?= ! empty($ehMaster) ? 'Vitalício' : (! empty($empresa->data_vencimento) ? e(date('d/m/Y', strtotime($empresa->data_vencimento))) : 'Não definido') ?>
                    </span>
                </div>
            </div>

            <!-- 4. Valor da Assinatura -->
            <div class="rounded-lg border border-border bg-surface-subtle p-4 flex flex-col justify-between">
                <span class="text-caption font-bold uppercase tracking-wider text-muted">Valor da Assinatura</span>
                <div class="mt-2 flex flex-col gap-1">
                    <?php if (! empty($ehMaster)) { ?>
                        <span class="text-success-ink font-bold text-heading-xs">Isento (Master)</span>
                    <?php } elseif (($tipoDesconto === 'valor' && $descontoValor > 0) || ($tipoDesconto === 'porcentagem' && $descontoPercentual > 0)) { ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="line-through text-muted text-caption">R$ <?= number_format($valorOriginal, 2, ',', '.') ?></span>
                            <?php
                            $rotuloBadge = ($tipoDesconto === 'valor')
                                ? ('R$ ' . number_format($descontoValor, 2, ',', '.') . ' OFF')
                                : (rtrim(rtrim(number_format($descontoPercentual, 2, ',', '.'), '0'), ',') . '% OFF');
                            ?>
                            <?= component('pill-status', ['label' => $rotuloBadge, 'variant' => 'warning']) ?>
                        </div>
                        <span class="text-success-ink font-bold text-heading-xs">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                        <?php if (! empty($descontoValidoAte)) { ?>
                            <span class="text-xs text-muted inline-flex items-center gap-1 mt-0.5">
                                <?= icon('info', ['class' => 'size-3.5 text-warning-ink']) ?>
                                Desconto válido até <strong><?= e(date('d/m/Y', strtotime($descontoValidoAte))) ?></strong>
                            </span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="text-success-ink font-bold text-heading-xs">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                        <?php if (! empty($descontoExpirado) && ! empty($descontoValidoAte)) { ?>
                            <span class="text-xs text-danger-ink inline-flex items-center gap-1 mt-0.5">
                                <?= icon('circle-alert', ['class' => 'size-3.5 text-danger-ink']) ?>
                                Desconto promocional expirado em <?= e(date('d/m/Y', strtotime($descontoValidoAte))) ?>
                            </span>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php if (! empty($ehMaster)) { ?>
            <!-- Card Informativo Empresa Master -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="p-2.5 rounded-lg bg-surface border border-border text-primary shadow-2xs">
                        <?= icon('shield-check', ['class' => 'size-6']) ?>
                    </div>
                    <div>
                        <h3 class="font-display text-base font-semibold text-text">Ambiente Master / Administrador Geral</h3>
                        <p class="text-caption text-muted">Esta é a empresa proprietária do sistema. O acesso a este painel é vitalício, permanente e isento de mensalidades.</p>
                    </div>
                </div>
                <div>
                    <?= component('button', [
                        'label' => 'Gerenciar SaaS e Assinantes',
                        'icon' => 'settings',
                        'href' => site_url('saas/gerenciar'),
                    ]) ?>
                </div>
            </div>
        <?php } else { ?>
            <!-- Seleção de Formas de Pagamento Ativas -->
            <div class="flex flex-col gap-3 pt-2">
                <span class="text-caption font-bold uppercase tracking-wider text-muted block">Formas de Pagamento Disponíveis</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" id="seletor-formas-pagamento">
                    <?php foreach ($formasAtivas as $index => $f) { ?>
                        <label class="cursor-pointer rounded-xl border p-4 flex flex-col justify-between transition-all opcao-forma-label <?= $index === 0 ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-surface-subtle hover:border-muted' ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-text font-semibold text-label-md">
                                    <?= icon($f->icone ?: 'banknote', ['class' => 'size-5 text-primary']) ?>
                                    <span><?= e($f->nome) ?></span>
                                </div>
                                <input type="radio" name="forma_escolhida" value="<?= e($f->codigo) ?>" <?= $index === 0 ? 'checked' : '' ?> class="size-4 text-primary border-input focus:ring-primary opcao-forma-radio">
                            </div>
                            <p class="text-caption text-muted mt-2"><?= e($f->descricao) ?></p>
                        </label>
                    <?php } ?>
                </div>
            </div>

            <!-- Checkbox de Cobrança e Lembretes -->
            <div class="pt-1">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" checked class="mt-0.5 size-4 rounded border-input text-primary focus:ring-primary">
                    <div class="flex flex-col">
                        <span class="text-label-md font-semibold text-text">Cobrança e lembrete</span>
                        <span class="text-caption text-muted">Você receberá um lembrete no sistema e por e-mail 3 dias e 1 dia antes do vencimento da sua assinatura.</span>
                    </div>
                </label>
            </div>

            <!-- Botão Principal de Renovação (+30 dias) -->
            <div class="pt-2">
                <button id="btn-pagar-assinatura" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3.5 px-6 shadow-sm transition-all text-body-lg">
                    <?= icon($primeiraForma->icone ?: 'qr-code', ['class' => 'size-5']) ?>
                    <span id="btn-label">Renovar assinatura via <?= e($primeiraForma->nome) ?> - R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                </button>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Modal 1: Pagamento via PIX -->
<div id="modal-pix" class="fixed inset-0 z-50 flex items-center justify-center bg-backdrop/80 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 sm:p-8 shadow-overlay flex flex-col gap-6 text-text">
        <button type="button" class="btn-fechar-modal absolute top-4 right-4 text-muted hover:text-text p-1 rounded-md">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>

        <div class="text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-success-soft text-success-ink mb-3">
                <?= icon('qr-code', ['class' => 'size-6']) ?>
            </div>
            <h3 class="font-display text-heading-md font-bold text-text">Pagamento via PIX</h3>
            <p class="text-caption text-muted mt-1">Escaneie o QR Code ou copie o código para liberar <strong class="text-text">+30 dias</strong> imediatamente.</p>
        </div>

        <div class="flex flex-col items-center justify-center p-4 bg-surface-subtle rounded-xl border border-border">
            <div class="size-48 bg-white p-2 rounded-lg flex items-center justify-center shadow-inner">
                <img id="pix-qrcode-img" src="" alt="QR Code PIX" class="size-full object-contain">
            </div>
            <span class="text-caption text-muted mt-3">Valor da Renovação: <strong id="pix-valor" class="text-success-ink font-bold text-body-md">R$ <?= number_format($valorFinal, 2, ',', '.') ?></strong></span>
        </div>

        <!-- Chave Copia e Cola -->
        <div class="flex flex-col gap-2">
            <label class="text-caption font-bold text-muted uppercase">Código Copia e Cola</label>
            <div class="flex gap-2">
                <input id="pix-copia-cola" type="text" readonly class="w-full text-xs font-mono bg-surface-subtle border border-input rounded-md px-3 py-2 text-text select-all">
                <button id="btn-copiar-pix" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-surface-subtle hover:bg-border text-text font-semibold border border-border text-label-md shrink-0">
                    <?= icon('copy', ['class' => 'size-4']) ?>
                    <span>Copiar</span>
                </button>
            </div>
            <span id="msg-copiado" class="text-xs text-success-ink font-semibold hidden">Código PIX copiado com sucesso!</span>
        </div>

        <!-- Status do Pagamento (Aguardando) -->
        <div class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg bg-primary/10 border border-primary/20 text-primary text-caption font-semibold">
            <span class="size-2 rounded-full bg-primary animate-ping"></span>
            <span>Aguardando compensação do banco…</span>
        </div>


    </div>
</div>

<!-- Modal 2: Pagamento via Boleto Bancário -->
<div id="modal-boleto" class="fixed inset-0 z-50 flex items-center justify-center bg-backdrop/80 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-lg rounded-2xl border border-border bg-surface p-6 sm:p-8 shadow-overlay flex flex-col gap-6 text-text">
        <button type="button" class="btn-fechar-modal absolute top-4 right-4 text-muted hover:text-text p-1 rounded-md">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>

        <div class="text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary mb-3">
                <?= icon('barcode', ['class' => 'size-6']) ?>
            </div>
            <h3 class="font-display text-heading-md font-bold text-text">Boleto Bancário Gerado</h3>
            <p class="text-caption text-muted mt-1">Utilize a linha digitável para pagar pelo internet banking ou imprima o boleto.</p>
        </div>

        <div class="flex flex-col gap-3 p-4 bg-surface-subtle rounded-xl border border-border">
            <div class="flex justify-between items-center text-sm">
                <span class="text-muted">Valor a Pagar:</span>
                <strong class="text-text font-bold text-base" id="boleto-valor">R$ <?= number_format($valorFinal, 2, ',', '.') ?></strong>
            </div>
            <div class="flex justify-between items-center text-sm border-t border-border pt-2">
                <span class="text-muted">Vencimento:</span>
                <span class="font-semibold text-text" id="boleto-vencimento">3 dias úteis</span>
            </div>
            <div class="flex justify-between items-center text-sm border-t border-border pt-2">
                <span class="text-muted">Prazo de Liberação:</span>
                <span class="font-semibold text-success-ink">+30 dias após compensação</span>
            </div>
        </div>

        <!-- Linha Digitável -->
        <div class="flex flex-col gap-2">
            <label class="text-caption font-bold text-muted uppercase">Linha Digitável / Código de Barras</label>
            <div class="flex gap-2">
                <input id="boleto-linha-digitavel" type="text" readonly class="w-full text-xs font-mono bg-surface-subtle border border-input rounded-md px-3 py-2 text-text select-all">
                <button id="btn-copiar-boleto" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-surface-subtle hover:bg-border text-text font-semibold border border-border text-label-md shrink-0">
                    <?= icon('copy', ['class' => 'size-4']) ?>
                    <span>Copiar</span>
                </button>
            </div>
            <span id="msg-boleto-copiado" class="text-xs text-success-ink font-semibold hidden">Linha digitável copiada com sucesso!</span>
        </div>

        <!-- Botão Imprimir Boleto / Visualizar PDF -->
        <div class="pt-1">
            <a id="btn-imprimir-boleto" href="#" target="_blank" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3 px-6 shadow-sm transition-all text-body-md text-center">
                <?= icon('printer', ['class' => 'size-5']) ?>
                <span>Imprimir Boleto / Visualizar PDF</span>
            </a>
        </div>


    </div>
</div>

<!-- Modal 3: Pagamento via Cartão de Crédito / Débito -->
<div id="modal-cartao" class="fixed inset-0 z-50 flex items-center justify-center bg-backdrop/80 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 sm:p-8 shadow-overlay flex flex-col gap-6 text-text">
        <button type="button" class="btn-fechar-modal absolute top-4 right-4 text-muted hover:text-text p-1 rounded-md">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>

        <div class="text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary mb-3">
                <?= icon('banknote', ['class' => 'size-6']) ?>
            </div>
            <h3 class="font-display text-heading-md font-bold text-text" id="cartao-modal-titulo">Pagamento com Cartão</h3>
            <p class="text-caption text-muted mt-1" id="cartao-modal-subtitulo">Liberação imediata de <strong class="text-text">+30 dias</strong> no sistema.</p>
        </div>

        <!-- Mensagem de Erro / Recusa do Cartão -->
        <div id="cartao-erro-container" class="hidden rounded-lg border border-danger/30 bg-danger/10 p-3 text-xs text-danger-ink flex items-start gap-2">
            <?= icon('circle-alert', ['class' => 'size-4 shrink-0 mt-0.5']) ?>
            <span id="cartao-erro-texto"></span>
        </div>

        <!-- Bloco 1: Formulário de Cartão de Crédito -->
        <form id="form-cartao-pagamento" class="flex flex-col gap-4">
            <div>
                <label class="text-caption font-semibold text-text block mb-1">Número do Cartão</label>
                <input id="cartao-numero" type="text" placeholder="" maxlength="19" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
            </div>

            <div>
                <label class="text-caption font-semibold text-text block mb-1">Nome Impresso no Cartão</label>
                <input id="cartao-nome" type="text" placeholder="" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary uppercase">
            </div>

            <div>
                <label class="text-caption font-semibold text-text block mb-1">CPF ou CNPJ do Titular</label>
                <input id="cartao-doc" type="text" placeholder="" value="<?= e($empresa->cnpj ?? $empresa->documento ?? '') ?>" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-caption font-semibold text-text block mb-1">Validade</label>
                    <input id="cartao-validade" type="text" placeholder="MM/AA" maxlength="5" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
                </div>
                <div>
                    <label class="text-caption font-semibold text-text block mb-1">CVV / CVC</label>
                    <input id="cartao-cvv" type="password" placeholder="" maxlength="4" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
                </div>
            </div>

            <?php if ($maxParcelas > 1) { ?>
                <div>
                    <label class="text-caption font-semibold text-text block mb-1">Parcelamento</label>
                    <select id="cartao-parcelas" class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary">
                        <?php for ($p = 1; $p <= $maxParcelas; $p++) {
                            $vParcela = $valorFinal / $p;
                            echo '<option value="' . $p . '">&nbsp;' . $p . 'x de R$ ' . number_format($vParcela, 2, ',', '.') . ($p === 1 ? ' (à vista)' : '') . '</option>';
                        } ?>
                    </select>
                </div>
            <?php } else { ?>
                <input type="hidden" id="cartao-parcelas" value="1">
            <?php } ?>

            <div class="rounded-lg border border-border bg-surface-subtle p-3 flex items-center justify-between text-caption text-muted">
                <span class="flex items-center gap-1.5 text-xs text-success-ink font-semibold">
                    <?= icon('shield-check', ['class' => 'size-4']) ?>
                    Ambiente 100% Criptografado
                </span>
                <span class="font-bold text-text text-sm">R$ <?= number_format($valorFinal, 2, ',', '.') ?></span>
            </div>

            <button id="btn-processar-cartao" type="submit" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3 px-6 shadow-sm transition-all text-body-md mt-2">
                <?= icon('check', ['class' => 'size-5']) ?>
                <span id="btn-processar-cartao-label">Confirmar Pagamento</span>
            </button>
        </form>

        <!-- Bloco 2: Informações de Cartão de Débito (Autenticação 3DS) -->
        <div id="bloco-debito-info" class="hidden flex flex-col gap-4">
            <div class="rounded-xl border border-border bg-surface-subtle p-4 flex flex-col gap-2">
                <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                    <?= icon('shield-check', ['class' => 'size-5']) ?>
                    <span>Autenticação Bancária Obrigatória</span>
                </div>
                <p class="text-caption text-muted">
                    No Brasil, pagamentos com cartão de débito exigem validação em 2 etapas (3-D Secure) com senha ou confirmação no aplicativo do seu banco.
                </p>
                <p class="text-caption text-muted">
                    Ao prosseguir, você será redirecionado para a página segura de autenticação.
                </p>
            </div>

            <div class="rounded-lg border border-border bg-surface-subtle p-3 flex items-center justify-between text-caption text-muted">
                <span class="flex items-center gap-1.5 text-xs text-success-ink font-semibold">
                    <?= icon('shield-check', ['class' => 'size-4']) ?>
                    Sessão Segura 3DS
                </span>
                <span class="font-bold text-text text-sm">R$ <?= number_format($valorFinal, 2, ',', '.') ?></span>
            </div>

            <button id="btn-iniciar-debito" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3 px-6 shadow-sm transition-all text-body-md mt-2">
                <?= icon('external-link', ['class' => 'size-5']) ?>
                <span>Prosseguir para o Banco</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const seletorFormas = document.getElementById('seletor-formas-pagamento');
    const btnPagar = document.getElementById('btn-pagar-assinatura');
    const btnLabel = document.getElementById('btn-label');

    const csrfName = '<?= e($this->security->get_csrf_token_name()) ?>';
    const csrfHash = '<?= e($this->security->get_csrf_hash()) ?>';

    function obterCsrf() {
        const metaName = document.querySelector('meta[name="csrf-token-name"]')?.content;
        const metaCookie = document.querySelector('meta[name="csrf-cookie-name"]')?.content;
        const name = metaName || csrfName;
        const cookieName = metaCookie || '<?= e(config_item('csrf_cookie_name')) ?>';
        let hash = csrfHash;
        if (cookieName && document.cookie) {
            const m = document.cookie.match(new RegExp('(^|;\\s*)' + cookieName + '=([^;]*)'));
            if (m && m[2]) {
                hash = decodeURIComponent(m[2]);
            }
        }
        return { name, hash };
    }

    // Modais
    const modalPix = document.getElementById('modal-pix');
    const modalBoleto = document.getElementById('modal-boleto');
    const modalCartao = document.getElementById('modal-cartao');
    const botoesFechar = document.querySelectorAll('.btn-fechar-modal');

    // Elementos PIX
    const qrImg = document.getElementById('pix-qrcode-img');
    const inputPix = document.getElementById('pix-copia-cola');
    const pixValor = document.getElementById('pix-valor');
    const btnCopiar = document.getElementById('btn-copiar-pix');
    const msgCopiado = document.getElementById('msg-copiado');

    // Elementos Boleto
    const inputBoleto = document.getElementById('boleto-linha-digitavel');
    const btnCopiarBoleto = document.getElementById('btn-copiar-boleto');
    const msgBoletoCopiado = document.getElementById('msg-boleto-copiado');
    const boletoVencimento = document.getElementById('boleto-vencimento');

    // Elementos Cartão
    const formCartao = document.getElementById('form-cartao-pagamento');
    const btnProcessarCartao = document.getElementById('btn-processar-cartao');
    const btnProcessarLabel = document.getElementById('btn-processar-cartao-label');
    const cartaoModalTitulo = document.getElementById('cartao-modal-titulo');
    const cartaoModalSubtitulo = document.getElementById('cartao-modal-subtitulo');
    const blocoDebitoInfo = document.getElementById('bloco-debito-info');
    const btnIniciarDebito = document.getElementById('btn-iniciar-debito');
    const erroContainer = document.getElementById('cartao-erro-container');
    const erroTexto = document.getElementById('cartao-erro-texto');

    let currentAssinaturaId = null;
    let pollingInterval = null;
    let formaSelecionada = '<?= e($primeiraForma->codigo) ?>';

    // Máscaras e formatações de entrada no cartão
    const inputNum = document.getElementById('cartao-numero');
    if (inputNum) {
        inputNum.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').substring(0, 16);
            v = v.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = v;
        });
    }
    const inputVal = document.getElementById('cartao-validade');
    if (inputVal) {
        inputVal.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (v.length >= 2) {
                v = v.substring(0, 2) + '/' + v.substring(2);
            }
            e.target.value = v;
        });
    }
    const inputDoc = document.getElementById('cartao-doc');
    if (inputDoc) {
        inputDoc.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').substring(0, 14);
            if (v.length > 11) {
                v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            } else if (v.length > 9) {
                v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            }
            e.target.value = v;
        });
    }

    function detectarBandeira(num) {
        num = num.replace(/\D/g, '');
        if (/^4/.test(num)) return 'visa';
        if (/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[0-1]|2720)/.test(num)) return 'mastercard';
        if (/^(4011|438935|451416|4576|504175|506699|5067|5090|627780|636297|636368)/.test(num)) return 'elo';
        if (/^(34|37)/.test(num)) return 'amex';
        if (/^(606282|3841)/.test(num)) return 'hipercard';
        return 'visa';
    }

    // Troca de forma de pagamento na tela
    if (seletorFormas) {
        seletorFormas.addEventListener('change', (e) => {
            const radio = e.target.closest('input[name="forma_escolhida"]');
            if (!radio) return;

            formaSelecionada = radio.value;

            // Atualiza destaque visual dos cards
            seletorFormas.querySelectorAll('.opcao-forma-label').forEach((label) => {
                const r = label.querySelector('input[type="radio"]');
                if (r && r.checked) {
                    label.classList.add('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                    label.classList.remove('border-border', 'bg-surface-subtle');
                } else {
                    label.classList.remove('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                    label.classList.add('border-border', 'bg-surface-subtle');
                }
            });

            // Atualiza texto do botão
            if (formaSelecionada === 'pix') {
                btnLabel.textContent = 'Renovar assinatura via PIX - R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês';
            } else if (formaSelecionada === 'boleto') {
                btnLabel.textContent = 'Gerar Boleto Bancário (+30 dias) - R$ <?= number_format($valorFinal, 2, ',', '.') ?>';
            } else if (formaSelecionada === 'cartao_credito') {
                btnLabel.textContent = 'Pagar com Cartão de Crédito (+30 dias) - R$ <?= number_format($valorFinal, 2, ',', '.') ?>';
            } else if (formaSelecionada === 'cartao_debito') {
                btnLabel.textContent = 'Pagar com Débito Online (+30 dias) - R$ <?= number_format($valorFinal, 2, ',', '.') ?>';
            }
        });
    }

    // Ação do Botão Principal
    if (btnPagar) {
        btnPagar.addEventListener('click', async () => {
            if (formaSelecionada === 'pix') {
                await executarPix();
            } else if (formaSelecionada === 'boleto') {
                await executarBoleto();
            } else if (formaSelecionada === 'cartao_credito') {
                if (cartaoModalTitulo) cartaoModalTitulo.textContent = 'Pagamento com Cartão de Crédito';
                if (cartaoModalSubtitulo) cartaoModalSubtitulo.textContent = 'Liberação imediata de +30 dias no sistema.';
                if (formCartao) formCartao.classList.remove('hidden');
                if (blocoDebitoInfo) blocoDebitoInfo.classList.add('hidden');
                if (erroContainer) erroContainer.classList.add('hidden');
                modalCartao.classList.remove('hidden');
            } else if (formaSelecionada === 'cartao_debito') {
                if (cartaoModalTitulo) cartaoModalTitulo.textContent = 'Pagamento com Cartão de Débito';
                if (cartaoModalSubtitulo) cartaoModalSubtitulo.textContent = 'Autenticação bancária segura via 3-D Secure.';
                if (formCartao) formCartao.classList.add('hidden');
                if (blocoDebitoInfo) blocoDebitoInfo.classList.remove('hidden');
                if (erroContainer) erroContainer.classList.add('hidden');
                modalCartao.classList.remove('hidden');
            }
        });
    }

    // Executar PIX
    async function executarPix() {
        btnPagar.disabled = true;
        btnPagar.classList.add('opacity-75');

        try {
            const csrf = obterCsrf();
            const formData = new FormData();
            formData.append(csrf.name, csrf.hash);

            const res = await fetch('<?= site_url('assinatura/gerarPix') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await res.json().catch(() => null);
            if (!res.ok) {
                alert(data?.message || `Erro no servidor (${res.status}).`);
                return;
            }

            if (data && data.result) {
                currentAssinaturaId = data.assinatura_id;
                pixValor.textContent = `R$ ${data.valor}`;
                inputPix.value = data.qr_code;
                if (data.qr_code_image) {
                    qrImg.src = data.qr_code_image.startsWith('data:')
                        ? data.qr_code_image
                        : `data:image/png;base64,${data.qr_code_image}`;
                } else {
                    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.qr_code)}`;
                }
                modalPix.classList.remove('hidden');
                iniciarPolling(data.assinatura_id);
            } else {
                alert(data.message || 'Erro ao gerar PIX.');
            }
        } catch (err) {
            alert('Falha na comunicação com o servidor.');
        } finally {
            btnPagar.disabled = false;
            btnPagar.classList.remove('opacity-75');
        }
    }

    // Executar Boleto
    async function executarBoleto() {
        btnPagar.disabled = true;
        btnPagar.classList.add('opacity-75');

        try {
            const csrf = obterCsrf();
            const formData = new FormData();
            formData.append(csrf.name, csrf.hash);

            const res = await fetch('<?= site_url('assinatura/gerarBoleto') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await res.json().catch(() => null);
            if (!res.ok) {
                alert(data?.message || `Erro no servidor (${res.status}).`);
                return;
            }

            if (data && data.result) {
                currentAssinaturaId = data.assinatura_id;
                inputBoleto.value = data.linha_digitavel;
                boletoVencimento.textContent = data.vencimento;
                const btnImprimir = document.getElementById('btn-imprimir-boleto');
                if (btnImprimir) {
                    if (data.pdf_url) {
                        btnImprimir.href = data.pdf_url;
                        btnImprimir.classList.remove('hidden');
                    } else {
                        btnImprimir.classList.add('hidden');
                    }
                }
                modalBoleto.classList.remove('hidden');
            } else {
                alert(data.message || 'Erro ao gerar Boleto.');
            }
        } catch (err) {
            alert('Falha na comunicação com o servidor.');
        } finally {
            btnPagar.disabled = false;
            btnPagar.classList.remove('opacity-75');
        }
    }

    // Ação do Botão Débito 3DS
    if (btnIniciarDebito) {
        btnIniciarDebito.addEventListener('click', async () => {
            btnIniciarDebito.disabled = true;
            btnIniciarDebito.classList.add('opacity-75');
            if (erroContainer) erroContainer.classList.add('hidden');

            try {
                const csrf = obterCsrf();
                const formData = new FormData();
                formData.append(csrf.name, csrf.hash);
                formData.append('tipo', 'debito');

                const res = await fetch('<?= site_url('assinatura/gerarCartao') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });

                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    throw new Error(data?.message || `Erro no servidor (${res.status}).`);
                }

                if (data && data.checkout_url) {
                    window.location.href = data.checkout_url;
                } else {
                    throw new Error(data?.message || 'Link de débito não retornado.');
                }
            } catch (err) {
                if (erroTexto) erroTexto.textContent = err.message || 'Falha ao iniciar débito bancário.';
                if (erroContainer) erroContainer.classList.remove('hidden');
                btnIniciarDebito.disabled = false;
                btnIniciarDebito.classList.remove('opacity-75');
            }
        });
    }

    // Submeter Cartão de Crédito com Tokenização
    if (formCartao) {
        formCartao.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (erroContainer) erroContainer.classList.add('hidden');
            btnProcessarCartao.disabled = true;
            btnProcessarCartao.classList.add('opacity-75');
            if (btnProcessarLabel) btnProcessarLabel.textContent = 'Validando dados...';

            const num = (document.getElementById('cartao-numero')?.value || '').replace(/\s+/g, '');
            const nome = (document.getElementById('cartao-nome')?.value || '').trim();
            const validade = (document.getElementById('cartao-validade')?.value || '').trim();
            const cvv = (document.getElementById('cartao-cvv')?.value || '').trim();
            const doc = (document.getElementById('cartao-doc')?.value || '').replace(/\D/g, '');
            const parcelas = document.getElementById('cartao-parcelas')?.value || 1;

            let [mes, ano] = validade.split('/');
            mes = mes ? mes.trim() : '';
            ano = ano ? ano.trim() : '';
            if (ano.length === 2) {
                ano = '20' + ano;
            }

            try {
                let cardToken = '';
                const provedorAtivo = '<?= e($provedorCartao) ?>';

                // 1. Tokenização via Mercado Pago
                if (provedorAtivo === 'mercadopago') {
                    const mpKey = '<?= e($mpPublicKey) ?>';
                    if (typeof MercadoPago !== 'undefined' && mpKey) {
                        const mp = new MercadoPago(mpKey);
                        const respToken = await mp.createCardToken({
                            cardNumber: num,
                            cardholderName: nome,
                            cardExpirationMonth: mes,
                            cardExpirationYear: ano,
                            securityCode: cvv,
                            identificationType: doc.length === 14 ? 'CNPJ' : 'CPF',
                            identificationNumber: doc
                        });
                        if (respToken && respToken.id) {
                            cardToken = respToken.id;
                        } else if (respToken && respToken.cause && respToken.cause[0]) {
                            throw new Error(respToken.cause[0].description || 'Dados do cartão inválidos.');
                        }
                    }
                }
                // 2. Tokenização via Efí (Gerencianet)
                else if (provedorAtivo === 'gerencianet') {
                    const efiPayee = '<?= e($efiPayeeCode) ?>';
                    const efiAmb = '<?= e($efiAmbiente) ?>';
                    if (typeof $gn !== 'undefined' && efiPayee) {
                        cardToken = await new Promise((resolve, reject) => {
                            $gn.ready(function(Checkout) {
                                Checkout.setAccount(efiPayee);
                                Checkout.setEnvironment(efiAmb === 'homologacao' ? 'sandbox' : 'production');
                                Checkout.getPaymentToken({
                                    brand: detectarBandeira(num),
                                    number: num,
                                    cvv: cvv,
                                    expiration_month: mes,
                                    expiration_year: ano,
                                    reuse: false
                                }, function(error, response) {
                                    if (error) {
                                        reject(new Error(error.error_description || error.message || 'Erro ao validar cartão na Efí.'));
                                    } else if (response && response.data && response.data.payment_token) {
                                        resolve(response.data.payment_token);
                                    } else {
                                        reject(new Error('Falha ao gerar token de cartão na Efí.'));
                                    }
                                });
                            });
                        });
                    }
                }

                if (btnProcessarLabel) btnProcessarLabel.textContent = 'Processando com o banco...';

                const csrf = obterCsrf();
                const formData = new FormData();
                formData.append(csrf.name, csrf.hash);
                formData.append('tipo', 'credito');
                formData.append('token', cardToken);
                formData.append('titular', nome);
                formData.append('doc_titular', doc);
                formData.append('parcelas', parcelas);
                formData.append('numero_cartao', num);
                formData.append('mes_expiracao', mes);
                formData.append('ano_expiracao', ano);
                formData.append('cvv', cvv);

                const res = await fetch('<?= site_url('assinatura/gerarCartao') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });

                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    throw new Error(data?.message || `Erro no servidor (${res.status}).`);
                }

                if (data && data.result) {
                    alert('🎉 ' + data.message);
                    window.location.reload();
                } else {
                    throw new Error(data?.message || 'Falha na aprovação do cartão.');
                }
            } catch (err) {
                if (erroTexto) erroTexto.textContent = err.message || 'Erro ao processar cartão.';
                if (erroContainer) erroContainer.classList.remove('hidden');
            } finally {
                btnProcessarCartao.disabled = false;
                btnProcessarCartao.classList.remove('opacity-75');
                if (btnProcessarLabel) btnProcessarLabel.textContent = 'Confirmar Pagamento';
            }
        });
    }

    // Fechar modais
    botoesFechar.forEach((btn) => {
        btn.addEventListener('click', () => {
            modalPix.classList.add('hidden');
            modalBoleto.classList.add('hidden');
            modalCartao.classList.add('hidden');
            if (pollingInterval) clearInterval(pollingInterval);
        });
    });

    // Copiar PIX
    if (btnCopiar) {
        btnCopiar.addEventListener('click', () => {
            inputPix.select();
            navigator.clipboard.writeText(inputPix.value).then(() => {
                msgCopiado.classList.remove('hidden');
                setTimeout(() => msgCopiado.classList.add('hidden'), 3000);
            });
        });
    }

    // Copiar Boleto
    if (btnCopiarBoleto) {
        btnCopiarBoleto.addEventListener('click', () => {
            inputBoleto.select();
            navigator.clipboard.writeText(inputBoleto.value).then(() => {
                msgBoletoCopiado.classList.remove('hidden');
                setTimeout(() => msgBoletoCopiado.classList.add('hidden'), 3000);
            });
        });
    }

    // Polling PIX
    function iniciarPolling(assinaturaId) {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(async () => {
            try {
                const res = await fetch(`<?= site_url('assinatura/verificarPix?id=') ?>${assinaturaId}`);
                const data = await res.json();
                if (data.pago) {
                    clearInterval(pollingInterval);
                    alert('🎉 Pagamento confirmado com sucesso! Mais 30 dias foram adicionados.');
                    window.location.reload();
                }
            } catch (e) {}
        }, 3000);
    }


});
</script>
