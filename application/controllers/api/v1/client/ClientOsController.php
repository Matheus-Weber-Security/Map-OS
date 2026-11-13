<?php

/*
    Ivan Sarkozin
    https://github.com/sarkozin
*/

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';


class ClientOsController extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Conecte_model');
        $this->load->helper('os');
        $this->load->library('Authorization_Token');
        $this->load->library('pagination');
        $this->load->library('format');
        $this->load->model('mapos_model');
        $this->load->model('os_model');
    }

    public function Index_get()
    {
        $clientLogged = $this->logged_client();
        
        $data['os'] = $this->Conecte_model->getLastOs($this->logged_client()->usuario->idClientes);
        $data['compras'] = $this->Conecte_model->getLastCompras($this->logged_client()->usuario->idClientes);
        
        if (empty($data['os'])) {
            $this->response([
                'status' => true,
                'message' => 'Nenhuma ordem de serviço encontrada'
            ], REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => true,
                'message' => 'Listando resultados',
                'result' => [
                    'Os' => $data['os'],
                    'compras' => $data['compras']
                ],
            ], REST_Controller::HTTP_OK);
        }
    }

    public function os_get(int $id = null)
    {
        $clientLogged = $this->logged_client();
        if (! $id) {
            $config['per_page'] = $this->CI->db->get_where('configuracoes', ['config' => 'per_page'])->row_object()->valor;
            $this->pagination->initialize($config);
            $data['results'] = $this->Conecte_model->getOs('os', '*', '', $config['per_page'], $this->uri->segment(3), '', '', $this->logged_client()->usuario->idClientes);
        
            $this->response([
                'status' => true,
                'message' => 'Listando resultados',
                'result' => [
                    'Os' => $data['results'],
                ],
            ], REST_Controller::HTTP_OK);
        }
        
        $osId = (int) $id;

        $emitente = $this->mapos_model->getEmitente();
        $pixConfig = $this->CI->db->get_where('configuracoes', ['config' => 'pix_key'])->row();
        $data['pix_key'] = !empty($emitente->pix_key) ? (string) $emitente->pix_key : (string) ($pixConfig->valor ?? '');
        $data['result'] = $this->os_model->getById($osId);
        if (empty($data['result'])) {
            $this->response([
                'status' => false,
                'message' => 'Ordem de serviço não encontrada'
            ], REST_Controller::HTTP_NOT_FOUND);
        }

        // A OS precisa pertencer ao cliente autenticado.
        if ((int) $data['result']->idClientes !== (int) $clientLogged->usuario->idClientes) {
            $this->response([
                'status' => false,
                'message' => 'Ordem de serviço não encontrada'
            ], REST_Controller::HTTP_NOT_FOUND);
        }

        $os = $data['result'];
        $produtos = $this->os_model->getProdutos($osId);
        $servicos = $this->os_model->getServicos($osId);
        $anexos = $this->os_model->getAnexos($osId);
        $emitente = $this->mapos_model->getEmitente();
        $qrCode = $this->os_model->getQrCode(
            $id,
            $data['pix_key'],
            $emitente
        );
        $chaveFormatada = $this->format->formatarChave($data['pix_key']);
        foreach ($produtos as $produto) {
            unset(
                $produto->precoCompra,
                $produto->estoque,
                $produto->estoqueMinimo,
                $produto->saida,
                $produto->entrada
            );
        }

        $anexos = osAnexosParaApi($anexos, site_url('api/v1/client/os/' . (int) $osId . '/anexos'));

        unset($os->senha);

        $data = [
            'pix_key' => $data['pix_key'],
            'os' => $os,
            'produtos' => $produtos,
            'servicos' => $servicos,
            'anexos' => $anexos,
            'emitente' => $emitente,
            'qrCode' => $qrCode,
            'chaveFormatada' => $chaveFormatada,
        ];
        
        $this->response([
            'status' => true,
            'message' => 'Listando resultados',
            'result' => $data,
        ], REST_Controller::HTTP_OK);
    }

    public function os_post()
    {
        header('Content-Type: application/json');
        
        $_POST = (array) json_decode(file_get_contents('php://input'), true);

        $clientLogged = $this->logged_client();

        $this->load->library('form_validation');
        $this->form_validation->set_rules('descricaoProduto', 'Descrição', 'required');
        $this->form_validation->set_rules('defeito', 'Defeito');
        $this->form_validation->set_rules('observacoes', 'Observações');
    
        if ($this->form_validation->run() === false) {
            $this->response([
                'status' => false,
                'message' => validation_errors() ?: 'Erro de validação.',
            ], REST_Controller::HTTP_BAD_REQUEST);
            return;
        }
    
        $empresaId = (int) ($this->logged_client()->usuario->empresa_id ?? $this->session->userdata('empresa_id') ?: 1);

        $usuario = $this->db->query('SELECT usuarios_id, count(*) as down FROM os WHERE empresa_id = ? GROUP BY usuarios_id ORDER BY down LIMIT 1', [$empresaId])->row();

        if ($usuario == null) {
            if ($this->db->table_exists('saas_empresa_usuarios')) {
                $this->db->join('saas_empresa_usuarios', 'saas_empresa_usuarios.usuario_id = usuarios.idUsuarios');
                $this->db->where('saas_empresa_usuarios.empresa_id', $empresaId);
            } elseif ($this->db->field_exists('empresa_id', 'usuarios')) {
                $this->db->where('usuarios.empresa_id', $empresaId);
            }
            $this->db->where('usuarios.situacao', 1);
            $this->db->limit(1);
            $usuario = $this->db->get('usuarios')->row();

            if ($usuario == null || ($usuario->idUsuarios ?? null) == null) {
                $this->response([
                    'status' => false,
                    'message' => 'Ocorreu um erro ao atribuir a ordem de serviço. Por favor, contate o administrador do sistema.',
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                return;
            }
        }

        $usuarioId = $usuario->usuarios_id ?? $usuario->idUsuarios;

        $data = [
            'dataInicial' => date('Y-m-d'),
            'clientes_id' => $this->logged_client()->usuario->idClientes,
            'usuarios_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'dataFinal' => date('Y-m-d'),
            'descricaoProduto' => osTextoParaGravar((string) $this->input->post('descricaoProduto')),
            'defeito' => osTextoParaGravar((string) $this->input->post('defeito')),
            'status' => 'Aberto',
            'observacoes' => osTextoParaGravar((string) $this->input->post('observacoes')),
            'faturado' => 0,
        ];

        $osId = $this->Conecte_model->add('os', $data, true);

        $this->load->model('Audit_model');
        $log_data = [
            'Usuario' => '[APP]',
            'tarefa' => 'Cliente ' . $this->logged_client()->usuario->nomeCliente . " Adicionou uma OS Nº ${osId} pelo APP",
            'data' => date('Y-m-d'),
            'hora' => date('H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'empresa_id' => $empresaId,
        ];
        $this->Audit_model->add($log_data);
            
        if (is_numeric($osId)) {
            $this->response([
                'status' => true,
                'message' => 'Ordem de serviço criada com sucesso.',
                'os_id' => $osId,
            ], REST_Controller::HTTP_CREATED);
        } else {
            $this->response([
                'status' => false,
                'message' => 'Ocorreu um erro ao criar a ordem de serviço.',
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Arquivo (ou miniatura com ?miniatura=1) de um anexo de uma OS do cliente autenticado. */
    public function anexo_get(int $id, int $idAnexo)
    {
        $clientLogged = $this->logged_client();
        $os = $this->os_model->getById($id);

        if (! $os || (int) $os->idClientes !== (int) $clientLogged->usuario->idClientes) {
            $this->response([
                'status' => false,
                'message' => 'Anexo não encontrado',
            ], REST_Controller::HTTP_NOT_FOUND);
        }

        $anexo = $this->os_model->getAnexoDaOs($id, $idAnexo);
        $arquivo = $anexo ? armazenamentoArquivoDoAnexo($anexo, $this->get('miniatura') !== null) : null;

        if ($arquivo === null) {
            $this->response([
                'status' => false,
                'message' => 'Anexo não encontrado',
            ], REST_Controller::HTTP_NOT_FOUND);
        }

        armazenamentoEnviar($arquivo, 'anexo-' . (int) $anexo->idAnexos . '.' . strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)));
    }
}
