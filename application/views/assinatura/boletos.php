<div class="flex flex-col gap-6 pt-2 pb-8 max-w-5xl mx-auto">
    <!-- Cabeçalho -->
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <?= component('button', [
                    'label' => 'Voltar',
                    'icon' => 'chevron-left',
                    'variant' => 'ghost',
                    'size' => 'sm',
                    'href' => site_url('assinatura'),
                ]) ?>
            </div>
            <h1 class="font-display text-heading-xl text-text">Boletos da Assinatura</h1>
            <p class="text-caption text-muted">Histórico de boletos emitidos para renovação de plano (+30 dias).</p>
        </div>
        <div class="flex items-center gap-2">
            <?= component('button', [
                'label' => 'Nova Renovação',
                'icon' => 'plus',
                'variant' => 'primary',
                'size' => 'sm',
                'href' => site_url('assinatura'),
            ]) ?>
        </div>
    </header>

    <!-- Lista de Boletos -->
    <div class="rounded-xl border border-border bg-surface p-6 shadow-2xs flex flex-col gap-4">
        <div class="flex items-center justify-between border-b border-border pb-4">
            <div class="flex items-center gap-2">
                <?= icon('barcode', ['class' => 'size-5 text-primary']) ?>
                <h2 class="font-display text-heading-sm font-semibold text-text">Meus Boletos</h2>
            </div>
            <span class="text-caption text-muted font-medium"><?= count($boletos) ?> <?= count($boletos) === 1 ? 'boleto registrado' : 'boletos registrados' ?></span>
        </div>

        <?php if (empty($boletos)) { ?>
            <div class="py-12 flex flex-col items-center justify-center text-center gap-3">
                <div class="size-12 rounded-full bg-surface-subtle flex items-center justify-center text-muted">
                    <?= icon('barcode', ['class' => 'size-6']) ?>
                </div>
                <h3 class="font-display text-base font-semibold text-text">Nenhum boleto encontrado</h3>
                <p class="text-caption text-muted max-w-sm">Você ainda não gerou boletos para renovação de assinatura. Acesse a tela de assinatura para gerar.</p>
                <div class="mt-2">
                    <?= component('button', [
                        'label' => 'Ir para Assinatura',
                        'variant' => 'outline',
                        'size' => 'sm',
                        'href' => site_url('assinatura'),
                    ]) ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-3">Boleto / Ref</th>
                            <th class="py-3 px-3">Plano</th>
                            <th class="py-3 px-3">Valor</th>
                            <th class="py-3 px-3">Vencimento</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($boletos as $b) {
                            $isVencido = ($b->status === 'pendente' && ! empty($b->data_vencimento) && strtotime($b->data_vencimento) < strtotime('today'));
                        ?>
                            <tr class="hover:bg-surface-subtle transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-text">#<?= (int) $b->id ?></span>
                                        <span class="text-caption text-muted"><?= date('d/m/Y H:i', strtotime($b->criado_em)) ?></span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-3 font-medium text-text">
                                    <?= e($b->plano_nome ?: 'Plano Mensal') ?>
                                </td>
                                <td class="py-3.5 px-3 font-bold text-text">
                                    R$ <?= number_format($b->valor, 2, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-3">
                                    <?php if (! empty($b->data_vencimento)) { ?>
                                        <span class="<?= $isVencido ? 'text-danger-ink font-bold' : 'text-text' ?>">
                                            <?= date('d/m/Y', strtotime($b->data_vencimento)) ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted">-</span>
                                    <?php } ?>
                                </td>
                                <td class="py-3.5 px-3">
                                    <?php if ($b->status === 'pago') { ?>
                                        <?= component('pill-status', ['label' => 'Pago (+30d)', 'variant' => 'success']) ?>
                                    <?php } elseif ($b->status === 'cancelado') { ?>
                                        <?= component('pill-status', ['label' => 'Cancelado', 'variant' => 'neutral']) ?>
                                    <?php } elseif ($isVencido) { ?>
                                        <?= component('pill-status', ['label' => 'Vencido', 'variant' => 'danger']) ?>
                                    <?php } else { ?>
                                        <?= component('pill-status', ['label' => 'Pendente', 'variant' => 'warning']) ?>
                                    <?php } ?>
                                </td>
                                <td class="py-3.5 px-3 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <?php if (! empty($b->linha_digitavel)) { ?>
                                            <button type="button" 
                                                    class="btn-copiar-linha p-2 rounded-lg text-muted hover:text-text hover:bg-surface border border-transparent hover:border-border transition-colors"
                                                    data-linha="<?= e($b->linha_digitavel) ?>"
                                                    title="Copiar Linha Digitável">
                                                <?= icon('copy', ['class' => 'size-4']) ?>
                                            </button>
                                        <?php } ?>

                                        <?php if (! empty($b->pdf_url)) { ?>
                                            <a href="<?= e($b->pdf_url) ?>" 
                                               target="_blank" 
                                               class="p-2 rounded-lg text-primary hover:text-primary-hover hover:bg-primary/10 transition-colors"
                                               title="Imprimir Boleto / Visualizar PDF">
                                                <?= icon('printer', ['class' => 'size-4']) ?>
                                            </a>
                                        <?php } ?>

                                        <?php if ($b->status === 'pendente') { ?>
                                            <button type="button" 
                                                    class="btn-cancelar-boleto p-2 rounded-lg text-danger-ink hover:text-danger hover:bg-danger/10 transition-colors"
                                                    data-id="<?= (int) $b->id ?>"
                                                    title="Cancelar Boleto">
                                                <?= icon('x', ['class' => 'size-4']) ?>
                                            </button>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Copiar Linha Digitável
    document.querySelectorAll('.btn-copiar-linha').forEach(btn => {
        btn.addEventListener('click', async () => {
            const linha = btn.getAttribute('data-linha');
            if (!linha) return;
            try {
                await navigator.clipboard.writeText(linha);
                alert('Linha digitável copiada com sucesso!');
            } catch (e) {
                prompt('Copie a linha digitável abaixo:', linha);
            }
        });
    });

    // Cancelar Boleto
    document.querySelectorAll('.btn-cancelar-boleto').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            if (!id) return;
            if (!confirm('Deseja realmente cancelar este boleto pendente?')) {
                return;
            }

            btn.disabled = true;
            try {
                const csrfName = '<?= e($this->security->get_csrf_token_name()) ?>';
                const csrfHash = '<?= e($this->security->get_csrf_hash()) ?>';
                const formData = new FormData();
                formData.append('id', id);
                formData.append(csrfName, csrfHash);

                const res = await fetch('<?= site_url('assinatura/cancelarBoleto') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                const data = await res.json().catch(() => null);
                if (data && data.result) {
                    alert('Boleto cancelado com sucesso.');
                    window.location.reload();
                } else {
                    alert(data?.message || 'Erro ao cancelar boleto.');
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Erro de comunicação ao cancelar boleto.');
                btn.disabled = false;
            }
        });
    });
});
</script>
