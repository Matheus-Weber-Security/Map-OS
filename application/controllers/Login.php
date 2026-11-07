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

        // 2FA ativo (#2876): a senha sozinha não entra. A sessão guarda só que a
        // senha conferiu, por 5 minutos e 5 tentativas, e o código decide. O
        // contador de falhas só zera com o código certo: senão repetir a senha
        // devolveria tentativas de código de graça.
        if ($user->totp_ativado_em ?? null) {
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

        return redirect(site_url('mapos'));
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
     * Tela de cadastro de nova empresa (Tenant com 10 dias de degustação).
     */
    public function cadastrar()
    {
        $this->load->model('saas_model');
        $erros = [];
        $valores = [];

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

            if ($captchaEsperado === '' || $captchaInformado === '' || strtolower($captchaInformado) !== strtolower($captchaEsperado)) {
                $erros['captcha'] = 'Os caracteres não conferem com a imagem. Digite os da imagem nova.';
            }

            if ($erros === []) {
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
                        'senha_plana' => $senha,
                        'telefone' => '',
                        'cep' => '',
                        'rua' => '',
                        'numero' => '',
                        'bairro' => '',
                        'cidade' => '',
                        'estado' => '',
                    ],
                    10 // 10 dias de degustação
                );

                if (! $resultado['sucesso']) {
                    $erros['_geral'] = $resultado['mensagem'] ?? 'Erro ao criar a empresa. Tente novamente.';
                } else {
                    // Auto-login na empresa recém-criada
                    $this->iniciarSessao($resultado['usuario']);
                    $this->session->set_userdata([
                        'empresa_id' => (int) $resultado['empresa_id'],
                        'empresa_nome' => (string) $resultado['empresa_nome'],
                        'saas_modal_boas_vindas' => true,
                    ]);

                    return redirect(site_url('mapos'));
                }
            }
        }

        $this->load->view('mapos/cadastrar_empresa', [
            'valores' => $valores,
            'erros' => $erros,
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
     * Tela de solicitação de redefinição de senha para usuários do painel.
     */
    public function resetarSenha()
    {
        $this->load->view('mapos/resetar_senha', [
            'erro' => $this->session->flashdata('error'),
        ]);
    }

    /**
     * Processa a solicitação de token para redefinir senha.
     */
    public function gerarTokenResetarSenha()
    {
        $email = strtolower(trim((string) $this->input->post('email')));
        $ip = $this->input->ip_address();

        $this->load->library('Limite_login');
        if ($this->limite_login->bloqueado('reset', $email, $ip)) {
            $this->session->set_flashdata('error', Limite_login::MENSAGEM);

            return redirect(site_url('login/resetarSenha'));
        }

        $this->limite_login->registrarFalha('reset', $email, $ip);

        $user = $this->mapos_model->check_credentials($email);

        // Resposta idêntica existindo ou não a conta, evitando enumeração de usuários
        if (! $user) {
            log_info("Solicitação de redefinição de senha para e-mail inexistente: {$email}");
            $this->session->set_flashdata('success', "Se o e-mail informado estiver cadastrado, as instruções para redefinição foram enviadas para {$email}.");

            return redirect(site_url('login'));
        }

        [$tokenEmClaro, $tokenHash] = tokenRecuperacaoGerar();

        // Invalida tokens anteriores
        $this->db->where('email', $user->email)->where('token_utilizado', 0)->update('resets_de_senha', ['token_utilizado' => 1]);

        $dadosToken = [
            'email' => $user->email,
            'token' => $tokenHash,
            'data_expiracao' => date('Y-m-d H:i:s', time() + 3600), // 1 hora
            'token_utilizado' => 0,
        ];

        $this->db->insert('resets_de_senha', $dadosToken);

        // Tenta enviar e-mail
        $this->enviarEmailRecuperacao($user, $tokenEmClaro);

        log_info("Usuário {$user->idUsuarios} solicitou redefinição de senha.");
        $this->session->set_flashdata('success', "Instruções para redefinir sua senha foram enviadas para {$user->email}. O link é válido por 1 hora.");

        return redirect(site_url('login'));
    }

    /**
     * Envia o e-mail com o link de recuperação.
     */
    private function enviarEmailRecuperacao(object $user, string $token): bool
    {
        $this->load->model('email_model');
        $emitente = $this->mapos_model->getEmitente();
        $nomeSistema = $this->config->item('app_name') ?: 'Nexus Ticket';
        $remetenteNome = $emitente ? $emitente->nome : $nomeSistema;
        $remetenteEmail = $emitente ? $emitente->email : 'nao-responder@mapos.com.br';

        $link = site_url('login/novaSenha?token=' . $token);

        $mensagem = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <h2 style='color: #0f172a;'>Recuperação de Senha</h2>
            <p>Olá, <strong>" . htmlspecialchars($user->nome) . "</strong>,</p>
            <p>Recebemos uma solicitação para redefinir a senha da sua conta de acesso ao sistema <strong>" . htmlspecialchars($nomeSistema) . "</strong>.</p>
            <p style='margin: 24px 0;'>
                <a href='" . htmlspecialchars($link) . "' style='background-color: #f37338; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Redefinir Minha Senha</a>
            </p>
            <p style='color: #64748b; font-size: 14px;'>Ou acesse o link manualmente: <br><a href='" . htmlspecialchars($link) . "'>" . htmlspecialchars($link) . "</a></p>
            <p style='color: #64748b; font-size: 14px;'>Se preferir digitar o código na página, use o token: <strong>" . htmlspecialchars($token) . "</strong></p>
            <p style='color: #94a3b8; font-size: 12px; margin-top: 32px;'>Este link expira em 1 hora. Se você não solicitou a alteração, ignore este e-mail com segurança.</p>
        </div>";

        $headers = [
            'From' => "\"$remetenteNome\" <$remetenteEmail>",
            'Subject' => 'Recuperação de Senha - ' . $nomeSistema,
            'Return-Path' => '',
        ];

        $emailData = [
            'to' => $user->email,
            'message' => $mensagem,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s'),
            'headers' => serialize($headers),
        ];

        try {
            $this->email_model->add('email_queue', $emailData);
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao enfileirar e-mail de recuperação: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tela para digitar nova senha a partir do token.
     */
    public function novaSenha()
    {
        $tokenEmClaro = trim((string) ($this->input->get('token') ?: $this->input->post('token')));

        if ($tokenEmClaro === '') {
            $this->session->set_flashdata('error', 'Token de recuperação não informado.');

            return redirect(site_url('login'));
        }

        $tokenHash = tokenRecuperacaoHash($tokenEmClaro);
        $registro = $this->db
            ->where('token', $tokenHash)
            ->where('token_utilizado', 0)
            ->where('data_expiracao >=', date('Y-m-d H:i:s'))
            ->get('resets_de_senha')
            ->row();

        if (! $registro) {
            $this->session->set_flashdata('error', 'Token inválido ou expirado. Por favor, solicite uma nova recuperação de senha.');

            return redirect(site_url('login'));
        }

        $this->load->view('mapos/nova_senha', [
            'token' => $tokenEmClaro,
            'erros' => [],
        ]);
    }

    /**
     * Grava a nova senha para o usuário.
     */
    public function salvarNovaSenha()
    {
        $tokenEmClaro = trim((string) $this->input->post('token'));
        if ($tokenEmClaro === '') {
            return redirect(site_url('login'));
        }

        $tokenHash = tokenRecuperacaoHash($tokenEmClaro);
        $registro = $this->db
            ->where('token', $tokenHash)
            ->where('token_utilizado', 0)
            ->where('data_expiracao >=', date('Y-m-d H:i:s'))
            ->get('resets_de_senha')
            ->row();

        if (! $registro) {
            $this->session->set_flashdata('error', 'Token inválido ou expirado. Solicite uma nova recuperação.');

            return redirect(site_url('login'));
        }

        $senha = (string) $this->input->post('senha');
        $senhaConfirmacao = (string) $this->input->post('senha_confirmacao');
        $erros = [];

        if (strlen($senha) < 8) {
            $erros['senha'] = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif ($senha !== $senhaConfirmacao) {
            $erros['senha_confirmacao'] = 'A confirmação de senha não confere.';
        }

        if ($erros !== []) {
            return $this->load->view('mapos/nova_senha', [
                'token' => $tokenEmClaro,
                'erros' => $erros,
            ]);
        }

        $user = $this->mapos_model->check_credentials($registro->email);
        if (! $user) {
            $this->session->set_flashdata('error', 'Usuário não encontrado.');

            return redirect(site_url('login'));
        }

        // Atualiza a senha e invalida sessões antigas
        $novaSenhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $novaVersao = (int) ($user->sessao_versao ?? 0) + 1;

        $this->db->where('idUsuarios', $user->idUsuarios)->update('usuarios', [
            'senha' => $novaSenhaHash,
            'sessao_versao' => $novaVersao,
        ]);

        // Marca token como utilizado
        $this->db->where('id', $registro->id)->update('resets_de_senha', ['token_utilizado' => 1]);

        log_info("Senha do usuário {$user->idUsuarios} alterada via recuperação.");
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
