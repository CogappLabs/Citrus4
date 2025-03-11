<?php
/**
 * citrus plugin for Craft CMS
 *
 * Automatically purge and ban cached elements in Varnish
 *
 * @link      https://www.dentsucreative.com
 * @copyright Copyright (c) 2018 Whitespace
 */

namespace dentsucreativeuk\citrus;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\User;
use dentsucreativeuk\citrus\models\Settings;

use dentsucreativeuk\citrus\services\AwsEc2Service;
use dentsucreativeuk\citrus\services\BindingsService;
use dentsucreativeuk\citrus\services\CitrusService;
use dentsucreativeuk\citrus\services\EntryService;
use dentsucreativeuk\citrus\services\HostsService;
use dentsucreativeuk\citrus\services\UriService;
use dentsucreativeuk\citrus\variables\CitrusVariable;
use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Whitespace
 * @package   Citrus
 * @since     0.0.1
 *
 * @property  BindingsService $bindingsService
 * @property  EntryService $entryService
 * @property  UriService $uriService
 * @property  CitrusService $citrusService
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Citrus extends Plugin
{
    // Static Properties
    // =========================================================================
    public const URI_TAG = 0;
    public const URI_ELEMENT = 1;
    public const URI_BINDING = 2;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public string $schemaVersion = '0.0.2';

    public bool $hasCpSection = true;

    // Public Methods
    // =========================================================================

    /**
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     */
    public function init()
    {
        parent::init();

        // Set components
        $this->setComponents([
            'bindings' => BindingsService::class,
            'entry' => EntryService::class,
            'uri' => UriService::class,
            'citrus' => CitrusService::class,
            'ec2' => AwsEc2Service::class,
            'hosts' => HostsService::class,
        ]);

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['citrus'] = 'citrus/citrus/index';
                $event->rules['citrus/purgeban'] = 'citrus/citrus/purgeban';
                $event->rules['citrus/pages'] = 'citrus/pages/index';
                $event->rules['citrus/bindings'] = 'citrus/bindings/index';
                $event->rules['citrus/bindings/section'] = 'citrus/bindings/section';
                $event->rules['citrus/ban'] = 'citrus/pages/index';
                $event->rules['citrus/ban/list'] = 'citrus/ban/list';
                $event->rules['citrus/test/purge'] = 'citrus/purge/test';
                $event->rules['citrus/test/ban'] = 'citrus/ban/test';
                $event->rules['citrus/test/bindings'] = 'citrus/bindings/test';
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('citrus', CitrusVariable::class);
            }
        );

        // Add/Remove citrus cookies
        Event::on(
            User::class,
            User::EVENT_AFTER_LOGIN,
            function(Event $event) {
                $this->setCitrusCookie('1');
            }
        );

        Event::on(
            User::class,
            User::EVENT_AFTER_LOGOUT,
            function(Event $event) {
                $this->setCitrusCookie();
            }
        );

        if ($this->settings->purgeEnabled) {
            $purgeRelated = $this->settings->purgeRelated;

            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                function(Event $event) use ($purgeRelated) {
                    // element saved
                    Citrus::getInstance()->citrus->purgeElement($event->element, $purgeRelated);
                }
            );

            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_DELETE_ELEMENT,
                function(Event $event) use ($purgeRelated) {
                    // element deleted
                    Citrus::getInstance()->citrus->purgeElement($event->element, $purgeRelated);
                }
            );

            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_PERFORM_ACTION,
                function(Event $event) use ($purgeRelated) {
                    //entry deleted via element action
                    $action = $event->action->className();
                    if ($action == 'Delete') {
                        $elements = $event->criteria->all();

                        foreach ($elements as $element) {
                            if ($element instanceof \craft\elements\Entry) {
                                Citrus::getInstance()->citrus->purgeElement($element, $purgeRelated);
                            }
                        }
                    }
                }
            );
        }

        // Log loading the plugin
        Craft::info(
            Craft::t(
                'citrus',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getCpNavItem(): array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'bindings' => ['label' => 'Bindings', 'url' => 'citrus/bindings'],
        ];
        return $item;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'citrus/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }

    public static function log(
        $message,
        $level = 'info',
        $override = false,
        $debug = false,
    ) {
        if ($debug) {
            // Also write to screen
            if ($level === 'error') {
                echo '<span style="color: red; font-weight: bold;">' . $message . "</span><br/>\n";
            } else {
                echo $message . "<br/>\n";
            }
        }

        Craft::getLogger()->log($message, $level, $category = 'Citrus');
    }

    private function setCitrusCookie($value = '')
    {
        $cookieName = $this->settings->adminCookieName;

        if ($cookieName === false) {
            return;
        }

        setcookie(
            $cookieName,
            $value,
            0,
            '/',
            null,
            Craft::$app->request->getIsSecureConnection(),
            true
        );
    }
}
