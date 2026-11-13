<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Produtos extends MY_Controller
{
    /** Filtros da listagem, na query string (listagemFiltros()). */
    public const FILTROS = [
        'pesquisa' => 'texto',
        'estoque' => ['baixo'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->helper('form');
        $this->load->model('produtos_model');
        $this->load->helper('produtos');
        $this->data['menuProdutos'] = 'Produtos';
    }

    public function index()
    {
        $this->gerenciar();
    }

    /**
     * Listagem de produtos (#2841), no padrão das listagens (#2852), com o
     * filtro de estoque baixo e as etiquetas em modal.
     */
    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar produtos.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->produtos_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->produtos_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('produtos/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aProduto'),
            'editar' => $this->permite('eProduto'),
            'excluir' => $this->permite('dProduto'),
            'etiquetas' => $this->permite('rProduto'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo produto', 'icon' => 'plus', 'href' => site_url('produtos/adicionar')];
        }

        $this->data['view'] = 'produtos/produtos';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(base_url());
        }

        return $this->formulario(null);
    }

    public function editar()
    {
        $produto = $this->produtoDaUrl();

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para editar produtos.');
            redirect(base_url());
        }

        return $this->formulario($produto);
    }

    /**
     * Formulário de produto (#2841), no padrão dos formulários (#2851). O id
     * editado é o da URL; os preços aceitam "1.234,56" ou "1234.56".
     */
    private function formulario(?object $produto)
    {
        $erros = [];

        if ($this->input->method() === 'post') {
            $erros = $this->validarFormulario('produtos');
            $precos = [];
            foreach (['precoCompra', 'precoVenda'] as $campo) {
                $raw = $this->input->post($campo);
                if ($campo === 'precoCompra' && trim((string) $raw) === '') {
                    $precos[$campo] = '0.00';
                    continue;
                }
                $precos[$campo] = valorDecimal($raw);
                if (! isset($erros[$campo]) && $precos[$campo] === null) {
                    $erros[$campo] = 'Informe o valor em reais, como 150,00 (até 99.999.999,99).';
                }
            }

            if ($erros === []) {
                $dados = produtoDadosDoFormulario($this->input->post(), $precos['precoCompra'], $precos['precoVenda']);

                if ($produto === null) {
                    $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
                    $dados['empresa_id'] = $empresaId;
                    $dados['codigo'] = $this->produtos_model->gerarProximoCodigo($empresaId);
                    $salvou = $this->produtos_model->add('produtos', $dados);
                } else {
                    $salvou = $this->produtos_model->edit('produtos', $dados, 'idProdutos', (int) $produto->idProdutos);
                }

                if ($salvou) {
                    log_info($produto === null ? 'Adicionou um produto' : 'Alterou um produto. ID: ' . (int) $produto->idProdutos);
                    $this->session->set_flashdata('success', $produto === null ? 'Produto cadastrado com sucesso!' : 'Alterações salvas.');

                    return redirect($produto === null ? 'produtos' : 'produtos/visualizar/' . (int) $produto->idProdutos);
                }

                $erros['_geral'] = 'Não foi possível salvar. Tente de novo.';
            }
        }

        $post = $this->input->method() === 'post' ? $this->input->post() : null;
        $valores = [];
        foreach (['codDeBarra', 'descricao', 'unidade', 'precoCompra', 'precoVenda', 'estoque', 'estoqueMinimo'] as $campo) {
            $valores[$campo] = is_array($post) ? (is_string($post[$campo] ?? null) ? $post[$campo] : '') : (string) ($produto->{$campo} ?? '');
        }
        // Novo produto: entra e sai do estoque, como na v4.
        foreach (['entrada', 'saida'] as $campo) {
            $valores[$campo] = is_array($post) ? ! empty($post[$campo]) : ($produto === null || (bool) $produto->{$campo});
        }

        $this->data['produto'] = $produto;
        $this->data['valores'] = $valores;
        $this->data['erros'] = $erros;
        $this->data['view'] = 'produtos/formulario';

        return $this->layout();
    }

    /**
     * Ficha do produto (#2841): dados, preços com o lucro e a entrada de
     * estoque.
     */
    public function visualizar()
    {
        $produto = $this->produtoDaUrl();

        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar produtos.');
            redirect(base_url());
        }

        $this->data['produto'] = $produto;
        $this->data['pode'] = [
            'editar' => $this->permite('eProduto'),
            'excluir' => $this->permite('dProduto'),
        ];

        if ($this->data['pode']['editar']) {
            $this->data['topbar_acao'] = ['label' => 'Editar produto', 'icon' => 'pencil', 'href' => site_url('produtos/editar/' . (int) $produto->idProdutos)];
        }

        $this->data['view'] = 'produtos/visualizar';

        return $this->layout();
    }

    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir produtos.');
            redirect(base_url());
        }

        $id = (int) $this->input->post('id');
        $produto = $id > 0 ? $this->produtos_model->getById($id) : null;
        if (! $produto) {
            $this->session->set_flashdata('error', 'Erro ao tentar excluir produto ou registro não pertence à sua empresa.');
            redirect(site_url('produtos'));
        }

        $this->produtos_model->delete('produtos_os', 'produtos_id', $id);
        $this->produtos_model->delete('itens_de_vendas', 'produtos_id', $id);
        $this->produtos_model->delete('produtos', 'idProdutos', $id);

        log_info('Removeu um produto. ID: ' . $id);

        $this->session->set_flashdata('success', 'Produto excluído com sucesso!');
        redirect(site_url('produtos') . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
    }

    /**
     * Soma (ou, com número negativo, retira) uma quantidade do estoque. A
     * conta é feita no banco (estoque = estoque + ?): o valor atual não vem
     * mais do navegador.
     */
    public function atualizar_estoque()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para atualizar estoque de produtos.');
            redirect(base_url());
        }

        $produto = $this->produtos_model->getById((int) $this->input->post('id'));
        if (! $produto) {
            $this->session->set_flashdata('error', 'Produto não encontrado.');
            redirect(site_url('produtos'));
        }

        $quantidade = filter_var($this->input->post('quantidade'), FILTER_VALIDATE_INT);
        // Da listagem, volta para ela com os mesmos filtros (vêm na query string).
        $destino = $this->input->post('voltar') === 'lista'
            ? site_url('produtos') . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get()))
            : site_url('produtos/visualizar/' . (int) $produto->idProdutos);

        if ($quantidade === false || $quantidade === 0 || abs($quantidade) > 1000000) {
            $this->session->set_flashdata('error', 'Informe uma quantidade inteira diferente de zero (negativa para retirar).');
            redirect($destino);
        }

        if ((int) $produto->estoque + $quantidade < 0) {
            $this->session->set_flashdata('error', 'O estoque não pode ficar negativo: há ' . (int) $produto->estoque . ' em estoque.');
            redirect($destino);
        }

        $this->produtos_model->updateEstoque((int) $produto->idProdutos, abs($quantidade), $quantidade > 0 ? '+' : '-');
        log_info('Atualizou estoque de um produto. ID: ' . (int) $produto->idProdutos . ' (' . sprintf('%+d', $quantidade) . ')');

        $this->session->set_flashdata('success', 'Estoque atualizado: ' . sprintf('%+d', $quantidade) . ' (agora ' . ((int) $produto->estoque + $quantidade) . ').');
        redirect($destino);
    }

    /** Produto do segmento 3 da URL, ou redireciona para a listagem. */
    private function produtoDaUrl(): object
    {
        $produto = is_numeric($this->uri->segment(3)) ? $this->produtos_model->getById((int) $this->uri->segment(3)) : null;
        if (! $produto) {
            $this->session->set_flashdata('error', 'Produto não encontrado ou parâmetro inválido.');
            redirect('produtos/gerenciar');
        }

        return $produto;
    }

    /**
     * Tela de envio do arquivo CSV para importação de produtos (Etapa 1).
     */
    public function importar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(site_url('produtos'));
        }

        $this->data['view'] = 'produtos/importar';

        return $this->layout();
    }

    /**
     * Download de planilha modelo de exemplo (.csv) com cabeçalhos recomendados.
     */
    public function modeloCsv()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(site_url('produtos'));
        }

        $linhas = [
            ['codigo_barras', 'descricao', 'unidade', 'preco_compra', 'preco_venda', 'estoque', 'estoque_minimo'],
            ['7891234567890', 'Cabo HDMI 2.0 2 Metros', 'UNID', '15,00', '35,00', '20', '5'],
            ['7899876543210', 'Memória RAM DDR4 8GB 3200MHz', 'UNID', '120,00', '210,00', '10', '2'],
            ['', 'Mouse Óptico USB Preto', 'UNID', '18,50', '39,90', '15', '3'],
        ];

        $fp = fopen('php://memory', 'r+');
        foreach ($linhas as $linha) {
            fputcsv($fp, $linha, ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="modelo_importacao_produtos.csv"');
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
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(site_url('produtos'));
        }

        if (empty($_FILES['arquivo_csv']['tmp_name']) || ! is_uploaded_file($_FILES['arquivo_csv']['tmp_name'])) {
            $this->session->set_flashdata('error', 'Por favor, selecione um arquivo CSV para enviar.');
            redirect(site_url('produtos/importar'));
        }

        $tamanho = (int) ($_FILES['arquivo_csv']['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > 10 * 1024 * 1024) {
            $this->session->set_flashdata('error', 'O arquivo enviado é inválido ou excede o limite de 10 MB.');
            redirect(site_url('produtos/importar'));
        }

        $conteudo = (string) file_get_contents($_FILES['arquivo_csv']['tmp_name']);
        [$cabecalhos, $linhas] = produtoLerLinhasCsv($conteudo);

        if ($cabecalhos === [] || $linhas === []) {
            $this->session->set_flashdata('error', 'Não foi possível ler os dados do arquivo CSV. Verifique se o arquivo possui cabeçalho e linhas de dados.');
            redirect(site_url('produtos/importar'));
        }

        // Limpa arquivos temporários antigos (> 2 horas)
        $cacheDir = APPPATH . 'cache' . DIRECTORY_SEPARATOR;
        foreach ((array) glob($cacheDir . 'import_prod_*.json') as $arqAntigo) {
            if (is_file($arqAntigo) && (time() - filemtime($arqAntigo)) > 7200) {
                @unlink($arqAntigo);
            }
        }

        $token = bin2hex(random_bytes(16));
        $arquivoTemp = $cacheDir . 'import_prod_' . $token . '.json';
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
        $this->data['view'] = 'produtos/mapear_csv';

        return $this->layout();
    }

    /**
     * Executa a gravação dos produtos no banco a partir do mapeamento de colunas.
     */
    public function concluirImportacao()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aProduto')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar produtos.');
            redirect(site_url('produtos'));
        }

        $token = (string) $this->input->post('token');
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            $this->session->set_flashdata('error', 'Sessão de importação inválida.');
            redirect(site_url('produtos/importar'));
        }

        $arquivoTemp = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'import_prod_' . $token . '.json';
        if (! is_file($arquivoTemp)) {
            $this->session->set_flashdata('error', 'A sessão desta importação expirou. Por favor, envie o arquivo novamente.');
            redirect(site_url('produtos/importar'));
        }

        $dadosJson = json_decode((string) file_get_contents($arquivoTemp), true);
        @unlink($arquivoTemp);

        if (! is_array($dadosJson) || empty($dadosJson['linhas'])) {
            $this->session->set_flashdata('error', 'Dados da importação corrompidos ou vazios.');
            redirect(site_url('produtos/importar'));
        }

        $colunaDescricao = (string) $this->input->post('coluna_descricao');
        $colunaPrecoVenda = (string) $this->input->post('coluna_precoVenda');

        if ($colunaDescricao === '' || $colunaPrecoVenda === '') {
            $this->session->set_flashdata('error', 'As colunas de Descrição e Preço de venda são obrigatórias.');
            redirect(site_url('produtos/importar'));
        }

        $colunaPrecoCompra = (string) $this->input->post('coluna_precoCompra');
        $colunaEstoque = (string) $this->input->post('coluna_estoque');
        $colunaEstoqueMinimo = (string) $this->input->post('coluna_estoqueMinimo');
        $colunaCodDeBarra = (string) $this->input->post('coluna_codDeBarra');
        $colunaUnidade = (string) $this->input->post('coluna_unidade');
        $estrategia = $this->input->post('estrategia_duplicados') === 'atualizar' ? 'atualizar' : 'pular';

        $empresaId = empresa_ativa_id();
        $linhas = (array) $dadosJson['linhas'];

        $produtosExistentes = [];
        $queryExistentes = $this->db->select('idProdutos, codDeBarra, estoque')
            ->where('empresa_id', $empresaId)
            ->where('codDeBarra IS NOT NULL')
            ->where('codDeBarra !=', '')
            ->get('produtos')
            ->result();
        foreach ($queryExistentes as $pEx) {
            $produtosExistentes[(string) $pEx->codDeBarra] = $pEx;
        }

        $proximoCodigo = $this->produtos_model->gerarProximoCodigo($empresaId);
        $importados = 0;
        $atualizados = 0;
        $ignorados = 0;

        foreach ($linhas as $linha) {
            $descricao = trim((string) ($linha[$colunaDescricao] ?? ''));
            if ($descricao === '') {
                $ignorados++;
                continue;
            }

            $precoVendaRaw = $linha[$colunaPrecoVenda] ?? '0.00';
            $precoVenda = valorDecimal($precoVendaRaw) ?? '0.00';

            $precoCompra = '0.00';
            if ($colunaPrecoCompra !== '' && isset($linha[$colunaPrecoCompra])) {
                $precoCompra = valorDecimal($linha[$colunaPrecoCompra]) ?? '0.00';
            }

            $estoque = 0;
            if ($colunaEstoque !== '' && isset($linha[$colunaEstoque])) {
                $estoqueLimpo = preg_replace('/[^\d]/', '', (string) $linha[$colunaEstoque]);
                $estoque = max(0, (int) $estoqueLimpo);
            }

            $estoqueMinimo = null;
            if ($colunaEstoqueMinimo !== '' && isset($linha[$colunaEstoqueMinimo])) {
                $minLimpo = preg_replace('/[^\d]/', '', (string) $linha[$colunaEstoqueMinimo]);
                if ($minLimpo !== '') {
                    $estoqueMinimo = (int) $minLimpo;
                }
            }

            $codDeBarra = '';
            if ($colunaCodDeBarra !== '' && isset($linha[$colunaCodDeBarra])) {
                $codDeBarra = substr(trim((string) $linha[$colunaCodDeBarra]), 0, 70);
            }

            $unidade = 'UNID';
            if ($colunaUnidade !== '' && isset($linha[$colunaUnidade])) {
                $u = substr(strtoupper(trim((string) $linha[$colunaUnidade])), 0, 10);
                if ($u !== '') {
                    $unidade = $u;
                }
            }

            $existente = ($codDeBarra !== '' && isset($produtosExistentes[$codDeBarra])) ? $produtosExistentes[$codDeBarra] : null;

            if ($existente !== null) {
                if ($estrategia === 'atualizar') {
                    $dadosUpdate = [
                        'descricao' => $descricao,
                        'precoVenda' => $precoVenda,
                        'unidade' => $unidade,
                    ];
                    if ((float) $precoCompra > 0) {
                        $dadosUpdate['precoCompra'] = $precoCompra;
                    }
                    if ($estoque > 0) {
                        $dadosUpdate['estoque'] = (int) $existente->estoque + $estoque;
                    }
                    if ($estoqueMinimo !== null) {
                        $dadosUpdate['estoqueMinimo'] = $estoqueMinimo;
                    }
                    $this->produtos_model->edit('produtos', $dadosUpdate, 'idProdutos', (int) $existente->idProdutos);
                    $atualizados++;
                } else {
                    $ignorados++;
                }
            } else {
                $dadosInsert = [
                    'empresa_id' => $empresaId,
                    'codigo' => $proximoCodigo++,
                    'descricao' => $descricao,
                    'precoVenda' => $precoVenda,
                    'precoCompra' => $precoCompra,
                    'estoque' => $estoque,
                    'estoqueMinimo' => $estoqueMinimo,
                    'codDeBarra' => $codDeBarra,
                    'unidade' => $unidade,
                    'entrada' => 1,
                    'saida' => 1,
                ];
                $this->produtos_model->add('produtos', $dadosInsert);
                if ($codDeBarra !== '') {
                    $produtosExistentes[$codDeBarra] = (object) ['estoque' => $estoque];
                }
                $importados++;
            }
        }

        log_info("Importação de produtos via CSV finalizada. Cadastrados: {$importados}, Atualizados: {$atualizados}, Ignorados: {$ignorados}.");

        $mensagem = "{$importados} produto(s) cadastrado(s) com sucesso!";
        if ($atualizados > 0) {
            $mensagem .= " {$atualizados} produto(s) existente(s) atualizado(s).";
        }
        if ($ignorados > 0) {
            $mensagem .= " {$ignorados} linha(s) ignorada(s).";
        }

        $this->session->set_flashdata('success', $mensagem);
        redirect(site_url('produtos'));
    }
}
