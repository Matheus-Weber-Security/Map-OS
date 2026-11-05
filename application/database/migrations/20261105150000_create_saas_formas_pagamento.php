<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Criação da tabela saas_formas_pagamento para configuração das opções
 * de pagamento de assinaturas (+30 dias) para empresas no SaaS.
 */
class Migration_create_saas_formas_pagamento extends CI_Migration
{
    public function up()
    {
        if (! $this->db->table_exists('saas_formas_pagamento')) {
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

            // Seed inicial das formas de pagamento
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
    }

    public function down()
    {
        if ($this->db->table_exists('saas_formas_pagamento')) {
            $this->dbforge->drop_table('saas_formas_pagamento');
        }
    }
}
