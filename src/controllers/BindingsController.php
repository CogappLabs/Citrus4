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
use dentsucreativeuk\citrus\jobs\PurgeJob;
use dentsucreativeuk\citrus\records\BindingsRecord;

/**
 * BindingsController Controller
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
class BindingsController extends Controller
{
    use BaseHelper;

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|int|bool $allowAnonymous = ['index', 'section', 'save', 'test'];

    // Public Methods
    // =========================================================================

    public const BINDINGS_TABLE_PREFIX = 'bindingsType_';

    /**
     * Handle a request going to our plugin's index action URL, e.g.: actions/controllersExample
     */
    public function actionIndex()
    {
        $variables = array(
            'title' => 'Citrus - Bindings',
            'sections' => Citrus::getInstance()->bindings->getSections(),
        );

        return $this->renderTemplate('citrus/bindings/index', $variables);
    }

    public function actionSection()
    {
        $bansSupported = Citrus::getInstance()->settings->bansSupported;
        $bindTypes = array('PURGE' => 'PURGE');

        if ($bansSupported) {
            $bindTypes['BAN'] = 'BAN';
            $bindTypes['FULLBAN'] = 'FULLBAN';
        }

        $variables = $this->getTemplateStandardVars([
            'title' => 'Citrus - Bindings',
            'sectionId' => Craft::$app->request->getRequiredParam('sectionId'),
            'bindTypes' => $bindTypes,
            'tabs' => [],
            'bindings' => [],
            'fullPageForm' => true,
            'bansSupported' => $bansSupported,
        ]);

        if (!empty($variables['sectionId'])) {
            $variables['section'] = Craft::$app->sections->getSectionById(
                $variables['sectionId']
            );

            if (isset($variables['section'])) {
                $variables['title'] .= ' - ' . $variables['section']->name;
                $variables['types'] = $variables['section']->getEntryTypes();

                // populate tabs with types
                foreach ($variables['types'] as $type) {
                    $variables['tabs'][$type->id] = [
                        'label' => $type->name,
                        'url' => '#type' . $type->id,
                    ];
                    $variables['bindings'][$type->id] = [];
                }

                // populate rows with bindings
                $bindings = Citrus::getInstance()->bindings->getBindings(
                    $variables['sectionId']
                );

                foreach ($bindings as $binding) {
                    $variables['bindings'][$binding->typeId][$binding->id] = [
                        'bindType' => $binding->bindType,
                        'query' => $binding->query,
                    ];
                }
            }

            return $this->renderTemplate('citrus/bindings/section', $variables);
        } else {
            throw new HttpException(400, Craft::t('app', 'Param sectionId must not be empty.'));
        }
    }

    public function actionSave()
    {
        $sectionId = (int) Craft::$app->request->getRequiredParam('sectionId');
        $bindings = [];
        $saved = true;

        foreach (Craft::$app->request->bodyParams as $key => $data) {
            if (($pos = strrpos($key, self::BINDINGS_TABLE_PREFIX)) !== false) {
                $typeId = (int) str_replace(self::BINDINGS_TABLE_PREFIX, '', $key);

                if ($typeId && gettype($data) === 'array') {
                    foreach ($data as $values) {
                        $bindings[$typeId][] = [
                            'bindType' => $values['bindType'],
                            'query' => $values['query'],
                        ];
                    }
                }
            }
        }

        $cleared = Citrus::getInstance()->bindings->clearBindings($sectionId);
        $saved = Citrus::getInstance()->bindings->setBindings($sectionId, $bindings);

        if ($cleared && $saved) {
            Craft::$app->getSession()->setNotice(Craft::t('app', 'Bindings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save bindings.'));
        }

        $this->redirect('citrus/bindings');
    }

    public function actionTest()
    {
        $element = Craft::$app->elements->getElementById(
            (int) Craft::$app->request->getQueryParam('id')
        );

        if (get_class($element->type) != 'craft\models\EntryType') {
            throw(new Exception('Element tyoe is not an Entry. Only entries are supported.'));
        }

        $uris = Citrus::getInstance()->citrus->getBindingQueries(
            $element->section->id,
            $element->type->id,
            BindingsRecord::TYPE_PURGE
        );

        $bans = Citrus::getInstance()->citrus->getBindingQueries(
            $element->section->id,
            $element->type->id,
            array(
                BindingsRecord::TYPE_BAN,
                BindingsRecord::TYPE_FULLBAN,
            )
        );

        if (count($uris) > 0) {
            $settings = array(
                'description' => null,
                'uris' => $uris,
                'debug' => true,
            );
            Craft::$app->queue->push(new PurgeJob($settings));
        }

        if (count($bans) > 0) {
            $settings = array(
                'description' => null,
                'bans' => $bans,
                'debug' => true,
            );
            Craft::$app->queue->push(new BanJob($settings));
        }

        Craft::$app->getQueue()->run();
    }
}
