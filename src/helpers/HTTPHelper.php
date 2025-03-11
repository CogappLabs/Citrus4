<?php

namespace dentsucreativeuk\citrus\helpers;

class HTTPHelper
{
    public function send(
        $ip,
        string $host,
        string $uri,
        $port = 80,
        string $method = 'PURGE',
        array $headers = array(),
    ): array {
        $response = '';

        $fp = @fsockopen(
            $ip,
            $port,
            $errno,
            $errstr,
            5
        );

        if ($fp) {
            $out = '';
            $body = '';

            $headers = array_merge(array(
                $method . ' ' . $uri . ' HTTP/1.1',
                'Host: ' . $host,
                'Connection: Close',
            ), $headers);

            // concatenate socket data
            $out .= implode("\r\n", $headers) . "\r\n\r\n";
            $out .= $body . "\r\n\r\n";

            // write to socket
            fwrite($fp, $out);

            // obtain response then close
            while (!feof($fp)) {
                $response .= fgets($fp, 128);
            }

            fclose($fp);

            // split response into header & body
            $response = explode("\r\n\r\n", $response, 2);
            $response[0] = explode("\r\n", $response[0]);

            // get http code from header
            preg_match('/HTTP\/[\d.]+\s(\d+)/', $response[0][0], $matches);

            if (count($matches) === 2) {
                $response_code = (int) $matches[1];
            }

            return array(
                'code' => $response_code,
                'headers' => $response[0],
                '_body' => (isset($response[1]) ? $response[1] : null),
            );
        }
        throw new Exception('Socket could not be opened to host. ' . $errstr);
    }
}
