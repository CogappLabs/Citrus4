<?php

namespace dentsucreativeuk\citrus\helpers;

use Craft;

use dentsucreativeuk\citrus\Citrus;

use njpanderson\VarnishConnect;

class BanHelper
{
    use BaseHelper;

    private array $socket = array();

    public const BAN_PREFIX = 'req.http.host == ${hostname} && req.url ~ ';

    /**
     * @return mixed[]
     */
    public function ban(array $ban, $debug = false): array
    {
        $response = array();

        foreach ($this->getVarnishHosts() as $id => $varnishHost) {
            if ($id === $ban['hostId'] || $ban['hostId'] === null) {
                if ($varnishHost['canDoAdminBans']) {
                    $response[] = $this->sendAdmin($varnishHost, $ban['query'], $ban['full'], $debug);
                } else {
                    $response[] = $this->sendHTTP();
                }
            }
        }

        return $response;
    }

    private function sendHTTP(): never
    {
        throw new \Exception('Banning over HTTP is not yet supported');
    }

    private function sendAdmin(array $host, $query, $isFullQuery = false, $debug = false): \dentsucreativeuk\citrus\helpers\ResponseHelper
    {
        $responseHelper = new ResponseHelper(
            ResponseHelper::CODE_OK
        );

        try {
            $socket = $this->getSocket($host['adminIP'], $host['adminPort'], $host['adminSecret']);

            $banQuery = $this->parseBan($host, $query, $isFullQuery);

            Citrus::log(
                "Adding BAN query to '{$host['adminIP']}': {$banQuery}",
                'info',
                Citrus::getInstance()->settings->logAll,
                $debug
            );

            $result = $socket->addBan($banQuery);

            if ($result !== true) {
                if ($result !== null) {
                    $responseHelper->code = $result['code'];
                    $responseHelper->message = "Ban error: {$result['code']} - '" .
                        implode($result['message'], '" "') .
                        "'";

                    Citrus::log(
                        $responseHelper->message,
                        'error',
                        true,
                        $debug
                    );
                } else {
                    $responseHelper->code = ResponseHelper::CODE_ERROR_GENERAL;
                    $responseHelper->message = "Ban error: could not send to '{$host['adminIP']}'";

                    Citrus::log(
                        $responseHelper->message,
                        'error',
                        true,
                        $debug
                    );
                }
            } else {
                $responseHelper->message = sprintf('BAN "%s" added successfully', $banQuery);
            }
        } catch (\Exception $e) {
            $responseHelper->code = ResponseHelper::CODE_ERROR_GENERAL;
            $responseHelper->message = 'Ban error: ' . $e->getMessage();

            Citrus::log(
                $responseHelper->message,
                'error',
                true,
                $debug
            );
        }

        return $responseHelper;
    }

    private function getSocket($ip, $port, $secret)
    {
        if (isset($this->socket[$ip])) {
            return $this->socket[$ip];
        }

        $this->socket[$ip] = new VarnishConnect\Socket(
            $ip,
            $port,
            $secret
        );

        $this->socket[$ip]->connect();

        return $this->socket[$ip];
    }

    private function parseBan(array $host, $query, $isFullQuery = false): array|string
    {
        if (!$isFullQuery) {
            $query = self::BAN_PREFIX . $query;
        }

        $find = ['${hostname}'];
        $replace = [$host['hostName']];

        foreach (Craft::$app->i18n->getEditableLocales() as $editableLocale) {
            $find[] = '${baseUrl-' . $editableLocale->id . '}';

            if (isset($host['url'][$editableLocale->id])) {
                $replace[] = $host['url'][$editableLocale->id];
            }
        }

        // run through parsing steps
        $query = str_replace($find, $replace, $query);

        return str_replace('\\', '\\\\', $query);
    }
}
