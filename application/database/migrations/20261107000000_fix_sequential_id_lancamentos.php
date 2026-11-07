<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Corrige IDs de lançamentos que saltaram para a faixa de 30000+ devido ao cache de auto-increment do TiDB.
 */
class Migration_fix_sequential_id_lancamentos extends CI_Migration
{
    public function up()
    {
        if ($this->db->table_exists('lancamentos')) {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

            // Busca todos os lançamentos que saltaram para a faixa de 30000+
            $saltados = $this->db
                ->where('idLancamentos >=', 30000)
                ->order_by('idLancamentos', 'ASC')
                ->get('lancamentos')
                ->result();

            foreach ($saltados as $l) {
                $idAntigo = (int) $l->idLancamentos;

                // Busca o próximo ID sequencial disponível menor que 30000
                $maxId = (int) ($this->db
                    ->select_max('idLancamentos')
                    ->where('idLancamentos <', 30000)
                    ->get('lancamentos')
                    ->row()->idLancamentos ?? 0);

                $proximoId = max(1, $maxId + 1);

                if ($this->db->table_exists('vendas')) {
                    $this->db->where('lancamentos_id', $idAntigo)->update('vendas', ['lancamentos_id' => $proximoId]);
                }

                if ($this->db->table_exists('os')) {
                    $this->db->where('lancamento', $idAntigo)->update('os', ['lancamento' => $proximoId]);
                }

                $this->db->where('idLancamentos', $idAntigo)->update('lancamentos', ['idLancamentos' => $proximoId]);
            }

            // Redefine o AUTO_INCREMENT para o próximo sequencial correto
            $maxFinal = (int) ($this->db
                ->select_max('idLancamentos')
                ->where('idLancamentos <', 30000)
                ->get('lancamentos')
                ->row()->idLancamentos ?? 0);

            $novoAutoIncrement = max(1, $maxFinal + 1);
            $this->db->query("ALTER TABLE `lancamentos` AUTO_INCREMENT = {$novoAutoIncrement}");

            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function down()
    {
        // Operação irreversível por manter consistência de chave estrangeira
    }
}
