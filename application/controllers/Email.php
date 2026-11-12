<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Email extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('email');
    }

    public function process()
    {
        if (! $this->input->is_cli_request()) {
            show_404();
        }

        $this->email->send_queue();
    }

    public function retry()
    {
        if (! $this->input->is_cli_request()) {
            show_404();
        }

        $this->email->retry_queue();
    }

    /**
     * Pixel transparente 1x1 para registrar quando um e-mail enviado foi aberto.
     */
    public function rastrear($token = '')
    {
        $token = trim((string) $token);
        if ($token !== '') {
            $this->load->model('email_model');
            $this->email_model->registrarAbertura(
                $token,
                $this->input->ip_address(),
                $this->input->user_agent()
            );
        }

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $this->output
            ->set_status_header(200)
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('Expires: Thu, 01 Jan 1970 00:00:00 GMT')
            ->set_content_type('image/png')
            ->set_output($png);
    }
}
