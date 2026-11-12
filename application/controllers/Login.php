<?php

class Login extends CI_Controller
{
    /** Quanto tempo a senha conferida espera o código, e quantos códigos errados são aceitos (#2876). */
    private const DOIS_FATORES_VALIDADE = 300;

    private const DOIS_FATORES_TENTATIVAS = 5;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('mapos_model');
    }

    public function index()
    {
        // A tela de entrada é sempre escura (DESIGN.md), então não lê o modo
        // de cor das configurações.
        $this->load->view('mapos/login', [
            'erro' => $this->session->flashdata('error'),
            'sucesso' => $this->session->flashdata('success'),
        ]);
    }

    public function sair()
    {
        $this->session->sess_destroy();

        // O Referer é controlado pelo cliente; redirecionar para ele permitiria
        // que um terceiro enviasse o usuário para um domínio externo.
        return redirect(site_url('login'));
    }

    public function verificarLogin()
    {
        header('Access-Control-Allow-Origin: ' . base_url());
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type');

        $this->load->library('form_validation');
        $this->form_validation->set_rules('email', 'E-mail', 'valid_email|required|trim');
        $this->form_validation->set_rules('senha', 'Senha', 'required|trim');
        if ($this->form_validation->run() == false) {
            echo json_encode(['result' => false, 'message' => validation_errors()]);
            exit();
        }

        $email = $this->input->post('email');
        $password = $this->input->post('senha');
        $ip = $this->input->ip_address();

        // Limite de tentativas (#2870): bloqueado, nem consulta a senha.
        $this->load->library('Limite_login');
        if ($this->limite_login->bloqueado('usuario', $email, $ip)) {
            echo json_encode($this->falha(Limite_login::MENSAGEM));
            exit();
        }

        $this->load->model('Mapos_model');
        $user = $this->Mapos_model->check_credentials($email);

        // Mesma mensagem para e-mail inexistente e senha errada: mensagens
        // distintas revelam quais e-mails possuem conta. A expiração só é
        // informada depois da senha certa, pelo mesmo motivo.
        // senhaConfere() gasta o mesmo bcrypt sem conta, para o tempo de resposta
        // também não revelar quais e-mails existem (#2879).
        $senhaConfere = senhaConfere((string) $password, $user->senha ?? null);
        if (! $user || ! $senhaConfere) {
            $this->limite_login->registrarFalha('usuario', $email, $ip);
            echo json_encode($this->falha('Os dados de acesso estão incorretos.'));
            exit();
        }

        $bloqueio = $this->motivoDeBloqueio($user);
        if ($bloqueio !== null) {
            echo json_encode($this->falha($bloqueio));
            exit();
        }

        // Primeiro acesso sem autenticador configurado: redireciona para vincular o 2FA antes de liberar o painel.
        if ($this->db->field_exists('totp_ativado_em', 'usuarios') && empty($user->totp_ativado_em)) {
            $this->limite_login->registrarSucesso('usuario', $email);
            $this->load->library('Totp');
            $this->session->sess_regenerate(true);
            $this->session->set_userdata('dois_fatores_primeiro_acesso', [
                'id_usuario' => (int) $user->idUsuarios,
                'email' => (string) $user->email,
                'nome' => (string) $user->nome,
                'segredo' => $this->totp->criarSegredo(),
                'expira' => time() + 600,
            ]);
            echo json_encode(['result' => true, 'destino' => site_url('login/configurarDoisFatores')]);
            exit();
        }

        // 2FA no login (#2876): só pede os 6 dígitos ao entrar se a opção global
        // "exigir_2fa_login" estiver ativada (padrão desativado) ou se o grupo exigir.
        $exigirNoLogin = $this->Mapos_model->exigir2faNoLogin() || $this->Mapos_model->grupoExige2fa((int) $user->permissoes_id);
        if (($user->totp_ativado_em ?? null) && $exigirNoLogin) {
            $this->session->sess_regenerate(true);
            $this->session->set_userdata('dois_fatores', ['email' => $user->email, 'expira' => time() + self::DOIS_FATORES_VALIDADE, 'tentativas' => 0]);
            echo json_encode(['result' => true, 'destino' => site_url('login/doisFatores')]);
            exit();
        }

        $this->limite_login->registrarSucesso('usuario', $email);
        $this->iniciarSessao($user);

        if ($this->session->userdata('qtd_empresas') > 1) {
            echo json_encode(['result' => true, 'destino' => site_url('login/escolherEmpresa')]);
            exit();
        }

        echo json_encode(['result' => true]);
        exit();
    }

    /**
     * Segundo passo do login (#2876): o código do aplicativo autenticador ou
     * um código de recuperação. Só abre com a senha já conferida na sessão.
     */
    public function doisFatores()
    {
        if ($this->doisFatoresPendente() === null) {
            $this->session->set_flashdata('error', 'Entre com e-mail e senha primeiro.');

            return redirect(site_url('login'));
        }

        return $this->load->view('mapos/login_dois_fatores', ['erro' => $this->session->flashdata('error')]);
    }

    public function verificarDoisFatores()
    {
        $pendente = $this->doisFatoresPendente();
        if ($pendente === null || $this->input->method() !== 'post') {
            $this->session->set_flashdata('error', 'Entre com e-mail e senha primeiro.');

            return redirect(site_url('login'));
        }

        $this->load->library('Limite_login');
        $this->load->library('Totp');
        $ip = $this->input->ip_address();
        $email = (string) $pendente['email'];

        if ($this->limite_login->bloqueado('usuario', $email, $ip)) {
            $this->session->unset_userdata('dois_fatores');
            $this->session->set_flashdata('error', Limite_login::MENSAGEM);

            return redirect(site_url('login'));
        }

        $user = $this->mapos_model->check_credentials($email);
        $codigo = trim((string) $this->input->post('codigo'));
        $ok = $user && $this->motivoDeBloqueio($user) === null && $this->totp->aceitar($this->mapos_model, $user, $codigo);

        if (! $ok) {
            $this->limite_login->registrarFalha('usuario', $email, $ip);
            $pendente['tentativas']++;

            if ($pendente['tentativas'] >= self::DOIS_FATORES_TENTATIVAS) {
                $this->session->unset_userdata('dois_fatores');
                $this->session->set_flashdata('error', 'Código incorreto várias vezes. Entre de novo com e-mail e senha.');

                return redirect(site_url('login'));
            }

            $this->session->set_userdata('dois_fatores', $pendente);
            $this->session->set_flashdata('error', 'Código incorreto. Confira o aplicativo ou use um código de recuperação.');

            return redirect(site_url('login/doisFatores'));
        }

        $this->limite_login->registrarSucesso('usuario', $email);
        $this->session->unset_userdata('dois_fatores');
        $this->iniciarSessao($user);

        if ($this->session->userdata('qtd_empresas') > 1) {
            return redirect(site_url('login/escolherEmpresa'));
        }

        return redirect(site_url('mapos'));
    }

    /**
     * Primeiro acesso de funcionário/usuário sem 2FA: tela obrigatória para
     * vincular o aplicativo autenticador logo após validar e-mail e senha.
     */
    public function configurarDoisFatores()
    {
        $setup = $this->primeiroAcessoPendente();
        if ($setup === null) {
            $this->session->set_flashdata('error', 'Entre com seu e-mail e senha primeiro.');

            return redirect(site_url('login'));
        }

        $this->load->library('Totp');

        return $this->load->view('mapos/login_configurar_2fa', [
            'nome' => $setup['nome'],
            'email' => $setup['email'],
            'qr' => $this->totp->qrCodeDataUri($setup['email'], $setup['segredo']),
            'segredo' => Totp::formatarSegredo($setup['segredo']),
            'segredo_puro' => $setup['segredo'],
            'otpauth_uri' => $this->totp->uri($setup['email'], $setup['segredo']),
            'erro' => $this->session->flashdata('error'),
        ]);
    }

    public function salvarDoisFatoresPrimeiroAcesso()
    {
        $setup = $this->primeiroAcessoPendente();
        if ($setup === null || $this->input->method() !== 'post') {
            $this->session->set_flashdata('error', 'Sessão expirada. Entre com seu e-mail e senha novamente.');

            return redirect(site_url('login'));
        }

        $this->load->library('Totp');
        $codigo = trim((string) $this->input->post('codigo'));
        $passo = $this->totp->verificar($setup['segredo'], $codigo);

        if ($passo === null) {
            $this->session->set_flashdata('error', 'Código incorreto. Confira os 6 dígitos mostrados no aplicativo e tente novamente.');

            return redirect(site_url('login/configurarDoisFatores'));
        }

        $codigos = $this->totp->gerarCodigosDeRecuperacao();
        $salvou = $this->mapos_model->ativarTotp(
            (int) $setup['id_usuario'],
            $this->totp->cifrar($setup['segredo']),
            array_map([Totp::class, 'hashDoCodigo'], $codigos),
            $passo
        );

        if (! $salvou) {
            $this->session->set_flashdata('error', 'Não foi possível salvar a configuração do autenticador. Tente novamente.');

            return redirect(site_url('login/configurarDoisFatores'));
        }

        $this->session->unset_userdata('dois_fatores_primeiro_acesso');
        $user = $this->mapos_model->usuarioParaLoginPorId((int) $setup['id_usuario']);
        if (! $user) {
            $this->session->set_flashdata('error', 'Usuário não encontrado.');

            return redirect(site_url('login'));
        }

        $this->iniciarSessao($user);
        log_info('Vinculou o aplicativo autenticador no primeiro acesso.');

        $destino = $this->session->userdata('qtd_empresas') > 1 ? site_url('login/escolherEmpresa') : site_url('mapos');
        $this->session->set_userdata('codigos_recuperacao_novos', [
            'codigos' => $codigos,
            'email' => (string) $user->email,
            'destino' => $destino,
        ]);

        return redirect(site_url('login/codigosRecuperacao'));
    }

    /**
     * Exibe os 10 códigos de recuperação uma única vez após ativar o 2FA.
     */
    public function codigosRecuperacao()
    {
        $dados = $this->session->userdata('codigos_recuperacao_novos');
        if (! is_array($dados) || empty($dados['codigos'])) {
            return redirect(site_url('mapos'));
        }

        $this->session->unset_userdata('codigos_recuperacao_novos');

        return $this->load->view('mapos/codigos_recuperacao', [
            'codigos' => $dados['codigos'],
            'email' => (string) ($dados['email'] ?? ''),
            'destino' => (string) ($dados['destino'] ?? site_url('mapos')),
        ]);
    }

    /** @return array{id_usuario: int, email: string, nome: string, segredo: string, expira: int}|null */
    private function primeiroAcessoPendente(): ?array
    {
        $p = $this->session->userdata('dois_fatores_primeiro_acesso');
        if (! is_array($p) || ($p['expira'] ?? 0) < time()) {
            $this->session->unset_userdata('dois_fatores_primeiro_acesso');

            return null;
        }

        return $p;
    }

    /** @return array{email: string, expira: int, tentativas: int}|null */
    private function doisFatoresPendente(): ?array
    {
        $pendente = $this->session->userdata('dois_fatores');
        if (! is_array($pendente) || (int) ($pendente['expira'] ?? 0) < time()) {
            $this->session->unset_userdata('dois_fatores');

            return null;
        }

        return $pendente;
    }

    /** Motivo para a conta não entrar (expirada, grupo desativado), ou null. */
    private function motivoDeBloqueio(object $user): ?string
    {
        if ($this->chk_date($user->dataExpiracao)) {
            return 'A conta do usuário está expirada, por favor entre em contato com o administrador do sistema.';
        }

        // Grupo de permissão desativado (#2846): a conta não entra, como a
        // expirada (até a v4 a situação do grupo era só informativa).
        if (! $this->mapos_model->grupoAtivo((int) $user->permissoes_id)) {
            return 'O grupo de permissão desta conta está desativado. Fale com o administrador do sistema.';
        }

        return null;
    }

    private function iniciarSessao(object $user): void
    {
        // Novo ID de sessão a cada autenticação, para que um ID
        // fixado antes do login não continue válido depois dele.
        $this->session->sess_regenerate(true);

        $this->load->model('saas_model');
        $empresas = $this->saas_model->getEmpresasDoUsuario((int) $user->idUsuarios);
        $isSuperadmin = (bool) ($user->is_superadmin ?? false);

        $empresaAtivaId = 1;
        $empresaAtivaNome = 'Empresa Principal';

        if (! empty($empresas)) {
            $empresaAtivaId = (int) $empresas[0]->id;
            $empresaAtivaNome = $empresas[0]->nome_fantasia ?: $empresas[0]->razao_social;
        }

        $this->session->set_userdata([
            'nome_admin' => $user->nome,
            'email_admin' => $user->email,
            'url_image_user_admin' => $user->url_image_user,
            'id_admin' => $user->idUsuarios,
            'permissao' => $user->permissoes_id,
            'tema_modo_admin' => $user->tema_modo ?? null,
            'tema_cor_admin' => $user->tema_cor ?? null,
            'sessao_versao' => (int) ($user->sessao_versao ?? 0),
            'logado' => true,
            'is_superadmin' => $isSuperadmin,
            'empresa_id' => $empresaAtivaId,
            'empresa_nome' => $empresaAtivaNome,
            'qtd_empresas' => count($empresas),
        ]);

        // Grupo que exige 2FA e usuário sem ele: entra, mas só alcança a tela de ativação (MY_Controller).
        if (! ($user->totp_ativado_em ?? null) && $this->mapos_model->grupoExige2fa((int) $user->permissoes_id)) {
            $this->session->set_userdata('exigir_2fa_cadastro', true);
        }

        log_info('Efetuou login no sistema');
    }

    /**
     * Tela de Escolha de Empresa (Workspace Switcher) para usuários vinculados a mais de 1 empresa.
     */
    public function escolherEmpresa()
    {
        if (! $this->session->userdata('logado')) {
            redirect('login');
        }

        $this->load->model('saas_model');
        $empresas = $this->saas_model->getEmpresasDoUsuario((int) $this->session->userdata('id_admin'));

        // Se for Superadmin e não tiver empresas explícitas vinculadas, lista todas
        if (empty($empresas) && $this->session->userdata('is_superadmin')) {
            $empresas = $this->saas_model->getEmpresas();
        }

        $this->load->view('mapos/escolher_empresa', [
            'empresas' => $empresas,
            'usuarioNome' => $this->session->userdata('nome_admin'),
            'isSuperadmin' => $this->session->userdata('is_superadmin'),
        ]);
    }

    /**
     * Ação de seleção da empresa ativa.
     */
    public function selecionarEmpresa($id = null)
    {
        if (! $this->session->userdata('logado')) {
            redirect('login');
        }

        $empresaId = (int) $id;
        if ($empresaId <= 0) {
            redirect('mapos');
        }

        $usuarioId = (int) $this->session->userdata('id_admin');
        $isSuperadmin = (bool) ($this->session->userdata('is_superadmin') ?? false);

        $this->load->model('saas_model');

        // Valida se o usuário pertence à empresa ou se é Superadmin
        if (! $isSuperadmin && ! $this->saas_model->usuarioPertenceAEmpresa($usuarioId, $empresaId)) {
            log_info("Tentativa não autorizada de trocar para a empresa {$empresaId} pelo usuário {$usuarioId}");
            $this->session->set_flashdata('error', 'Você não tem permissão para acessar esta empresa.');

            redirect('mapos');
        }

        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if ($empresa) {
            $this->session->set_userdata([
                'empresa_id' => $empresa->id,
                'empresa_nome' => $empresa->nome_fantasia ?: $empresa->razao_social,
            ]);
            $this->session->set_flashdata('success', "Empresa selecionada: {$empresa->nome_fantasia}");
        } else {
            $this->session->set_flashdata('error', 'Empresa não encontrada.');
        }

        redirect('mapos');
    }

    /**
     * Resposta de login recusado, com o token CSRF novo para a próxima tentativa.
     */
    private function falha(string $mensagem): array
    {
        return ['result' => false, 'message' => $mensagem, 'MAPOS_TOKEN' => $this->security->get_csrf_hash()];
    }

    /**
     * Diz se a conta está expirada.
     *
     * Sem data cadastrada a conta não expira: usuarios.dataExpiracao é
     * date DEFAULT NULL e o servidor não exige preenchimento, então tratar
     * o vazio como expirado trancava o login de quem nunca teve expiração
     * configurada — com a mensagem enganosa de "conta expirada".
     *
     * @param  string|null  $data_banco  Data no formato aceito por DateTime, ou null
     */
    private function chk_date($data_banco)
    {
        return contaExpirada($data_banco);
    }

    /**
     * Valida regra de complexidade forte de senha.
     *
     * @return array<string, string>
     */
    private function validarSenhaForte(string $senha, string $senhaConfirmacao): array
    {
        $erros = [];

        if (strlen($senha) < 8) {
            $erros['senha'] = 'A senha precisa ter no mínimo 8 caracteres.';
        } elseif (! preg_match('/[A-Z]/', $senha)) {
            $erros['senha'] = 'A senha precisa conter pelo menos 1 letra maiúscula.';
        } elseif (! preg_match('/[a-z]/', $senha)) {
            $erros['senha'] = 'A senha precisa conter pelo menos 1 letra minúscula.';
        } elseif (! preg_match('/[0-9]/', $senha)) {
            $erros['senha'] = 'A senha precisa conter pelo menos 1 número.';
        } elseif (! preg_match('/[^a-zA-Z0-9]/', $senha)) {
            $erros['senha'] = 'A senha precisa conter pelo menos 1 caractere especial (ex: !@#$%&*).';
        } elseif ($senha !== $senhaConfirmacao) {
            $erros['senha_confirmacao'] = 'A confirmação de senha não confere.';
        }

        return $erros;
    }

    /**
     * Passo 1 de 2 do cadastro de nova empresa (Tenant com 10 dias de degustação).
     * Valida os dados na sessão e segue para a vinculação do autenticador (2FA).
     */
    public function cadastrar()
    {
        $this->load->model('saas_model');
        $erros = [];
        $valores = [];

        if ($this->input->method() === 'get' && $this->input->get('voltar')) {
            $pendente = $this->session->userdata('cadastro_empresa_pendente');
            if (is_array($pendente) && ($pendente['expira'] ?? 0) >= time()) {
                $valores = [
                    'nome' => (string) ($pendente['nome'] ?? ''),
                    'email' => (string) ($pendente['email'] ?? ''),
                ];
            }
        }

        if ($this->input->method() === 'post') {
            $captchaEsperado = (string) $this->session->userdata('captchaWord_empresa');
            $captchaInformado = trim((string) $this->input->post('captcha'));
            $this->session->unset_userdata('captchaWord_empresa');

            $nome = trim((string) $this->input->post('nome'));
            $email = strtolower(trim((string) $this->input->post('email')));
            $senha = (string) $this->input->post('senha');
            $senhaConfirmacao = (string) $this->input->post('senha_confirmacao');

            $valores = [
                'nome' => $nome,
                'email' => $email,
            ];

            if ($nome === '') {
                $erros['nome'] = 'Informe o seu nome ou o nome do seu negócio.';
            }

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $erros['email'] = 'Informe um e-mail válido para acesso.';
            }

            $erros += $this->validarSenhaForte($senha, $senhaConfirmacao);

            if ($captchaEsperado === '' || $captchaInformado === '' || strtolower($captchaInformado) !== strtolower($captchaEsperado)) {
                $erros['captcha'] = 'Os caracteres não conferem com a imagem. Digite os da imagem nova.';
            }

            $userExistente = null;
            if ($erros === []) {
                $userExistente = $this->db->get_where('usuarios', ['email' => $email])->row();
                if ($userExistente && ! senhaConfere($senha, $userExistente->senha)) {
                    $erros['email'] = 'Este e-mail já possui uma conta no sistema. Informe a sua senha correta para vincular uma nova empresa ou utilize outro e-mail.';
                }
            }

            if ($erros === []) {
                $jaTem2fa = $userExistente && ! empty($userExistente->totp_ativado_em);
                $this->load->library('Totp');

                $this->session->set_userdata('cadastro_empresa_pendente', [
                    'nome' => $nome,
                    'email' => $email,
                    'senha_plana' => $senha,
                    'usuario_existente_id' => $userExistente ? (int) $userExistente->idUsuarios : null,
                    'ja_tem_2fa' => $jaTem2fa,
                    'segredo' => $jaTem2fa ? null : $this->totp->criarSegredo(),
                    'expira' => time() + 1800,
                ]);

                return redirect(site_url('login/cadastrar2fa'));
            }
        }

        $this->load->view('mapos/cadastrar_empresa', [
            'valores' => $valores,
            'erros' => $erros,
            'erro' => $this->session->flashdata('error'),
        ]);
    }

    /**
     * Passo 2 de 2 do cadastro de nova empresa: vincula o Google Authenticator (2FA)
     * e só então grava empresa + usuário no banco.
     */
    public function cadastrar2fa()
    {
        $pendente = $this->session->userdata('cadastro_empresa_pendente');
        if (! is_array($pendente) || ($pendente['expira'] ?? 0) < time()) {
            $this->session->unset_userdata('cadastro_empresa_pendente');
            $this->session->set_flashdata('error', 'Preencha os dados da sua conta primeiro.');

            return redirect(site_url('login/cadastrar'));
        }

        $this->load->library('Totp');
        $this->load->model('saas_model');

        $jaTem2fa = ! empty($pendente['ja_tem_2fa']);
        $email = (string) $pendente['email'];
        $nome = (string) $pendente['nome'];
        $segredo = (string) ($pendente['segredo'] ?? '');
        $erro = null;

        if ($this->input->method() === 'post') {
            $codigo = trim((string) $this->input->post('codigo'));
            $passo = null;

            if ($jaTem2fa) {
                $userExistente = $this->mapos_model->usuarioParaLoginPorId((int) $pendente['usuario_existente_id']);
                $ok = $userExistente && $this->totp->aceitar($this->mapos_model, $userExistente, $codigo);
                $passo = $ok ? 1 : null;
            } else {
                $passo = $this->totp->verificar($segredo, $codigo);
            }

            if ($passo === null) {
                $erro = 'Código incorreto. Confira os 6 dígitos mostrados no aplicativo e tente novamente.';
            } else {
                $resultado = $this->saas_model->cadastrarEmpresaComDono(
                    [
                        'razao_social' => $nome,
                        'nome_fantasia' => $nome,
                        'documento' => '',
                        'telefone' => '',
                        'email' => $email,
                    ],
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'senha_plana' => (string) $pendente['senha_plana'],
                        'telefone' => '',
                        'cep' => '',
                        'rua' => '',
                        'numero' => '',
                        'bairro' => '',
                        'cidade' => '',
                        'estado' => '',
                    ],
                    10
                );

                if (! $resultado['sucesso']) {
                    $erro = $resultado['mensagem'] ?? 'Erro ao criar a empresa. Tente novamente.';
                } else {
                    $usuario = $resultado['usuario'];
                    $codigos = null;

                    if (! $jaTem2fa) {
                        $codigos = $this->totp->gerarCodigosDeRecuperacao();
                        $this->mapos_model->ativarTotp(
                            (int) $usuario->idUsuarios,
                            $this->totp->cifrar($segredo),
                            array_map([Totp::class, 'hashDoCodigo'], $codigos),
                            (int) $passo
                        );
                        $usuario = $this->mapos_model->usuarioParaLoginPorId((int) $usuario->idUsuarios) ?? $usuario;
                    }

                    $this->session->unset_userdata('cadastro_empresa_pendente');
                    $this->iniciarSessao($usuario);
                    $this->session->set_userdata([
                        'empresa_id' => (int) $resultado['empresa_id'],
                        'empresa_nome' => (string) $resultado['empresa_nome'],
                        'saas_modal_boas_vindas' => true,
                    ]);

                    if ($codigos !== null) {
                        $this->session->set_userdata('codigos_recuperacao_novos', [
                            'codigos' => $codigos,
                            'email' => (string) $usuario->email,
                            'destino' => site_url('mapos'),
                        ]);

                        return redirect(site_url('login/codigosRecuperacao'));
                    }

                    return redirect(site_url('mapos'));
                }
            }
        }

        return $this->load->view('mapos/cadastrar_empresa_2fa', [
            'ja_tem_2fa' => $jaTem2fa,
            'email' => $email,
            'nome' => $nome,
            'qr' => $jaTem2fa ? null : $this->totp->qrCodeDataUri($email, $segredo),
            'segredo' => $jaTem2fa ? null : Totp::formatarSegredo($segredo),
            'segredo_puro' => $jaTem2fa ? null : $segredo,
            'otpauth_uri' => $jaTem2fa ? null : $this->totp->uri($email, $segredo),
            'erro' => $erro,
        ]);
    }

    /**
     * Captcha para o formulário de cadastro de empresa.
     */
    public function captcha()
    {
        header('Content-type: image/png');
        header('Cache-Control: no-store');

        $arrFont = [
            'font-ZXX_Noise.otf',
            'font-karabine.ttf',
            'font-capture.ttf',
            'font-captcha.ttf',
        ];
        shuffle($arrFont);

        $codigoCaptcha = substr(bin2hex(random_bytes(4)), 0, 7);

        $bgFile = FCPATH . 'assets/img/captcha_bg.jpg';
        if (file_exists($bgFile)) {
            $img = imagecreatefromjpeg($bgFile);
        } else {
            $img = imagecreatetruecolor(180, 50);
            $bg = imagecolorallocate($img, 240, 240, 240);
            imagefill($img, 0, 0, $bg);
        }

        $corCaptcha = imagecolorallocate($img, 220, 38, 38);
        $fontPath = FCPATH . 'assets/fonts/captcha/' . $arrFont[0];

        if (file_exists($fontPath)) {
            imagettftext($img, 22, 0, 10, rand(32, 38), $corCaptcha, $fontPath, $codigoCaptcha);
        } else {
            imagestring($img, 5, 30, 15, $codigoCaptcha, $corCaptcha);
        }

        imagepng($img);
        imagedestroy($img);

        $this->session->set_userdata('captchaWord_empresa', $codigoCaptcha);
    }

    /**
     * Tela de recuperação de senha sem dependência de e-mail (usa o aplicativo autenticador).
     */
    public function resetarSenha()
    {
        $this->load->view('mapos/resetar_senha', [
            'erro' => $this->session->flashdata('error'),
            'emailValor' => $this->session->flashdata('reset_email'),
        ]);
    }

    /**
     * Mantido por compatibilidade de rota antiga; redireciona para o fluxo via TOTP.
     */
    public function gerarTokenResetarSenha()
    {
        return redirect(site_url('login/resetarSenha'));
    }

    /**
     * Valida e-mail + código de 6 dígitos (ou código de recuperação) para liberar a troca de senha.
     */
    public function verificarRecuperacaoTotp()
    {
        if ($this->input->method() !== 'post') {
            return redirect(site_url('login/resetarSenha'));
        }

        $email = strtolower(trim((string) $this->input->post('email')));
        $codigo = trim((string) $this->input->post('codigo'));
        $ip = $this->input->ip_address();

        $this->load->library('Limite_login');
        if ($this->limite_login->bloqueado('reset', $email, $ip)) {
            $this->session->set_flashdata('error', Limite_login::MENSAGEM);
            $this->session->set_flashdata('reset_email', $email);

            return redirect(site_url('login/resetarSenha'));
        }

        $this->limite_login->registrarFalha('reset', $email, $ip);

        $user = $email !== '' ? $this->mapos_model->check_credentials($email) : null;
        $this->load->library('Totp');

        $ok = $user
            && $this->motivoDeBloqueio($user) === null
            && ! empty($user->totp_ativado_em)
            && $this->totp->aceitar($this->mapos_model, $user, $codigo);

        if (! $ok) {
            log_info("Tentativa de recuperação de senha via 2FA recusada para: {$email}");
            $this->session->set_flashdata('error', 'E-mail ou código inválido. Confira os 6 dígitos no aplicativo (ou um código de recuperação) e tente novamente.');
            $this->session->set_flashdata('reset_email', $email);

            return redirect(site_url('login/resetarSenha'));
        }

        $this->limite_login->registrarSucesso('reset', $email);
        $this->session->sess_regenerate(true);

        $tokenSessao = bin2hex(random_bytes(32));
        $this->session->set_userdata('reset_totp_autorizado', [
            'token' => $tokenSessao,
            'usuario_id' => (int) $user->idUsuarios,
            'email' => (string) $user->email,
            'expira' => time() + 600,
        ]);

        return redirect(site_url('login/novaSenha'));
    }

    /** @return array{token: string, usuario_id: int, email: string, expira: int}|null */
    private function resetTotpValido(?string $tokenPost = null): ?array
    {
        $dados = $this->session->userdata('reset_totp_autorizado');
        if (! is_array($dados) || ($dados['expira'] ?? 0) < time()) {
            $this->session->unset_userdata('reset_totp_autorizado');

            return null;
        }

        if ($tokenPost !== null && ! hash_equals((string) ($dados['token'] ?? ''), $tokenPost)) {
            return null;
        }

        return $dados;
    }

    /**
     * Tela para digitar nova senha após validação pelo autenticador (ou token legado).
     */
    public function novaSenha()
    {
        $sessaoReset = $this->resetTotpValido();
        if ($sessaoReset !== null) {
            return $this->load->view('mapos/nova_senha', [
                'token' => $sessaoReset['token'],
                'erros' => [],
            ]);
        }

        $tokenEmClaro = trim((string) ($this->input->get('token') ?: $this->input->post('token')));
        if ($tokenEmClaro === '') {
            $this->session->set_flashdata('error', 'Informe seu e-mail e o código do autenticador para redefinir a senha.');

            return redirect(site_url('login/resetarSenha'));
        }

        $tokenHash = tokenRecuperacaoHash($tokenEmClaro);
        $registro = $this->db
            ->where('token', $tokenHash)
            ->where('token_utilizado', 0)
            ->where('data_expiracao >=', date('Y-m-d H:i:s'))
            ->get('resets_de_senha')
            ->row();

        if (! $registro) {
            $this->session->set_flashdata('error', 'Código inválido ou expirado. Faça uma nova verificação abaixo.');

            return redirect(site_url('login/resetarSenha'));
        }

        return $this->load->view('mapos/nova_senha', [
            'token' => $tokenEmClaro,
            'erros' => [],
        ]);
    }

    /**
     * Grava a nova senha para o usuário.
     */
    public function salvarNovaSenha()
    {
        if ($this->input->method() !== 'post') {
            return redirect(site_url('login/resetarSenha'));
        }

        $tokenEmClaro = trim((string) $this->input->post('token'));
        if ($tokenEmClaro === '') {
            return redirect(site_url('login/resetarSenha'));
        }

        $sessaoReset = $this->resetTotpValido($tokenEmClaro);
        $registroLegado = null;
        $user = null;

        if ($sessaoReset !== null) {
            $user = $this->mapos_model->usuarioParaLoginPorId((int) $sessaoReset['usuario_id']);
        } else {
            $tokenHash = tokenRecuperacaoHash($tokenEmClaro);
            $registroLegado = $this->db
                ->where('token', $tokenHash)
                ->where('token_utilizado', 0)
                ->where('data_expiracao >=', date('Y-m-d H:i:s'))
                ->get('resets_de_senha')
                ->row();

            if ($registroLegado) {
                $user = $this->mapos_model->check_credentials($registroLegado->email);
            }
        }

        if (! $user) {
            $this->session->unset_userdata('reset_totp_autorizado');
            $this->session->set_flashdata('error', 'Sessão expirada ou código inválido. Verifique novamente.');

            return redirect(site_url('login/resetarSenha'));
        }

        $senha = (string) $this->input->post('senha');
        $senhaConfirmacao = (string) $this->input->post('senha_confirmacao');
        $erros = $this->validarSenhaForte($senha, $senhaConfirmacao);

        if ($erros !== []) {
            return $this->load->view('mapos/nova_senha', [
                'token' => $tokenEmClaro,
                'erros' => $erros,
            ]);
        }

        // Atualiza a senha e invalida sessões antigas
        $novaSenhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $novaVersao = (int) ($user->sessao_versao ?? 0) + 1;

        $this->db->where('idUsuarios', $user->idUsuarios)->update('usuarios', [
            'senha' => $novaSenhaHash,
            'sessao_versao' => $novaVersao,
        ]);

        if ($registroLegado) {
            $this->db->where('id', $registroLegado->id)->update('resets_de_senha', ['token_utilizado' => 1]);
        }
        $this->session->unset_userdata('reset_totp_autorizado');

        log_info("Senha do usuário {$user->idUsuarios} alterada via recuperação 2FA.");
        $this->session->set_flashdata('success', 'Sua senha foi redefinida com sucesso! Entre agora com a nova senha.');

        return redirect(site_url('login'));
    }

    /**
     * Tela para colar o token manualmente.
     */
    public function tokenManual()
    {
        if ($this->input->method() === 'post') {
            $token = trim((string) $this->input->post('token'));
            if ($token !== '') {
                return redirect(site_url('login/novaSenha?token=' . urlencode($token)));
            }
        }

        $this->load->view('mapos/token_manual');
    }
}
