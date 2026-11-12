<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Expande a coluna refGarantia da tabela garantias de VARCHAR(15) para VARCHAR(50)
 * para comportar referências de termos de garantia mais descritivas sem erro no MySQL.
 */
class Migration_expand_garantias_ref_column extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('garantias') && $this->db->field_exists('refGarantia', 'garantias')) {
            $this->db->query('ALTER TABLE `garantias` MODIFY `refGarantia` VARCHAR(50) NULL');
        }
    }

    public function down()
    {
        if ($this->db->table_exists('garantias') && $this->db->field_exists('refGarantia', 'garantias')) {
            $this->db->query('ALTER TABLE `garantias` MODIFY `refGarantia` VARCHAR(15) NULL');
        }
    }
}
