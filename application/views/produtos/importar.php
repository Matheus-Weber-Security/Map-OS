<?php
/**
 * Tela de Envio de Arquivo CSV para Importação de Produtos (Etapa 1).
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
?>
<div class="flex max-w-3xl flex-col gap-6 pt-2 pb-8 mx-auto w-full">
    <header>
        <div class="flex items-center gap-2 mb-2">
            <a href="<?= e(site_url('produtos')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                <?= icon('chevron-left', ['class' => 'size-4']) ?> Voltar para Produtos
            </a>
        </div>
        <h1 class="font-display text-heading-xl text-text">Importar produtos via CSV</h1>
        <p class="text-caption text-muted">Envie um arquivo CSV com seus produtos para cadastrá-los em lote no estoque da sua empresa.</p>
    </header>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-border bg-surface-subtle p-4">
        <div class="flex items-start gap-3">
            <?= icon('info', ['class' => 'size-5 shrink-0 mt-0.5 text-primary']) ?>
            <div>
                <p class="text-label-md font-medium text-text">Planilha modelo recomendada</p>
                <p class="text-caption text-muted">Baixe o modelo com os cabeçalhos sugeridos para agilizar o mapeamento de colunas.</p>
            </div>
        </div>
        <div>
            <?= component('button', [
                'label' => 'Baixar modelo (.csv)',
                'icon' => 'download',
                'variant' => 'outline',
                'href' => site_url('produtos/modeloCsv'),
            ]) ?>
        </div>
    </div>

    <form method="post" action="<?= e(site_url('produtos/processarCsv')) ?>" enctype="multipart/form-data" novalidate class="flex flex-col gap-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-4">Selecione o arquivo CSV</h2>

            <div class="flex flex-col gap-2">
                <label for="arquivo_csv" class="text-label-md text-text">Arquivo (.csv) *</label>
                <input type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv,text/csv,text/plain" required
                       class="block w-full rounded-sm border border-input bg-field px-3 py-2 text-body-md text-text file:mr-3 file:rounded-sm file:border-0 file:bg-surface-subtle file:px-3 file:py-1 file:text-label-md file:text-text focus:outline-3 focus:outline-offset-0 focus:outline-ring cursor-pointer">
                <p class="text-caption text-muted">Formatos suportados: .csv delimitado por vírgula ou ponto e vírgula (máximo 50 MB).</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('produtos')]) ?>
                <?= component('button', [
                    'label' => 'Avançar para mapeamento',
                    'icon' => 'chevron-right',
                    'type' => 'submit',
                    'attrs' => ['data-rotulo-carregando' => 'Lendo arquivo...'],
                ]) ?>
            </div>
        </section>
    </form>
</div>
