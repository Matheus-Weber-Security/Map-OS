<!DOCTYPE html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selecionar Empresa - Nexus Ticket</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/img/favicon-32x32.png?v=2')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('assets/img/favicon-16x16.png?v=2')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/img/apple-touch-icon.png?v=2')) ?>">
    <link rel="manifest" href="<?= e(base_url('assets/manifest.json')) ?>">
    <meta name="theme-color" content="#7c3aed">
    <link rel="stylesheet" href="<?= e(base_url('assets/dist/app.css')) ?>">
</head>
<body class="h-full bg-slate-950 text-slate-100 flex flex-col justify-center items-center p-4 sm:p-6 antialiased">
    <div class="w-full max-w-2xl flex flex-col gap-6">
        <!-- Logo / Cabeçalho -->
        <div class="text-center flex flex-col items-center">
            <img src="<?= e(base_url('assets/img/logo-sistema.png')) ?>" alt="Logo" class="size-16 rounded-2xl object-contain mb-3 shadow-lg">
            <h1 class="font-display text-heading-xl font-bold text-white">Escolha a Empresa</h1>
            <p class="text-body-md text-slate-400 mt-1 max-w-md">
                Olá, <strong class="text-white"><?= e($usuarioNome) ?></strong>! Seu e-mail está vinculado a mais de uma empresa. Selecione em qual deseja trabalhar:
            </p>
        </div>

        <!-- Grade de Empresas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($empresas as $emp) { 
                $papel = ! empty($emp->papel) ? ucfirst($emp->papel) : 'Membro';
                $dias = (int) ($emp->dias_restantes ?? 0);
            ?>
                <div class="rounded-xl border border-slate-800 bg-slate-900/90 hover:border-primary/50 transition-all p-5 flex flex-col justify-between gap-4 shadow-lg group">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                <?= e($papel) ?>
                            </span>
                            <?php if ($dias > 0) { ?>
                                <span class="text-xs text-emerald-400 font-semibold flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                    Ativa
                                </span>
                            <?php } else { ?>
                                <span class="text-xs text-red-400 font-semibold flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-red-400"></span>
                                    Vencida
                                </span>
                            <?php } ?>
                        </div>

                        <h3 class="font-display text-heading-sm font-bold text-white mt-3 group-hover:text-primary transition-colors">
                            <?= e($emp->nome_fantasia ?: $emp->razao_social) ?>
                        </h3>
                        <p class="text-caption text-slate-400 mt-0.5">
                            <?= ! empty($emp->documento) ? e($emp->documento) : e($emp->email ?: 'Sem documento cadastrado') ?>
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                        <span class="text-caption text-slate-400 font-medium"><?= $dias ?> dias restantes</span>
                        <a href="<?= site_url('login/selecionarEmpresa/' . $emp->id) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-primary hover:bg-primary/90 text-on-primary text-caption font-bold px-3.5 py-2 shadow-xs transition-colors">
                            <span>Acessar</span>
                            <?= icon('chevron-right', ['class' => 'size-3.5']) ?>
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php if ($isSuperadmin) { ?>
            <div class="text-center pt-2">
                <a href="<?= site_url('saas/gerenciar') ?>" class="inline-flex items-center gap-2 text-caption font-bold text-slate-400 hover:text-white transition-colors">
                    <?= icon('settings', ['class' => 'size-4 text-primary']) ?>
                    <span>Acessar Painel Master do SaaS (Gerenciar)</span>
                </a>
            </div>
        <?php } ?>

        <div class="text-center">
            <a href="<?= site_url('login/sair') ?>" class="text-caption font-semibold text-slate-500 hover:text-slate-300 transition-colors">
                Sair da conta
            </a>
        </div>
    </div>
</body>
</html>
