<?php
/**
 * Menu suspenso de ações de linha (botão retangular vertical com 3 pontos).
 *
 * Agrupa ações de uma linha de listagem sem alargar a tabela nem ser cortado
 * pelo overflow-x-auto de data-table: o módulo JS `componentes/menu-acoes`
 * posiciona o painel em `position: fixed` respeitando a viewport e o zoom.
 *
 * Cada entrada de `$items` pode ser:
 * - `['separador' => true]`
 * - `[
 *       'label' => string,
 *       'icon' => ?string,
 *       'href' => ?string,
 *       'disabled' => ?bool,
 *       'description' => ?string,
 *       'danger' => ?bool,
 *       'class' => ?string,
 *       'attrs' => ?array,
 *   ]`
 *
 * @var string      $label  Rótulo acessível do botão (ex.: "Ações da OS 1")
 * @var list<array> $items  Lista de itens ou separadores do menu
 * @var string      $icon   Ícone Lucide do gatilho (padrão ellipsis-vertical)
 * @var string|null $id
 * @var string      $class
 * @var array       $attrs
 */
if (! is_array($items)) {
    throw new InvalidArgumentException('A prop items de menu-acoes deve ser um array.');
}

// Remove separadores sobrando no começo, no fim ou duplicados em sequência.
$itensLimpos = [];
foreach ($items as $item) {
    if (! is_array($item)) {
        throw new InvalidArgumentException('Cada item de menu-acoes deve ser um array.');
    }
    if (! empty($item['separador'])) {
        if ($itensLimpos !== [] && empty(end($itensLimpos)['separador'])) {
            $itensLimpos[] = ['separador' => true];
        }
        continue;
    }
    if (empty($item['label']) || ! is_string($item['label'])) {
        throw new InvalidArgumentException('Cada item de menu-acoes precisa de label.');
    }
    $itensLimpos[] = $item;
}
if ($itensLimpos !== [] && ! empty(end($itensLimpos)['separador'])) {
    array_pop($itensLimpos);
}

$atributosRaiz = componenteMesclarAtributos([
    'id' => $id,
    'class' => componenteClasses('relative inline-flex', $class),
    'data-menu-acoes' => true,
    'data-module' => 'componentes/menu-acoes',
], $attrs);
?>
<div<?= componenteAtributos($atributosRaiz) ?>>
    <button
        type="button"
        class="v5-menu-acoes-gatilho rounded-md border border-hairline-cool bg-transparent text-muted transition-colors hover:bg-surface-subtle hover:text-text focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-ring dark:border-input"
        aria-label="<?= e($label) ?>"
        title="<?= e($label) ?>"
        aria-haspopup="menu"
        aria-expanded="false"
        data-menu-acoes-gatilho
    >
        <?= icon($icon, ['class' => 'size-4']) ?>
        <span class="sr-only"><?= e($label) ?></span>
    </button>

    <div
        class="v5-menu-acoes-painel fixed z-50 flex flex-col gap-0.5 rounded-xl border border-border bg-surface p-1.5 shadow-overlay"
        role="menu"
        aria-label="<?= e($label) ?>"
        hidden
        data-menu-acoes-painel
    >
        <?php foreach ($itensLimpos as $item) { ?>
            <?php if (! empty($item['separador'])) { ?>
                <div class="my-1 border-t border-border" role="separator"></div>
                <?php continue; ?>
            <?php } ?>
            <?php
            $desabilitado = ! empty($item['disabled']);
            $perigo = ! empty($item['danger']);
            $href = ! $desabilitado && ! empty($item['href']) ? (string) $item['href'] : null;
            $iconeItem = isset($item['icon']) && $item['icon'] !== '' ? (string) $item['icon'] : null;
            $descricao = isset($item['description']) && trim((string) $item['description']) !== ''
                ? trim((string) $item['description'])
                : null;
            $attrsItem = is_array($item['attrs'] ?? null) ? $item['attrs'] : [];

            $baseItem = [
                'class' => componenteClasses('v5-menu-acoes-item', $item['class'] ?? ''),
                'role' => 'menuitem',
                'data-menu-acoes-item' => true,
                'data-perigo' => $perigo ? 'true' : null,
            ];

            if ($href !== null) {
                $tag = 'a';
                $baseItem['href'] = $href;
            } else {
                $tag = 'button';
                $baseItem['type'] = 'button';
                if ($desabilitado) {
                    $baseItem['disabled'] = true;
                    $baseItem['aria-disabled'] = 'true';
                    if ($descricao !== null && ! isset($attrsItem['title'])) {
                        $baseItem['title'] = $descricao;
                    }
                }
            }

            $classeIcone = $desabilitado
                ? 'size-4 shrink-0 text-disabled'
                : ($perigo ? 'size-4 shrink-0 text-danger-ink' : 'size-4 shrink-0 text-muted');
            ?>
            <<?= e($tag) ?><?= componenteAtributos(componenteMesclarAtributos($baseItem, $attrsItem)) ?>>
                <?php if ($iconeItem !== null) { ?>
                    <?= icon($iconeItem, ['class' => $classeIcone]) ?>
                <?php } ?>
                <span class="min-w-0 flex-1">
                    <span class="block truncate"><?= e($item['label']) ?></span>
                    <?php if ($descricao !== null) { ?>
                        <span class="v5-menu-acoes-desc"><?= e($descricao) ?></span>
                    <?php } ?>
                </span>
            </<?= e($tag) ?>>
        <?php } ?>
    </div>
</div>
