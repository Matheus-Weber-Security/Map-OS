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
?>
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
                    <?php } elseif ($descontoPercentual > 0) { ?>
                        <div class="flex items-center gap-2">
                            <span class="line-through text-muted text-caption">R$ <?= number_format($valorOriginal, 2, ',', '.') ?></span>
                            <?= component('pill-status', ['label' => $descontoPercentual . '% OFF', 'variant' => 'warning']) ?>
                        </div>
                        <span class="text-success-ink font-bold text-heading-xs">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                    <?php } else { ?>
                        <span class="text-success-ink font-bold text-heading-xs">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
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
                    <?= icon($primeiraForma->icone ?: 'qr-code', ['class' => 'size-5', 'id' => 'btn-icone']) ?>
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

        <!-- Botão de Simulação Instantânea em Testes -->
        <div class="border-t border-border pt-4">
            <button id="btn-confirmar-teste" type="button" class="w-full py-2.5 px-4 rounded-lg bg-surface-subtle hover:bg-border border border-border text-caption font-bold text-text transition-colors flex items-center justify-center gap-2">
                <?= icon('check', ['class' => 'size-4 text-success-ink']) ?>
                <span>Simular Confirmação Instantânea (Ambiente de Testes)</span>
            </button>
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

        <!-- Botão de Simulação em Testes -->
        <div class="border-t border-border pt-2">
            <button id="btn-confirmar-boleto-teste" type="button" class="w-full py-2.5 px-4 rounded-lg bg-surface-subtle hover:bg-border border border-border text-caption font-bold text-text transition-colors flex items-center justify-center gap-2">
                <?= icon('check', ['class' => 'size-4 text-success-ink']) ?>
                <span>Simular Baixa do Boleto (Ambiente de Testes)</span>
            </button>
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
            <p class="text-caption text-muted mt-1">Liberação imediata de <strong class="text-text">+30 dias</strong> no sistema.</p>
        </div>

        <!-- Formulário Seguro de Cartão -->
        <form id="form-cartao-pagamento" class="flex flex-col gap-4">
            <div>
                <label class="text-caption font-semibold text-text block mb-1">Número do Cartão</label>
                <input id="cartao-numero" type="text" placeholder="0000 0000 0000 0000" maxlength="19" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
            </div>

            <div>
                <label class="text-caption font-semibold text-text block mb-1">Nome Impresso no Cartão</label>
                <input id="cartao-nome" type="text" placeholder="COMO NO CARTAO" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary uppercase">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-caption font-semibold text-text block mb-1">Validade</label>
                    <input id="cartao-validade" type="text" placeholder="MM/AA" maxlength="5" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
                </div>
                <div>
                    <label class="text-caption font-semibold text-text block mb-1">CVV / CVC</label>
                    <input id="cartao-cvv" type="password" placeholder="123" maxlength="4" required class="w-full text-sm bg-surface-subtle border border-input rounded-lg px-3 py-2 text-text focus:outline-primary font-mono">
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface-subtle p-3 flex items-center justify-between text-caption text-muted">
                <span class="flex items-center gap-1.5 text-xs text-success-ink font-semibold">
                    <?= icon('shield-check', ['class' => 'size-4']) ?>
                    Ambiente 100% Criptografado
                </span>
                <span class="font-bold text-text text-sm">R$ <?= number_format($valorFinal, 2, ',', '.') ?></span>
            </div>

            <button id="btn-processar-cartao" type="submit" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3 px-6 shadow-sm transition-all text-body-md mt-2">
                <?= icon('check', ['class' => 'size-5']) ?>
                <span>Confirmar Pagamento</span>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const seletorFormas = document.getElementById('seletor-formas-pagamento');
    const btnPagar = document.getElementById('btn-pagar-assinatura');
    const btnLabel = document.getElementById('btn-label');
    const btnIcone = document.getElementById('btn-icone');
    const csrfName = '<?= e($this->security->get_csrf_token_name()) ?>';
    const csrfHash = '<?= e($this->security->get_csrf_hash()) ?>';

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
    const btnConfirmarTeste = document.getElementById('btn-confirmar-teste');

    // Elementos Boleto
    const inputBoleto = document.getElementById('boleto-linha-digitavel');
    const btnCopiarBoleto = document.getElementById('btn-copiar-boleto');
    const msgBoletoCopiado = document.getElementById('msg-boleto-copiado');
    const btnConfirmarBoletoTeste = document.getElementById('btn-confirmar-boleto-teste');
    const boletoVencimento = document.getElementById('boleto-vencimento');

    // Elementos Cartão
    const formCartao = document.getElementById('form-cartao-pagamento');
    const btnProcessarCartao = document.getElementById('btn-processar-cartao');
    const cartaoModalTitulo = document.getElementById('cartao-modal-titulo');

    let currentAssinaturaId = null;
    let pollingInterval = null;
    let formaSelecionada = '<?= e($primeiraForma->codigo) ?>';

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
            } else if (formaSelecionada === 'cartao_credito' || formaSelecionada === 'cartao_debito') {
                cartaoModalTitulo.textContent = formaSelecionada === 'cartao_debito' ? 'Pagamento com Cartão de Débito' : 'Pagamento com Cartão de Crédito';
                modalCartao.classList.remove('hidden');
            }
        });
    }

    // Executar PIX
    async function executarPix() {
        btnPagar.disabled = true;
        btnPagar.classList.add('opacity-75');

        try {
            const formData = new FormData();
            formData.append(csrfName, csrfHash);

            const res = await fetch('<?= site_url('assinatura/gerarPix') ?>', {
                method: 'POST',
                body: formData,
            });

            const data = await res.json();
            if (data.result) {
                currentAssinaturaId = data.assinatura_id;
                pixValor.textContent = `R$ ${data.valor}`;
                inputPix.value = data.qr_code;
                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.qr_code)}`;
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
            const formData = new FormData();
            formData.append(csrfName, csrfHash);

            const res = await fetch('<?= site_url('assinatura/gerarBoleto') ?>', {
                method: 'POST',
                body: formData,
            });

            const data = await res.json();
            if (data.result) {
                currentAssinaturaId = data.assinatura_id;
                inputBoleto.value = data.linha_digitavel;
                boletoVencimento.textContent = data.vencimento;
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

    // Submeter Cartão
    if (formCartao) {
        formCartao.addEventListener('submit', async (e) => {
            e.preventDefault();
            btnProcessarCartao.disabled = true;
            btnProcessarCartao.classList.add('opacity-75');

            try {
                const formData = new FormData();
                formData.append(csrfName, csrfHash);
                formData.append('tipo', formaSelecionada === 'cartao_debito' ? 'debito' : 'credito');

                const res = await fetch('<?= site_url('assinatura/gerarCartao') ?>', {
                    method: 'POST',
                    body: formData,
                });

                const data = await res.json();
                if (data.result) {
                    alert('🎉 ' + data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Falha na aprovação do cartão.');
                    btnProcessarCartao.disabled = false;
                    btnProcessarCartao.classList.remove('opacity-75');
                }
            } catch (err) {
                alert('Erro na comunicação.');
                btnProcessarCartao.disabled = false;
                btnProcessarCartao.classList.remove('opacity-75');
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

    // Confirmação de Teste PIX
    if (btnConfirmarTeste) {
        btnConfirmarTeste.addEventListener('click', async () => {
            await confirmarTeste();
        });
    }

    // Confirmação de Teste Boleto
    if (btnConfirmarBoletoTeste) {
        btnConfirmarBoletoTeste.addEventListener('click', async () => {
            await confirmarTeste();
        });
    }

    async function confirmarTeste() {
        if (!currentAssinaturaId) return;

        const formData = new FormData();
        formData.append('id', currentAssinaturaId);
        formData.append(csrfName, csrfHash);

        const res = await fetch('<?= site_url('assinatura/confirmarPixTeste') ?>', {
            method: 'POST',
            body: formData,
        });

        const data = await res.json();
        if (data.result) {
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message || 'Erro ao confirmar.');
        }
    }
});
</script>
