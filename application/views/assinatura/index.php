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
            <!-- Forma de Pagamento -->
            <div class="rounded-lg border border-border bg-surface-subtle p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-surface border border-border text-success-ink">
                        <?= icon('qr-code', ['class' => 'size-6']) ?>
                    </div>
                    <div>
                        <span class="text-caption font-bold uppercase tracking-wider text-muted block">Forma de Pagamento</span>
                        <span class="font-display text-heading-sm font-bold text-text">PIX Instantâneo</span>
                    </div>
                </div>
                <div>
                    <span class="inline-block text-caption text-muted bg-surface px-3 py-1.5 rounded-md border border-border font-medium">
                        ⚡ Compensação automática e liberação imediata
                    </span>
                </div>
            </div>

            <!-- Checkbox de Cobrança Automática -->
            <div class="pt-1">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" checked class="mt-0.5 size-4 rounded border-input text-primary focus:ring-primary">
                    <div class="flex flex-col">
                        <span class="text-label-md font-semibold text-text">Cobrança e lembrete</span>
                        <span class="text-caption text-muted">Você receberá um lembrete no sistema e por e-mail 3 dias e 1 dia antes do vencimento da sua assinatura.</span>
                    </div>
                </label>
            </div>

            <!-- Botão Principal de Renovação -->
            <div class="pt-2">
                <button id="btn-pagar-assinatura" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-hover active:bg-primary-hover text-on-primary font-bold py-3.5 px-6 shadow-sm transition-all text-body-lg">
                    <?= icon('qr-code', ['class' => 'size-5']) ?>
                    <span id="btn-label">Renovar assinatura via PIX - R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                </button>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Modal PIX de Pagamento -->
<div id="modal-pix" class="fixed inset-0 z-50 flex items-center justify-center bg-backdrop/80 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 sm:p-8 shadow-overlay flex flex-col gap-6 text-text">
        <button id="btn-fechar-modal" class="absolute top-4 right-4 text-muted hover:text-text p-1 rounded-md">
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
            <span class="text-caption text-muted mt-3">Valor da Renovação: <strong id="pix-valor" class="text-success-ink font-bold text-body-md">R$ 0,00</strong></span>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnPagar = document.getElementById('btn-pagar-assinatura');
    const modalPix = document.getElementById('modal-pix');
    const btnFecharModal = document.getElementById('btn-fechar-modal');
    const qrImg = document.getElementById('pix-qrcode-img');
    const inputPix = document.getElementById('pix-copia-cola');
    const pixValor = document.getElementById('pix-valor');
    const btnCopiar = document.getElementById('btn-copiar-pix');
    const msgCopiado = document.getElementById('msg-copiado');
    const btnConfirmarTeste = document.getElementById('btn-confirmar-teste');

    let currentAssinaturaId = null;
    let pollingInterval = null;

    btnPagar.addEventListener('click', async () => {
        btnPagar.disabled = true;
        btnPagar.classList.add('opacity-75');

        try {
            const formData = new FormData();
            formData.append('<?= e($this->security->get_csrf_token_name()) ?>', '<?= e($this->security->get_csrf_hash()) ?>');

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
    });

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

    btnFecharModal.addEventListener('click', () => {
        modalPix.classList.add('hidden');
        if (pollingInterval) clearInterval(pollingInterval);
    });

    btnCopiar.addEventListener('click', () => {
        inputPix.select();
        navigator.clipboard.writeText(inputPix.value).then(() => {
            msgCopiado.classList.remove('hidden');
            setTimeout(() => msgCopiado.classList.add('hidden'), 3000);
        });
    });

    btnConfirmarTeste.addEventListener('click', async () => {
        if (!currentAssinaturaId) return;
        btnConfirmarTeste.disabled = true;

        const formData = new FormData();
        formData.append('id', currentAssinaturaId);
        formData.append('<?= e($this->security->get_csrf_token_name()) ?>', '<?= e($this->security->get_csrf_hash()) ?>');

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
            btnConfirmarTeste.disabled = false;
        }
    });
});
</script>
