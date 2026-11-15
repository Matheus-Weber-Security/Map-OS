<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_forma_pagamento_to_os extends CI_Migration
{
    public function up()
    {
        if (! $this->db->field_exists('forma_pagamento', 'os')) {
            $this->dbforge->add_column('os', [
                'forma_pagamento' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'null' => true,
                    'default' => 'pix',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('forma_pagamento', 'os')) {
            $this->dbforge->drop_column('os', 'forma_pagamento');
        }
    }
}
