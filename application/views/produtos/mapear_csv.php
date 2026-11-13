<?php
/**
 * Tela de Mapeamento de Colunas e Prévia da Importação (Etapa 2).
 *
 * @var string                    $token
 * @var list<string>              $cabecalhos
 * @var list<array<string, string>> $previas
 * @var int                       $totalLinhas
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';

$opcoesColunasObrigatorias = ['' => '— Escolha a coluna do CSV —'] + array_combine($cabecalhos, $cabecalhos);
$opcoesColunasOpcionais = ['' => '— Não mapear (usar padrão) —'] + array_combine($cabecalhos, $cabecalhos);

$sugerir = static fn (string $campo) => produtoSugerirMapeamento($campo, $cabecalhos);
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header>
        <div class="flex items-center gap-2 mb-2">
            <a href="<?= e(site_url('produtos/importar')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                <?= icon('chevron-left', ['class' => 'size-4']) ?> Voltar e escolher outro arquivo
            </a>
        </div>
        <h1 class="font-display text-heading-xl text-text">Mapear colunas do CSV</h1>
        <p class="text-caption text-muted">Selecione qual coluna do seu arquivo corresponde a cada campo do cadastro de produtos.</p>
    </header>

    <form method="post" action="<?= e(site_url('produtos/concluirImportacao')) ?>" novalidate class="flex flex-col gap-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-1">Mapeamento dos campos do produto</h2>
            <p class="text-caption text-muted mb-4">Os campos com asterisco (*) são obrigatórios. O sistema tentou pré-selecionar as colunas com base no cabeçalho do arquivo.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <?= component('select', [
                        'name' => 'coluna_descricao',
                        'label' => 'Descrição do produto',
                        'required' => true,
                        'options' => $opcoesColunasObrigatorias,
                        'selected' => $sugerir('descricao'),
                        'help' => 'Nome principal do item.',
                        'attrs' => ['data-msg-vazio' => 'Selecione a coluna que contém a descrição do produto.'],
                    ]) ?>
                </div>

                <?= component('select', [
                    'name' => 'coluna_precoVenda',
                    'label' => 'Preço de venda (R$)',
                    'required' => true,
                    'options' => $opcoesColunasObrigatorias,
                    'selected' => $sugerir('precoVenda'),
                    'help' => 'Valor comercializado.',
                    'attrs' => ['data-msg-vazio' => 'Selecione a coluna que contém o preço de venda.'],
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_precoCompra',
                    'label' => 'Preço de compra (R$)',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('precoCompra'),
                    'help' => 'Opcional (padrão: 0,00).',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_estoque',
                    'label' => 'Estoque inicial',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('estoque'),
                    'help' => 'Opcional (padrão: 0).',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_estoqueMinimo',
                    'label' => 'Estoque mínimo',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('estoqueMinimo'),
                    'help' => 'Opcional. Dispara alerta de estoque baixo.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_codDeBarra',
                    'label' => 'Código de barras / EAN',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('codDeBarra'),
                    'help' => 'Opcional. Usado para identificar produtos duplicados.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_unidade',
                    'label' => 'Unidade de medida',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('unidade'),
                    'help' => 'Opcional (padrão: UNID).',
                ]) ?>
            </div>

            <div class="mt-6 border-t border-border pt-4">
                <h3 class="text-label-md font-medium text-text mb-2">Regra para produtos já existentes</h3>
                <?= component('select', [
                    'name' => 'estrategia_duplicados',
                    'label' => 'Quando encontrar produto já cadastrado (mesmo código de barras)',
                    'options' => [
                        'atualizar' => 'Atualizar os dados e somar a quantidade ao estoque existente',
                        'pular' => 'Ignorar e manter o produto já existente sem alterações',
                    ],
                    'selected' => 'atualizar',
                ]) ?>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('produtos')]) ?>
            <?= component('button', [
                'label' => 'Concluir importação',
                'icon' => 'check',
                'type' => 'submit',
                'attrs' => ['data-rotulo-carregando' => 'Importando produtos...'],
            ]) ?>
        </div>
    </form>

    <section class="<?= e($caixa) ?>">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-heading-sm text-text">Prévia dos dados do arquivo</h2>
                <p class="text-caption text-muted">Exibindo as primeiras <?= e(count($previas)) ?> linhas de um total de <?= e(number_format($totalLinhas, 0, ',', '.')) ?> linha(s) detectada(s).</p>
            </div>
            <span class="text-caption text-muted">Role horizontalmente ou verticalmente dentro da tabela</span>
        </div>

        <div class="max-h-80 overflow-auto rounded-lg border border-border">
            <table class="w-full text-left text-body-sm text-text">
                <thead class="sticky top-0 bg-surface-subtle text-caption font-medium uppercase text-muted">
                    <tr>
                        <th class="p-2.5 border-b border-border">#</th>
                        <?php foreach ($cabecalhos as $coluna) { ?>
                            <th class="p-2.5 border-b border-border whitespace-nowrap"><?= e($coluna) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($previas as $idx => $linha) { ?>
                        <tr class="hover:bg-surface-subtle/50">
                            <td class="p-2.5 text-muted"><?= e($idx + 1) ?></td>
                            <?php foreach ($cabecalhos as $coluna) { ?>
                                <td class="p-2.5 whitespace-nowrap"><?= e($linha[$coluna] ?? '—') ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
