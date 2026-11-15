<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Clientes extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
    public const FILTROS = [
        'pesquisa' => 'texto',
        'tipo' => ['cliente', 'fornecedor'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('clientes_model');
        $this->load->helper('clientes');
        $this->data['menuClientes'] = 'clientes';
    }

    public function index()
    {
        $this->gerenciar();
    }

    /**
     * Listagem de clientes e fornecedores, a primeira tela migrada para os
     * componentes da v5 (#2841, #2852): filtros na URL, paginação que os
     * mantém e exclusão confirmada em modal-confirm.
     */
    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->clientes_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->clientes_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('clientes/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aCliente'),
            'editar' => $this->permite('eCliente'),
            'excluir' => $this->permite('dCliente'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo cliente', 'icon' => 'plus', 'href' => site_url('clientes/adicionar')];
        }

        $this->data['view'] = 'clientes/clientes';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(base_url());
        }

        return $this->formulario(null);
    }

    public function editar()
    {
        $cliente = is_numeric($this->uri->segment(3)) ? $this->clientes_model->getById((int) $this->uri->segment(3)) : null;
        if (! $cliente) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar clientes.');
            redirect(base_url());
        }

        return $this->formulario($cliente);
    }

    /**
     * Formulário de cliente da v5 (#2851), para adicionar ($cliente null) e
     * editar. Padrão dos formulários: POST comum; com erro, a tela volta com
     * os valores digitados e cada erro no seu campo (validarFormulario()); com
     * sucesso, redireciona com o toast.
     *
     * O id editado é sempre o da URL, nunca um campo do POST.
     */
    private function formulario(?object $cliente)
    {
        $erros = [];

        if ($this->input->method() === 'post') {
            [$erros, $id] = $this->salvarCliente($cliente);
            if ($erros === []) {
                $this->session->set_flashdata('success', $cliente === null ? 'Cliente cadastrado com sucesso!' : 'Alterações salvas.');

                return redirect($cliente === null ? 'clientes' : 'clientes/editar/' . $id);
            }
        }

        $this->data['cliente'] = $cliente;
        $this->data['valores'] = clienteValoresDoFormulario($this->input->method() === 'post' ? $this->input->post() : null, $cliente);
        $this->data['erros'] = $erros;
        $this->data['view'] = 'clientes/formulario';

        return $this->layout();
    }

    /**
     * Valida o POST e grava o cliente (novo, ou o editado). É o que o
     * formulário e o cadastro rápido (modal da OS e da venda) têm em comum.
     *
     * @param  bool  $aceitaSenha  false ignora a senha do POST (o cadastro rápido não tem o campo)
     * @return array{0: array<string, string>, 1: int|null} erros por campo ('_geral' para falha ao gravar) e o id gravado
     */
    private function salvarCliente(?object $cliente, bool $aceitaSenha = true): array
    {
        $erros = $this->validarFormulario('clientes');
        if ($erros !== []) {
            return [$erros, null];
        }

        $email = trim((string) $this->input->post('email'));
        if ($email !== '' && $this->clientes_model->emailExists($email, $cliente ? (int) $cliente->idClientes : null)) {
            return [['email' => 'Este e-mail já está sendo usado por outro cliente nesta empresa.'], null];
        }

        $documento = trim((string) $this->input->post('documento'));
        if ($documento !== '' && $this->clientes_model->documentoExists($documento, $cliente ? (int) $cliente->idClientes : null)) {
            return [['documento' => 'Este CPF ou CNPJ já está sendo usado por outro cliente nesta empresa.'], null];
        }

        $post = $this->input->post();
        if (! $aceitaSenha && is_array($post)) {
            unset($post['senha']);
        }
        $dados = clienteDadosDoFormulario($post, $cliente === null);
        $dados['empresa_id'] = empresa_ativa_id();

        // Se for um novo cliente e não foi digitada senha manual, verifica se já existe
        // cadastro em outra empresa com o mesmo e-mail ou documento para manter a mesma senha.
        if ($cliente === null && empty($post['senha'])) {
            $clienteExistente = $this->db
                ->group_start()
                    ->where('email', $dados['email'])
                    ->or_where('documento', $dados['documento'])
                ->group_end()
                ->where('senha !=', '')
                ->order_by('idClientes', 'desc')
                ->limit(1)
                ->get('clientes')
                ->row();

            if ($clienteExistente && ! empty($clienteExistente->senha)) {
                $dados['senha'] = $clienteExistente->senha;
            }
        }

        if ($cliente === null) {
            $id = $this->clientes_model->add('clientes', $dados + ['dataCadastro' => date('Y-m-d')]);
            $salvou = (bool) $id;
            $id = $salvou ? (int) $id : null;
        } else {
            $id = (int) $cliente->idClientes;
            $salvou = $this->clientes_model->edit('clientes', $dados, 'idClientes', $id);
        }

        if (! $salvou) {
            return [['_geral' => 'Não foi possível salvar. Tente de novo.'], null];
        }

        log_info($cliente === null ? 'Adicionou um cliente.' : 'Alterou um cliente. ID ' . $id);

        return [[], $id];
    }

    /**
     * Cadastro rápido de cliente, no modal da tela da OS e da venda (#3003).
     * POST sem recarregar, com os mesmos campos e regras do formulário, mas
     * sem a senha da área do cliente: ela é gerada a partir do documento.
     *
     * 201 {result, message, cliente: {id, valor, detalhe}} (o formato do
     * autocomplete), 422 {result: false, erros: {campo: mensagem}} ou 403.
     */
    public function cadastroRapido()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para adicionar clientes.']);

            return;
        }

        if ($this->input->method() !== 'post') {
            $this->responderJson(405, ['result' => false, 'message' => 'Método não permitido.']);

            return;
        }

        [$erros, $id] = $this->salvarCliente(null, false);
        if ($erros !== []) {
            $this->responderJson(422, ['result' => false, 'message' => 'Confira os campos destacados.', 'erros' => $erros]);

            return;
        }

        $cliente = $this->clientes_model->getById($id);

        $this->responderJson(201, [
            'result' => true,
            'message' => 'Cliente cadastrado e escolhido.',
            'cliente' => [
                'id' => (string) $id,
                'valor' => (string) $cliente->nomeCliente,
                'detalhe' => implode(' · ', array_filter([(string) $cliente->documento, (string) ($cliente->celular ?: $cliente->telefone)], static fn ($v) => $v !== '')),
            ],
        ]);
    }

    /** Resposta JSON dos endpoints do cadastro rápido. */
    private function responderJson(int $status, array $corpo): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }

    /**
     * Ficha do cliente (#2841): dados, ordens de serviço e vendas em abas por
     * link (?aba=dados|os|vendas), sem JavaScript.
     */
    public function visualizar()
    {
        $cliente = is_numeric($this->uri->segment(3)) ? $this->clientes_model->getById((int) $this->uri->segment(3)) : null;
        if (! $cliente) {
            $this->session->set_flashdata('error', 'Cliente não encontrado ou parâmetro inválido.');
            redirect('clientes/gerenciar');
        }

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar clientes.');
            redirect(base_url());
        }

        $id = (int) $cliente->idClientes;
        $aba = listagemFiltros(['aba' => ['dados', 'os', 'vendas']], $this->input->get())['aba'] ?? 'dados';

        $this->data['cliente'] = $cliente;
        $this->data['aba'] = $aba;
        $this->data['total_os'] = $this->clientes_model->contarOsDoCliente($id);
        $this->data['total_vendas'] = $this->clientes_model->contarVendasDoCliente($id);
        // O segmento 3 da URL é o id do cliente: o início da página vai na query string.
        $porPagina = (int) $this->data['configuration']['per_page'];
        $offset = max(0, (int) $this->input->get('inicio'));
        $this->data['os'] = $aba === 'os' ? $this->clientes_model->osDoCliente($id, $porPagina, $offset) : [];
        $this->data['vendas'] = $aba === 'vendas' ? $this->clientes_model->vendasDoCliente($id, $porPagina, $offset) : [];
        $this->data['paginacao'] = $aba === 'dados'
            ? null
            : $this->paginacao(site_url('clientes/visualizar/' . $id), $aba === 'os' ? $this->data['total_os'] : $this->data['total_vendas'], $offset, 'inicio', ['aba' => $aba]);
        $this->data['pode'] = [
            'editar' => $this->permite('eCliente'),
            'excluir' => $this->permite('dCliente'),
            'ver_os' => $this->permite('vOs'),
            'editar_os' => $this->permite('eOs'),
            'ver_venda' => $this->permite('vVenda'),
            'editar_venda' => $this->permite('eVenda'),
        ];

        if ($this->data['pode']['editar']) {
            $this->data['topbar_acao'] = ['label' => 'Editar cliente', 'icon' => 'pencil', 'href' => site_url('clientes/editar/' . $id)];
        }

        $this->data['view'] = 'clientes/visualizar';

        return $this->layout();
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir clientes.');
            redirect(base_url());
        }

        $id = $this->input->post('id');
        if ($id == null) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir cliente.');
            redirect(site_url('clientes/gerenciar/'));
        }

        $cliente = $this->clientes_model->getById((int) $id);
        if (! $cliente) {
            $this->session->set_flashdata('error', 'Cliente não encontrado.');
            redirect(site_url('clientes/gerenciar/'));
        }

        $os = $this->clientes_model->getAllOsByClient($id);
        if ($os != null) {
            $this->clientes_model->removeClientOs($os);
        }

        // excluindo Vendas vinculadas ao cliente
        $vendas = $this->clientes_model->getAllVendasByClient($id);
        if ($vendas != null) {
            $this->clientes_model->removeClientVendas($vendas);
        }

        $this->clientes_model->delete('clientes', 'idClientes', $id);
        log_info('Removeu um cliente. ID' . $id);

        $this->session->set_flashdata('success', 'Cliente excluído com sucesso!');
        // Volta para a listagem com os mesmos filtros (só os permitidos).
        redirect(site_url('clientes') . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
    }

    /**
     * Tela de upload para importação de clientes e fornecedores via CSV (Etapa 1).
     */
    public function importar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(site_url('clientes'));
        }

        $this->data['view'] = 'clientes/importar';

        return $this->layout();
    }

    /**
     * Gera e baixa um modelo CSV de exemplo para preenchimento de clientes e fornecedores.
     */
    public function modeloCsv()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(site_url('clientes'));
        }

        $linhas = [
            ['tipo', 'nome', 'documento', 'telefone', 'celular', 'email', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'contato'],
            ['Cliente', 'João da Silva', '123.456.789-00', '(11) 3333-4444', '(11) 98888-7777', 'joao@email.com', '01001-000', 'Praça da Sé', '100', 'Apto 101', 'Centro', 'São Paulo', 'SP', ''],
            ['Fornecedor', 'Tech Distribuidora Ltda', '12.345.678/0001-90', '(11) 3222-1111', '(11) 97777-6666', 'contato@techdistribuidora.com.br', '04571-010', 'Av. das Nações Unidas', '12901', 'Conj 801', 'Brooklin', 'São Paulo', 'SP', 'Carlos Vendas'],
            ['Cliente', 'Maria Oliveira', '987.654.321-99', '', '(21) 99999-8888', 'maria@email.com', '20040-002', 'Rua da Assembleia', '10', 'Sala 201', 'Centro', 'Rio de Janeiro', 'RJ', ''],
        ];

        $fp = fopen('php://memory', 'r+');
        foreach ($linhas as $linha) {
            fputcsv($fp, $linha, ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="modelo_importacao_clientes.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF";
        echo $csv;
        exit;
    }

    /**
     * Processa o upload do CSV e abre a tela de mapeamento e prévia (Etapa 2).
     */
    public function processarCsv()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(site_url('clientes'));
        }

        if (empty($_FILES['arquivo_csv']['tmp_name']) || ! is_uploaded_file($_FILES['arquivo_csv']['tmp_name'])) {
            $this->session->set_flashdata('error', 'Por favor, selecione um arquivo CSV para enviar.');
            redirect(site_url('clientes/importar'));
        }

        $tamanho = (int) ($_FILES['arquivo_csv']['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > 50 * 1024 * 1024) {
            $this->session->set_flashdata('error', 'O arquivo enviado é inválido ou excede o limite de 50 MB.');
            redirect(site_url('clientes/importar'));
        }

        $conteudo = (string) file_get_contents($_FILES['arquivo_csv']['tmp_name']);
        [$cabecalhos, $linhas] = clienteLerLinhasCsv($conteudo);

        if ($cabecalhos === [] || $linhas === []) {
            $this->session->set_flashdata('error', 'Não foi possível ler os dados do arquivo CSV. Verifique se o arquivo possui cabeçalho e linhas de dados.');
            redirect(site_url('clientes/importar'));
        }

        // Limpa arquivos temporários antigos (> 2 horas)
        $cacheDir = APPPATH . 'cache' . DIRECTORY_SEPARATOR;
        foreach ((array) glob($cacheDir . 'import_cli_*.json') as $arqAntigo) {
            if (is_file($arqAntigo) && (time() - filemtime($arqAntigo)) > 7200) {
                @unlink($arqAntigo);
            }
        }

        $token = bin2hex(random_bytes(16));
        $arquivoTemp = $cacheDir . 'import_cli_' . $token . '.json';
        file_put_contents($arquivoTemp, json_encode([
            'token' => $token,
            'empresa_id' => empresa_ativa_id(),
            'cabecalhos' => $cabecalhos,
            'linhas' => $linhas,
            'criado_em' => time(),
        ], JSON_UNESCAPED_UNICODE));

        $this->data['token'] = $token;
        $this->data['cabecalhos'] = $cabecalhos;
        $this->data['totalLinhas'] = count($linhas);
        $this->data['previas'] = array_slice($linhas, 0, 10);
        $this->data['view'] = 'clientes/mapear_csv';

        return $this->layout();
    }

    /**
     * Executa a gravação dos clientes e fornecedores a partir do mapeamento de colunas.
     */
    public function concluirImportacao()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCliente')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar clientes.');
            redirect(site_url('clientes'));
        }

        $token = (string) $this->input->post('token');
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            $this->session->set_flashdata('error', 'Sessão de importação inválida.');
            redirect(site_url('clientes/importar'));
        }

        $arquivoTemp = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'import_cli_' . $token . '.json';
        if (! is_file($arquivoTemp)) {
            $this->session->set_flashdata('error', 'A sessão desta importação expirou. Por favor, envie o arquivo novamente.');
            redirect(site_url('clientes/importar'));
        }

        $dadosJson = json_decode((string) file_get_contents($arquivoTemp), true);
        @unlink($arquivoTemp);

        if (! is_array($dadosJson) || empty($dadosJson['linhas'])) {
            $this->session->set_flashdata('error', 'Dados da importação corrompidos ou vazios.');
            redirect(site_url('clientes/importar'));
        }

        $colunaNomeCliente = (string) $this->input->post('coluna_nomeCliente');
        if ($colunaNomeCliente === '') {
            $this->session->set_flashdata('error', 'A coluna do Nome / Razão Social é obrigatória.');
            redirect(site_url('clientes/importar'));
        }

        $colunaDocumento = (string) $this->input->post('coluna_documento');
        $colunaTipo = (string) $this->input->post('coluna_tipo');
        $colunaTelefone = (string) $this->input->post('coluna_telefone');
        $colunaCelular = (string) $this->input->post('coluna_celular');
        $colunaEmail = (string) $this->input->post('coluna_email');
        $colunaCep = (string) $this->input->post('coluna_cep');
        $colunaRua = (string) $this->input->post('coluna_rua');
        $colunaNumero = (string) $this->input->post('coluna_numero');
        $colunaComplemento = (string) $this->input->post('coluna_complemento');
        $colunaBairro = (string) $this->input->post('coluna_bairro');
        $colunaCidade = (string) $this->input->post('coluna_cidade');
        $colunaEstado = (string) $this->input->post('coluna_estado');
        $colunaContato = (string) $this->input->post('coluna_contato');

        $modoTipo = (string) $this->input->post('modo_tipo');
        if (! in_array($modoTipo, ['coluna', 'todos_clientes', 'todos_fornecedores'], true)) {
            $modoTipo = 'coluna';
        }

        $filtroTipo = (string) $this->input->post('filtro_tipo');
        if (! in_array($filtroTipo, ['todos', 'apenas_clientes', 'apenas_fornecedores'], true)) {
            $filtroTipo = 'todos';
        }

        $criterioDuplicados = (string) $this->input->post('criterio_duplicados');
        if (! in_array($criterioDuplicados, ['cpf_cnpj_ou_nome_ou_email', 'apenas_documento', 'apenas_nome', 'apenas_email'], true)) {
            $criterioDuplicados = 'cpf_cnpj_ou_nome_ou_email';
        }

        $estrategia = (string) $this->input->post('estrategia_duplicados');
        if (! in_array($estrategia, ['mesclar', 'substituir', 'pular'], true)) {
            $estrategia = 'mesclar';
        }

        $empresaId = empresa_ativa_id();
        $linhas = (array) $dadosJson['linhas'];

        $clientesPorDocumento = [];
        $clientesPorNome = [];
        $clientesPorEmail = [];

        $queryExistentes = $this->db->select('idClientes, codigo, nomeCliente, documento, email, telefone, celular, cep, rua, numero, complemento, bairro, cidade, estado, contato, fornecedor, senha')
            ->where('empresa_id', $empresaId)
            ->get('clientes')
            ->result();

        foreach ($queryExistentes as $cEx) {
            $docLimpo = preg_replace('/[^A-Za-z0-9]/', '', (string) $cEx->documento);
            if ($docLimpo !== '') {
                $clientesPorDocumento[$docLimpo] = $cEx;
            }
            $nomeNorm = mb_strtolower(trim((string) $cEx->nomeCliente));
            if ($nomeNorm !== '') {
                $clientesPorNome[$nomeNorm] = $cEx;
            }
            $emailNorm = mb_strtolower(trim((string) $cEx->email));
            if ($emailNorm !== '') {
                $clientesPorEmail[$emailNorm] = $cEx;
            }
        }

        $importados = 0;
        $atualizados = 0;
        $substituidos = 0;
        $ignorados = 0;

        foreach ($linhas as $linha) {
            $nome = trim((string) ($linha[$colunaNomeCliente] ?? ''));
            if ($nome === '') {
                $ignorados++;
                continue;
            }

            // Determina o tipo (0 = cliente, 1 = fornecedor)
            $tipo = 0;
            if ($modoTipo === 'todos_fornecedores') {
                $tipo = 1;
            } elseif ($modoTipo === 'todos_clientes') {
                $tipo = 0;
            } elseif ($colunaTipo !== '' && isset($linha[$colunaTipo])) {
                $tipo = clienteNormalizarTipo($linha[$colunaTipo]);
            }

            // Aplica filtro de tipo se configurado
            if ($filtroTipo === 'apenas_clientes' && $tipo === 1) {
                $ignorados++;
                continue;
            }
            if ($filtroTipo === 'apenas_fornecedores' && $tipo === 0) {
                $ignorados++;
                continue;
            }

            $documento = $colunaDocumento !== '' && isset($linha[$colunaDocumento]) ? trim((string) $linha[$colunaDocumento]) : '';
            $docLimpo = (string) preg_replace('/[^A-Za-z0-9]/', '', $documento);
            $pessoaFisica = strlen($docLimpo) === 11 ? 1 : 0;

            $telefone = $colunaTelefone !== '' && isset($linha[$colunaTelefone]) ? trim((string) $linha[$colunaTelefone]) : '';
            $celular = $colunaCelular !== '' && isset($linha[$colunaCelular]) ? trim((string) $linha[$colunaCelular]) : '';
            $email = $colunaEmail !== '' && isset($linha[$colunaEmail]) ? trim((string) $linha[$colunaEmail]) : '';
            $emailNorm = mb_strtolower($email);
            $cep = $colunaCep !== '' && isset($linha[$colunaCep]) ? trim((string) $linha[$colunaCep]) : '';
            $rua = $colunaRua !== '' && isset($linha[$colunaRua]) ? trim((string) $linha[$colunaRua]) : '';
            $numero = $colunaNumero !== '' && isset($linha[$colunaNumero]) ? trim((string) $linha[$colunaNumero]) : '';
            $complemento = $colunaComplemento !== '' && isset($linha[$colunaComplemento]) ? trim((string) $linha[$colunaComplemento]) : '';
            $bairro = $colunaBairro !== '' && isset($linha[$colunaBairro]) ? trim((string) $linha[$colunaBairro]) : '';
            $cidade = $colunaCidade !== '' && isset($linha[$colunaCidade]) ? trim((string) $linha[$colunaCidade]) : '';
            $estadoRaw = $colunaEstado !== '' && isset($linha[$colunaEstado]) ? strtoupper(trim((string) $linha[$colunaEstado])) : '';
            $estado = array_key_exists($estadoRaw, ufsDoBrasil()) ? $estadoRaw : '';
            $contato = $colunaContato !== '' && isset($linha[$colunaContato]) ? trim((string) $linha[$colunaContato]) : '';

            $nomeNorm = mb_strtolower($nome);

            $existente = null;
            if ($criterioDuplicados === 'apenas_documento') {
                if ($docLimpo !== '' && isset($clientesPorDocumento[$docLimpo])) {
                    $existente = $clientesPorDocumento[$docLimpo];
                }
            } elseif ($criterioDuplicados === 'apenas_nome') {
                if ($nomeNorm !== '' && isset($clientesPorNome[$nomeNorm])) {
                    $existente = $clientesPorNome[$nomeNorm];
                }
            } elseif ($criterioDuplicados === 'apenas_email') {
                if ($emailNorm !== '' && isset($clientesPorEmail[$emailNorm])) {
                    $existente = $clientesPorEmail[$emailNorm];
                }
            } else {
                // 'cpf_cnpj_ou_nome_ou_email': busca por documento, depois e-mail, depois nome
                if ($docLimpo !== '' && isset($clientesPorDocumento[$docLimpo])) {
                    $existente = $clientesPorDocumento[$docLimpo];
                } elseif ($emailNorm !== '' && isset($clientesPorEmail[$emailNorm])) {
                    $existente = $clientesPorEmail[$emailNorm];
                } elseif ($nomeNorm !== '' && isset($clientesPorNome[$nomeNorm])) {
                    $existente = $clientesPorNome[$nomeNorm];
                }
            }

            if ($existente !== null) {
                if ($estrategia === 'pular') {
                    $ignorados++;
                    continue;
                }

                if ($estrategia === 'mesclar') {
                    $dadosUpdate = [
                        'nomeCliente' => $nome,
                    ];
                    if ($documento !== '') {
                        $dadosUpdate['documento'] = $documento;
                        $dadosUpdate['pessoa_fisica'] = $pessoaFisica;
                    }
                    if ($telefone !== '') {
                        $dadosUpdate['telefone'] = $telefone;
                    }
                    if ($celular !== '') {
                        $dadosUpdate['celular'] = $celular;
                    }
                    if ($email !== '') {
                        $dadosUpdate['email'] = $email;
                    }
                    if ($cep !== '') {
                        $dadosUpdate['cep'] = $cep;
                    }
                    if ($rua !== '') {
                        $dadosUpdate['rua'] = $rua;
                    }
                    if ($numero !== '') {
                        $dadosUpdate['numero'] = $numero;
                    }
                    if ($complemento !== '') {
                        $dadosUpdate['complemento'] = $complemento;
                    }
                    if ($bairro !== '') {
                        $dadosUpdate['bairro'] = $bairro;
                    }
                    if ($cidade !== '') {
                        $dadosUpdate['cidade'] = $cidade;
                    }
                    if ($estado !== '') {
                        $dadosUpdate['estado'] = $estado;
                    }
                    if ($contato !== '') {
                        $dadosUpdate['contato'] = $contato;
                    }
                    if ($modoTipo !== 'coluna' || $colunaTipo !== '') {
                        $dadosUpdate['fornecedor'] = $tipo;
                    }

                    $this->clientes_model->edit('clientes', $dadosUpdate, 'idClientes', (int) $existente->idClientes);

                    foreach ($dadosUpdate as $k => $v) {
                        $existente->{$k} = $v;
                    }
                    if ($docLimpo !== '') {
                        $clientesPorDocumento[$docLimpo] = $existente;
                    }
                    if ($emailNorm !== '') {
                        $clientesPorEmail[$emailNorm] = $existente;
                    }
                    $clientesPorNome[$nomeNorm] = $existente;

                    $atualizados++;
                } elseif ($estrategia === 'substituir') {
                    $dadosUpdate = [
                        'nomeCliente' => $nome,
                        'contato' => $contato,
                        'documento' => $documento,
                        'telefone' => $telefone,
                        'celular' => $celular,
                        'email' => $email,
                        'cep' => $cep,
                        'rua' => $rua,
                        'numero' => $numero,
                        'complemento' => $complemento,
                        'bairro' => $bairro,
                        'cidade' => $cidade,
                        'estado' => $estado,
                        'pessoa_fisica' => $pessoaFisica,
                        'fornecedor' => $tipo,
                    ];

                    $this->clientes_model->edit('clientes', $dadosUpdate, 'idClientes', (int) $existente->idClientes);

                    foreach ($dadosUpdate as $k => $v) {
                        $existente->{$k} = $v;
                    }
                    if ($docLimpo !== '') {
                        $clientesPorDocumento[$docLimpo] = $existente;
                    }
                    if ($emailNorm !== '') {
                        $clientesPorEmail[$emailNorm] = $existente;
                    }
                    $clientesPorNome[$nomeNorm] = $existente;

                    $substituidos++;
                }
            } else {
                $senhaHash = '';
                if ($email !== '' || $documento !== '') {
                    $this->db->group_start();
                    if ($email !== '') {
                        $this->db->where('email', $email);
                    }
                    if ($documento !== '') {
                        if ($email !== '') {
                            $this->db->or_where('documento', $documento);
                        } else {
                            $this->db->where('documento', $documento);
                        }
                    }
                    $clienteOutraEmpresa = $this->db->group_end()
                        ->where('senha !=', '')
                        ->order_by('idClientes', 'desc')
                        ->limit(1)
                        ->get('clientes')
                        ->row();

                    if ($clienteOutraEmpresa && ! empty($clienteOutraEmpresa->senha)) {
                        $senhaHash = $clienteOutraEmpresa->senha;
                    }
                }

                if ($senhaHash === '') {
                    $senhaPadrao = $docLimpo !== '' ? $docLimpo : bin2hex(random_bytes(16));
                    $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
                }

                $dadosInsert = [
                    'empresa_id' => $empresaId,
                    'nomeCliente' => $nome,
                    'contato' => $contato,
                    'documento' => $documento,
                    'telefone' => $telefone,
                    'celular' => $celular,
                    'email' => $email,
                    'cep' => $cep,
                    'rua' => $rua,
                    'numero' => $numero,
                    'complemento' => $complemento,
                    'bairro' => $bairro,
                    'cidade' => $cidade,
                    'estado' => $estado,
                    'pessoa_fisica' => $pessoaFisica,
                    'fornecedor' => $tipo,
                    'senha' => $senhaHash,
                    'dataCadastro' => date('Y-m-d'),
                ];

                $novoId = $this->clientes_model->add('clientes', $dadosInsert);

                $novoObj = (object) ($dadosInsert + ['idClientes' => $novoId]);
                if ($docLimpo !== '') {
                    $clientesPorDocumento[$docLimpo] = $novoObj;
                }
                if ($emailNorm !== '') {
                    $clientesPorEmail[$emailNorm] = $novoObj;
                }
                $clientesPorNome[$nomeNorm] = $novoObj;

                $importados++;
            }
        }

        log_info("Importação de clientes via CSV finalizada. Cadastrados: {$importados}, Mesclados: {$atualizados}, Substituídos: {$substituidos}, Ignorados: {$ignorados}.");

        $mensagem = "{$importados} cadastro(s) realizado(s) com sucesso.";
        if ($atualizados > 0) {
            $mensagem .= " {$atualizados} registro(s) existente(s) atualizado(s).";
        }
        if ($substituidos > 0) {
            $mensagem .= " {$substituidos} registro(s) existente(s) substituído(s).";
        }
        if ($ignorados > 0) {
            $mensagem .= " {$ignorados} registro(s) ignorado(s) ou repetido(s).";
        }

        $this->session->set_flashdata('success', $mensagem);
        redirect(site_url('clientes'));
    }

}
