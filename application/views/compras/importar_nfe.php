<?php
/**
 * Tela de Upload e Importação de NF-e (XML) (#Compras-01).
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
?>
<div class="flex max-w-3xl flex-col gap-6 pt-2 pb-8 mx-auto w-full">
    <header>
        <div class="flex items-center gap-2 mb-2">
            <a href="<?= e(site_url('compras')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                <?= icon('chevron-left', ['class' => 'size-4']) ?> Voltar para Compras
            </a>
        </div>
        <h1 class="font-display text-heading-xl text-text">Importar NF-e (Entrada de Estoque)</h1>
        <p class="text-caption text-muted">Carregue o arquivo XML da nota fiscal emitida pelo fornecedor para dar entrada automatizada no estoque.</p>
    </header>

    <div class="rounded-xl border border-info-soft bg-info-subtle/20 p-4 text-info-ink flex items-start gap-3">
        <?= icon('info', ['class' => 'size-5 shrink-0 mt-0.5 text-primary']) ?>
        <div class="text-caption">
            <strong class="font-medium">Processamento seguro em memória:</strong>
            O arquivo XML da NF-e é lido e validado em tempo real e descartado imediatamente após a leitura. Nenhum arquivo residual é mantido no disco do servidor.
        </div>
    </div>

    <form method="post" action="<?= e(site_url('compras/processarXmlNfe')) ?>" enctype="multipart/form-data" class="flex flex-col gap-6">
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-4">Selecione o arquivo XML da NF-e</h2>

            <div class="flex flex-col items-center justify-center border-2 border-dashed border-border rounded-xl p-8 hover:border-primary transition-colors bg-surface-subtle/30 text-center">
                <?= icon('upload', ['class' => 'size-10 text-muted mb-3']) ?>
                <label for="xml_nfe" class="cursor-pointer">
                    <span class="text-label-md font-medium text-primary hover:underline">Clique para selecionar o arquivo XML</span>
                    <span class="text-caption text-muted block mt-1">Formatos suportados: .xml (NF-e modelo 55 ou 65)</span>
                    <input type="file" id="xml_nfe" name="xml_nfe" accept=".xml,text/xml" required class="sr-only" onchange="document.getElementById('nome-arquivo').textContent = this.files[0] ? this.files[0].name : '';">
                </label>
                <div id="nome-arquivo" class="mt-3 text-caption font-medium text-text"></div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('compras')]) ?>
                <?= component('button', ['label' => 'Avançar e ler produtos do XML', 'icon' => 'chevron-right', 'type' => 'submit', 'variant' => 'primary']) ?>
            </div>
        </section>
    </form>
</div>
