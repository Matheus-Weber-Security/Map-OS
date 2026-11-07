<?php
/**
 * Campo de texto com label, ajuda e erro associados.
 *
 * @var string      $name
 * @var string      $label
 * @var string      $type           text | email | password | number | date | datetime-local | time | tel | url | search
 * @var mixed       $value
 * @var string|null $placeholder
 * @var string|null $help           Texto de ajuda abaixo do campo
 * @var string|null $error          Mensagem de erro; marca o campo como inválido
 * @var bool        $required
 * @var bool        $disabled
 * @var bool        $readonly
 * @var string|null $autocomplete
 * @var bool        $hide_label     Esconde o label visualmente (continua acessível)
 * @var bool        $alternar_senha Habilita botão para mostrar/ocultar senha quando type=password
 * @var string|null $id             Padrão: derivado do name
 * @var string      $class          Classes extras do campo
 * @var array       $attrs          Atributos extras do campo
 */
$id = $id ?? componenteId($name);
$idAjuda = $help !== null ? $id . '-ajuda' : null;
$idErro = $error !== null ? $id . '-erro' : null;

$temAlternarSenha = ($type === 'password' && ! empty($alternar_senha));

$atributos = [
    'type' => $type,
    'id' => $id,
    'name' => $name,
    'value' => $value === null ? null : (string) $value,
    'placeholder' => $placeholder,
    'autocomplete' => $autocomplete,
    'required' => $required,
    'disabled' => $disabled,
    'readonly' => $readonly,
    'aria-invalid' => $error !== null ? 'true' : null,
    'aria-describedby' => trim(($idAjuda ?? '') . ' ' . ($idErro ?? '')) ?: null,
    'class' => componenteClasses(
        'block w-full rounded-sm border bg-field px-3 py-1.5 text-body-md text-text placeholder:text-muted max-sm:min-h-11',
        $temAlternarSenha ? 'pr-10' : '',
        // DESIGN.md text-input: borda hairline-input (3:1), texto body-md 400,
        // foco com sombra interna e anel azul de 3px, erro em danger.
        'focus:outline-3 focus:outline-offset-0 focus:outline-ring focus:shadow-field-focus',
        'disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:text-muted read-only:bg-surface-subtle',
        $error !== null ? 'border-danger' : 'border-input',
        $class
    ),
];
?>
<div class="flex flex-col gap-1">
    <label for="<?= e($id) ?>" class="<?= e($hide_label ? 'sr-only' : 'text-sm font-medium text-text') ?>">
        <?= componenteConteudo($label) ?><?php if ($required) { ?> <span class="text-danger-ink" aria-hidden="true">*</span><?php } ?>
    </label>
    <?php if ($temAlternarSenha) { ?>
        <div class="relative w-full">
            <input<?= componenteAtributos(componenteMesclarAtributos($atributos, $attrs)) ?>>
            <button type="button"
                    class="absolute inset-y-0 right-3 flex items-center text-muted hover:text-text focus:outline-none cursor-pointer"
                    data-alternar-senha
                    tabindex="-1"
                    title="Ver senha"
                    aria-label="Ver senha">
                <span data-icone-olho-aberto class="inline-flex items-center pointer-events-none"><?= icon('eye', ['class' => 'size-5']) ?></span>
                <span data-icone-olho-fechado hidden class="inline-flex items-center pointer-events-none"><?= icon('eye-off', ['class' => 'size-5']) ?></span>
            </button>
        </div>
    <?php } else { ?>
        <input<?= componenteAtributos(componenteMesclarAtributos($atributos, $attrs)) ?>>
    <?php } ?>
    <?php if ($help !== null) { ?><p id="<?= e($idAjuda) ?>" class="text-caption text-muted"><?= e($help) ?></p><?php } ?>
    <?php if ($error !== null) { ?><p id="<?= e($idErro) ?>" class="flex items-start gap-1.5 text-caption text-danger-ink"><?= icon('circle-alert', ['class' => 'mt-0.5 size-4']) ?><span><?= e($error) ?></span></p><?php } ?>
</div>
