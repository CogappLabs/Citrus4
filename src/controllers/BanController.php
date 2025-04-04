<?php
/**
 * citrus plugin for Craft CMS 3.x
 *
 * Automatically purge and ban cached elements in Varnish
 *
 * @link      https://www.dentsucreative.com
 * @copyright Copyright (c) 2018 Whitespace
 */

namespace dentsucreativeuk\citrus\controllers;

use Craft;

use craft\web\Controller;
use dentsucreativeuk\citrus\Citrus;
use dentsucreativeuk\citrus\helpers\BaseHelper;
use dentsucreativeuk\citrus\jobs\BanJob;

use njpanderson\VarnishConnect;

/**
 * BanController Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Whitespace
 * @package   Citrus
 * @since     0.0.1
 */
class BanController extends Controller
{
    use BaseHelper;

    // Protected Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['list', 'test'];

    // Public Methods
    // =========================================================================

    private $query;
    private $isFullQuery;
    private $hostId;
    private ?\njpanderson\VarnishConnect\Socket $socket = null;

    #[\Override]
    public function init(): void
    {
        parent::init();

        $this->query = Craft::$app->request->getQueryParam('q');
        $this->hostId = Craft::$app->request->getQueryParam('h');
        $this->isFullQuery = Craft::$app->request->getQueryParam('f', false);
    }

    public function actionTest(): void
    {
        if (!empty($this->query)) {
            $bans = array(
                'query' => $this->query,
                'full' => $this->isFullQuery,
            );
        } else {
            $bans = array(
                array('query' => '.*\.jpg', 'hostId' => $this->hostId),
                array('query' => '.*\.gif', 'hostId' => $this->hostId),
                array('query' => '^/testing', 'hostId' => $this->hostId),
                array('query' => 'admin', 'hostId' => $this->hostId),
                array('query' => '\?.+$', 'hostId' => $this->hostId),
            );
        }

        $settings = array(
            'description' => null,
            'bans' => $bans,
            'debug' => true,
        );

        Craft::$app->queue->push(new BanJob($settings));
        Craft::$app->getQueue()->run();
    }

    public function actionList(): \yii\web\Response
    {
        $variables = array(
            'hostList' => array(),
        );
        $hostId = $this->getPostWithDefault('host', null);

        foreach ($this->getVarnishHosts() as $id => $varnishHost) {
            if (($id === $hostId || $hostId === null) && $varnishHost['canDoAdminBans']) {
                $this->socket = new VarnishConnect\Socket(
                    $varnishHost['adminIP'],
                    $varnishHost['adminPort'],
                    $varnishHost['adminSecret']
                );

                try {
                    $this->socket->connect();
                    $variables['hostList'][$id]['banList'] = $this->socket->getBanList();
                    $variables['hostList'][$id]['hostName'] = $varnishHost['hostName'];
                    $variables['hostList'][$id]['id'] = $id;
                } catch (\Exception $e) {
                    $variables['hostList'][$id]['adminError'] = $e->getMessage();
                }
            }
        }

        return $this->renderTemplate('citrus/fragments/banlist', $variables);
    }
}
