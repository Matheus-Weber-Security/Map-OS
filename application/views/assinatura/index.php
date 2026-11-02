<div class="bg-[#070d18] text-[#f1f5f9] min-h-screen -m-4 sm:-m-6 p-6 sm:p-10 flex flex-col gap-6 font-sans">
    <!-- Cabeçalho da Página -->
    <div>
        <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-[#00d2ff] tracking-tight">Assinatura</h1>
        <p class="text-sm text-[#64748b] mt-1 font-medium">Gerencie seu plano, tipo de assinatura, forma de pagamento e renovação</p>
    </div>

    <!-- Card Principal: Status do Plano (Fiel à Imagem 1 do Finance) -->
    <div class="rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 sm:p-8 shadow-2xl flex flex-col gap-6 max-w-4xl">
        <div class="flex items-center gap-2.5 pb-4 border-b border-[#1e293b]">
            <svg class="size-5 text-[#00d2ff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            <h2 class="font-display text-lg font-bold text-[#f1f5f9]">Status do Plano</h2>
        </div>

        <!-- Grade de Indicadores (Topo - 3 Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Status -->
            <div class="rounded-xl border border-[#1e293b] bg-[#070d18] p-5 flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">STATUS</span>
                <div class="mt-3">
                    <?php if ($diasRestantes > 0) { ?>
                        <span class="font-display text-xl font-extrabold text-[#34d399]">Ativa</span>
                    <?php } else { ?>
                        <span class="font-display text-xl font-extrabold text-[#f43f5e]">Vencida</span>
                    <?php } ?>
                </div>
            </div>

            <!-- 2. Tipo de Assinatura -->
            <div class="rounded-xl border border-[#1e293b] bg-[#070d18] p-5 flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">TIPO DE ASSINATURA</span>
                <div class="mt-2 relative">
                    <select id="select-plano" class="w-full appearance-none rounded-lg border border-[#1e293b] bg-[#0c1527] px-3.5 py-2 text-xs font-bold text-[#f1f5f9] focus:outline-none focus:border-[#00d2ff] cursor-pointer">
                        <?php foreach ($planos as $p) { ?>
                            <option value="<?= (int) $p->id ?>" data-valor="<?= number_format((float) $p->valor, 2, '.', '') ?>" data-nome="<?= e($p->nome) ?>" <?= ((int) $p->id === (int) ($planoAtual->id ?? 0)) ? 'selected' : '' ?>>
                                ⭐ <?= e($p->nome) ?> (R$ <?= number_format((float) $p->valor, 2, ',', '.') ?>/mês)
                            </option>
                        <?php } ?>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-4 text-[#64748b]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>

            <!-- 3. Dias Restantes -->
            <div class="rounded-xl border border-[#1e293b] bg-[#070d18] p-5 flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">DIAS RESTANTES</span>
                <div class="mt-3">
                    <span class="font-display text-xl font-extrabold <?= $diasRestantes > 0 ? 'text-[#34d399]' : 'text-[#f43f5e]' ?>">
                        <?= (int) $diasRestantes ?> dias
                    </span>
                </div>
            </div>
        </div>

        <!-- Grade Secundária (Meio - 2 Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Forma de Pagamento -->
            <div class="rounded-xl border border-[#1e293b] bg-[#070d18] p-5 flex flex-col justify-between gap-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">FORMA DE PAGAMENTO</span>
                    <div class="mt-3">
                        <span class="font-display text-lg font-bold text-[#00d2ff]">PIX</span>
                    </div>
                </div>
                <div>
                    <button type="button" class="text-xs font-bold text-[#94a3b8] hover:text-[#00d2ff] border border-[#1e293b] bg-[#0c1527] px-3.5 py-1.5 rounded-lg transition-colors">
                        Alterar Forma de Pagamento
                    </button>
                </div>
            </div>

            <!-- Vencimento da Assinatura -->
            <div class="rounded-xl border border-[#1e293b] bg-[#070d18] p-5 flex flex-col justify-between gap-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#64748b]">VENCIMENTO DA ASSINATURA</span>
                    <div class="mt-3">
                        <span class="font-display text-lg font-bold text-[#00d2ff]">
                            <?= ! empty($empresa->data_vencimento) ? e(date('d/m/Y', strtotime($empresa->data_vencimento))) : 'Não definido' ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center justify-between text-xs pt-2 border-t border-[#1e293b]">
                    <span class="text-[#64748b]">Valor do Plano (<?= e($planoAtual->nome ?? 'Essencial') ?>):</span>
                    <span class="font-bold text-[#00d2ff]">
                        R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês
                    </span>
                </div>
            </div>
        </div>

        <!-- Checkbox de Cobrança Automática -->
        <div class="pt-2">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" checked class="mt-0.5 size-4 rounded border-[#1e293b] bg-[#070d18] text-[#1d4ed8] focus:ring-0">
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-[#f1f5f9]">Cobrança automática</span>
                    <span class="text-xs text-[#64748b]">Você receberá um lembrete por email 1 dia antes do vencimento da sua assinatura.</span>
                </div>
            </label>
        </div>

        <!-- Botão Verde Largo (Fiel à Imagem 1 do Finance) -->
        <div class="pt-2">
            <button id="btn-pagar-assinatura" type="button" class="w-full flex items-center justify-center gap-2.5 rounded-xl bg-[#10b981] hover:bg-[#059669] active:bg-[#047857] text-white font-bold py-4 px-6 shadow-xl transition-all text-sm tracking-wide">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                <span id="btn-label">Pagar assinatura (<?= e($planoAtual->nome ?? 'Essencial') ?>) - R$ <?= number_format($valorFinal, 2, ',', '.') ?>/mês</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal PIX de Pagamento (Fiel ao Finance Dark) -->
<div id="modal-pix" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4 hidden">
    <div class="relative w-full max-w-md rounded-2xl border border-[#1e293b] bg-[#0c1527] p-6 sm:p-8 shadow-2xl flex flex-col gap-6 text-white">
        <button id="btn-fechar-modal" class="absolute top-4 right-4 text-[#64748b] hover:text-white">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>

        <div class="text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#10b981]/15 text-[#10b981] mb-3">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <h3 class="font-display text-2xl font-bold text-[#00d2ff]">Pagamento via PIX</h3>
            <p class="text-xs text-[#94a3b8] mt-1">Escaneie o QR Code ou copie o código para liberar <strong class="text-white">+30 dias</strong> imediatamente.</p>
        </div>

        <div class="flex flex-col items-center justify-center p-4 bg-[#070d18] rounded-xl border border-[#1e293b]">
            <div class="size-48 bg-white p-2 rounded-lg flex items-center justify-center shadow-inner">
                <img id="pix-qrcode-img" src="" alt="QR Code PIX" class="size-full object-contain">
            </div>
            <span class="text-xs text-[#94a3b8] mt-3">Valor da Renovação: <strong id="pix-valor" class="text-[#34d399] font-bold text-base">R$ 0,00</strong></span>
        </div>

        <!-- Chave Copia e Cola -->
        <div class="flex flex-col gap-2">
            <label class="text-[11px] font-bold text-[#64748b] uppercase">Código Copia e Cola</label>
            <div class="flex gap-2">
                <input id="pix-copia-cola" type="text" readonly class="w-full text-xs font-mono bg-[#070d18] border border-[#1e293b] rounded-lg px-3.5 py-2.5 text-[#cbd5e1] select-all">
                <button id="btn-copiar-pix" type="button" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1e293b] hover:bg-[#334155] text-white font-bold text-xs transition-colors shrink-0">
                    <span>Copiar</span>
                </button>
            </div>
            <span id="msg-copiado" class="text-xs text-[#34d399] font-semibold hidden">Código PIX copiado com sucesso!</span>
        </div>

        <!-- Status do Pagamento (Aguardando) -->
        <div class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg bg-[#00d2ff]/10 border border-[#00d2ff]/20 text-[#00d2ff] text-xs font-semibold">
            <span class="size-2 rounded-full bg-[#00d2ff] animate-ping"></span>
            <span>Aguardando compensação do banco…</span>
        </div>

        <!-- Botão de Simulação Instantânea em Testes -->
        <div class="border-t border-[#1e293b] pt-4">
            <button id="btn-confirmar-teste" type="button" class="w-full py-2.5 px-4 rounded-xl bg-[#070d18] hover:bg-[#1e293b] border border-[#1e293b] text-xs font-bold text-[#cbd5e1] transition-colors flex items-center justify-center gap-2">
                <svg class="size-4 text-[#34d399]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
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
