<?php

// log info
function log_info($task)
{
    $ci = &get_instance();
    $ci->load->model('Audit_model');

    $usuario = (string) ($ci->session->userdata('nome_admin') ?? '');
    $tarefa = (string) $task;

    $data = [
        'usuario' => mb_strimwidth($usuario, 0, 80, '', 'UTF-8'),
        'ip' => (string) $ci->input->ip_address(),
        'tarefa' => mb_strimwidth($tarefa, 0, 255, '', 'UTF-8'),
        'data' => date('Y-m-d'),
        'hora' => date('H:i:s'),
    ];

    $ci->Audit_model->add($data);
}
