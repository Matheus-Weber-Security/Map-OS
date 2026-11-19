<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

if (! class_exists('MpdfClienteSemRede')) {
    /**
     * Cliente HTTP do mPDF que nunca sai para a rede (#2879): um relatório com
     * <img src="https://..."> ou CSS remoto faria o servidor requisitar a URL
     * (SSRF cego). A resposta é 403, o que o mPDF trata como recurso ausente.
     * Logos e imagens locais não passam por aqui.
     */
    class MpdfClienteSemRede implements \Mpdf\Http\ClientInterface
    {
        public function sendRequest(\Psr\Http\Message\RequestInterface $request)
        {
            return new \Mpdf\PsrHttpMessageShim\Response(403);
        }
    }
}

if (! function_exists('mpdfCriar')) {
    /** mPDF A4 sem acesso à rede, com a pasta temporária do projeto. */
    function mpdfCriar(bool $landscape = false): \Mpdf\Mpdf
    {
        return new \Mpdf\Mpdf(
            ['c', $landscape ? 'A4-L' : 'A4', 'tempDir' => FCPATH . 'assets/uploads/temp/'],
            new \Mpdf\Container\SimpleContainer(['httpClient' => new MpdfClienteSemRede()])
        );
    }
}

function pdf_create($html, $filename, $stream = true, $landscape = false)
{
    $mpdf = mpdfCriar($landscape);

    $mpdf->showImageErrors = true;
    $mpdf->WriteHTML($html);

    if ($stream) {
        $mpdf->Output($filename . '.pdf', 'I');
    } else {
        $mpdf->Output(FCPATH . 'assets/uploads/temp/' . $filename . '.pdf', 'F');

        return FCPATH . 'assets/uploads/temp/' . $filename . '.pdf';
    }
}

if (! function_exists('mpdfCriarTermica')) {
    /** mPDF bobina 80mm sem acesso à rede, com a pasta temporária do projeto. */
    function mpdfCriarTermica(): \Mpdf\Mpdf
    {
        return new \Mpdf\Mpdf(
            [
                'mode' => 'utf-8',
                'format' => [80, 297],
                'margin_left' => 2,
                'margin_right' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
                'tempDir' => FCPATH . 'assets/uploads/temp/',
            ],
            new \Mpdf\Container\SimpleContainer(['httpClient' => new MpdfClienteSemRede()])
        );
    }
}

if (! function_exists('pdf_create_termica')) {
    function pdf_create_termica($html, $filename, $stream = true)
    {
        $mpdf = mpdfCriarTermica();

        $mpdf->showImageErrors = true;
        $mpdf->WriteHTML($html);

        if ($stream) {
            $mpdf->Output($filename . '.pdf', 'I');
        } else {
            $mpdf->Output(FCPATH . 'assets/uploads/temp/' . $filename . '.pdf', 'F');

            return FCPATH . 'assets/uploads/temp/' . $filename . '.pdf';
        }
    }
}
