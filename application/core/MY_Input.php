<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Extensão do CI_Input para suporte aprimorado a Proxy Reverso e CDNs.
 *
 * Adiciona suporte aos cabeçalhos HTTP_CF_CONNECTING_IP (Cloudflare) e
 * HTTP_X_REAL_IP (Nginx) na resolução do IP do visitante quando a requisição
 * passa por um proxy confiável (proxy_ips).
 */
class MY_Input extends CI_Input
{
    /**
     * Determina e valida o endereço IP do visitante.
     *
     * @return string
     */
    public function ip_address()
    {
        if ($this->ip_address !== false) {
            return $this->ip_address;
        }

        $proxy_ips = config_item('proxy_ips');
        if (! empty($proxy_ips) && ! is_array($proxy_ips)) {
            $proxy_ips = explode(',', str_replace(' ', '', $proxy_ips));
        }

        $this->ip_address = $this->server('REMOTE_ADDR');

        if ($proxy_ips) {
            $headers = [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_REAL_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP',
                'HTTP_X_CLIENT_IP',
                'HTTP_X_CLUSTER_CLIENT_IP',
            ];

            $spoof = null;
            foreach ($headers as $header) {
                $valorHeader = $this->server($header);
                if ($valorHeader !== null && trim((string) $valorHeader) !== '') {
                    // Proxies podem listar múltiplos IPs separados por vírgula (ex: client_ip, proxy1, proxy2).
                    // O primeiro IP da cadeia é o do cliente original.
                    $candidato = trim(explode(',', (string) $valorHeader)[0]);

                    if ($this->valid_ip($candidato)) {
                        $spoof = $candidato;
                        break;
                    }
                }
            }

            if ($spoof) {
                for ($i = 0, $c = count($proxy_ips); $i < $c; $i++) {
                    // Compara IP direto
                    if (strpos($proxy_ips[$i], '/') === false) {
                        if ($proxy_ips[$i] === $this->ip_address) {
                            $this->ip_address = $spoof;
                            break;
                        }
                        continue;
                    }

                    // Valida sub-rede CIDR
                    $separator = $this->valid_ip($this->ip_address, 'ipv6') ? ':' : '.';
                    if (strpos($proxy_ips[$i], $separator) === false) {
                        continue;
                    }

                    if (! isset($ip, $sprintf)) {
                        if ($separator === ':') {
                            $ip = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($this->ip_address, ':')), $this->ip_address));
                            for ($j = 0; $j < 8; $j++) {
                                $ip[$j] = intval($ip[$j], 16);
                            }
                            $sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
                        } else {
                            $ip = explode('.', $this->ip_address);
                            $sprintf = '%08b%08b%08b%08b';
                        }
                        $ip = vsprintf($sprintf, $ip);
                    }

                    sscanf($proxy_ips[$i], '%[^/]/%d', $netaddr, $masklen);

                    if ($separator === ':') {
                        $netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
                        for ($j = 0; $j < 8; $j++) {
                            $netaddr[$j] = intval($netaddr[$j], 16);
                        }
                    } else {
                        $netaddr = explode('.', $netaddr);
                    }

                    if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0) {
                        $this->ip_address = $spoof;
                        break;
                    }
                }
            }
        }

        if (! $this->valid_ip($this->ip_address)) {
            return $this->ip_address = '0.0.0.0';
        }

        return $this->ip_address;
    }
}
