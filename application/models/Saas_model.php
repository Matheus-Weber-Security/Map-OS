<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Saas_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // --- Planos ---

    public function getPlanos(bool $apenasAtivos = true): array
    {
        if ($apenasAtivos) {
            $this->db->where('ativo', 1);
        }

        return $this->db->order_by('valor', 'ASC')->get('saas_planos')->result();
    }

    public function getPlanoById(int $id): ?object
    {
        return $this->db->where('id', $id)->get('saas_planos')->row();
    }

    public function atualizarPrecoAssinatura(float $valor): bool
    {
        $this->db->update('saas_planos', ['valor' => $valor]);

        return true;
    }

    public function atualizarPrecosPlanos(float $valorBasico, float $valorEssencial): bool
    {
        return $this->atualizarPrecoAssinatura($valorEssencial ?: $valorBasico);


        return true;
    }

    // --- Empresas (Tenants) ---

    public function getEmpresaById(int $id): ?object
    {
        return $this->db
            ->select('e.*, p.nome as plano_nome, p.valor as plano_valor')
            ->from('saas_empresas e')
            ->join('saas_planos p', 'p.id = e.plano_id', 'left')
            ->where('e.id', $id)
            ->get()
            ->row();
    }

    public function getEmpresas(array $filtros = []): array
    {
        $this->db
            ->select('e.*, p.nome as plano_nome, p.valor as plano_valor')
            ->from('saas_empresas e')
            ->join('saas_planos p', 'p.id = e.plano_id', 'left');

        if (! empty($filtros['status']) && $filtros['status'] !== 'todos') {
            if ($filtros['status'] === 'ativos') {
                $this->db->where('e.status', 'ativa')->where('e.dias_restantes >', 0);
            } elseif ($filtros['status'] === 'inativos') {
                $this->db->group_start()
                    ->where('e.status !=', 'ativa')
                    ->or_where('e.dias_restantes <=', 0)
                    ->group_end();
            } elseif ($filtros['status'] === 'suspensas') {
                $this->db->where('e.status', 'suspensa');
            }
        }

        if (! empty($filtros['busca'])) {
            $termo = $filtros['busca'];
            $this->db->group_start()
                ->like('e.razao_social', $termo)
                ->or_like('e.nome_fantasia', $termo)
                ->or_like('e.email', $termo)
                ->or_like('e.documento', $termo)
                ->group_end();
        }

        return $this->db->order_by('e.id', 'DESC')->get()->result();
    }

    public function adicionarDias(int $empresaId, int $dias): bool
    {
        $empresa = $this->getEmpresaById($empresaId);
        if (! $empresa) {
            return false;
        }

        $vencimentoAtual = $empresa->data_vencimento ? strtotime($empresa->data_vencimento) : time();
        $base = max($vencimentoAtual, time());
        $novoVencimento = date('Y-m-d', strtotime("+{$dias} days", $base));
        $diasCalculados = max(0, (int) ceil(($base + ($dias * 86400) - time()) / 86400));

        return $this->db->where('id', $empresaId)->update('saas_empresas', [
            'data_vencimento' => $novoVencimento,
            'dias_restantes' => $diasCalculados,
            'status' => 'ativa',
        ]);
    }

    public function atualizarDesconto(int $empresaId, int $descontoPercentual): bool
    {
        return $this->db->where('id', $empresaId)->update('saas_empresas', [
            'desconto_percentual' => max(0, min(100, $descontoPercentual)),
        ]);
    }

    public function alterarPlanoEmpresa(int $empresaId, int $planoId): bool
    {
        return $this->db->where('id', $empresaId)->update('saas_empresas', [
            'plano_id' => $planoId,
        ]);
    }

    public function alterarStatusEmpresa(int $empresaId, string $status): bool
    {
        $statusValido = in_array($status, ['ativa', 'inativa', 'suspensa'], true) ? $status : 'inativa';

        return $this->db->where('id', $empresaId)->update('saas_empresas', [
            'status' => $statusValido,
        ]);
    }

    // --- Vínculos Usuário - Empresa (Workspace Switcher) ---

    public function getEmpresasDoUsuario(int $usuarioId): array
    {
        return $this->db
            ->select('e.*, eu.papel, p.nome as plano_nome')
            ->from('saas_empresa_usuarios eu')
            ->join('saas_empresas e', 'e.id = eu.empresa_id')
            ->join('saas_planos p', 'p.id = e.plano_id', 'left')
            ->where('eu.usuario_id', $usuarioId)
            ->where('eu.ativo', 1)
            ->order_by('e.nome_fantasia', 'ASC')
            ->get()
            ->result();
    }

    public function vincularUsuarioEmpresa(int $empresaId, int $usuarioId, string $papel = 'funcionario'): bool
    {
        $existe = $this->db
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->count_all_results('saas_empresa_usuarios') > 0;

        if ($existe) {
            return $this->db
                ->where('empresa_id', $empresaId)
                ->where('usuario_id', $usuarioId)
                ->update('saas_empresa_usuarios', ['papel' => $papel, 'ativo' => 1]);
        }

        return $this->db->insert('saas_empresa_usuarios', [
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'papel' => $papel,
            'ativo' => 1,
            'criado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    // --- Assinaturas e Pagamentos PIX ---

    public function criarAssinatura(int $empresaId, int $planoId, float $valor, string $forma = 'PIX'): int
    {
        $txid = 'MAPOS' . date('YmdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 10));
        
        // Simulação / payload padrão PIX Copia e Cola (padrão EMV BRCode)
        $pixPayload = '00020126580014BR.GOV.BCB.PIX0136' . $txid . '520400005303986540' . number_format($valor, 2, '.', '') . '5802BR5915MapOS Software6009Sao Paulo62070503***6304';

        $dados = [
            'empresa_id' => $empresaId,
            'plano_id' => $planoId,
            'valor' => $valor,
            'forma_pagamento' => $forma,
            'status' => 'pendente',
            'dias_adicionados' => 30,
            'txid' => $txid,
            'qr_code' => $pixPayload,
            'criado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('saas_assinaturas', $dados);

        return (int) $this->db->insert_id();
    }

    public function getAssinaturaById(int $id): ?object
    {
        return $this->db
            ->select('a.*, e.razao_social, e.nome_fantasia, p.nome as plano_nome')
            ->from('saas_assinaturas a')
            ->join('saas_empresas e', 'e.id = a.empresa_id')
            ->join('saas_planos p', 'p.id = a.plano_id')
            ->where('a.id', $id)
            ->get()
            ->row();
    }

    public function confirmarPagamento(int $assinaturaId): bool
    {
        $assinatura = $this->getAssinaturaById($assinaturaId);
        if (! $assinatura || $assinatura->status === 'aprovado') {
            return false;
        }

        $this->db->where('id', $assinaturaId)->update('saas_assinaturas', [
            'status' => 'aprovado',
            'pago_em' => date('Y-m-d H:i:s'),
        ]);

        // Adiciona os 30 dias na empresa
        return $this->adicionarDias((int) $assinatura->empresa_id, (int) $assinatura->dias_adicionados);
    }

    // --- Métricas do Dashboard SaaS (Gerenciar) ---

    public function getMetricasDashboard(): array
    {
        // 1. Assinantes Ativos
        $ativos = $this->db
            ->where('id >', 1)
            ->where('status', 'ativa')
            ->where('dias_restantes >', 0)
            ->count_all_results('saas_empresas');

        // 2. Novos Assinantes (últimos 30 dias)
        $trintaDiasAtras = date('Y-m-d H:i:s', strtotime('-30 days'));
        $novos = $this->db
            ->where('id >', 1)
            ->where('criado_em >=', $trintaDiasAtras)
            ->count_all_results('saas_empresas');

        // 3. Receita Mensal (MRR): soma dos valores dos planos das empresas ativas (com desconto aplicado)
        $empresasAtivas = $this->db
            ->select('e.desconto_percentual, p.valor as plano_valor')
            ->from('saas_empresas e')
            ->join('saas_planos p', 'p.id = e.plano_id')
            ->where('e.id >', 1)
            ->where('e.status', 'ativa')
            ->where('e.dias_restantes >', 0)
            ->get()
            ->result();

        $mrr = 0.00;
        foreach ($empresasAtivas as $emp) {
            $desc = (float) ($emp->desconto_percentual ?? 0);
            $val = (float) ($emp->plano_valor ?? 0);
            $mrr += $val * (1 - ($desc / 100));
        }

        // 4. Cancelamentos (Inativos / Churn)
        $totalEmpresas = max(1, $this->db->where('id >', 1)->count_all_results('saas_empresas'));
        $inativos = $this->db
            ->where('id >', 1)
            ->group_start()
            ->where('status !=', 'ativa')
            ->or_where('dias_restantes <=', 0)
            ->group_end()
            ->count_all_results('saas_empresas');
        
        $churnPercent = ($inativos / $totalEmpresas) * 100;

        // 5. Formas de pagamento distribution
        $pagamentosPix = $this->db->where('forma_pagamento', 'PIX')->count_all_results('saas_assinaturas');
        $pagamentosManual = $this->db->where('forma_pagamento', 'manual')->count_all_results('saas_assinaturas');
        $totalPagamentos = max(1, $pagamentosPix + $pagamentosManual);

        // 6. Últimos Assinantes
        $ultimosAssinantes = $this->db
            ->select('e.nome_fantasia as nome, a.valor, a.forma_pagamento as pagamento, a.pago_em as data, a.criado_em')
            ->from('saas_assinaturas a')
            ->join('saas_empresas e', 'e.id = a.empresa_id')
            ->order_by('a.id', 'DESC')
            ->limit(5)
            ->get()
            ->result();

        // 7. Preços atuais dos planos
        $planoAssinatura = $this->db->where('slug', 'essencial')->get('saas_planos')->row() ?: $this->db->order_by('id', 'ASC')->get('saas_planos')->row();
        $valorAssinatura = (float) ($planoAssinatura->valor ?? 37.90);


        return [
            'assinantes_ativos' => $ativos,
            'novos_assinantes' => $novos,
            'mrr' => $mrr,
            'churn_percent' => round($churnPercent, 1),
            'forma_pix_percent' => round(($pagamentosPix / $totalPagamentos) * 100),
            'forma_manual_percent' => round(($pagamentosManual / $totalPagamentos) * 100),
            'ultimos_assinantes' => $ultimosAssinantes,
            'valor_assinatura' => $valorAssinatura,
            'valor_basico' => $valorAssinatura,
            'valor_essencial' => $valorAssinatura,
        ];
    }

    /**
     * Cadastro completo de uma nova empresa (Tenant) com período de degustação (trial).
     *
     * @param array $dadosEmpresa  Campos para saas_empresas (razao_social, nome_fantasia, documento, telefone, email)
     * @param array $dadosUsuario  Campos para usuarios (nome, email, senha_plana, cpf, telefone, cep, rua, numero, bairro, cidade, estado)
     * @param int   $diasTrial     Quantidade de dias de trial (padrão 10)
     * @return array{sucesso: bool, mensagem?: string, empresa_id?: int, empresa_nome?: string, usuario?: object}
     */
    public function cadastrarEmpresaComDono(array $dadosEmpresa, array $dadosUsuario, int $diasTrial = 10): array
    {
        $this->db->trans_start();

        $email = strtolower(trim((string) $dadosUsuario['email']));
        $userExistente = $this->db->get_where('usuarios', ['email' => $email])->row();

        if ($userExistente) {
            // Se o usuário já existe, valida se a senha bate com a conta dele
            if (! senhaConfere((string) ($dadosUsuario['senha_plana'] ?? ''), $userExistente->senha)) {
                $this->db->trans_rollback();

                return [
                    'sucesso' => false,
                    'mensagem' => 'Este e-mail já possui uma conta no sistema. Entre com a sua senha correta para criar e vincular uma nova empresa ou utilize outro e-mail.',
                ];
            }
            $usuarioId = (int) $userExistente->idUsuarios;
            $usuario = $userExistente;
        } else {
            // Cria novo usuário administrador
            $insertUser = [
                'nome' => trim((string) $dadosUsuario['nome']),
                'email' => $email,
                'senha' => password_hash((string) $dadosUsuario['senha_plana'], PASSWORD_DEFAULT),
                'telefone' => (string) ($dadosUsuario['telefone'] ?? ''),
                'celular' => (string) ($dadosUsuario['celular'] ?? $dadosUsuario['telefone'] ?? ''),
                'cpf' => preg_replace('/\D/', '', (string) ($dadosEmpresa['documento'] ?? '')),
                'cep' => (string) ($dadosUsuario['cep'] ?? ''),
                'rua' => (string) ($dadosUsuario['rua'] ?? ''),
                'numero' => (string) ($dadosUsuario['numero'] ?? ''),
                'bairro' => (string) ($dadosUsuario['bairro'] ?? ''),
                'cidade' => (string) ($dadosUsuario['cidade'] ?? ''),
                'estado' => (string) ($dadosUsuario['estado'] ?? ''),
                'situacao' => 1,
                'dataCadastro' => date('Y-m-d'),
                'permissoes_id' => 1, // Administrador
                'is_superadmin' => 0,
            ];

            $this->db->insert('usuarios', $insertUser);
            $usuarioId = (int) $this->db->insert_id();
            $usuario = $this->db->get_where('usuarios', ['idUsuarios' => $usuarioId])->row();
        }

        // Cria a nova empresa com 10 dias de trial no plano Essencial
        $hoje = date('Y-m-d');
        $vencimento = date('Y-m-d', strtotime("+{$diasTrial} days"));
        $nomeFantasia = trim((string) ($dadosEmpresa['nome_fantasia'] ?? '')) ?: trim((string) $dadosEmpresa['razao_social']);

        $insertEmpresa = [
            'razao_social' => trim((string) $dadosEmpresa['razao_social']),
            'nome_fantasia' => $nomeFantasia,
            'documento' => trim((string) ($dadosEmpresa['documento'] ?? '')),
            'telefone' => trim((string) ($dadosEmpresa['telefone'] ?? '')),
            'email' => $email,
            'plano_id' => 2, // Essencial
            'dias_restantes' => $diasTrial,
            'data_vencimento' => $vencimento,
            'status' => 'ativa',
            'desconto_percentual' => 0,
            'forma_pagamento_preferida' => 'PIX',
            'criado_em' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('saas_empresas', $insertEmpresa);
        $empresaId = (int) $this->db->insert_id();

        // Vincula o usuário como 'dono' da nova empresa
        $this->db->insert('saas_empresa_usuarios', [
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'papel' => 'dono',
            'ativo' => 1,
            'criado_em' => date('Y-m-d H:i:s'),
        ]);

        // Cria registro inicial em emitente para a nova empresa
        $this->db->insert('emitente', [
            'empresa_id' => $empresaId,
            'nome' => $nomeFantasia,
            'cnpj' => trim((string) ($dadosEmpresa['documento'] ?? '')),
            'telefone' => trim((string) ($dadosEmpresa['telefone'] ?? '')),
            'email' => $email,
            'cep' => (string) ($dadosUsuario['cep'] ?? ''),
            'rua' => (string) ($dadosUsuario['rua'] ?? ''),
            'numero' => (string) ($dadosUsuario['numero'] ?? ''),
            'bairro' => (string) ($dadosUsuario['bairro'] ?? ''),
            'cidade' => (string) ($dadosUsuario['cidade'] ?? ''),
            'uf' => (string) ($dadosUsuario['estado'] ?? ''),
            'url_logo' => '',
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return [
                'sucesso' => false,
                'mensagem' => 'Não foi possível cadastrar a empresa no banco de dados. Tente novamente.',
            ];
        }

        return [
            'sucesso' => true,
            'empresa_id' => $empresaId,
            'empresa_nome' => $nomeFantasia,
            'usuario' => $usuario,
        ];
    }
    // --- Formas de Pagamento SaaS (+30 dias) ---

    /**
     * Garante que a tabela saas_formas_pagamento exista com os dados iniciais.
     */
    public function garantirTabelaFormasPagamento(): void
    {
        if ($this->db->table_exists('saas_formas_pagamento')) {
            return;
        }

        $this->load->dbforge();
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'codigo' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
            ],
            'nome' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'descricao' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'icone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'banknote',
            ],
            'provedor' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'mercadopago',
            ],
            'ativo' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'ordem' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1,
            ],
            'instrucoes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'configuracoes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'criado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'atualizado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('codigo');
        $this->dbforge->create_table('saas_formas_pagamento', true, ['ENGINE' => 'InnoDB']);

        $agora = date('Y-m-d H:i:s');
        $this->db->insert_batch('saas_formas_pagamento', [
            [
                'codigo' => 'pix',
                'nome' => 'PIX Instantâneo',
                'descricao' => 'Compensação automática e liberação imediata',
                'icone' => 'qr-code',
                'provedor' => 'mercadopago',
                'ativo' => 1,
                'ordem' => 1,
                'instrucoes' => 'Pague escaneando o QR Code ou copiando o código PIX no aplicativo do seu banco.',
                'configuracoes' => json_encode([
                    'tipo_pix' => 'dinamico',
                    'chave_pix' => '',
                    'tipo_chave' => 'aleatoria',
                    'titular' => '',
                    'expiracao_minutos' => 60,
                    'usar_env' => 1,
                ]),
                'criado_em' => $agora,
                'atualizado_em' => $agora,
            ],
            [
                'codigo' => 'boleto',
                'nome' => 'Boleto Bancário',
                'descricao' => 'Compensação bancária em até 3 dias úteis',
                'icone' => 'barcode',
                'provedor' => 'asaas',
                'ativo' => 0,
                'ordem' => 2,
                'instrucoes' => 'Pague até a data de vencimento em qualquer agência bancária ou internet banking.',
                'configuracoes' => json_encode([
                    'dias_vencimento' => 3,
                    'usar_env' => 1,
                ]),
                'criado_em' => $agora,
                'atualizado_em' => $agora,
            ],
            [
                'codigo' => 'cartao_credito',
                'nome' => 'Cartão de Crédito',
                'descricao' => 'Aprovação imediata e renovação de +30 dias',
                'icone' => 'banknote',
                'provedor' => 'mercadopago',
                'ativo' => 0,
                'ordem' => 3,
                'instrucoes' => 'Pagamento à vista no cartão de crédito com liberação imediata.',
                'configuracoes' => json_encode([
                    'modo' => 'transparente',
                    'max_parcelas' => 1,
                    'usar_env' => 1,
                ]),
                'criado_em' => $agora,
                'atualizado_em' => $agora,
            ],
            [
                'codigo' => 'cartao_debito',
                'nome' => 'Cartão de Débito',
                'descricao' => 'Débito online com autenticação 3DS no banco',
                'icone' => 'receipt',
                'provedor' => 'mercadopago',
                'ativo' => 0,
                'ordem' => 4,
                'instrucoes' => 'Débito em conta com autenticação segura no app do seu banco.',
                'configuracoes' => json_encode([
                    'modo' => 'link',
                    'usar_env' => 1,
                ]),
                'criado_em' => $agora,
                'atualizado_em' => $agora,
            ],
        ]);
    }

    /**
     * Retorna todas as formas de pagamento cadastradas.
     */
    public function getFormasPagamento(bool $apenasAtivas = false): array
    {
        $this->garantirTabelaFormasPagamento();

        if ($apenasAtivas) {
            $this->db->where('ativo', 1);
        }

        $formas = $this->db
            ->order_by('ordem', 'ASC')
            ->order_by('id', 'ASC')
            ->get('saas_formas_pagamento')
            ->result();

        foreach ($formas as $forma) {
            $forma->config = json_decode($forma->configuracoes ?? '{}', true) ?: [];
        }

        return $formas;
    }

    /**
     * Retorna uma forma de pagamento por ID.
     */
    public function getFormaPagamentoById(int $id): ?object
    {
        $this->garantirTabelaFormasPagamento();

        $forma = $this->db
            ->where('id', $id)
            ->get('saas_formas_pagamento')
            ->row();

        if ($forma) {
            $forma->config = json_decode($forma->configuracoes ?? '{}', true) ?: [];
        }

        return $forma;
    }

    /**
     * Retorna uma forma de pagamento pelo código identificador.
     */
    public function getFormaPagamentoByCodigo(string $codigo): ?object
    {
        $this->garantirTabelaFormasPagamento();

        $forma = $this->db
            ->where('codigo', $codigo)
            ->get('saas_formas_pagamento')
            ->row();

        if ($forma) {
            $forma->config = json_decode($forma->configuracoes ?? '{}', true) ?: [];
        }

        return $forma;
    }

    /**
     * Alterna o status ativo/inativo de uma forma de pagamento.
     */
    public function alternarStatusFormaPagamento(int $id, bool $ativo): bool
    {
        $this->garantirTabelaFormasPagamento();

        return $this->db
            ->where('id', $id)
            ->update('saas_formas_pagamento', [
                'ativo' => $ativo ? 1 : 0,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Atualiza dados e configurações de uma forma de pagamento.
     */
    public function salvarFormaPagamento(int $id, array $dados): bool
    {
        $this->garantirTabelaFormasPagamento();

        $dados['atualizado_em'] = date('Y-m-d H:i:s');

        return $this->db
            ->where('id', $id)
            ->update('saas_formas_pagamento', $dados);
    }
}
