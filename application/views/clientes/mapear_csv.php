<?php
/**
 * Tela de Mapeamento de Colunas e Prévia da Importação de Clientes/Fornecedores (Etapa 2).
 *
 * @var string                      $token
 * @var list<string>                $cabecalhos
 * @var list<array<string, string>> $previas
 * @var int                         $totalLinhas
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';

$opcoesColunasObrigatorias = ['' => '— Escolha a coluna do CSV —'] + array_combine($cabecalhos, $cabecalhos);
$opcoesColunasOpcionais = ['' => '— Não mapear (deixar em branco) —'] + array_combine($cabecalhos, $cabecalhos);

$sugerir = static fn (string $campo) => clienteSugerirMapeamento($campo, $cabecalhos);
?>
<div class="flex max-w-5xl flex-col gap-6 pt-2 pb-12 mx-auto w-full">
    <header>
        <div class="flex items-center gap-2 mb-2">
            <a href="<?= e(site_url('clientes/importar')) ?>" class="text-caption text-muted hover:text-text flex items-center gap-1">
                <?= icon('chevron-left', ['class' => 'size-4']) ?> Voltar e escolher outro arquivo
            </a>
        </div>
        <h1 class="font-display text-heading-xl text-text">Mapear colunas do CSV</h1>
        <p class="text-caption text-muted">Selecione qual coluna do seu arquivo corresponde a cada campo do cadastro de clientes e fornecedores.</p>
    </header>

    <form method="post" action="<?= e(site_url('clientes/concluirImportacao')) ?>" novalidate class="flex flex-col gap-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <section class="<?= e($caixa) ?>">
            <h2 class="text-heading-sm text-text mb-1">Dados principais e contato</h2>
            <p class="text-caption text-muted mb-4">O campo Nome / Razão Social com asterisco (*) é obrigatório. O sistema pré-selecionou as colunas sugeridas pelo cabeçalho do arquivo.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <?= component('select', [
                        'name' => 'coluna_nomeCliente',
                        'label' => 'Nome / Razão Social',
                        'required' => true,
                        'options' => $opcoesColunasObrigatorias,
                        'selected' => $sugerir('nomeCliente'),
                        'help' => 'Nome do cliente ou razão social da empresa.',
                        'attrs' => ['data-msg-vazio' => 'Selecione a coluna que contém o nome ou razão social.'],
                    ]) ?>
                </div>

                <?= component('select', [
                    'name' => 'coluna_documento',
                    'label' => 'CPF ou CNPJ',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('documento'),
                    'help' => 'Opcional. Usado na identificação de duplicados e senha inicial.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_tipo',
                    'label' => 'Coluna de tipo (Cliente / Fornecedor)',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('tipo'),
                    'help' => 'Opcional. Identifica se o registro é cliente ou fornecedor.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_celular',
                    'label' => 'Celular / WhatsApp',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('celular'),
                    'help' => 'Opcional.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_telefone',
                    'label' => 'Telefone fixo',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('telefone'),
                    'help' => 'Opcional.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_email',
                    'label' => 'E-mail',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('email'),
                    'help' => 'Opcional. Usado para login e notificações.',
                ]) ?>

                <?= component('select', [
                    'name' => 'coluna_contato',
                    'label' => 'Pessoa de contato / Responsável',
                    'options' => $opcoesColunasOpcionais,
                    'selected' => $sugerir('contato'),
                    'help' => 'Opcional. Útil para contatos em fornecedores ou empresas.',
                ]) ?>
            </div>

            <div class="mt-6 border-t border-border pt-4">
                <h3 class="text-heading-xs font-medium text-text mb-1">Endereço</h3>
                <p class="text-caption text-muted mb-4">Mapeie as colunas de localização (todas opcionais).</p>

                <div class="grid gap-4 sm:grid-cols-3">
                    <?= component('select', [
                        'name' => 'coluna_cep',
                        'label' => 'CEP',
                        'options' => $opcoesColunasOpcionais,
                        'selected' => $sugerir('cep'),
                    ]) ?>

                    <div class="sm:col-span-2">
                        <?= component('select', [
                            'name' => 'coluna_rua',
                            'label' => 'Logradouro / Rua',
                            'options' => $opcoesColunasOpcionais,
                            'selected' => $sugerir('rua'),
                        ]) ?>
                    </div>

                    <?= component('select', [
                        'name' => 'coluna_numero',
                        'label' => 'Número',
                        'options' => $opcoesColunasOpcionais,
                        'selected' => $sugerir('numero'),
                    ]) ?>

                    <div class="sm:col-span-2">
                        <?= component('select', [
                            'name' => 'coluna_complemento',
                            'label' => 'Complemento',
                            'options' => $opcoesColunasOpcionais,
                            'selected' => $sugerir('complemento'),
                        ]) ?>
                    </div>

                    <?= component('select', [
                        'name' => 'coluna_bairro',
                        'label' => 'Bairro',
                        'options' => $opcoesColunasOpcionais,
                        'selected' => $sugerir('bairro'),
                    ]) ?>

                    <?= component('select', [
                        'name' => 'coluna_cidade',
                        'label' => 'Cidade',
                        'options' => $opcoesColunasOpcionais,
                        'selected' => $sugerir('cidade'),
                    ]) ?>

                    <?= component('select', [
                        'name' => 'coluna_estado',
                        'label' => 'Estado (UF)',
                        'options' => $opcoesColunasOpcionais,
                        'selected' => $sugerir('estado'),
                        'help' => 'Sigla de 2 letras (ex: SP, RJ).',
                    ]) ?>
                </div>
            </div>

            <div class="mt-6 border-t border-border pt-4 flex flex-col gap-4">
                <div>
                    <h3 class="text-heading-xs font-medium text-text">Classificação de tipo (Cliente ou Fornecedor)</h3>
                    <p class="text-caption text-muted">Defina como classificar os registros desta planilha e se deseja filtrar por tipo.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <?= component('select', [
                        'name' => 'modo_tipo',
                        'label' => 'Como classificar os registros',
                        'options' => [
                            'coluna' => 'Definir pelo valor da coluna mapeada (misto)',
                            'todos_clientes' => 'Importar todos desta planilha como Clientes',
                            'todos_fornecedores' => 'Importar todos desta planilha como Fornecedores',
                        ],
                        'selected' => 'coluna',
                        'help' => 'Se não houver coluna de tipo mapeada, registros sem indicação serão Clientes.',
                    ]) ?>

                    <?= component('select', [
                        'name' => 'filtro_tipo',
                        'label' => 'Filtro de importação',
                        'options' => [
                            'todos' => 'Importar todos os registros da planilha',
                            'apenas_clientes' => 'Importar apenas quem for Cliente (ignora fornecedores)',
                            'apenas_fornecedores' => 'Importar apenas quem for Fornecedor (ignora clientes)',
                        ],
                        'selected' => 'todos',
                        'help' => 'Permite importar seletivamente apenas uma categoria desta planilha.',
                    ]) ?>
                </div>
            </div>

            <div class="mt-6 border-t border-border pt-4 flex flex-col gap-4">
                <div>
                    <h3 class="text-heading-xs font-medium text-text">Regras para cadastros já existentes (duplicados)</h3>
                    <p class="text-caption text-muted">Defina como o sistema deve identificar se o cliente ou fornecedor já existe e qual ação executar.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <?= component('select', [
                        'name' => 'criterio_duplicados',
                        'label' => 'Como identificar se já existe no sistema',
                        'options' => [
                            'cpf_cnpj_ou_nome_ou_email' => 'CPF/CNPJ OU Nome OU E-mail (Recomendado)',
                            'apenas_documento' => 'Apenas pelo CPF/CNPJ',
                            'apenas_nome' => 'Apenas pelo Nome / Razão Social',
                            'apenas_email' => 'Apenas pelo E-mail',
                        ],
                        'selected' => 'cpf_cnpj_ou_nome_ou_email',
                        'help' => 'Cadastros coincidentes serão tratados conforme a ação escolhida ao lado.',
                    ]) ?>

                    <?= component('select', [
                        'name' => 'estrategia_duplicados',
                        'label' => 'Ação para cadastros repetidos',
                        'options' => [
                            'mesclar' => 'Mesclar dados (atualiza dados informados sem apagar campos já cadastrados)',
                            'substituir' => 'Substituir dados mantendo o ID (sobrescreve campos e preserva histórico)',
                            'pular' => 'Não importar (ignorar e manter o cadastro existente sem alterações)',
                        ],
                        'selected' => 'mesclar',
                        'help' => 'Mesclar ou substituir preserva o histórico de OS e Vendas vinculado ao cliente.',
                    ]) ?>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <?= component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'href' => site_url('clientes')]) ?>
            <?= component('button', [
                'label' => 'Concluir importação',
                'icon' => 'check',
                'type' => 'submit',
                'attrs' => ['data-rotulo-carregando' => 'Importando cadastros...'],
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
