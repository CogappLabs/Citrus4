<?php

namespace dentsucreativeuk\citrus\helpers;

use Craft;

use dentsucreativeuk\citrus\Citrus;

trait BaseHelper
{
    public $hashAlgo = 'crc32';

    public function getPostWithDefault($var, $default = null)
    {
        $value = Craft::$app->request->getBodyParam($var);
        return (empty($value) ? $default : $value);
    }

    public function getParamWithDefault($var, $default = null)
    {
        $value = Craft::$app->request->getParam($var);
        return (empty($value) ? $default : $value);
    }

    protected function hash($str): string
    {
        return hash($this->hashAlgo, $str);
    }

    protected function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    protected function getUrls(array $uri)
    {
        $urls = array();
        $hosts = $this->getVarnishHosts();

        // Sanity check uri
        $uri['uri'] = $uri['uri'] == '__home__' ? '' : rtrim($uri['uri'], '/');

        if (count($hosts) === 0) {
            Citrus::log(
                "Citrus — no host data for site! Have the hosts been set up correctly?",
                'warning',
                Citrus::getInstance()->settings->logAll,
                false
            );
        }

        foreach ($hosts as $id => $host) {
            foreach ($host['url'] as $hostLocale => $hostUrl) {
                $thisLocale = (
                    ($hostLocale === $uri['locale'] || $uri['locale'] === null) &&
                    ($uri['host'] === $id || $uri['host'] === null)
                );

                if ($thisLocale) {
                    $url = rtrim($hostUrl, '/') . '/' . ltrim($uri['uri'], '/');

                    if ($uri['uri'] && Craft::$app->config->general->addTrailingSlashesToUrls) {
                        $url .= '/';
                    }

                    $urls[] = array(
                        'hostId' => $id,
                        'hostName' => $host['hostName'],
                        'locale' => $uri['locale'],
                        'url' => $url,
                    );
                }
            }
        }

        return $this->_uniqueUrls($urls);
    }

    protected function getVarnishHosts()
    {
        $hosts = Citrus::getInstance()->hosts->getHosts();

        if (!is_array($hosts) || $hosts === []) {
            $hosts = [];
        }

        // Normalise and sanity check hosts before returning
        foreach ($hosts as &$host) {
            $host['canDoAdminBans'] = (
                !empty($host['adminIP']) &&
                !empty($host['adminPort']) &&
                !empty($host['adminSecret'])
            );

            if (!$host['url']) {
                $host['url'] = array_fill_keys([Craft::$app->sites->currentSite->id], UrlHelper::baseSiteUrl());
            }

            if (!is_array($host['url'])) {
                $url = $host['url'];
                $hostArray = array();
                // URL array is not split by site, create with each site
                foreach (Craft::$app->sites->allSiteIds as $siteId) {
                    $hostArray[$siteId] = $url;
                }

                $host['url'] = $hostArray;
            }
        }

        return $hosts;
    }

    protected function getVarnishAdminHosts(): ?array
    {
        $hosts = $this->getVarnishHosts();

        return array_filter($hosts, function(array $host) {
            return $host['canDoAdminBans'];
        });
    }

    protected function parseGuzzleResponse($httpRequest, $httpResponse, $showUri = false): \dentsucreativeuk\citrus\helpers\ResponseHelper
    {
        $responseHelper = new ResponseHelper(
            ResponseHelper::CODE_OK
        );

        if ($showUri) {
            $responseHelper->message = sprintf(
                '%s %s',
                $httpRequest->getUri(),
                $httpResponse->getReasonPhrase()
            );
        } else {
            $responseHelper->message = sprintf(
                '%s:%s %s',
                $httpRequest->getUri()->getHost(),
                $httpRequest->getUri()->getPort(),
                $httpResponse->getReasonPhrase()
            );
        }

        $statusCode = $httpResponse->getStatusCode();
        if ($statusCode != 200) {
            $responseHelper->code = $statusCode;
        }

        return $responseHelper;
    }

    protected function parseGuzzleError(string $hostId, $e, $debug = false): \dentsucreativeuk\citrus\helpers\ResponseHelper
    {
        $responseHelper = new ResponseHelper(
            ResponseHelper::CODE_ERROR_GENERAL
        );

        if ($e instanceof \GuzzleHttp\Exception\BadResponseException) {
            $responseHelper->message = 'Error on "' . $hostId . '(' . $e->getResponse()->getStatusCode() . ' - ' .
                    $e->getResponse()->getReasonPhrase() . ')';

            Citrus::log(
                $responseHelper->message,
                'error',
                true,
                $debug
            );
        } elseif ($e instanceof \GuzzleHttp\Exception\CurlException) {
            $responseHelper->code = ResponseHelper::CODE_ERROR_CURL;
            $responseHelper->message = 'cURL Error on "' . $hostId . '" URL "' . $e->getMessage();

            Citrus::log(
                $responseHelper->message,
                'error',
                true,
                $debug
            );
        } elseif ($e instanceof \Exception) {
            $responseHelper->message = 'Error on "' . $hostId . '" URL "' . $e->getMessage();

            Citrus::log(
                $responseHelper->message,
                'error',
                true,
                $debug
            );
        }

        return $responseHelper;
    }

    protected function getTemplateStandardVars(array $customVariables): array
    {
        $variables = array();
        $locales = array();

        foreach (Craft::$app->i18n->getEditableLocales() as $editableLocale) {
            $locales[] = $editableLocale;
        }

        $variables['locales'] = $locales;

        return array_merge($customVariables, $variables);
    }

    private function _uniqueUrls($urls): ?array
    {
        $found = array();

        return array_filter($urls, function(array $url) use ($found) {
            if (!in_array($url['url'], $found)) {
                $found[] = $url['url'];
                return true;
            }
        });
    }
}
