<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Conecte_model extends CI_Model
{
    /**
     * Colunas de `usuarios` que podem ser expostas na área do cliente.
     *
     * As consultas desta model fazem join com `usuarios`; um `SELECT *` traria
     * junto o hash da senha do técnico para dentro da resposta ao cliente.
     */
    private const USUARIO_CAMPOS_PUBLICOS = 'usuarios.idUsuarios, usuarios.nome, usuarios.email, usuarios.telefone, usuarios.celular';

    public function add($table, $data, $returnId = false)
    {
        if ($table === 'os' && empty($data['codigo']) && ! empty($data['empresa_id']) && $this->db->field_exists('codigo', 'os')) {
            $this->load->model('os_model');
            $data['codigo'] = $this->os_model->gerarProximoCodigo((int) $data['empresa_id']);
        }

        $this->db->insert($table, $data);
        if ($this->db->affected_rows() == '1') {
            if ($returnId == true) {
                return $this->db->insert_id($table);
            }

            return true;
        }

        return false;
    }

    public function getLastOs($cliente, int $limite = 10)
    {
        $this->db->select('os.*, ' . self::USUARIO_CAMPOS_PUBLICOS);
        $this->db->from('os');
        $this->db->join('usuarios', 'os.usuarios_id = usuarios.idUsuarios', 'left');
        $this->db->where('clientes_id', $cliente);
        $this->db->limit($limite);
        $this->db->order_by('idOs', 'desc');

        return $this->db->get()->result();
    }

    public function getLastCompras($cliente, int $limite = 10)
    {
        $this->db->select('vendas.*,usuarios.nome');
        $this->db->from('vendas');
        $this->db->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id');
        $this->db->order_by('idVendas', 'desc');
        $this->db->where('clientes_id', $cliente);
        $this->db->limit($limite);
        $this->db->order_by('idVendas', 'desc');

        return $this->db->get()->result();
    }

    public function getCompras($table, $fields, $where, $perpage, $start, $one, $array, $cliente)
    {
        // $fields costuma vir como '*', o que traria usuarios.senha pelo join.
        $this->db->select($fields === '*' ? 'vendas.*, ' . self::USUARIO_CAMPOS_PUBLICOS : $fields);
        $this->db->from($table);
        $this->db->join('usuarios', 'vendas.usuarios_id = usuarios.idUsuarios', 'left');
        $this->db->order_by('idVendas', 'desc');
        $this->db->where('clientes_id', $cliente);
        $this->db->limit($perpage, $start);
        $this->db->order_by('idVendas', 'desc');
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getCobrancas($table, $fields, $where, $perpage, $start, $one, $array, $cliente)
    {
        // $fields costuma vir como '*', o que traria clientes.senha pelo join.
        $this->db->select($fields === '*'
            ? 'cobrancas.*, clientes.idClientes, clientes.nomeCliente, clientes.documento,
               clientes.telefone, clientes.celular, clientes.email, clientes.rua, clientes.numero,
               clientes.complemento, clientes.bairro, clientes.cidade, clientes.estado, clientes.cep'
            : $fields);
        $this->db->from($table);
        $this->db->join('clientes', 'cobrancas.clientes_id = clientes.idClientes', 'left');
        $this->db->where('clientes_id', $cliente);
        $this->db->order_by('expire_at', 'desc');
        $this->db->limit($perpage, $start);
        $this->db->order_by('idCobranca', 'desc');
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getOs($table, $fields, $where, $perpage, $start, $one, $array, $cliente)
    {
        // $fields costuma vir como '*', o que traria usuarios.senha pelo join.
        $this->db->select($fields === '*' ? 'os.*, ' . self::USUARIO_CAMPOS_PUBLICOS : $fields);
        $this->db->from($table);
        $this->db->join('usuarios', 'os.usuarios_id = usuarios.idUsuarios', 'left');
        $this->db->where('clientes_id', $cliente);
        $this->db->limit($perpage, $start);
        $this->db->order_by('idOs', 'desc');
        if ($where) {
            $this->db->where($where);
        }

        $query = $this->db->get();

        $result = ! $one ? $query->result() : $query->row();

        return $result;
    }

    public function getById($id)
    {
        $this->db->select('os.*, clientes.*, os.codigo as codigo, os.idOs as idOs, clientes.codigo as codigo_cliente, clientes.celular as celular_cliente, garantias.refGarantia, garantias.textoGarantia, usuarios.telefone as telefone_usuario, usuarios.email as email_usuario, usuarios.nome');
        $this->db->from('os');
        $this->db->join('clientes', 'clientes.idClientes = os.clientes_id');
        $this->db->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id');
        $this->db->join('garantias', 'garantias.idGarantias = os.garantias_id', 'left');
        $this->db->where('os.idOs', $id);
        $this->db->limit(1);

        return $this->db->get()->row();
    }

    public function count($table, $cliente)
    {
        $this->db->where('clientes_id', $cliente);

        return $this->db->count_all_results($table);
    }

    /**
     * OS do cliente que ainda não terminaram: nem finalizadas, nem faturadas,
     * nem canceladas.
     */
    public function contarOsEmAndamento($cliente): int
    {
        return (int) $this->db->where('clientes_id', $cliente)
            ->where_not_in('status', ['Finalizado', 'Faturado', 'Cancelado'])
            ->count_all_results('os');
    }

    public function getDados()
    {
        $this->db->where('idclientes', $this->session->userdata('cliente_id'));
        $this->db->limit(1);

        return $this->db->get('clientes')->row();
    }

    public function edit($table, $data, $fieldID, $ID)
    {
        $this->db->where($fieldID, $ID);
        $this->db->update($table, $data);

        if ($this->db->affected_rows() >= 0) {
            // Senha trocada: os access tokens da API do cliente deixam de valer.
            if ($table === 'clientes' && $fieldID === 'idClientes' && isset($data['senha'])) {
                $this->load->library('Authorization_Token');
                $this->authorization_token->incrementarVersao('clientes', 'idClientes', (int) $ID);
            }

            return true;
        }

        return false;
    }

    public function getQrCode($id, $pixKey, $emitente)
    {
        if (empty($id) || empty($pixKey) || empty($emitente)) {
            return;
        }

        $result = $this->valorTotalOS($id);
        $amount = $result['valor_desconto'] != 0 ? round(floatval($result['valor_desconto']), 2) : round(floatval($result['totalServico'] + $result['totalProdutos']), 2);

        if ($amount <= 0) {
            return;
        }

        $pix = (new StaticPayload())
            ->setAmount($amount)
            ->setTid($id)
            ->setDescription(sprintf('%s OS %s', substr($emitente->nome, 0, 18), $id), true)
            ->setPixKey(getPixKeyType($pixKey), $pixKey)
            ->setMerchantName($emitente->nome)
            ->setMerchantCity($emitente->cidade);

        return $pix->getQRCode();
    }

    /**
     * Retorna todas as empresas/oficinas onde o cliente possui cadastro.
     *
     * @param  string      $email
     * @param  string|null $documento
     * @return list<object>
     */
    public function getEmpresasDoCliente(string $email, ?string $documento = null): array
    {
        $this->db->group_start();
        $this->db->where('email', $email);
        if ($documento !== null && $documento !== '') {
            $this->db->or_where('documento', $documento);
        }
        $this->db->group_end();
        $this->db->order_by('idClientes', 'desc');

        $clientes = $this->db->get('clientes')->result();

        $empresas = [];
        $empresasVistas = [];

        foreach ($clientes as $c) {
            $empresaId = (int) ($c->empresa_id ?: 1);

            if (isset($empresasVistas[$empresaId])) {
                continue;
            }
            $empresasVistas[$empresaId] = true;

            $emitente = $this->db->where('empresa_id', $empresaId)->get('emitente')->row();
            if (! $emitente && $empresaId === 1) {
                $emitente = $this->db->order_by('id', 'ASC')->limit(1)->get('emitente')->row();
            }

            $saasEmpresa = null;
            if ($this->db->table_exists('saas_empresas')) {
                $saasEmpresa = $this->db->where('id', $empresaId)->get('saas_empresas')->row();
            }

            $nome = $emitente->nome ?? ($saasEmpresa->nome_fantasia ?: ($saasEmpresa->razao_social ?? 'Oficina #' . $empresaId));
            $doc = $emitente->cnpj ?? ($saasEmpresa->documento ?? '');
            $tel = $emitente->telefone ?? ($saasEmpresa->telefone ?? '');
            $cidade = $emitente->cidade ?? '';
            $uf = $emitente->uf ?? '';
            $logo = $emitente->url_logo ?? '';

            // Contagem de OS em andamento desta empresa para este cliente
            $osAndamento = $this->db
                ->where('clientes_id', $c->idClientes)
                ->where_not_in('status', ['Finalizado', 'Cancelado', 'Faturado'])
                ->count_all_results('os');

            $totalOs = $this->db
                ->where('clientes_id', $c->idClientes)
                ->count_all_results('os');

            $totalCompras = $this->db
                ->where('clientes_id', $c->idClientes)
                ->count_all_results('vendas');

            $empresas[] = (object) [
                'cliente_id' => (int) $c->idClientes,
                'empresa_id' => $empresaId,
                'nome' => $nome,
                'documento' => $doc,
                'telefone' => $tel,
                'cidade' => $cidade,
                'uf' => $uf,
                'logo' => $logo,
                'os_andamento' => $osAndamento,
                'total_os' => $totalOs,
                'total_compras' => $totalCompras,
            ];
        }

        return $empresas;
    }
}

/* End of file conecte_model.php */
/* Location: ./application/models/conecte_model.php */
