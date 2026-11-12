<?php
/**
 * Histórico e fila de e-mails enviados pela empresa (#2846), com isolamento
 * multi-tenant, envio imediato e log de leitura.
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var array                 $paginacao
 */
$temFiltro = $filtros !== [];
$colunas = [
    ['key' => 'id', 'label' => 'Nº', 'align' => 'right', 'nowrap' => true],
    ['label' => 'Para', 'class' => 'min-w-56 [overflow-wrap:anywhere]', 'render' => fn ($m) => (string) $m->to],
    ['label' => 'Assunto', 'class' => 'min-w-40 [overflow-wrap:anywhere]', 'hide_until' => 'md', 'render' => fn ($m) => emailFilaAssunto($m->headers) ?: '—'],
    ['label' => 'Enviado em', 'align' => 'right', 'nowrap' => true, 'hide_until' => 'lg', 'render' => fn ($m) => ! empty($m->enviado_em) ? date('d/m/Y H:i', strtotime((string) $m->enviado_em)) : ($m->date ? date('d/m/Y H:i', strtotime((string) $m->date)) : '—')],
    ['label' => 'Situação', 'nowrap' => true, 'render' => fn ($m) => component('pill-status', emailFilaPill($m->status))],
    ['label' => 'Leitura', 'nowrap' => true, 'hide_until' => 'md', 'render' => fn ($m) => component('pill-status', emailFilaLeituraPill((int) ($m->total_leituras ?? 0), $m->status ?? null))],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => fn ($m) => [
        component('button', [
            'label' => 'Log',
            'icon' => 'history',
            'icon_only' => true,
            'variant' => 'ghost',
            'size' => 'sm',
            'attrs' => [
                'data-modal-abrir' => 'log-email',
                'data-valor-id' => (string) $m->id,
                'data-valor-para' => (string) $m->to,
                'data-valor-assunto' => emailFilaAssunto($m->headers) ?: '—',
                'data-email-log-url' => site_url('mapos/logEmail/' . (int) $m->id),
                'title' => 'Ver log e leitura',
                'aria-label' => 'Log do e-mail ' . (int) $m->id,
            ],
        ]),
        component('button', [
            'label' => 'Excluir e-mail ' . (int) $m->id,
            'icon' => 'trash-2',
            'icon_only' => true,
            'variant' => 'ghost',
            'size' => 'sm',
            'class' => 'hover:text-danger-ink',
            'attrs' => [
                'data-modal-abrir' => 'excluir-email',
                'data-valor-id' => (string) $m->id,
                'data-valor-para' => (string) $m->to,
                'title' => 'Excluir registro',
            ],
        ]),
    ]],
];

$corpoModalLog = new HtmlSeguro(
    '<div class="flex flex-col gap-3">'
    . '<div class="rounded-md border border-border bg-surface-subtle p-3">'
    . '<p class="text-caption text-muted">Para</p>'
    . '<p class="text-sm font-medium text-text [overflow-wrap:anywhere]" data-modal-valor="para">—</p>'
    . '<p class="mt-2 text-caption text-muted">Assunto</p>'
    . '<p class="text-sm text-text [overflow-wrap:anywhere]" data-modal-valor="assunto">—</p>'
    . '</div>'
    . '<div class="flex flex-col gap-3" data-email-log-conteudo>'
    . '<p class="text-sm text-muted">Selecione um e-mail para consultar o histórico.</p>'
    . '</div>'
    . '</div>'
);
?>
<div class="flex flex-col gap-4 pt-2 pb-8" <?= js_module('emails/lista') ?>>
    <header>
        <h1 class="font-display text-heading-xl text-text">E-mails enviados</h1>
        <p class="text-caption text-muted" aria-live="polite"><?= e(($total === 1 ? '1 e-mail' : number_format($total, 0, ',', '.') . ' e-mails') . ($temFiltro ? ' com o filtro' : '')) ?>. Os disparos são imediatos e você pode acompanhar o log de entrega e leitura.</p>
    </header>

    <form method="get" action="<?= e(site_url('mapos/emails')) ?>" role="search" aria-label="Filtrar e-mails" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[14rem_auto]">
        <?= component('select', ['name' => 'status', 'label' => 'Situação', 'placeholder' => 'Todas', 'options' => ['sent' => 'Enviados', 'failed' => 'Falharam', 'sending' => 'Enviando', 'pending' => 'Na fila'], 'selected' => $filtros['status'] ?? null]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('mapos/emails')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'caption' => 'Histórico de e-mails enviados',
        'empty' => component('empty-state', [
            'title' => $temFiltro ? 'Nenhum e-mail nesta situação' : 'Nenhum e-mail enviado ainda',
            'message' => $temFiltro ? 'Escolha outra situação ou limpe o filtro.' : 'Os e-mails enviados para clientes (OS, cobranças e avisos) aparecem aqui com o histórico de leitura.',
            'icon' => 'mail',
            'class' => 'border-0',
        ]),
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?= component('modal', [
    'id' => 'log-email',
    'title' => 'Log de envio e leitura',
    'size' => 'lg',
    'body' => $corpoModalLog,
    'footer' => component('button', ['label' => 'Fechar', 'variant' => 'outline', 'attrs' => ['data-modal-fechar' => true]]),
]) ?>

<form id="form-excluir-email" method="post" action="<?= e(site_url('mapos/excluirEmail') . listagemQuery($filtros)) ?>" hidden>
    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
    <input type="hidden" name="id" value="" data-modal-de="excluir-email" data-modal-valor="id">
</form>
<?= component('modal-confirm', [
    'id' => 'excluir-email',
    'title' => 'Excluir registro de e-mail?',
    'message' => ['O registro do e-mail para ', new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="para"></strong>'), ' será removido do histórico.'],
    'confirm_label' => 'Excluir',
    'confirm_attrs' => ['form' => 'form-excluir-email'],
]) ?>
