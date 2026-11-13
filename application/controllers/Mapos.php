<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
class Mapos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('os');
        $this->load->model('mapos_model');
    }

    /**
     * Painel inicial (#2847), com os componentes da v5: cards de resumo,
     * agenda das entregas de OS (FullCalendar 6), balanço do ano e OS por
     * status (Chart.js 4) e as listas do que pede atenção. Cada bloco só
     * aparece com a permissão de ver o módulo dele (na v4 as listas de OS e
     * de lançamentos apareciam para todos).
     */
    public function index()
    {
        $this->load->helper(['os', 'painel']);

        $hoje = date('Y-m-d');
        $anoAtual = (int) date('Y');
        $pode = [
            'os' => $this->permite('vOs'),
            'vendas' => $this->permite('vVenda'),
            'lancamentos' => $this->permite('vLancamento'),
            'balanco' => $this->permite('rFinanceiro'),
            'produtos' => $this->permite('vProduto'),
        ];

        // Instalador esquecido no servidor (#2874): só quem administra o sistema vê o aviso.
        $painel = ['hoje' => $hoje, 'pode' => $pode, 'instalador_no_servidor' => $this->permite('cSistema') && is_dir(FCPATH . 'install')];

        // Migrations pendentes e .env antigo (#2858): também só para quem administra o sistema.
        $this->load->helper('manutencao');
        $painel['avisos_sistema'] = $this->permite('cSistema')
            ? manutencaoAvisos($this->db, $_ENV, ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            : [];

        if ($pode['os']) {
            $osPorStatus = $this->mapos_model->contarPorStatus('os');
            $painel['os_por_status'] = $osPorStatus;
            $painel['os_grafico'] = painelOsPorStatus($osPorStatus);
            $painel['os_lista'] = $this->mapos_model->osParaPainel(PAINEL_OS_ANDAMENTO, 8);
        }

        if ($pode['vendas']) {
            $painel['vendas_por_status'] = $this->mapos_model->contarPorStatus('vendas');
            $painel['vendas_lista'] = $this->mapos_model->vendasParaPainel(PAINEL_VENDAS_ABERTAS, 8);
        }

        if ($pode['lancamentos'] || $pode['balanco']) {
            $this->load->model('financeiro_model');
        }

        if ($pode['lancamentos']) {
            $mesAtual = painelBalanco($this->financeiro_model->balancoAnual($anoAtual), $anoAtual);
            $indice = (int) date('n') - 1;
            $painel['mes'] = ['receitas' => $mesAtual['receitas'][$indice], 'despesas' => $mesAtual['despesas'][$indice], 'saldo' => $mesAtual['saldo'][$indice]];
            $painel['visao_geral'] = $this->financeiro_model->visaoGeral();
            $painel['lancamentos_lista'] = $this->financeiro_model->proximosPendentes(8);
        }

        if ($pode['balanco']) {
            $ano = painelAno($this->input->get('ano'), $anoAtual);
            $painel['ano'] = $ano;
            $painel['anos'] = range($anoAtual + 1, max(2000, $anoAtual - 5));
            $painel['balanco'] = painelBalanco($this->financeiro_model->balancoAnual($ano), $ano);
        }

        if ($pode['produtos']) {
            $painel['estoque_lista'] = $this->mapos_model->produtosEstoqueBaixo(8);
        }

        $this->data = array_merge($this->data, $painel);

        if ($this->permite('aOs')) {
            $this->data['topbar_acao'] = ['label' => 'Nova OS', 'icon' => 'plus', 'href' => site_url('os/adicionar')];
        } elseif ($this->permite('aVenda')) {
            $this->data['topbar_acao'] = ['label' => 'Nova venda', 'icon' => 'plus', 'href' => site_url('vendas/adicionar')];
        }

        $this->data['menuPainel'] = 'Painel';
        $this->data['view'] = 'mapos/painel';

        return $this->layout();
    }

    /**
     * Minha conta (#2855): dados do usuário, foto e troca de senha, com os
     * componentes da v5.
     */
    public function minhaConta()
    {
        return $this->telaDaConta([]);
    }

    /**
     * @param  array<string, string>  $erros  Por campo da troca de senha
     */
    private function telaDaConta(array $erros)
    {
        $usuario = $this->mapos_model->getById($this->session->userdata('id_admin'));

        $this->data['usuario'] = $usuario;
        $this->data['avatar'] = layoutAvatarUrl($usuario->url_image_user ?? null, FCPATH . 'assets/userImage', base_url());
        $this->data['tem_foto'] = $this->data['avatar'] !== base_url() . 'assets/img/User.png';
        $this->data['pode_foto'] = $this->permite('cUsuario');
        $this->data['dois_fatores_ativo'] = (bool) ($usuario->totp_ativado_em ?? null);
        $this->data['erros'] = $erros;
        $this->data['view'] = 'mapos/minhaConta';

        return $this->layout();
    }

    /**
     * Grava o modo de cor do usuário logado (#2854): POST {modo}, chamado pela
     * topbar quando a pessoa troca claro, escuro ou sistema. Responde JSON.
     */
    public function salvarTemaModo()
    {
        $modo = strtolower(trim((string) $this->input->post('modo')));
        $this->load->helper('tema');
        $valido = $this->input->method() === 'post' && in_array($modo, TEMA_MODOS, true);
        $salvou = $valido && $this->mapos_model->salvarTemaModo((int) $this->session->userdata('id_admin'), $modo);

        if ($salvou) {
            $this->session->set_userdata('tema_modo_admin', $modo);
        }

        return $this->output
            ->set_status_header($valido ? 200 : 422)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['result' => $salvou, 'modo' => $valido ? $modo : null]));
    }

    /**
     * Grava a cor do tema do usuário logado (#3032): POST {cor}, valor da
     * allow-list TEMA_CORES. Só mexe na própria pessoa (id da sessão). JSON.
     */
    public function salvarTemaCor()
    {
        $cor = strtolower(trim((string) $this->input->post('cor')));
        $this->load->helper('tema');
        $valido = $this->input->method() === 'post' && in_array($cor, TEMA_CORES, true);
        $salvou = $valido && $this->mapos_model->salvarTemaCor((int) $this->session->userdata('id_admin'), $cor);

        if ($salvou) {
            $this->session->set_userdata('tema_cor_admin', $cor);
        }

        return $this->output
            ->set_status_header($valido ? 200 : 422)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['result' => $salvou, 'cor' => $valido ? $cor : null]));
    }

    /**
     * Autenticação em dois fatores da própria conta (#2876): ativar (QR code e
     * código de confirmação), ver os códigos de recuperação uma vez, gerar
     * novos e desativar (os dois pedem a senha). Um grupo que exige o 2FA não
     * permite desativar.
     */
    public function doisFatores()
    {
        return $this->telaDoisFatores();
    }

    public function ativarDoisFatores()
    {
        $this->load->library('Totp');
        $usuario = $this->usuarioDoDoisFatores();
        $segredo = $this->session->userdata('totp_pendente');
        $origemGeral = $this->input->post('origem') === 'geral';
        $destinoVolta = $origemGeral ? site_url('mapos/configurar#secao-2fa-conta') : site_url('mapos/doisFatores');

        if ($this->input->method() !== 'post' || $usuario->totp_ativado_em || ! is_string($segredo) || $segredo === '') {
            return redirect($destinoVolta);
        }

        // A senha também na ativação (#2879): evita que outra pessoa com sessão aberta trave a conta.
        if (! password_verify((string) $this->input->post('senha'), $usuario->senha)) {
            return $this->responderDoisFatores(['senha_ativar' => 'A senha não confere.']);
        }

        $passo = $this->totp->verificar($segredo, (string) $this->input->post('codigo'));
        if ($passo === null) {
            return $this->responderDoisFatores(['codigo' => 'Código incorreto. Confira o aplicativo e tente de novo.']);
        }

        $codigos = $this->totp->gerarCodigosDeRecuperacao();
        $salvou = $this->mapos_model->ativarTotp((int) $usuario->idUsuarios, $this->totp->cifrar($segredo), array_map([Totp::class, 'hashDoCodigo'], $codigos), $passo);

        if (! $salvou) {
            return $this->responderDoisFatores(['_geral' => 'Não foi possível ativar. Tente de novo.']);
        }

        $this->session->unset_userdata(['totp_pendente', 'exigir_2fa_cadastro']);
        log_info('Ativou a autenticação em dois fatores.');

        return $this->responderDoisFatores([], $codigos, 'Autenticação em dois fatores ativada! Guarde seus códigos de recuperação abaixo.');
    }

    public function novosCodigosDoisFatores()
    {
        $this->load->library('Totp');
        $usuario = $this->usuarioDoDoisFatores();
        $origemGeral = $this->input->post('origem') === 'geral';
        $destinoVolta = $origemGeral ? site_url('mapos/configurar#secao-2fa-conta') : site_url('mapos/doisFatores');

        if ($this->input->method() !== 'post' || ! $usuario->totp_ativado_em) {
            return redirect($destinoVolta);
        }
        if ($erro = $this->confirmarSenhaDoUsuario($usuario, (string) $this->input->post('senha'))) {
            return $this->responderDoisFatores(['senha_codigos' => $erro]);
        }

        $codigos = $this->totp->gerarCodigosDeRecuperacao();
        $this->mapos_model->substituirCodigosDeRecuperacao((int) $usuario->idUsuarios, array_map([Totp::class, 'hashDoCodigo'], $codigos));
        log_info('Gerou novos códigos de recuperação do 2FA.');

        return $this->responderDoisFatores([], $codigos, 'Novos códigos de recuperação gerados abaixo.');
    }

    public function desativarDoisFatores()
    {
        $usuario = $this->usuarioDoDoisFatores();
        $origemGeral = $this->input->post('origem') === 'geral';
        $destinoVolta = $origemGeral ? site_url('mapos/configurar#secao-2fa-conta') : site_url('mapos/doisFatores');

        if ($this->input->method() !== 'post' || ! $usuario->totp_ativado_em) {
            return redirect($destinoVolta);
        }

        $exigeObrigatorio = $this->mapos_model->exigir2faNoLogin() || $this->mapos_model->grupoExige2fa((int) $usuario->permissoes_id);
        $trocar = $this->input->post('modo') === 'trocar';

        if ($exigeObrigatorio && ! $trocar) {
            return $this->responderDoisFatores([
                '_geral' => 'A exigência de 2FA no login está ativa para sua conta/sistema. Desative a opção acima primeiro ou clique em "Trocar aplicativo".',
            ]);
        }

        if ($erro = $this->confirmarSenhaDoUsuario($usuario, (string) $this->input->post('senha'))) {
            return $this->responderDoisFatores(['senha_desativar' => $erro]);
        }

        if ($exigeObrigatorio && $trocar) {
            $this->mapos_model->desativarTotp((int) $usuario->idUsuarios, false);
            $this->session->set_userdata('exigir_2fa_cadastro', true);
            log_info('Reiniciou o 2FA para vincular um novo aplicativo.');
            $this->session->set_flashdata('success', 'Vínculo anterior removido. Leia o novo QR Code abaixo para ativar o novo aplicativo.');
        } else {
            $this->mapos_model->desativarTotp((int) $usuario->idUsuarios, true);
            $this->session->unset_userdata('exigir_2fa_cadastro');
            log_info('Desativou a autenticação em dois fatores.');
            $this->session->set_flashdata('success', 'Autenticação em dois fatores desativada na sua conta.');
        }

        return redirect($destinoVolta);
    }

    private function responderDoisFatores(array $erros = [], ?array $codigos = null, ?string $sucessoFlash = null)
    {
        if ($this->input->post('origem') === 'geral') {
            if ($erros !== []) {
                $this->session->set_flashdata('tfa_erros_geral', $erros);
            }
            if ($codigos !== null) {
                $this->session->set_flashdata('tfa_codigos_geral', $codigos);
            }
            if ($sucessoFlash !== null) {
                $this->session->set_flashdata('success', $sucessoFlash);
            }

            return redirect(site_url('mapos/configurar#secao-2fa-conta'));
        }

        if ($sucessoFlash !== null && $erros === []) {
            $this->session->set_flashdata('success', $sucessoFlash);
        }

        return $this->telaDoisFatores($erros, $codigos);
    }

    /**
     * Confere a senha de quem já está logado antes de uma ação sensível, com o
     * limite de tentativas do login (#2879): quem tem uma sessão roubada não
     * pode adivinhar a senha sem limite. Devolve a mensagem de erro, ou null
     * se a senha confere.
     */
    private function confirmarSenhaDoUsuario(object $usuario, string $senha, string $mensagemErro = 'A senha não confere.'): ?string
    {
        $this->load->library('Limite_login');
        $resultado = $this->limite_login->confirmarSenha((string) $usuario->email, $this->input->ip_address(), $senha, (string) $usuario->senha);

        return match ($resultado) {
            Limite_login::SENHA_OK => null,
            Limite_login::SENHA_BLOQUEADA => Limite_login::MENSAGEM,
            default => $mensagemErro,
        };
    }

    private function usuarioDoDoisFatores(): object
    {
        $usuario = $this->mapos_model->usuarioParaLoginPorId((int) $this->session->userdata('id_admin'));
        if (! $usuario) {
            redirect(site_url('login/sair'));
        }

        return $usuario;
    }

    /**
     * @param  array<string, string>  $erros
     * @param  list<string>|null      $codigos  Códigos de recuperação recém-gerados (mostrados só agora)
     */
    private function montarDadosDoisFatores(array $erros = [], ?array $codigos = null): array
    {
        $this->load->library('Totp');
        $usuario = $this->usuarioDoDoisFatores();
        $ativo = (bool) $usuario->totp_ativado_em;
        $exigido = $this->mapos_model->exigir2faNoLogin() || $this->mapos_model->grupoExige2fa((int) $usuario->permissoes_id);

        $qr = null;
        $segredo = null;
        $segredoPuro = null;
        $otpauthUri = null;

        if (! $ativo && $codigos === null) {
            // O segredo só vai para o banco depois que o usuário prova que o aplicativo o leu.
            $segredo = $this->session->userdata('totp_pendente');
            if (! is_string($segredo) || $segredo === '') {
                $segredo = $this->totp->criarSegredo();
                $this->session->set_userdata('totp_pendente', $segredo);
            }
            $segredoPuro = $segredo;
            $qr = $this->totp->qrCodeDataUri((string) $usuario->email, $segredo);
            $segredo = Totp::formatarSegredo($segredo);
            $otpauthUri = $this->totp->uri((string) $usuario->email, $segredoPuro);
        }

        return [
            'ativo' => $ativo,
            'exigido' => $exigido,
            'exigir_2fa_login' => $exigido,
            'restantes' => $ativo ? $this->mapos_model->codigosDeRecuperacaoRestantes((int) $usuario->idUsuarios) : 0,
            'codigos' => $codigos,
            'erros' => $erros,
            'email' => (string) $usuario->email,
            'qr' => $qr,
            'segredo' => $segredo,
            'segredo_puro' => $segredoPuro,
            'otpauth_uri' => $otpauthUri,
        ];
    }

    private function telaDoisFatores(array $erros = [], ?array $codigos = null)
    {
        foreach ($this->montarDadosDoisFatores($erros, $codigos) as $chave => $valor) {
            $this->data[$chave] = $valor;
        }
        $this->data['view'] = 'mapos/doisFatores';

        return $this->layout();
    }

    /**
     * Troca a senha do próprio usuário. Os erros aparecem no campo; a senha
     * atual precisa conferir, e a nova tem de ter ao menos 8 caracteres e ser
     * repetida igual (antes a confirmação só era conferida no navegador).
     */
    public function alterarSenha()
    {
        // Só POST confere a senha: um GET (link, imagem de terceiros) não pode
        // gastar as tentativas de quem está logado (#2879).
        if ($this->input->method() !== 'post') {
            return redirect(site_url('mapos/minhaConta'));
        }

        $current_user = $this->mapos_model->getById($this->session->userdata('id_admin'));

        if (! $current_user) {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao pesquisar usuário!');
            redirect(site_url('mapos/minhaConta'));
        }

        $erros = $this->validarFormulario('alterar_senha');

        if ($erros === [] && ($erro = $this->confirmarSenhaDoUsuario($current_user, (string) $this->input->post('oldSenha'), 'A senha atual não confere.'))) {
            $erros['oldSenha'] = $erro;
        }

        if ($erros !== []) {
            return $this->telaDaConta($erros);
        }

        if ($this->mapos_model->alterarSenha($this->input->post('novaSenha'))) {
            log_info('Alterou a própria senha.');
            $this->session->set_flashdata('success', 'Senha alterada com sucesso!');
        } else {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar alterar a senha!');
        }

        redirect(site_url('mapos/minhaConta'));
    }

    /**
     * Busca geral (#2855), a da topbar: clientes, OS, produtos e serviços. Cada
     * grupo só aparece para quem pode visualizá-lo; sem termo, a tela só pede
     * o que buscar.
     */
    public function pesquisar()
    {
        $termo = mb_substr(trim((string) $this->input->get('termo')), 0, 100);
        $pode = [
            'clientes' => $this->permite('vCliente'),
            'os' => $this->permite('vOs'),
            'produtos' => $this->permite('vProduto'),
            'servicos' => $this->permite('vServico'),
        ];
        $resultados = $termo === '' ? [] : $this->mapos_model->pesquisar($termo);

        $this->data['termo'] = $termo;
        $this->data['pode'] = $pode + [
            'editar_cliente' => $this->permite('eCliente'),
            'editar_os' => $this->permite('eOs'),
            'editar_produto' => $this->permite('eProduto'),
            'editar_servico' => $this->permite('eServico'),
        ];
        foreach ($pode as $grupo => $permitido) {
            $this->data[$grupo] = $permitido ? ($resultados[$grupo] ?? []) : [];
        }
        $this->data['view'] = 'mapos/pesquisa';

        return $this->layout();
    }

    /**
     * Resultados da paleta Ctrl+K, em JSON (#2850). Mesmas permissões da
     * busca completa: um grupo que o usuário não pode ver nem é consultado.
     */
    public function buscaRapida()
    {
        $termo = mb_substr(trim((string) $this->input->get('termo')), 0, 100);
        $grupos = [];

        if (mb_strlen($termo) >= 2) {
            $this->load->helper('os');
            $r = $this->mapos_model->buscaRapida($termo);

            if ($this->permite('vCliente')) {
                $grupos[] = ['chave' => 'clientes', 'titulo' => 'Clientes e fornecedores', 'itens' => array_map(static fn ($c) => [
                    'titulo' => (string) $c->nomeCliente,
                    'detalhe' => trim(implode(' · ', array_filter([(string) $c->documento, (string) ($c->celular ?: $c->telefone)]))),
                    'url' => site_url('clientes/visualizar/' . (int) $c->idClientes),
                ], $r['clientes'])];
            }
            if ($this->permite('vOs')) {
                $grupos[] = ['chave' => 'os', 'titulo' => 'Ordens de serviço', 'itens' => array_map(static fn ($o) => [
                    'titulo' => 'OS #' . (int) $o->idOs,
                    'detalhe' => trim(implode(' · ', array_filter([(string) $o->nomeCliente, (string) $o->status]))),
                    'url' => site_url('os/visualizar/' . (int) $o->idOs),
                ], $r['os'])];
            }
            if ($this->permite('vProduto')) {
                $grupos[] = ['chave' => 'produtos', 'titulo' => 'Produtos', 'itens' => array_map(static fn ($p) => [
                    'titulo' => (string) $p->descricao,
                    'detalhe' => dinheiro($p->precoVenda) . ' · estoque ' . (float) $p->estoque,
                    'url' => site_url('produtos/visualizar/' . (int) $p->idProdutos),
                ], $r['produtos'])];
            }
            if ($this->permite('vServico')) {
                $grupos[] = ['chave' => 'servicos', 'titulo' => 'Serviços', 'itens' => array_map(static fn ($s) => [
                    'titulo' => (string) $s->nome,
                    'detalhe' => dinheiro($s->preco),
                    // Serviço não tem tela de visualização: abre a listagem (vServico) já filtrada.
                    'url' => site_url('servicos') . '?pesquisa=' . rawurlencode((string) $s->nome),
                ], $r['servicos'])];
            }
        }

        return $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_header('Cache-Control: private, no-store')
            ->set_output(json_encode([
                'termo' => $termo,
                'grupos' => array_values(array_filter($grupos, static fn ($g) => $g['itens'] !== [])),
                'todos' => site_url('mapos/pesquisar') . '?termo=' . rawurlencode($termo),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));
    }

    public function backup()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cBackup')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para efetuar backup.');
            redirect(base_url());
        }

        $isSuperadmin = (bool) $this->session->userdata('is_superadmin');
        $empresaId = empresa_ativa_id();
        $tipo = $this->input->get('tipo');

        $this->load->library('backup_tenant');

        try {
            if ($tipo === 'completo' && $isSuperadmin) {
                $backup = $this->backup_tenant->gerarBackupCompleto();
                $nomeArquivo = 'backup-completo-' . date('Y-m-d-H\hi') . '.zip';
                log_info('Efetuou backup completo do banco de dados.');
            } else {
                $backup = $this->backup_tenant->gerarBackupEmpresa($empresaId);
                $nomeArquivo = 'backup-empresa-' . $empresaId . '-' . date('Y-m-d-H\hi') . '.zip';
                log_info("Efetuou backup do banco de dados da empresa #{$empresaId}.");
            }

            $this->load->helper('download');
            force_download($nomeArquivo, $backup);
        } catch (\Throwable $e) {
            log_message('error', 'Erro ao gerar backup: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Não foi possível gerar o backup: ' . $e->getMessage());
            redirect(site_url('mapos/configurar?aba=sistema'));
        }
    }

    /**
     * Emitente (#2846): dados da empresa nas impressões, nos e-mails e no PIX,
     * num formulário só (cadastra quando ainda não há, senão edita o que
     * existe; o id nunca vem do POST). O logo é opcional e troca só o arquivo
     * dele (na v4 trocar o logo apagava a pasta de uploads inteira).
     */
    public function emitente()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cEmitente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar emitente.');
            redirect(base_url());
        }

        $this->load->helper('configuracoes');
        $emitente = $this->mapos_model->getEmitente();
        $erros = [];

        if ($this->input->method() === 'post') {
            [$dados, $erros] = emitenteDadosDoFormulario($this->input->post());

            $enviouLogo = isset($_FILES['userfile']) && ($_FILES['userfile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($erros === [] && $enviouLogo) {
                [$arquivo, $erroLogo] = $this->enviarImagem(FCPATH . 'assets/uploads');
                if ($arquivo === null) {
                    $erros['userfile'] = $erroLogo;
                } else {
                    $anterior = $emitente ? emitenteArquivoDoLogo($emitente->url_logo, FCPATH . 'assets/uploads') : null;
                    $dados['url_logo'] = base_url('assets/uploads/' . $arquivo);
                }
            } elseif ($erros === [] && ! $emitente) {
                $dados['url_logo'] = '';
            }

            if ($erros === []) {
                $salvou = $emitente
                    ? $this->mapos_model->edit('emitente', $dados, 'id', (int) $emitente->id)
                    : $this->mapos_model->add('emitente', $dados + ['empresa_id' => empresa_ativa_id()]);

                if ($salvou) {
                    if (! empty($anterior)) {
                        @unlink($anterior);
                    }
                    log_info($emitente ? 'Alterou informações de emitente.' : 'Adicionou informações de emitente.');
                    $this->session->set_flashdata('success', 'Dados do emitente salvos.');

                    return redirect('mapos/emitente');
                }

                $erros['_geral'] = 'Não foi possível salvar. Tente de novo.';
            }
        }

        $post = $this->input->method() === 'post' ? $this->input->post() : null;
        $campos = ['nome', 'cnpj', 'ie', 'cep', 'rua', 'numero', 'bairro', 'cidade', 'estado', 'telefone', 'email', 'pix_key'];
        $valores = [];
        foreach ($campos as $campo) {
            $valores[$campo] = $post !== null
                ? (is_scalar($post[$campo] ?? null) ? trim((string) $post[$campo]) : '')
                : (string) ($emitente->{$campo === 'estado' ? 'uf' : $campo} ?? '');
        }

        $this->data['menuConfiguracoes'] = 'Configuracoes';
        $this->data['emitente'] = $emitente;
        $this->data['valores'] = $valores;
        $this->data['erros'] = $erros;
        $this->data['view'] = 'mapos/emitente';

        return $this->layout();
    }

    /**
     * Grava a imagem enviada em userfile (png, jpg, bmp até 2 MB, nome
     * aleatório) e devolve [nome do arquivo, null] ou [null, mensagem]. Na
     * v4 o erro de upload aparecia cru na tela e a página parava.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function enviarImagem(string $pasta): array
    {
        if (!is_dir($pasta) && !@mkdir($pasta, DIR_WRITE_MODE, true)) {
            return [null, 'Não foi possível criar a pasta de uploads no servidor.'];
        }

        // Acima do limite do PHP o arquivo nem chega: a mensagem do CI fala em
        // "configuração do PHP", que não ajuda quem está enviando.
        if (in_array($_FILES['userfile']['error'] ?? UPLOAD_ERR_OK, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, 'A imagem passa de 2 MB. Reduza o tamanho e envie de novo.'];
        }

        armazenamentoProtegerPastaPublica($pasta);

        $this->load->library('upload');

        $this->upload->initialize([
            // SVG fora da lista: é um documento XML que pode carregar script e
            // seria servido a partir da própria origem da aplicação.
            'upload_path' => $pasta,
            'allowed_types' => 'png|jpg|jpeg|bmp',
            'max_size' => 2048,
            'remove_space' => true,
            'encrypt_name' => true,
        ]);

        // Mensagens do upload em português: o idioma global é english, e a
        // biblioteca carrega o arquivo de idioma ao registrar cada erro (como
        // em validarFormulario()).
        $idioma = $this->config->item('language');
        $this->config->set_item('language', 'pt-br');
        try {
            $enviou = $this->upload->do_upload('userfile');
            $erro = $enviou ? null : (trim(strip_tags($this->upload->display_errors('', ''))) ?: 'Não foi possível enviar a imagem.');
        } finally {
            $this->config->set_item('language', $idioma);
        }

        if (! $enviou) {
            return [null, $erro];
        }

        // O conteúdo precisa ser mesmo uma imagem da extensão e ter um número
        // razoável de pixels (a logo vai para PDFs, que descompactam a imagem).
        if (($erroConteudo = armazenamentoValidarEnvio($this->upload->data())) !== null) {
            return [null, $erroConteudo];
        }

        return [$this->upload->data('file_name'), null];
    }

    public function uploadUserImage()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cUsuario')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para mudar a foto.');
            redirect(base_url());
        }

        $id = $this->session->userdata('id_admin');
        if ($id == null || !is_numeric($id)) {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar alterar sua foto.');
            redirect(site_url('mapos/minhaConta'));
        }

        $usuario = $this->mapos_model->getById($id);

        [$image, $erroUpload] = $this->enviarImagem(FCPATH . 'assets/userImage');
        if ($image === null) {
            $this->session->set_flashdata('error', $erroUpload);
            redirect(site_url('mapos/minhaConta'));
        }

        // A foto antiga sai só depois que a nova foi gravada.
        $antiga = FCPATH . 'assets/userImage/' . basename((string) $usuario->url_image_user);
        if ((string) $usuario->url_image_user !== '' && is_file($antiga)) {
            unlink($antiga);
        }
        $imageUserPath = $image;
        $retorno = $this->mapos_model->editImageUser($id, $imageUserPath);

        if ($retorno) {
            // A chave que o layout lê (a mesma do login): sem ela o avatar da topbar
            // só mudava no login seguinte.
            $this->session->set_userdata('url_image_user_admin', $imageUserPath);
            $this->session->set_flashdata('success', 'Foto alterada com sucesso.');
            log_info('Alterou a Imagem do Usuario.');
        } else {
            $this->session->set_flashdata('error', 'Ocorreu um erro ao tentar alterar sua foto.');
        }
        redirect(site_url('mapos/minhaConta'));
    }

    /**
     * Fila de e-mails (#2846), no padrão das listagens (#2852): filtro de
     * situação na URL e exclusão em modal-confirm.
     */
    public function emails()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cEmail')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar fila de e-mails');
            redirect(base_url());
        }

        $this->load->helper('configuracoes');
        $this->load->model('email_model');

        $filtros = listagemFiltros(['status' => ['pending', 'sending', 'sent', 'failed']], $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->email_model->contar($filtros);

        $this->data['menuConfiguracoes'] = 'Email';
        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->email_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('mapos/emails'), $total, $offset, null, $filtros);
        $this->data['view'] = 'emails/emails';

        return $this->layout();
    }

    public function logEmail($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'cEmail')) {
            return $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => 'Você não tem permissão para consultar logs de e-mails.']));
        }

        $idEmail = (int) $id;
        $this->load->helper('configuracoes');
        $this->load->model('email_model');
        $email = $idEmail > 0 ? $this->email_model->getById($idEmail) : null;

        if (! $email) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => 'E-mail não encontrado.']));
        }

        $logs = $this->email_model->logsDoEmail($idEmail);
        $pill = emailFilaPill($email->status ?? null);

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'result' => true,
                'email' => [
                    'id' => (int) $email->id,
                    'to' => (string) $email->to,
                    'assunto' => emailFilaAssunto($email->headers ?? '') ?: '—',
                    'status' => (string) ($email->status ?? ''),
                    'status_label' => $pill['label'],
                    'criado_em' => ! empty($email->date) ? date('d/m/Y H:i:s', strtotime((string) $email->date)) : '—',
                    'enviado_em' => ! empty($email->enviado_em) ? date('d/m/Y H:i:s', strtotime((string) $email->enviado_em)) : '—',
                    'lido_em' => ! empty($email->lido_em) ? date('d/m/Y H:i:s', strtotime((string) $email->lido_em)) : null,
                    'ultima_leitura_em' => ! empty($email->ultima_leitura_em) ? date('d/m/Y H:i:s', strtotime((string) $email->ultima_leitura_em)) : null,
                    'total_leituras' => (int) ($email->total_leituras ?? 0),
                    'erro_mensagem' => $email->erro_mensagem ?? null,
                ],
                'logs' => array_map(static fn ($l) => [
                    'evento' => (string) $l->evento,
                    'detalhes' => (string) ($l->detalhes ?? ''),
                    'ip' => (string) ($l->ip ?? ''),
                    'criado_em' => ! empty($l->criado_em) ? date('d/m/Y H:i:s', strtotime((string) $l->criado_em)) : '—',
                ], $logs),
            ]));
    }

    public function excluirEmail()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cEmail')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir e-mail da fila.');
            redirect(base_url());
        }

        $id = (int) $this->input->post('id');
        $voltar = site_url('mapos/emails') . listagemQuery(listagemFiltros(['status' => ['pending', 'sending', 'sent', 'failed']], $this->input->get()));

        $this->load->model('email_model');
        if ($id <= 0 || !$this->email_model->getById($id)) {
            $this->session->set_flashdata('error', 'E-mail não encontrado na fila.');
            redirect($voltar);
        }

        $empresaId = empresa_ativa_id();
        if ($this->db->table_exists('email_logs')) {
            $this->db->where('email_id', $id)->where('empresa_id', $empresaId)->delete('email_logs');
        }

        $this->email_model->delete('email_queue', 'id', $id);
        log_info('Removeu um e-mail da fila de envio. ID: ' . $id);

        $this->session->set_flashdata('success', 'E-mail removido da fila.');
        redirect($voltar);
    }

    /**
     * Configurações do sistema (#2846), em abas por link (?aba=). Cada aba é
     * um formulário que grava só os campos dela, na tabela configuracoes ou
     * no .env (configuracaoCampos()). Segredos do .env não voltam para a tela
     * e, em branco, ficam como estão.
     */
    public function configurar()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar o sistema');
            redirect(base_url());
        }

        $this->load->helper(['configuracoes', 'os']);
        $aba = listagemFiltros(['aba' => array_keys(CONFIG_ABAS)], $this->input->get())['aba'] ?? 'geral';
        $erros = [];

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));

        if ($this->input->method() === 'post' && $aba !== 'sistema') {
            $postDados = $this->input->post();
            [$config, $env, $erros] = configuracaoDadosDoFormulario($aba, $postDados);

            // Todas as configurações da aba são salvas no banco para a empresa ativa
            $dadosParaSalvar = array_merge($config, $env);

            // Se for a empresa master (id 1) e tiver variáveis de env, sincroniza com o .env também
            if ($ehMaster && $empresaId === 1 && $env !== []) {
                $this->gravarEnv($env);
            }

            if ($erros === [] && ($dadosParaSalvar === [] || $this->mapos_model->saveConfiguracao($dadosParaSalvar, $empresaId))) {
                // Sincroniza a chave PIX no emitente se informada na aba financeiro
                if ($aba === 'financeiro' && isset($postDados['pix_key']) && $this->db->table_exists('emitente') && $this->db->field_exists('pix_key', 'emitente')) {
                    $this->db->where('empresa_id', $empresaId)->update('emitente', [
                        'pix_key' => trim((string) $postDados['pix_key']),
                    ]);
                }

                log_info('Alterou as configurações do sistema (' . CONFIG_ABAS[$aba] . ').');
                $this->session->set_flashdata('success', 'Configurações salvas.');

                return redirect('mapos/configurar' . ($aba === 'geral' ? '' : listagemQuery(['aba' => $aba])));
            }

            $erros['_geral'] ??= 'Não foi possível salvar. Tente de novo.';
        }

        $this->data['menuConfiguracoes'] = 'Sistema';
        $this->data['aba'] = $aba;
        $this->data['campos'] = configuracaoCampos($aba);
        $this->data['valores'] = configuracaoValores($aba, $this->input->method() === 'post' ? $this->input->post() : null, $this->data['configuration'], $_ENV, $empresaId);
        $this->data['erros'] = $erros;
        $this->data['pode_backup'] = $this->permite('cBackup');
        $this->data['is_superadmin'] = $ehMaster;
        $this->data['tema_pessoal'] = configuracaoTemaPessoal($this->data['configuration']);
        if ($aba === 'geral') {
            $errosTfa = $this->session->flashdata('tfa_erros_geral');
            $codigosTfa = $this->session->flashdata('tfa_codigos_geral');
            $this->data['tfa_geral'] = $this->montarDadosDoisFatores(
                is_array($errosTfa) ? $errosTfa : [],
                is_array($codigosTfa) ? $codigosTfa : null
            );
        }
        $this->data['view'] = 'mapos/configurar';

        return $this->layout();
    }

    /** Grava as chaves no application/.env (configuracaoEnvAtualizado()). */
    private function gravarEnv(array $valores): bool
    {
        $arquivo = dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . '.env';
        $conteudo = is_readable($arquivo) ? (string) file_get_contents($arquivo) : '';

        return is_writable($arquivo) && file_put_contents($arquivo, configuracaoEnvAtualizado($conteudo, $valores)) !== false;
    }

    public function atualizarBanco()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar o sistema');
            redirect(base_url());
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));
        if (! $ehMaster) {
            $this->session->set_flashdata('error', 'Apenas o administrador da plataforma pode atualizar o banco de dados.');

            return redirect(base_url());
        }

        // Só por POST (com o token CSRF): na v4 bastava abrir o link.
        if ($this->input->method() !== 'post') {
            return redirect(site_url('mapos/configurar?aba=sistema'));
        }

        $this->load->library('migration');

        try {
            $resultado = $this->migration->latest();
        } catch (Throwable $erro) {
            // Uma migration que não consegue terminar (p. ex. STORAGE_PATH sem permissão) para aqui
            // com a causa; a versão do banco fica na última que concluiu e a próxima tentativa continua dela.
            log_message('error', 'Atualizar banco: ' . $erro->getMessage());
            $this->session->set_flashdata('error', 'A atualização do banco parou: ' . $erro->getMessage());

            return redirect(site_url('mapos/configurar?aba=sistema'));
        }

        if ($resultado === false) {
            $this->session->set_flashdata('error', $this->migration->error_string());
        } else {
            $this->session->set_flashdata('success', 'Banco de dados atualizado com sucesso!');

            return redirect(base_url());
        }

        return redirect(site_url('mapos/configurar?aba=sistema'));
    }

    /**
     * Tela da atualização do Map-OS (#2871): consulta a última release do
     * GitHub e mostra a versão, o changelog e o botão de aplicar. Nada é
     * baixado aqui.
     */
    public function atualizacao()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar o sistema');

            return redirect(base_url());
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));
        if (! $ehMaster) {
            $this->session->set_flashdata('error', 'Apenas o administrador da plataforma pode verificar atualizações.');

            return redirect(base_url());
        }

        $this->load->library('github_updater');

        $release = null;
        $erro = null;
        try {
            $release = $this->github_updater->ultimaRelease();
        } catch (Throwable $e) {
            log_message('error', 'Atualização do Map-OS: ' . $e->getMessage());
            $erro = 'Não foi possível consultar as releases no GitHub agora. Tente de novo em alguns minutos.';
        }

        $this->data['versao_atual'] = $this->github_updater->versaoAtual();
        $this->data['release'] = $release;
        $this->data['tem_atualizacao'] = $this->github_updater->temAtualizacao($release);
        $this->data['problemas'] = $this->github_updater->problemasDoAmbiente();
        $this->data['erro'] = $erro;
        $this->data['view'] = 'mapos/atualizacao';

        return $this->layout();
    }

    /**
     * Aplica a atualização escolhida na tela (POST com CSRF e a tag). O
     * download só vem da release com tag e é conferido pelo SHA256; ver
     * Github_updater::atualizar().
     */
    public function atualizarMapos()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar o sistema');

            return redirect(base_url());
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));
        if (! $ehMaster) {
            $this->session->set_flashdata('error', 'Apenas o administrador da plataforma pode atualizar o sistema.');

            return redirect(base_url());
        }

        if ($this->input->method() !== 'post') {
            return redirect(site_url('mapos/atualizacao'));
        }

        $this->load->library('github_updater');

        try {
            $resultado = $this->github_updater->atualizar((string) $this->input->post('versao'));
            log_info('Atualizou o Nexus Ticket para a versão ' . $resultado['versao'] . '.');
            $this->session->set_flashdata('success', 'Nexus Ticket atualizado para a versão ' . $resultado['versao'] . '. Agora clique em "Atualizar banco de dados" para aplicar as mudanças das tabelas.');

            return redirect(site_url('mapos/configurar?aba=sistema'));
        } catch (Throwable $e) {
            log_message('error', 'Atualização do Nexus Ticket: ' . $e->getMessage());
            $this->session->set_flashdata('error', $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao atualizar o Nexus Ticket. Veja o log da aplicação.');

            return redirect(site_url('mapos/atualizacao'));
        }
    }

    public function calendario()
    {
        if (!$this->permission->checkPermission($this->session->userdata('permissao'), 'vOs')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar O.S.');
            redirect(base_url());
        }
        $this->load->model('os_model');
        // Status fora da lista é ignorado (mostra todos).
        $status = listagemFiltros(['status' => array_keys(OS_STATUS_VARIANTES)], $this->input->get())['status'] ?? null;
        // O FullCalendar manda start/end em ISO 8601; sem eles (ou com lixo) a
        // consulta virava `dataFinal >= NULL` e quebrava com erro 500 (#2901).
        $start = dataIsoParaYmd($this->input->get('start'));
        $end = dataIsoParaYmd($this->input->get('end'));

        if ($start === null || $end === null || $start > $end) {
            return $this->output
                ->set_content_type('application/json')
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'Informe start e end válidos (AAAA-MM-DD).']));
        }

        $allOs = $this->mapos_model->calendario(
            $start,
            $end,
            $status
        );
        // Só texto e URLs (painelEventoDaOs()); o painel monta o modal.
        $this->load->helper(['os', 'painel']);
        $podeEditar = $this->permite('eOs');
        $events = array_map(fn ($os) => painelEventoDaOs(
            $os,
            site_url('os/visualizar/' . (int) $os->idOs),
            $podeEditar && $this->os_model->isEditable($os->idOs) ? site_url('os/editar/' . (int) $os->idOs) : null
        ), $allOs);

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode($events));
    }
}
