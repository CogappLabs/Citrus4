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

/**
 * PagesController Controller
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
class PagesController extends Controller
{
    // Protected Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['index'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL, e.g.: actions/controllersExample
     */
    public function actionIndex(): \yii\web\Response
    {
        $variables = array(
            'title' => 'Citrus - Pages',
        );

        return $this->renderTemplate('citrus/pages/index', $variables);
    }
}
