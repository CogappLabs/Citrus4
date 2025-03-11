<?php

namespace dentsucreativeuk\citrus\helpers;

use dentsucreativeuk\citrus\Citrus;

use GuzzleHttp\Psr7\Request;

class PurgeHelper
{
    use BaseHelper;

    public function purge(array $uri, $debug = false)
    {
        $response = array();

        foreach ($this->getUrls($uri) as $url) {
            array_push(
                $response,
                $this->sendPurge(
                    $url['hostId'],
                    $url['hostName'],
                    $url['url'],
                    $debug
                )
            );
        }

        return $response;
    }

    private function sendPurge($id, $host, $url, $debug = false)
    {
        Citrus::log(
            "CitrusDebug - Sending purge for: '{$url}'",
            'info',
            Citrus::getInstance()->settings->logAll,
            false
        );

        $client = new \GuzzleHttp\Client(['headers/Accept' => '*/*']);

        $headers = array(
            'Host' => $host,
        );

        $request = new Request('PURGE', $url, $headers);

        try {
            $httpResponse = $client->send($request);
            return $this->parseGuzzleResponse($request, $httpResponse, true);
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return $this->parseGuzzleError($id, $e, $debug);
        } catch (\GuzzleHttp\Exception\CurlException $e) {
            return $this->parseGuzzleError($id, $e, $debug);
        } catch (Exception $e) {
            return $this->parseGuzzleError($id, $e, $debug);
        }
    }
}
