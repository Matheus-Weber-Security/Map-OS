<?php
/**
 * Escolha de oficina / empresa na Área do Cliente.
 *
 * Exibida quando o cliente possui cadastro em múltiplas oficinas/empresas.
 *
 * @var string       $clienteNome
 * @var list<object> $empresas
 * @var int          $empresaAtivaId
 */
$entrada = [
    'titulo' => 'Escolha a Oficina',
    'secundaria' => [
        'label' => 'Sair da conta',
        'href' => site_url('mine/sair'),
        'icon' => 'log-out',
    ],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 py-8 sm:py-12 lg:px-8">
        <div class="mb-8 flex w-full flex-col items-center text-center">
            <span class="mb-3 rounded-xs border border-hairline-violet bg-night px-2.5 py-1 text-caption text-on-dark">
                Área do Cliente
            </span>
            <h1 class="font-display text-display-hero-sm font-bold text-on-dark sm:text-display-large">
                Escolha a <span class="rounded-xs bg-accent-lime px-2.5 text-ink">oficina</span>
            </h1>
            <p class="mt-2 max-w-lg text-body-md text-on-dark-muted">
                Olá, <strong class="text-on-dark"><?= e($clienteNome) ?></strong>! Seu cadastro está vinculado a mais de uma empresa. Selecione em qual deseja navegar:
            </p>
        </div>

        <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($empresas as $emp) {
                $isAtiva = ((int) $emp->empresa_id === (int) $empresaAtivaId);
            ?>
                <div class="group relative flex flex-col justify-between rounded-xxl border border-hairline-violet bg-night p-5 shadow-lg transition-all hover:border-primary">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <?php if (! empty($emp->logo)) { ?>
                                <img src="<?= e($emp->logo) ?>" alt="<?= e($emp->nome) ?>" class="size-10 rounded-lg bg-surface p-1 object-contain">
                            <?php } else { ?>
                                <div class="flex size-10 items-center justify-center rounded-lg bg-surface-subtle text-primary">
                                    <?= icon('wrench', ['class' => 'size-5']) ?>
                                </div>
                            <?php } ?>

                            <?php if ($isAtiva) { ?>
                                <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-caption font-semibold text-emerald-400">
                                    Ativa
                                </span>
                            <?php } elseif ((int) $emp->os_andamento > 0) { ?>
                                <span class="rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-caption font-semibold text-primary">
                                    <?= (int) $emp->os_andamento ?> OS em aberto
                                </span>
                            <?php } ?>
                        </div>

                        <div>
                            <h2 class="font-display text-heading-sm font-bold text-text transition-colors group-hover:text-primary">
                                <?= e($emp->nome) ?>
                            </h2>
                            <?php if (! empty($emp->documento)) { ?>
                                <p class="mt-0.5 text-caption text-muted"><?= e($emp->documento) ?></p>
                            <?php } ?>
                            <?php if (! empty($emp->cidade)) { ?>
                                <p class="text-caption text-muted"><?= e($emp->cidade . (! empty($emp->uf) ? ' - ' . $emp->uf : '')) ?></p>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-2 border-t border-border pt-3">
                        <span class="text-caption text-muted">
                            <?= (int) $emp->total_os ?> <?= (int) $emp->total_os === 1 ? 'OS' : 'OSs' ?>
                        </span>
                        <a href="<?= e(site_url('mine/selecionarEmpresa/' . $emp->cliente_id)) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-caption font-bold text-on-primary shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-3 focus-visible:outline-ring">
                            <span>Acessar</span>
                            <?= icon('chevron-right', ['class' => 'size-3.5']) ?>
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="mt-8 text-center">
            <a href="<?= e(site_url('mine/sair')) ?>" class="text-caption font-semibold text-on-dark-muted transition-colors hover:text-on-dark">
                Sair da conta
            </a>
        </div>
    </main>
<?php
include APPPATH . 'views/entrada/fim.php';
