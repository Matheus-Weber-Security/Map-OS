<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Controller de Compras e Importação de NF-e (#Compras-01).
 */
class Compras extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
        'status' => ['Concluída', 'Pendente', 'Cancelada'],
        'de' => 'data',
        'ate' => 'data',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->load->model('compras_model');
        $this->load->helper(['compras', 'financeiro']);
        $this->data['menuCompras'] = 'compras';
    }

    public function index()
    {
        $this->gerenciar();
    }

    /**
     * Listagem de compras com filtros e paginação.
     */
    public function gerenciar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar compras.');
            redirect(base_url());
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->uri->segment(3);
        $total = $this->compras_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->compras_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('compras/gerenciar'), $total, $offset, null, $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aCompra'),
            'editar' => $this->permite('eCompra'),
            'excluir' => $this->permite('dCompra'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Importar NF-e', 'icon' => 'upload', 'href' => site_url('compras/importarNfe')];
        }

        $this->data['view'] = 'compras/compras';

        return $this->layout();
    }

    /**
     * Visualização detalhada de uma compra.
     */
    public function visualizar($id = null)
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'vCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para visualizar compras.');
            redirect(base_url());
        }

        $id = (int) $id;
        $compra = $this->compras_model->getById($id);
        if (! $compra) {
            $this->session->set_flashdata('error', 'Compra não encontrada.');
            redirect(site_url('compras'));
        }

        $this->data['compra'] = $compra;
        $this->data['itens'] = $this->compras_model->getItens($id);
        $this->data['pode'] = [
            'editar' => $this->permite('eCompra'),
            'excluir' => $this->permite('dCompra'),
        ];

        $this->data['view'] = 'compras/visualizar';

        return $this->layout();
    }

    /**
     * Cadastro manual de nova compra.
     */
    public function adicionar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para adicionar compras.');
            redirect(base_url());
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        if ($this->input->method() === 'post') {
            $fornecedorId = (int) $this->input->post('fornecedores_id');
            $dataCompra = $this->input->post('dataCompra') ?: date('Y-m-d');
            $observacoes = (string) $this->input->post('observacoes');
            $status = $this->input->post('status') ?: 'Concluída';

            $dados = [
                'empresa_id' => $empresaId,
                'fornecedores_id' => $fornecedorId > 0 ? $fornecedorId : null,
                'usuarios_id' => (int) $this->session->userdata('id_usuarios'),
                'dataCompra' => $dataCompra,
                'valorTotal' => 0.00,
                'status' => $status,
                'observacoes' => $observacoes,
                'criado_em' => date('Y-m-d H:i:s'),
            ];

            $idCompra = $this->compras_model->add('compras', $dados, true);
            if ($idCompra) {
                log_info('Adicionou compra ID: ' . $idCompra);
                $this->session->set_flashdata('success', 'Compra iniciada com sucesso. Adicione os itens da compra.');
                redirect(site_url('compras/visualizar/' . $idCompra));
            } else {
                $this->session->set_flashdata('error', 'Não foi possível iniciar a compra.');
            }
        }

        $this->data['fornecedores'] = $this->db
            ->where('empresa_id', $empresaId)
            ->where('fornecedor', 1)
            ->order_by('nomeCliente', 'asc')
            ->get('clientes')
            ->result();

        $this->data['view'] = 'compras/formulario';

        return $this->layout();
    }

    /**
     * Exclui ou cancela uma compra com estorno automático de estoque.
     */
    public function excluir()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'dCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para excluir compras.');
            redirect(site_url('compras'));
        }

        $id = (int) $this->input->post('id');
        $compra = $this->compras_model->getById($id);
        if (! $compra) {
            $this->session->set_flashdata('error', 'Compra não encontrada.');
            redirect(site_url('compras'));
        }

        // Se a compra estava concluída e movimentou estoque, estorna do estoque
        $st = strtolower((string) $compra->status);
        if ($st === 'concluída' || $st === 'concluida') {
            $this->compras_model->movimentarEstoque($id, '-', 'Exclusão da compra #' . $id);
        }

        $this->compras_model->delete('itens_de_compras', 'compras_id', $id);
        $this->compras_model->delete('compras', 'idCompras', $id);

        log_info('Excluiu a compra com ID: ' . $id);
        $this->session->set_flashdata('success', 'Compra #' . $id . ' e seus itens foram excluídos.');
        redirect(site_url('compras'));
    }

    /**
     * Fatura a compra no contas a pagar do Financeiro.
     */
    public function faturar()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'eCompra')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Você não tem permissão para faturar compras.']);

            return;
        }

        $id = (int) $this->input->post('idCompras');
        $compra = $this->compras_model->getById($id);
        if (! $compra) {
            $this->responderJson(404, ['result' => false, 'message' => 'Compra não encontrada.']);

            return;
        }

        if ((int) $compra->faturado === 1) {
            $this->responderJson(422, ['result' => false, 'message' => 'Esta compra já foi faturada no financeiro.']);

            return;
        }

        $vencimento = $this->input->post('vencimento') ?: date('Y-m-d');
        $formaPgto = $this->input->post('forma_pgto') ?: 'Boleto';

        $idLancamento = $this->compras_model->faturarCompra($id, [
            'vencimento' => $vencimento,
            'forma_pgto' => $formaPgto,
            'baixado' => (bool) $this->input->post('pago'),
        ]);

        if ($idLancamento > 0) {
            log_info('Faturou compra ID: ' . $id . ' gerando lançamento #' . $idLancamento);
            $this->session->set_flashdata('success', 'Compra #' . $id . ' faturada com sucesso no Financeiro.');
            $this->responderJson(200, ['result' => true, 'message' => 'Compra faturada no Financeiro.', 'redirecionar' => site_url('compras/visualizar/' . $id)]);
        } else {
            $this->responderJson(500, ['result' => false, 'message' => 'Não foi possível faturar a compra.']);
        }
    }

    /**
     * Tela inicial de importação de NF-e (XML).
     */
    public function importarNfe()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para importar NF-e.');
            redirect(site_url('compras'));
        }

        $this->data['view'] = 'compras/importar_nfe';

        return $this->layout();
    }

    /**
     * Processa o upload do arquivo XML da NF-e em memória e descarta o arquivo imediatamente.
     */
    public function processarXmlNfe()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para importar NF-e.');
            redirect(site_url('compras'));
        }

        if (empty($_FILES['xml_nfe']['tmp_name']) || ! is_uploaded_file($_FILES['xml_nfe']['tmp_name'])) {
            $this->session->set_flashdata('error', 'Selecione um arquivo XML de NF-e válido.');
            redirect(site_url('compras/importarNfe'));
        }

        $tmpFile = $_FILES['xml_nfe']['tmp_name'];
        $xmlContent = (string) file_get_contents($tmpFile);

        // Descarte imediato do arquivo temporário da VPS conforme exigência de infraestrutura
        @unlink($tmpFile);

        try {
            $nfe = nfeParser($xmlContent);
        } catch (Throwable $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            redirect(site_url('compras/importarNfe'));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        // 1. Verificar se a chave da NF-e já foi importada
        if ($nfe['chave'] !== '') {
            $existente = $this->compras_model->buscarPorChaveNfe($empresaId, $nfe['chave']);
            if ($existente) {
                $this->session->set_flashdata('error', "Esta NF-e (Chave: {$nfe['chave']}) já foi importada anteriormente na Compra #{$existente->idCompras} em " . date('d/m/Y', strtotime($existente->dataCompra)) . '.');
                redirect(site_url('compras/visualizar/' . $existente->idCompras));
            }
        }

        // 2. Reconhecimento de Fornecedor
        $fornecedorExistente = $this->compras_model->buscarFornecedorPorDocumento($empresaId, $nfe['emitente']['documento']);
        $nfe['fornecedor_id_sugerido'] = $fornecedorExistente ? (int) $fornecedorExistente->idClientes : null;
        $nfe['fornecedor_ja_cadastrado'] = (bool) $fornecedorExistente;

        // 3. Reconhecimento De-Para dos Produtos
        foreach ($nfe['itens'] as &$item) {
            $item['produto_id_vinculado'] = null;
            $item['fator_conversao'] = 1.0000;
            $item['origem_vinculo'] = 'nenhum';

            // Se o fornecedor já existia, busca na tabela de De-Para
            if ($fornecedorExistente && $item['codigo_fornecedor'] !== '') {
                $vinculo = $this->compras_model->buscarVinculoFornecedor($empresaId, (int) $fornecedorExistente->idClientes, $item['codigo_fornecedor']);
                if ($vinculo) {
                    $item['produto_id_vinculado'] = (int) $vinculo->produto_id;
                    $item['fator_conversao'] = (float) $vinculo->fator_conversao;
                    $item['origem_vinculo'] = 'historico_fornecedor';
                }
            }

            // Se não achou pelo histórico, tenta pelo código de barras (EAN)
            if (! $item['produto_id_vinculado'] && $item['ean'] !== '') {
                $prodEan = $this->db
                    ->where('empresa_id', $empresaId)
                    ->where('codDeBarra', $item['ean'])
                    ->limit(1)
                    ->get('produtos')
                    ->row();
                if ($prodEan) {
                    $item['produto_id_vinculado'] = (int) $prodEan->idProdutos;
                    $item['origem_vinculo'] = 'codigo_barras';
                }
            }
        }
        unset($item);

        // Armazena na sessão para a etapa de conferência
        $this->session->set_userdata('nfe_importacao', $nfe);

        redirect(site_url('compras/conferirNfe'));
    }

    /**
     * Tela de conferência, vinculação De-Para e confirmação dos itens da NF-e.
     */
    public function conferirNfe()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para importar NF-e.');
            redirect(site_url('compras'));
        }

        $nfe = $this->session->userdata('nfe_importacao');
        if (! $nfe || empty($nfe['itens'])) {
            $this->session->set_flashdata('error', 'Nenhuma NF-e em processo de importação. Envie o arquivo novamente.');
            redirect(site_url('compras/importarNfe'));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        // Lista de produtos do sistema para vinculação manual
        $produtosSistema = $this->db
            ->select('idProdutos, descricao, codDeBarra, unidade, precoCompra, precoVenda, estoque')
            ->where('empresa_id', $empresaId)
            ->order_by('descricao', 'asc')
            ->get('produtos')
            ->result();

        // Lista de fornecedores do sistema
        $fornecedores = $this->db
            ->select('idClientes, nomeCliente, documento')
            ->where('empresa_id', $empresaId)
            ->where('fornecedor', 1)
            ->order_by('nomeCliente', 'asc')
            ->get('clientes')
            ->result();

        $this->data['nfe'] = $nfe;
        $this->data['produtosSistema'] = $produtosSistema;
        $this->data['fornecedores'] = $fornecedores;
        $this->data['view'] = 'compras/conferir_nfe';

        return $this->layout();
    }

    /**
     * Conclui a importação da NF-e:
     * - Garante o fornecedor cadastrado e vinculado
     * - Grava a compra e seus itens
     * - Grava o De-Para em produtos_fornecedores
     * - Credita o estoque
     * - Atualiza preço de custo (se marcado)
     * - Lança financeiro (se marcado)
     */
    public function concluirImportacaoNfe()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->session->set_flashdata('error', 'Você não tem permissão para importar NF-e.');
            redirect(site_url('compras'));
        }

        $nfe = $this->session->userdata('nfe_importacao');
        if (! $nfe || empty($nfe['itens'])) {
            $this->session->set_flashdata('error', 'Sessão de importação expirada. Envie o XML novamente.');
            redirect(site_url('compras/importarNfe'));
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);

        // 1. Identificar Fornecedor
        $fornecedorId = (int) $this->input->post('fornecedor_id');
        if ($fornecedorId <= 0) {
            // Cadastra automaticamente com os dados do XML
            $fornecedorId = $this->compras_model->cadastrarFornecedorNfe($empresaId, $nfe['emitente']);
        }

        // 2. Criar a Compra
        $dadosCompra = [
            'empresa_id' => $empresaId,
            'fornecedores_id' => $fornecedorId,
            'usuarios_id' => (int) $this->session->userdata('id_usuarios'),
            'dataCompra' => substr($nfe['data_emissao'], 0, 10),
            'valorTotal' => (float) $nfe['totais']['valor_total'],
            'desconto' => (float) $nfe['totais']['valor_desconto'],
            'status' => 'Concluída',
            'faturado' => 0,
            'chave_nfe' => $nfe['chave'],
            'numero_nfe' => $nfe['numero'],
            'serie_nfe' => $nfe['serie'],
            'data_emissao_nfe' => $nfe['data_emissao'],
            'observacoes' => 'Importada via XML da NF-e ' . $nfe['numero'],
            'criado_em' => date('Y-m-d H:i:s'),
        ];

        $idCompra = $this->compras_model->add('compras', $dadosCompra, true);
        if (! $idCompra) {
            $this->session->set_flashdata('error', 'Erro ao salvar o cabeçalho da compra.');
            redirect(site_url('compras/conferirNfe'));
        }

        // 3. Processar Itens da NF-e
        $postItens = $this->input->post('itens');
        $atualizarCusto = (bool) $this->input->post('atualizar_custo');
        $this->load->model('produtos_model');

        foreach ($nfe['itens'] as $idx => $itemXml) {
            $prodPost = $postItens[$idx] ?? [];
            $produtoId = (int) ($prodPost['produto_id'] ?? 0);
            $fator = (float) ($prodPost['fator_conversao'] ?? 1.0);
            if ($fator <= 0) {
                $fator = 1.0;
            }

            if ($produtoId <= 0) {
                // Se não vinculou a nenhum produto, cria um produto novo automaticamente
                $produtoId = $this->compras_model->cadastrarProdutoRapido($empresaId, [
                    'descricao' => $itemXml['descricao'],
                    'unidade' => $itemXml['unidade'],
                    'precoCompra' => $itemXml['valor_unitario'] / $fator,
                    'codDeBarra' => $itemXml['ean'],
                ]);
            }

            $qtdEntrada = (float) $itemXml['quantidade'] * $fator;
            $precoUnitarioReal = (float) $itemXml['valor_unitario'] / $fator;

            // Salva item na compra
            $this->compras_model->add('itens_de_compras', [
                'compras_id' => $idCompra,
                'produtos_id' => $produtoId,
                'quantidade' => $qtdEntrada,
                'precoUnitario' => $precoUnitarioReal,
                'subTotal' => (float) $itemXml['valor_total'],
                'codigo_fornecedor' => $itemXml['codigo_fornecedor'],
            ]);

            // Grava na memória do De-Para para próximas compras do mesmo fornecedor
            if ($itemXml['codigo_fornecedor'] !== '') {
                $this->compras_model->salvarVinculoFornecedor($empresaId, $fornecedorId, $itemXml['codigo_fornecedor'], $produtoId, $fator);
            }

            // Credita estoque
            $this->produtos_model->updateEstoque($produtoId, $qtdEntrada, '+');

            // Atualiza preço de custo no cadastro de produtos se solicitado
            if ($atualizarCusto && $precoUnitarioReal > 0) {
                $this->db->where('idProdutos', $produtoId)->where('empresa_id', $empresaId)->update('produtos', [
                    'precoCompra' => $precoUnitarioReal,
                ]);
            }
        }

        // 4. Lançamento no Contas a Pagar (se marcado)
        $lancarFinanceiro = (bool) $this->input->post('lancar_financeiro');
        if ($lancarFinanceiro) {
            $duplicatas = ! empty($nfe['duplicatas']) ? $nfe['duplicatas'] : [
                [
                    'numero' => '1',
                    'vencimento' => date('Y-m-d', strtotime('+30 days')),
                    'valor' => (float) $nfe['totais']['valor_total'],
                ],
            ];

            $this->compras_model->faturarCompra($idCompra, [
                'parcelas' => $duplicatas,
                'forma_pgto' => 'Boleto',
                'baixado' => false,
            ]);
        }

        // Limpa dados temporários da sessão
        $this->session->unset_userdata('nfe_importacao');

        log_info("Importou NF-e {$nfe['numero']} com sucesso. Compra ID: {$idCompra}");
        $this->session->set_flashdata('success', "NF-e {$nfe['numero']} importada com sucesso! Compra #{$idCompra} gerada e estoque atualizado.");
        redirect(site_url('compras/visualizar/' . $idCompra));
    }

    /**
     * Endpoint AJAX para cadastrar produto rápido direto do modal/linha da conferência.
     */
    public function cadastrarProdutoRapido()
    {
        if (! $this->permission->checkPermission($this->session->userdata('permissao'), 'aCompra')) {
            $this->responderJson(403, ['result' => false, 'message' => 'Permissão negada.']);

            return;
        }

        $empresaId = (int) ($this->session->userdata('empresa_id') ?: 1);
        $descricao = trim((string) $this->input->post('descricao'));

        if ($descricao === '') {
            $this->responderJson(422, ['result' => false, 'message' => 'Informe a descrição do produto.']);

            return;
        }

        $id = $this->compras_model->cadastrarProdutoRapido($empresaId, [
            'descricao' => $descricao,
            'unidade' => trim((string) $this->input->post('unidade') ?: 'UN'),
            'precoCompra' => (float) $this->input->post('precoCompra'),
            'precoVenda' => (float) $this->input->post('precoVenda'),
            'codDeBarra' => trim((string) $this->input->post('codDeBarra')),
        ]);

        if ($id > 0) {
            $this->responderJson(200, [
                'result' => true,
                'id' => $id,
                'descricao' => $descricao,
                'message' => 'Produto cadastrado com sucesso!',
            ]);
        } else {
            $this->responderJson(500, ['result' => false, 'message' => 'Erro ao cadastrar o produto.']);
        }
    }

    private function responderJson(int $status, array $corpo): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
