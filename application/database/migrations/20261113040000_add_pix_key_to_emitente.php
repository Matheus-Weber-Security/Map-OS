<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_pix_key_to_emitente extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('emitente') && ! $this->db->field_exists('pix_key', 'emitente')) {
            $this->dbforge->add_column('emitente', [
                'pix_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'default' => null,
                    'after' => 'email',
                ],
            ]);

            // Se existir uma chave PIX configurada globalmente, migra para o emitente da empresa master (id=1)
            if ($this->db->table_exists('configuracoes')) {
                $pixConfig = $this->db->where('config', 'pix_key')->get('configuracoes', 1)->row();
                if ($pixConfig && ! empty($pixConfig->valor)) {
                    $this->db->where('id', 1)->or_where('empresa_id', 1)->update('emitente', [
                        'pix_key' => trim((string) $pixConfig->valor),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->table_exists('emitente') && $this->db->field_exists('pix_key', 'emitente')) {
            $this->dbforge->drop_column('emitente', 'pix_key');
        }
    }
}
