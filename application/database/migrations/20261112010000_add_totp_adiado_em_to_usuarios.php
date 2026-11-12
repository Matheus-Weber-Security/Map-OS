<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Marca quando o usuário escolheu "Cadastrar depois" na vinculação de 2FA,
 * evitando repetir a solicitação a cada login quando o 2FA no login está desativado.
 */
class Migration_add_totp_adiado_em_to_usuarios extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('usuarios') && ! $this->db->field_exists('totp_adiado_em', 'usuarios')) {
            $this->dbforge->add_column('usuarios', [
                'totp_adiado_em' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->table_exists('usuarios') && $this->db->field_exists('totp_adiado_em', 'usuarios')) {
            $this->dbforge->drop_column('usuarios', 'totp_adiado_em');
        }
    }
}
