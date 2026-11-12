<?php
/**
 * Exibição única dos 10 códigos de recuperação após vincular o autenticador
 * no cadastro de empresa ou no primeiro acesso.
 *
 * @var list<string> $codigos
 * @var string       $email
 * @var string       $destino
 */
$entrada = [
    'titulo' => 'Códigos de recuperação',
    'secundaria' => ['label' => 'Acessar o painel', 'href' => $destino, 'icon' => 'check'],
];
include APPPATH . 'views/entrada/inicio.php';
?>
    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-6 lg:py-8" <?= js_module('login/dois-fatores-cadastro') ?>>
        <header class="mb-5">
            <span class="mb-2 inline-block rounded-xs bg-night px-2 py-1 text-caption text-on-dark">Autenticador ativado com sucesso</span>
            <h1 class="font-display font-bold tracking-tight text-on-dark titulo-cadastrar-empresa">
                Guarde seus <span class="rounded-xs bg-accent-lime px-2 py-1 text-ink">códigos</span>
            </h1>
            <p class="mt-1.5 text-sm text-on-dark-muted">
                Estes códigos substituem o aplicativo autenticador caso você perca ou troque de celular. Eles não serão exibidos novamente.
            </p>
        </header>

        <div class="superficie-entrada flex flex-col gap-4 rounded-xxl border border-hairline-violet bg-night p-5 text-text sm:p-6">
            <?= component('alert', [
                'variant' => 'warning',
                'message' => 'Salve ou imprima em um local seguro agora. Cada código pode ser usado uma única vez para entrar ou recuperar sua senha.',
            ]) ?>

            <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3" data-codigos>
                <?php foreach ($codigos as $codigo) { ?>
                    <li class="rounded-md border border-border bg-surface-subtle px-3 py-2 text-center font-mono text-sm text-text"><?= e($codigo) ?></li>
                <?php } ?>
            </ul>

            <div class="flex flex-wrap gap-2 pt-1">
                <?= component('button', [
                    'label' => 'Copiar todos',
                    'variant' => 'outline',
                    'size' => 'md',
                    'icon' => 'copy',
                    'class' => 'flex-1',
                    'attrs' => ['data-copiar-codigos' => true],
                ]) ?>
                <?= component('button', [
                    'label' => 'Baixar (.txt)',
                    'variant' => 'outline',
                    'size' => 'md',
                    'icon' => 'download',
                    'class' => 'flex-1',
                    'attrs' => ['data-baixar-codigos' => true, 'data-conta-email' => (string) $email],
                ]) ?>
            </div>

            <div class="border-t border-border pt-4">
                <?= component('button', [
                    'label' => 'Guardei os códigos, acessar o sistema',
                    'href' => $destino,
                    'size' => 'lg',
                    'icon' => 'check',
                    'class' => 'w-full shadow-elev-3',
                ]) ?>
            </div>
        </div>
    </main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
