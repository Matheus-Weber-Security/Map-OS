<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Torna a coluna telefone da tabela clientes opcional (NULL com default vazio)
 * e garante que celular seja VARCHAR(20) NULL DEFAULT '', permitindo que o
 * cadastro exija apenas o celular.
 */
class Migration_make_telefone_nullable_in_clientes extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('clientes') && $this->db->field_exists('telefone', 'clientes')) {
            $this->db->query("ALTER TABLE `clientes` MODIFY `telefone` VARCHAR(20) NULL DEFAULT ''");
        }
        if ($this->db->table_exists('clientes') && $this->db->field_exists('celular', 'clientes')) {
            $this->db->query("ALTER TABLE `clientes` MODIFY `celular` VARCHAR(20) NULL DEFAULT ''");
        }
    }

    public function down()
    {
        if ($this->db->table_exists('clientes') && $this->db->field_exists('telefone', 'clientes')) {
            $this->db->query("ALTER TABLE `clientes` MODIFY `telefone` VARCHAR(20) NOT NULL");
        }
    }
}
