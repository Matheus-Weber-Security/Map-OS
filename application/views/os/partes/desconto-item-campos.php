<?php
/**
 * Desconto individual do item (#2880) no formulário de adicionar produto ou
 * serviço, em R$ ou %. Opcional: vazio ou 0 é sem desconto. O servidor
 * calcula o subtotal do item (osDescontoDoItem()); estes campos só enviam o
 * que foi digitado. Usado pelas telas da OS e da venda.
 */
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:col-span-3 lg:col-span-4 pt-1">
    <div class="grid gap-4 sm:grid-cols-[9rem_minmax(0,12rem)]">
        <?= component('select', [
            'name' => 'tipoDescontoItem',
            'id' => 'item-desconto-tipo',
            'label' => 'Tipo do desconto',
            'options' => ['real' => 'Em reais (R$)', 'porcento' => 'Em porcentagem (%)'],
            'selected' => 'real',
        ]) ?>
        <?= component('input', [
            'name' => 'descontoItem',
            'id' => 'item-desconto-valor',
            'label' => 'Desconto do item',
            'placeholder' => '0,00',
            'help' => 'Opcional. Vale só para este item.',
            'attrs' => [
                'inputmode' => 'decimal',
                'pattern' => '^\d+([.,]\d{1,2})?$',
                'data-msg-invalido' => 'Use só números, como 10 ou 10,50.',
            ],
        ]) ?>
    </div>
    <div class="sm:pt-7 flex justify-end">
        <?= component('button', [
            'label' => 'Adicionar',
            'icon' => 'plus',
            'variant' => 'outline',
            'type' => 'submit',
            'attrs' => ['data-rotulo-carregando' => 'Adicionando…'],
        ]) ?>
    </div>
</div>
