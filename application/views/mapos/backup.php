<?php
/**
 * Gerenciamento de Backup e Restauração (#SaaS-Backup).
 *
 * @var object|null $empresa
 * @var int         $empresaId
 * @var bool        $is_superadmin
 * @var bool        $pode_restaurar
 * @var bool        $modo_suporte_master
 */
$nomeEmpresa = $empresa ? ($empresa->nome_fantasia ?: $empresa->razao_social) : 'Empresa #' . $empresaId;
$caixa = 'rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-2xs';
$csrf = [$this->security->get_csrf_token_name(), $this->security->get_csrf_hash()];
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-10 mx-auto w-full">
    <header class="flex flex-col gap-1">
        <h1 class="font-display text-heading-xl text-text">Gerenciamento de Backup</h1>
        <p class="text-caption text-muted">Cópias de segurança e restauração de dados para <strong class="font-semibold text-text"><?= e($nomeEmpresa) ?></strong> (Código #<?= e($empresaId) ?>).</p>
    </header>

    <?php if (! empty($pode_restaurar)) { ?>
        <?= component('alert', [
            'variant' => 'warning',
            'title' => 'Modo Suporte Técnico Master Ativo',
            'message' => 'Você está conectado na loja do assinante em modo de suporte. A restauração de banco de dados está desbloqueada e substituirá os registros desta empresa pelos dados do arquivo enviado.',
        ]) ?>
    <?php } ?>

    <div class="grid gap-6 md:grid-cols-2">
        <!-- 1. Card Exportar / Baixar Backup -->
        <section class="<?= e($caixa) ?> flex flex-col justify-between">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-surface-subtle border border-border text-primary shadow-2xs">
                        <?= icon('download', ['class' => 'size-6']) ?>
                    </div>
                    <div>
                        <h2 class="font-display text-heading-sm font-semibold text-text">Exportar Cópia de Segurança</h2>
                        <p class="text-caption text-muted">Download dos dados em arquivo SQL compactado.</p>
                    </div>
                </div>

                <p class="text-body text-text">
                    Gera uma cópia completa de todos os dados operacionais da empresa: clientes, ordens de serviço, produtos, serviços, vendas, compras, lançamentos financeiros e configurações.
                </p>

                <div class="rounded-lg border border-border bg-surface-subtle p-3.5 flex flex-col gap-2 text-caption">
                    <div class="flex justify-between items-center">
                        <span class="text-muted">Empresa:</span>
                        <strong class="font-semibold text-text"><?= e($nomeEmpresa) ?></strong>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-muted">Identificador:</span>
                        <span class="font-mono text-xs font-semibold text-text">#<?= e($empresaId) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-muted">Formato do arquivo:</span>
                        <span class="font-mono text-xs text-text">ZIP (.sql UTF-8)</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-2.5">
                <?= component('button', [
                    'label' => 'Baixar backup da empresa',
                    'icon' => 'download',
                    'variant' => 'primary',
                    'href' => site_url('mapos/baixarBackup'),
                ]) ?>

                <?php if (! empty($is_superadmin) && $empresaId === 1) { ?>
                    <?= component('button', [
                        'label' => 'Baixar backup global (todas as empresas)',
                        'icon' => 'archive',
                        'variant' => 'outline',
                        'href' => site_url('mapos/baixarBackup?tipo=completo'),
                    ]) ?>
                <?php } ?>
            </div>
        </section>

        <!-- 2. Card Restaurar Backup -->
        <section class="<?= e($caixa) ?> flex flex-col justify-between">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-surface-subtle border border-border <?= $pode_restaurar ? 'text-danger-ink' : 'text-muted' ?> shadow-2xs">
                        <?= icon('history', ['class' => 'size-6']) ?>
                    </div>
                    <div>
                        <h2 class="font-display text-heading-sm font-semibold text-text">Restaurar Cópia de Dados</h2>
                        <p class="text-caption text-muted">Recuperação de registros a partir de um backup prévio.</p>
                    </div>
                </div>

                <?php if ($pode_restaurar) { ?>
                    <?= component('alert', [
                        'variant' => 'danger',
                        'message' => 'Atenção: A restauração substituirá os registros desta empresa pelos dados contidos no backup. Esta ação é destrutiva e irreversível.',
                    ]) ?>

                    <form id="form-restaurar-backup" method="post" action="<?= e(site_url('mapos/restaurarBackup')) ?>" enctype="multipart/form-data" novalidate <?= js_module('formulario/padrao') ?>>
                        <input type="hidden" name="<?= e($csrf[0]) ?>" value="<?= e($csrf[1]) ?>">
                        <?= component('input', [
                            'name' => 'arquivo_backup',
                            'label' => 'Selecione o arquivo de backup (.zip ou .sql)',
                            'type' => 'file',
                            'required' => true,
                            'help' => 'Envie o arquivo .zip ou .sql gerado previamente pelo sistema.',
                            'attrs' => ['accept' => '.zip,.sql', 'data-msg-vazio' => 'Selecione o arquivo de backup.'],
                        ]) ?>
                    </form>
                <?php } else { ?>
                    <?= component('alert', [
                        'variant' => 'info',
                        'message' => '🔒 A restauração de banco de dados é bloqueada por segurança para evitar perda acidental de informações. Apenas o Administrador Geral em modo de suporte técnico na sua loja pode realizar restaurações.',
                    ]) ?>

                    <div class="opacity-60 pointer-events-none">
                        <?= component('input', [
                            'name' => 'arquivo_backup_bloqueado',
                            'label' => 'Arquivo de backup (.zip ou .sql)',
                            'type' => 'file',
                            'disabled' => true,
                            'help' => 'Função bloqueada para usuários da empresa.',
                        ]) ?>
                    </div>
                <?php } ?>
            </div>

            <div class="mt-6">
                <?php if ($pode_restaurar) { ?>
                    <?= component('button', [
                        'label' => 'Restaurar backup da empresa',
                        'icon' => 'history',
                        'variant' => 'danger',
                        'type' => 'button',
                        'class' => 'w-full',
                        'attrs' => ['data-modal-abrir' => 'modal-confirmar-restauracao'],
                    ]) ?>
                <?php } else { ?>
                    <?= component('button', [
                        'label' => 'Restauração bloqueada',
                        'icon' => 'shield-off',
                        'variant' => 'outline',
                        'disabled' => true,
                        'class' => 'w-full cursor-not-allowed opacity-60',
                    ]) ?>
                <?php } ?>
            </div>
        </section>
    </div>

    <!-- 3. Card Boas Práticas e Segurança -->
    <section class="<?= e($caixa) ?>">
        <h2 class="font-display text-heading-sm font-semibold text-text mb-3 flex items-center gap-2">
            <?= icon('shield-check', ['class' => 'size-5 text-primary']) ?>
            Orientações de Segurança & Armazenamento
        </h2>
        <div class="grid gap-4 sm:grid-cols-3 text-caption text-muted">
            <div class="flex flex-col gap-1">
                <strong class="font-semibold text-text">Frequência recomendada</strong>
                <p>Baixe cópias de segurança periodicamente (semanal ou mensalmente) e guarde em local seguro fora do servidor.</p>
            </div>
            <div class="flex flex-col gap-1">
                <strong class="font-semibold text-text">Isolamento Multi-Tenant</strong>
                <p>O arquivo de backup gerado para a sua empresa contém apenas os seus cadastros, respeitando a privacidade dos dados.</p>
            </div>
            <div class="flex flex-col gap-1">
                <strong class="font-semibold text-text">Restauração Segura</strong>
                <p>Caso tenha enfrentado problemas e precise restaurar um backup antigo, nossa equipe técnica auxilia todo o processo.</p>
            </div>
        </div>
    </section>
</div>

<?php if ($pode_restaurar) { ?>
<?= component('modal-confirm', [
    'id' => 'modal-confirmar-restauracao',
    'title' => 'Confirmar Restauração?',
    'message' => 'Tem certeza de que deseja restaurar este backup na empresa ' . e($nomeEmpresa) . '? Os registros atuais serão substituídos pelo arquivo enviado.',
    'confirm_label' => 'Sim, restaurar dados',
    'confirm_attrs' => ['form' => 'form-restaurar-backup', 'data-rotulo-carregando' => 'Restaurando…'],
]) ?>
<?php } ?>
