<?php

class MY_Controller extends CI_Controller
{
    public $data = [
        'configuration' => [
            // Itens por página das listagens. As telas usam o componente
            // pagination via paginacao(); o application/config/pagination.php
            // só continua para a API do app.
            'per_page' => 10,
            'app_name' => 'Nexus Ticket',
            'os_notification' => 'cliente',
            'control_estoque' => '1',
            'notifica_whats' => '',
            'control_baixa' => '0',
            'control_edit_pago' => '0',
            'control_del_pago' => '0',
            'control_editos' => '1',
            'baixa_auto_cobranca' => '0',
            'pix_key' => '',
            'exigir_2fa_login' => '0',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        if ((! session_id()) || (! $this->session->userdata('logado'))) {
            redirect('login');
        }
        $this->validarSessaoDoUsuario();
        $this->exigirBancoAtualizado();
        $this->exigirDoisFatores();
        $this->verificarAssinaturaDaEmpresa();
        $this->load_configuration();
        $this->verificarPermissaoDaRota();
    }

    /**
     * Situação da assinatura do tenant:
     * - 'ativa': em dia ou vence hoje ($diasRestantes >= 0)
     * - 'carencia': vencida há até 2 dias ($diasRestantes >= -2 e status = 'ativa')
     * - 'somente_leitura': carência encerrada ($diasRestantes < -2 ou status != 'ativa')
     *
     * @return array{status: 'ativa'|'carencia'|'somente_leitura', dias_restantes: int, dias_carencia: int, eh_master: bool}
     */
    public function obterSituacaoAssinaturaEmpresa(): array
    {
        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $ehMaster = ($empresaId === 1 || (bool) $this->session->userdata('is_superadmin'));

        if ($ehMaster) {
            return [
                'status' => 'ativa',
                'dias_restantes' => 9999,
                'dias_carencia' => 0,
                'eh_master' => true,
            ];
        }

        $this->load->model('saas_model');
        $empresa = $this->saas_model->getEmpresaById($empresaId);

        if (! $empresa) {
            return [
                'status' => 'ativa',
                'dias_restantes' => 0,
                'dias_carencia' => 0,
                'eh_master' => false,
            ];
        }

        $hoje = strtotime('today');
        $vencimento = $empresa->data_vencimento ? strtotime($empresa->data_vencimento) : $hoje;
        $diasRestantes = (int) ceil(($vencimento - $hoje) / 86400);

        if ($empresa->status !== 'ativa') {
            return [
                'status' => 'somente_leitura',
                'dias_restantes' => $diasRestantes,
                'dias_carencia' => 0,
                'eh_master' => false,
            ];
        }

        if ($diasRestantes >= 0) {
            return [
                'status' => 'ativa',
                'dias_restantes' => $diasRestantes,
                'dias_carencia' => 0,
                'eh_master' => false,
            ];
        }

        // Carência de 2 dias após o vencimento
        // $diasRestantes == -1 -> 1º dia após vencimento (restam 2 dias de carência)
        // $diasRestantes == -2 -> 2º dia após vencimento (resta 1 dia de carência / último dia)
        if ($diasRestantes >= -2) {
            $diasCarenciaRestantes = 2 + ($diasRestantes + 1);
            return [
                'status' => 'carencia',
                'dias_restantes' => $diasRestantes,
                'dias_carencia' => max(1, $diasCarenciaRestantes),
                'eh_master' => false,
            ];
        }

        return [
            'status' => 'somente_leitura',
            'dias_restantes' => $diasRestantes,
            'dias_carencia' => 0,
            'eh_master' => false,
        ];
    }

    /**
     * Determina se a rota atual é segura para acesso em modo somente leitura
     * (consultas, listagens, detalhes, relatórios e impressões térmicas/A4).
     */
    protected function ehRotaSomenteLeitura(string $controller, string $metodo): bool
    {
        $controller = strtolower($controller);
        $metodo = strtolower($metodo);

        // Controladores administrativos e de pagamento sempre permitidos
        if (in_array($controller, ['assinatura', 'saas', 'login', 'componentes'], true)) {
            return true;
        }

        // Preferências de tela do usuário e 2FA no Mapos
        if ($controller === 'mapos') {
            return in_array($metodo, [
                'index',
                'minhaconta',
                'salvartemamodo',
                'salvartemacor',
                'doisfatores',
                'ativardoisfatores',
                'novoscodigosdoisfatores',
                'desativardoisfatores',
                'pesquisar',
                'buscarapida',
                'calendario',
            ], true);
        }

        // Relatórios são todos apenas de consulta e impressão
        if ($controller === 'relatorios') {
            return true;
        }

        // Rotas universais de consulta, listagem e download
        if (in_array($metodo, [
            'index',
            'gerenciar',
            'visualizar',
            'lancamentos',
            'cobrancas',
            'download',
            'downloadanexo',
            'anexo',
            'pesquisar',
            'buscarapida',
            'calendario',
        ], true)) {
            return true;
        }

        // Impressões (imprimir, imprimirTermica, imprimirA4, imprimirGarantiaOs, etc.)
        if (str_starts_with($metodo, 'imprimir')) {
            return true;
        }

        // Autocompletes de busca
        if (str_starts_with($metodo, 'autocomplete')) {
            return true;
        }

        return false;
    }

    /**
     * Interceptador de expiração de assinatura do SaaS.
     * - Carência de 2 dias: acesso permitido com banner informativo no layout.
     * - Somente Leitura (após carência): permite consultas e impressões, bloqueando mutações.
     */
    protected function verificarAssinaturaDaEmpresa(): void
    {
        $situacao = $this->obterSituacaoAssinaturaEmpresa();
        $this->data['saas_assinatura'] = $situacao;

        if ($situacao['status'] !== 'somente_leitura') {
            return;
        }

        $controller = (string) $this->router->class;
        $metodo = (string) $this->router->method;

        if ($this->ehRotaSomenteLeitura($controller, $metodo)) {
            return;
        }

        // Ação mutável bloqueada em modo somente leitura
        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => 'O período de carência da sua assinatura encerrou. O sistema está em modo de consulta para garantir a segurança dos seus dados. Renove sua assinatura para realizar alterações.',
                    'bloqueado_assinatura' => true,
                ]));
            $this->output->_display();
            exit;
        }

        $this->session->set_flashdata('error', 'O período de carência da sua assinatura encerrou. O sistema está em modo de consulta para que você possa consultar e imprimir seu histórico com segurança. Para criar ou editar registros, renove sua assinatura.');

        $redirecionarPara = method_exists($this, 'index') ? $this->router->class : 'mapos';
        redirect($redirecionarPara);
    }

    /**
     * Revalida o usuário da sessão no banco a cada requisição (#2879): conta
     * desativada, excluída ou expirada, grupo desativado e senha trocada em outra
     * sessão encerram esta com uma mensagem. O grupo e a exigência de 2FA seguem
     * o banco, não o que ficou gravado no login.
     */
    protected function validarSessaoDoUsuario(): void
    {
        $this->load->model('mapos_model');
        $estado = $this->mapos_model->estadoDaSessao((int) $this->session->userdata('id_admin'));

        $motivo = $this->motivoDeEncerramento($estado, (int) $this->session->userdata('sessao_versao'));

        if ($motivo !== null) {
            $this->session->unset_userdata(['logado', 'id_admin', 'nome_admin', 'email_admin', 'url_image_user_admin', 'tema_modo_admin', 'tema_cor_admin', 'permissao', 'exigir_2fa_cadastro', 'totp_pendente', 'sessao_versao']);
            $this->session->sess_regenerate(true);
            $this->session->set_flashdata('error', $motivo);
            redirect('login');
        }

        if ((int) $this->session->userdata('permissao') !== $estado['permissoes_id']) {
            $this->session->set_userdata('permissao', $estado['permissoes_id']);
        }
        if ($estado['exige_2fa_cadastro']) {
            $this->session->set_userdata('exigir_2fa_cadastro', true);
        } elseif ($this->session->userdata('exigir_2fa_cadastro')) {
            // Voltou a um grupo sem exigência (ou ativou o 2FA): solta a tela de ativação.
            $this->session->unset_userdata('exigir_2fa_cadastro');
        }
    }

    /**
     * Por que a sessão não vale mais, ou null se vale (#2879).
     *
     * @param  array{situacao: int, expirada: bool, grupo_ativo: bool, versao: int}|null  $estado  De Mapos_model::estadoDaSessao()
     */
    protected function motivoDeEncerramento(?array $estado, int $versaoDaSessao): ?string
    {
        if ($estado === null || $estado['situacao'] !== 1) {
            return 'Sua conta está desativada. Fale com o administrador do sistema.';
        }
        if ($estado['expirada']) {
            return 'A conta do usuário está expirada, por favor entre em contato com o administrador do sistema.';
        }
        if (! $estado['grupo_ativo']) {
            return 'O grupo de permissão desta conta está desativado. Fale com o administrador do sistema.';
        }
        if ($estado['versao'] !== $versaoDaSessao) {
            return 'A senha desta conta foi alterada. Entre novamente.';
        }

        return null;
    }

    /**
     * Banco com migrations pendentes (#3006): quem instala a v5 e importa um
     * dump da 4.x fica com o esquema antigo, e as telas quebram com colunas
     * inexistentes. Enquanto houver pendência, nenhuma rota do painel roda: a
     * tela mapos/banco-desatualizado pergunta se quer atualizar (só
     * cSistema; os demais pedem para avisar o administrador). Só
     * mapos/atualizarBanco passa; o logout é do controller Login, que não
     * estende MY_Controller. A tela não usa o layout() nem colunas novas,
     * porque precisa abrir com o esquema antigo. AJAX recebe 503 em JSON.
     */
    protected function exigirBancoAtualizado(): void
    {
        $rota = strtolower($this->router->class . '/' . $this->router->method);
        if ($rota === 'mapos/atualizarbanco') {
            return;
        }

        $this->load->helper('manutencao');
        $pendentes = migracoesPendentes($this->db);
        if ($pendentes === []) {
            return;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(503)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'result' => false,
                    'message' => 'O banco de dados precisa ser atualizado. Abra o painel para continuar.',
                ]));
            $this->output->_display();
            $this->encerrar();

            return;
        }

        $this->output->set_status_header(503);
        $this->load->view('mapos/banco-desatualizado', [
            'quantidade' => count($pendentes),
            'podeAtualizar' => (bool) $this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema'),
            'erro' => $this->session->flashdata('error'),
            'acaoAtualizar' => site_url('mapos/atualizarBanco'),
            'acaoSair' => site_url('login/sair'),
            'csrfNome' => $this->security->get_csrf_token_name(),
            'csrfValor' => $this->security->get_csrf_hash(),
        ]);
        $this->output->_display();
        $this->encerrar();
    }

    /**
     * Grupo que exige o 2FA (#2876): até o usuário ativar, só a tela de
     * ativação e o logout abrem. Qualquer outra rota leva para ela.
     */
    protected function exigirDoisFatores(): void
    {
        if (! $this->session->userdata('exigir_2fa_cadastro')) {
            return;
        }

        $rota = strtolower($this->router->class . '/' . $this->router->method);
        if (! in_array($rota, ['mapos/doisfatores', 'mapos/ativardoisfatores'], true)) {
            $this->session->set_flashdata('error', 'Configure a autenticação em dois fatores (aplicativo autenticador) para continuar e garantir sua recuperação de senha.');
            redirect(site_url('mapos/doisFatores'));
        }
    }

    /**
     * Confere a rota atual contra application/config/permissions_map.php.
     *
     * Roda antes do método do controller. Método público fora do mapa é
     * negado, para que esquecer a checagem numa rota nova feche o acesso em
     * vez de abri-lo.
     */
    protected function verificarPermissaoDaRota()
    {
        $controller = $this->router->class;
        $metodo = $this->router->method;

        // Rota que o CodeIgniter vai responder com 404 continua com 404.
        if (! $this->ehRota($metodo)) {
            return;
        }

        $this->config->load('permissions_map');
        $regra = $this->regraDaRota((array) $this->config->item('permissions_map'), $controller, $metodo);

        if (! $this->regraPermite($regra)) {
            $this->negarAcesso();
        }
    }

    /**
     * Diz se o método é alcançável pela URL, com os mesmos critérios do
     * CodeIgniter: público, sem prefixo "_" e não herdado de CI_Controller.
     */
    protected function ehRota($metodo)
    {
        $metodo = (string) $metodo;

        if ($metodo === '' || $metodo[0] === '_' || ! method_exists($this, $metodo)) {
            return false;
        }

        if (method_exists('CI_Controller', $metodo)) {
            return false;
        }

        return (new ReflectionMethod($this, $metodo))->isPublic();
    }

    /**
     * Busca a regra do método no mapa, ignorando maiúsculas e minúsculas.
     *
     * @return string|array|false|null null quando a rota não está no mapa
     */
    protected function regraDaRota(array $mapa, $controller, $metodo)
    {
        foreach ($mapa as $nomeController => $metodos) {
            if (strcasecmp($nomeController, (string) $controller) !== 0) {
                continue;
            }

            foreach ($metodos as $nomeMetodo => $regra) {
                if (strcasecmp($nomeMetodo, (string) $metodo) === 0) {
                    return $regra;
                }
            }
        }

        return null;
    }

    /**
     * Aplica uma regra do mapa ao usuário logado. Ver o formato no cabeçalho
     * de permissions_map.php.
     */
    protected function regraPermite($regra)
    {
        if ((bool) $this->session->userdata('is_superadmin')) {
            return true;
        }

        if ($regra === '*') {
            return true;
        }

        if (is_string($regra) && $regra !== '') {
            return $this->hasAnyPermission([$regra]);
        }

        if (is_array($regra) && isset($regra['todas'])) {
            if (! is_array($regra['todas']) || $regra['todas'] === []) {
                return false;
            }

            foreach ($regra['todas'] as $permissao) {
                if (! $this->hasAnyPermission([$permissao])) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($regra) && $regra !== []) {
            return $this->hasAnyPermission(array_values($regra));
        }

        // null (fora do mapa), false (não é rota) e qualquer valor malformado
        return false;
    }

    /**
     * Responde a uma rota negada: JSON 403 para AJAX, aviso e redirecionamento
     * para o painel nas demais.
     */
    protected function negarAcesso()
    {
        $mensagem = 'Você não tem permissão para acessar esta página.';

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['result' => false, 'message' => $mensagem]));
            $this->output->_display();
            $this->encerrar();

            return;
        }

        $this->session->set_flashdata('error', $mensagem);
        $this->redirecionarParaOPainel();
    }

    protected function redirecionarParaOPainel()
    {
        redirect(base_url());
    }

    protected function encerrar()
    {
        exit;
    }

    private function load_configuration()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $configuracoes = $this->CI->db->get('configuracoes')->result();

        foreach ($configuracoes as $c) {
            $this->data['configuration'][$c->config] = $c->valor;
        }

        // Nome do sistema fixo e bloqueado para manter o original
        $this->data['configuration']['app_name'] = 'Nexus Ticket';

        // Modo de cor do próprio usuário (#2854); separado de app_tema_modo, o padrão do sistema.
        $this->data['configuration']['tema_usuario_modo'] = (string) $this->session->userdata('tema_modo_admin');
        // Cor do próprio usuário (#3032); separada de app_tema_cor, a cor padrão do sistema.
        $this->data['configuration']['tema_usuario_cor'] = (string) $this->session->userdata('tema_cor_admin');
    }

    /**
     * O usuário logado pode conceder, editar ou redefinir algo que tem estas
     * permissões? Só o que ele mesmo tem; o administrador pode tudo (#2879).
     *
     * @param  list<string>  $codigosDoAlvo
     */
    protected function podeDelegar(array $codigosDoAlvo): bool
    {
        $this->load->helper('usuarios');

        if ((int) $this->session->userdata('id_admin') === USUARIO_SUPER_ADMIN || (int) $this->session->userdata('permissao') === PERMISSAO_ADMIN || (bool) $this->session->userdata('is_superadmin')) {
            return true;
        }

        $this->load->model('permissoes_model');
        $meu = $this->permissoes_model->getById((int) $this->session->userdata('permissao'));

        return delegacaoPermitida(false, permissoesMarcadas($meu->permissoes ?? null), $codigosDoAlvo);
    }

    /**
     * O grupo de permissão (por id) está ao alcance de quem opera? Ver podeDelegar().
     */
    protected function podeDelegarGrupo(int $idPermissao): bool
    {
        $this->load->helper('usuarios');
        $this->load->model('permissoes_model');
        $grupo = $this->permissoes_model->getById($idPermissao);

        return $grupo ? $this->podeDelegar(permissoesMarcadas($grupo->permissoes ?? null)) : false;
    }

    /**
     * Verifica se o usuário logado possui ao menos uma das permissões informadas.
     */
    protected function hasAnyPermission(array $permissoes)
    {
        foreach ($permissoes as $permissao) {
            if ($this->permission->checkPermission($this->session->userdata('permissao'), $permissao)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Roda o form_validation com as regras de config/form_validation.php e
     * devolve os erros por campo (vazio quando passou), para a view mostrar
     * cada erro no seu campo (#2851).
     *
     * As mensagens saem em português: o idioma global continua english (o
     * pt-br não tem todos os arquivos, como o da paginação), então ele só é
     * trocado durante a validação.
     *
     * @return array<string, string>
     */
    protected function validarFormulario(string $regras): array
    {
        $this->load->library('form_validation');

        $idioma = $this->config->item('language');
        $this->config->set_item('language', 'pt-br');
        try {
            $valido = $this->form_validation->run($regras);
        } finally {
            $this->config->set_item('language', $idioma);
        }

        return $valido ? [] : $this->form_validation->error_array();
    }

    /**
     * Diz se o usuário logado tem a permissão, para mostrar ou esconder uma
     * ação na tela (ex. o botão de excluir de cada linha).
     *
     * Não protege a rota: quem protege é o mapa de permissões
     * (config/permissions_map.php) e a checagem no início do método de destino.
     */
    protected function permite(string $permissao): bool
    {
        if ((bool) $this->session->userdata('is_superadmin')) {
            return true;
        }

        return (bool) $this->permission->checkPermission($this->session->userdata('permissao'), $permissao);
    }

    /**
     * Props do componente pagination para uma listagem nova.
     *
     *     $this->data['paginacao'] = $this->paginacao(site_url('clientes/gerenciar'), $total, $this->uri->segment(3));
     *     // na view: <?= component('pagination', $paginacao) ?>
     *
     * O offset na URL é o mesmo do CI_Pagination, então o model continua
     * recebendo (per_page, offset) como hoje. Com $parametroDaQuery, o offset
     * vai na query string (ex. 'per_page', como no financeiro).
     *
     * @param  string      $urlBase           URL da listagem, sem o offset
     * @param  int         $totalLinhas       Total de registros
     * @param  mixed       $offset            Offset atual (segmento da URL ou query)
     * @param  string|null $parametroDaQuery  Nome do parâmetro quando o offset vai na query
     * @param  array       $filtros           Filtros da listagem (listagemFiltros()), mantidos nos links
     */
    protected function paginacao($urlBase, $totalLinhas, $offset, $parametroDaQuery = null, array $filtros = [])
    {
        return paginacaoProps([
            'base_url' => (string) $urlBase,
            'total_rows' => (int) $totalLinhas,
            'per_page' => (int) $this->data['configuration']['per_page'],
            'offset' => $offset,
            'query_string' => $parametroDaQuery,
            'params' => $filtros,
        ]);
    }

    /**
     * Renderiza a tela dentro do layout do painel (views/tema/).
     *
     * A tela informa a view em $this->data['view'].
     *
     * A ação principal da tela (o único button-primary, ex. "Nova OS") vai na
     * topbar, com as props do button (ícone obrigatório: no celular ele fica
     * só com o ícone, para caber na topbar):
     * $this->data['topbar_acao'] = ['label' => 'Nova OS', 'icon' => 'plus', 'href' => site_url('os/adicionar')].
     */
    public function layout()
    {
        $this->data['layout'] = $this->dadosDoLayout();

        $this->load->view('tema/topo', $this->data);
        $this->load->view('tema/menu');
        $this->load->view('tema/conteudo');
        $this->load->view('tema/rodape');
    }

    /**
     * Dados que as views do layout imprimem: menu, breadcrumb, usuário,
     * notificações e assets.
     */
    protected function dadosDoLayout()
    {
        $configuracao = $this->data['configuration'];
        $siteUrl = static fn ($caminho) => site_url($caminho);

        $this->config->load('permissions_map', false, true);
        $mapa = (array) $this->config->item('permissions_map');
        $permite = fn ($controller, $metodo) => $this->regraPermite($this->regraDaRota($mapa, $controller, $metodo));

        $flash = [];
        foreach (['success' => ['success', 'Sucesso!', 5000], 'error' => ['danger', 'Falha!', 8000]] as $chave => [$variante, $titulo, $duracao]) {
            $mensagem = $this->session->flashdata($chave);
            if (is_string($mensagem) && trim($mensagem) !== '') {
                $flash[] = ['variant' => $variante, 'title' => $titulo, 'duration' => $duracao, 'message' => $mensagem];
            }
        }

        $menu = layoutMenuVisivel(layoutMenu(), $permite, (string) $this->router->class, (string) $this->router->method);
        $usuario = (string) $this->session->userdata('nome_admin');
        $avatar = layoutAvatarUrl($this->session->userdata('url_image_user_admin'), FCPATH . 'assets/userImage', base_url());
        $acao = $this->data['topbar_acao'] ?? null;

        $situacaoAssinatura = $this->data['saas_assinatura'] ?? $this->obterSituacaoAssinaturaEmpresa();
        $somenteLeitura = ($situacaoAssinatura['status'] === 'somente_leitura');
        $diasRestantes = (int) $situacaoAssinatura['dias_restantes'];
        $ehMaster = (bool) $situacaoAssinatura['eh_master'];
        $statusAssinatura = (string) $situacaoAssinatura['status'];

        $abrirModalPreVencimento = false;
        $abrirModalCarencia = false;
        $abrirModalBoasVindas = false;

        if (! $ehMaster && strtolower((string) $this->router->class) !== 'assinatura') {
            // Modal no Primeiro Acesso: Boas-vindas com 10 dias de trial
            if ($this->session->userdata('saas_modal_boas_vindas')) {
                $abrirModalBoasVindas = true;
                $this->session->unset_userdata('saas_modal_boas_vindas');
            }
            // Modal no Login: 3 dias antes de vencer até o dia do vencimento (3, 2, 1, 0 dias)
            elseif ($statusAssinatura === 'ativa' && $diasRestantes <= 3 && $diasRestantes >= 0) {
                if (! $this->session->userdata('saas_modal_prevencimento_visto')) {
                    $abrirModalPreVencimento = true;
                    $this->session->set_userdata('saas_modal_prevencimento_visto', true);
                }
            }
            // Modal no Login: Durante o período de carência de 2 dias
            elseif ($statusAssinatura === 'carencia') {
                if (! $this->session->userdata('saas_modal_carencia_visto')) {
                    $abrirModalCarencia = true;
                    $this->session->set_userdata('saas_modal_carencia_visto', true);
                }
            }
        }

        if ($somenteLeitura && is_array($acao)) {
            $acao['attrs'] = array_merge($acao['attrs'] ?? [], [
                'data-modal-abrir' => 'modal-bloqueio-assinatura',
            ]);
            $acao['href'] = '#';
        }

        return [
            'assets' => layoutAssets(),
            'menu' => $menu,
            'grupos' => layoutMenuGrupos($menu),
            'breadcrumb' => layoutBreadcrumb([$this->uri->segment(1), $this->uri->segment(2), $this->uri->segment(3)], $siteUrl),
            'saudacao' => layoutSaudacao((int) date('G')),
            'usuario' => $usuario,
            'iniciais' => layoutIniciais($usuario),
            'avatar' => $avatar,
            'tem_foto' => $avatar !== base_url() . 'assets/img/User.png',
            'nome_sistema' => (string) (($configuracao['app_name'] ?? '') ?: 'Nexus Ticket'),
            'versao' => (string) $this->config->item('app_version'),
            'acao' => is_array($acao) ? $acao : null,
            'atalhos' => layoutAtalhos($menu, $siteUrl),
            'flash' => $flash,
            'saas_status' => $situacaoAssinatura['status'],
            'saas_dias_restantes' => $situacaoAssinatura['dias_restantes'],
            'saas_dias_carencia' => $situacaoAssinatura['dias_carencia'],
            'saas_somente_leitura' => $somenteLeitura,
            'saas_modal_boas_vindas' => $abrirModalBoasVindas,
            'saas_modal_prevencimento' => $abrirModalPreVencimento,
            'saas_modal_carencia' => $abrirModalCarencia,
        ];
    }
}
