<div class="flex flex-col gap-6 p-4 sm:p-6 max-w-5xl mx-auto">
    <!-- Cabeçalho da Página -->
    <div>
        <h1 class="font-display text-heading-lg font-bold text-text">Assinatura</h1>
        <p class="text-body-md text-muted mt-1">Gerencie seu plano, tipo de assinatura, forma de pagamento e renovação</p>
    </div>

    <!-- Card Principal: Status do Plano -->
    <div class="rounded-xl border border-border bg-surface p-6 sm:p-8 shadow-sm flex flex-col gap-6">
        <div class="flex items-center gap-2 pb-4 border-b border-border">
            <?= icon('banknote', ['class' => 'size-6 text-primary']) ?>
            <h2 class="font-display text-heading-md font-semibold text-text">Status do Plano</h2>
        </div>

        <!-- Grade de Indicadores (Topo) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Status -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col justify-between">
                <span class="text-caption font-semibold uppercase tracking-wider text-muted">Status</span>
                <div class="mt-2 flex items-center gap-2">
                    <?php if ($diasRestantes > 0) { ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-label-md font-bold bg-success-soft text-success-ink">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            Ativa
                        </span>
                    <?php } else { ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-label-md font-bold bg-danger-soft text-danger-ink">
                            <span class="size-2 rounded-full bg-red-500"></span>
                            Vencida
                        </span>
                    <?php } ?>
                </div>
            </div>

            <!-- Tipo de Assinatura -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col justify-between">
                <span class="text-caption font-semibold uppercase tracking-wider text-muted">Tipo de Assinatura</span>
                <div class="mt-2">
                    <select id="select-plano" class="w-full rounded-md border border-input bg-surface px-3 py-2 text-label-md font-bold text-text focus:outline-none focus:ring-2 focus:ring-primary">
                        <?php foreach ($planos as $p) { ?>
                            <option value="<?= (int) $p->id ?>" data-valor="<?= number_format((float) $p->valor, 2, '.', '') ?>" data-nome="<?= e($p->nome) ?>" <?= ((int) $p->id === (int) ($planoAtual->id ?? 0)) ? 'selected' : '' ?>>
                                ⭐ <?= e($p->nome) ?> (R$ <?= number_format((float) $p->valor, 2, ',', '.') ?>/mês)
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <!-- Dias Restantes -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col justify-between">
                <span class="text-caption font-semibold uppercase tracking-wider text-muted">Dias Restantes</span>
                <div class="mt-2">
                    <span class="font-display text-display-small font-bold <?= $diasRestantes > 0 ? 'text-emerald-500' : 'text-red-500' ?>">
                        <?= (int) $diasRestantes ?> dias
                    </span>
                </div>
            </div>
        </div>

        <!-- Grade Secundária (Meio) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
            <!-- Forma de Pagamento -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col justify-between gap-4">
                <div>
                    <span class="text-caption font-semibold uppercase tracking-wider text-muted">Forma de Pagamento</span>
                    <div class="mt-2 flex items-center gap-2">
                        <?= icon('qr-code', ['class' => 'size-5 text-emerald-500']) ?>
                        <span class="font-display text-heading-sm font-bold text-text">PIX</span>
                    </div>
                </div>
                <div>
                    <span class="inline-block text-caption text-muted bg-surface px-3 py-1.5 rounded border border-border">
                        Compensação imediata e automática
                    </span>
                </div>
            </div>

            <!-- Vencimento e Valor -->
            <div class="rounded-lg border border-border bg-surface-subtle p-5 flex flex-col justify-between gap-4">
                <div>
                    <span class="text-caption font-semibold uppercase tracking-wider text-muted">Vencimento da Assinatura</span>
                    <div class="mt-2">
                        <span class="font-display text-heading-sm font-bold text-text">
                            <?= ! empty($empresa->data_vencimento) ? e(date('d/m/Y', strtotime($empresa->data_vencimento))) : 'Não definido' ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-between text-body-md border-t border-border/50 pt-2">
                    <span class="text-muted">Valor do Plano (<?= e($planoAtual->nome ?? 'Essencial') ?>):</span>
                    <span class="font-bold text-text">
                        <?php if ($descontoPercentual > 0) { ?>
                            <span class="line-through text-muted text-caption mr-1">R$ <?= number_format($valorOriginal, 2, ',', '.') ?></span>
                            <span class="text-emerald-500 font-bold">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                            <span class="text-xs bg-emerald-500/10 text-emerald-500 px-1.5 py-0.5 rounded font-bold ml-1"><?= $descontoPercentual ?>% OFF</span>
                        <?php } else { ?>
                            <span class="text-emerald-500 font-bold">R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
                        <?php } ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Opção de Cobrança Automática -->
        <div class="pt-2">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked class="mt-1 size-4 rounded border-input text-primary focus:ring-primary">
                <div class="flex flex-col">
                    <span class="text-label-md font-semibold text-text">Cobrança e lembrete</span>
                    <span class="text-body-sm text-muted">Você receberá um lembrete no sistema e por e-mail 3 dias e 1 dia antes do vencimento da sua assinatura.</span>
                </div>
            </label>
        </div>

        <!-- Botão Principal de Renovação -->
        <div class="pt-4">
            <button id="btn-pagar-assinatura" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold py-4 px-6 shadow-md transition-all text-body-lg">
                <?= icon('credit-card', ['class' => 'size-5']) ?>
                <span id="btn-label">Pagar assinatura (<?= e($planoAtual->nome ?? 'Essencial') ?>) - R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal PIX de Pagamento -->
<div id="modal-pix" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 sm:p-8 shadow-2xl flex flex-col gap-6">
        <!-- Fechar Modal -->
        <button id="btn-fechar-modal" class="absolute top-4 right-4 text-muted hover:text-text p-1 rounded-md">
            <?= icon('x', ['class' => 'size-5']) ?>
        </button>

        <div class="text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500 mb-3">
                <?= icon('qr-code', ['class' => 'size-6']) ?>
            </div>
            <h3 class="font-display text-heading-md font-bold text-text">Pagamento via PIX</h3>
            <p class="text-body-sm text-muted mt-1">Escaneie o QR Code ou copie o código para adicionar <strong class="text-text">+30 dias</strong>.</p>
        </div>

        <div class="flex flex-col items-center justify-center p-4 bg-surface-subtle rounded-xl border border-border">
            <div id="pix-qrcode-container" class="size-48 bg-white p-2 rounded-lg flex items-center justify-center shadow-inner">
                <img id="pix-qrcode-img" src="" alt="QR Code PIX" class="size-full object-contain">
            </div>
            <span class="text-caption text-muted mt-2">Valor: <strong id="pix-valor" class="text-emerald-500 font-bold text-body-md">R$ 0,00</strong></span>
        </div>

        <!-- Chave Copia e Cola -->
        <div class="flex flex-col gap-2">
            <label class="text-caption font-semibold text-muted uppercase">Código Copia e Cola</label>
            <div class="flex gap-2">
                <input id="pix-copia-cola" type="text" readonly class="w-full text-caption font-mono bg-surface-subtle border border-input rounded-md px-3 py-2 text-text select-all">
                <button id="btn-copiar-pix" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-surface-subtle hover:bg-border text-text font-semibold border border-border text-label-md">
                    <?= icon('copy', ['class' => 'size-4']) ?>
                    <span>Copiar</span>
                </button>
            </div>
            <span id="msg-copiado" class="text-xs text-emerald-500 font-semibold hidden">Código PIX copiado com sucesso!</span>
        </div>

        <!-- Status do Pagamento (Aguardando) -->
        <div class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg bg-primary/10 border border-primary/20 text-primary text-caption font-semibold">
            <span class="size-2 rounded-full bg-primary animate-ping"></span>
            <span>Aguardando compensação do banco…</span>
        </div>

        <!-- Botão de Simulação / Teste Rápido -->
        <div class="border-t border-border pt-4">
            <button id="btn-confirmar-teste" type="button" class="w-full py-2.5 px-4 rounded-lg bg-surface-subtle hover:bg-border border border-border text-caption font-bold text-text transition-colors flex items-center justify-center gap-2">
                <?= icon('check', ['class' => 'size-4 text-emerald-500']) ?>
                <span>Simular Confirmação Instantânea (Ambiente de Testes)</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectPlano = document.getElementById('select-plano');
    const btnPagar = document.getElementById('btn-pagar-assinatura');
    const btnLabel = document.getElementById('btn-label');
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

    selectPlano.addEventListener('change', () => {
        const opt = selectPlano.options[selectPlano.selectedIndex];
        const nome = opt.getAttribute('data-nome');
        const valor = parseFloat(opt.getAttribute('data-valor')).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        btnLabel.textContent = `Pagar assinatura (${nome}) - R$ ${valor}/mês`;
    });

    btnPagar.addEventListener('click', async () => {
        const planoId = selectPlano.value;
        btnPagar.disabled = true;
        btnPagar.classList.add('opacity-75');

        try {
            const formData = new FormData();
            formData.append('plano_id', planoId);
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
                
                // Gera o QR Code visual via API segura de renderização de QRCode
                qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.qr_code)}`;

                modalPix.classList.remove('hidden');

                // Inicia polling para verificar pagamento a cada 3 segundos
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
