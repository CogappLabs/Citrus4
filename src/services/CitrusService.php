<?php
/**
 * citrus plugin for Craft CMS 3.x
 *
 * Automatically purge and ban cached elements in Varnish
 *
 * @link      https://www.dentsucreative.com
 * @copyright Copyright (c) 2018 Whitespace
 */

namespace dentsucreativeuk\citrus\services;

use Craft;

use craft\base\Component;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use dentsucreativeuk\citrus\Citrus;

use dentsucreativeuk\citrus\helpers\BanHelper;
use dentsucreativeuk\citrus\helpers\PurgeHelper;
use dentsucreativeuk\citrus\jobs\BanJob;

use dentsucreativeuk\citrus\jobs\PurgeJob;
use dentsucreativeuk\citrus\records\BindingsRecord;

/**
 * CitrusService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Whitespace
 * @package   Citrus
 * @since     0.0.1
 */
class CitrusService extends Component
{
    public $settings = array();

    /**
     * Purge a single element. Just a wrapper for purgeElements().
     *
     * @param  mixed $event
     */
    public function purgeElement($element, $purgeRelated = false, $debug = false): array
    {
        return $this->purgeElements(array($element), $purgeRelated, $debug);
    }

    /**
     * Purge an array of elements
     *
     * @param  mixed $event
     */
    public function purgeElements($elements, $purgeRelated = false, $debug = false): array
    {
        $tasks = array();

        if (count($elements) > 0) {
            // Assume that we only want to purge elements in one locale.
            // May not be the case if other thirdparty plugins sends elements.
            $locale = $elements[0]->siteId;

            $uris = array();
            $bans = array();

            foreach ($elements as $element) {
                $uris = array_merge(
                    $uris,
                    $this->getElementUris($element, $locale, $purgeRelated)
                );

                if ($element instanceof \craft\elements\Entry) {
                    if (!isset($element->section->id)) {
                        continue;
                    }
                    if ($element->type->id === null) {
                        continue;
                    }
                    $uris = array_merge($uris, $this->getTagUris($element->id));
                    $uris = array_merge($uris, $this->getBindingQueries(
                        $element->section->id,
                        $element->type->id,
                        BindingsRecord::TYPE_PURGE
                    ));
                    $bans = array_merge($bans, $this->getBindingQueries(
                        $element->section->id,
                        $element->type->id,
                        array(
                            BindingsRecord::TYPE_BAN,
                            BindingsRecord::TYPE_FULLBAN,
                        )
                    ));
                }
            }

            $uris = $this->uniqueUris($uris);

            if ($uris !== []) {
                $tasks[] = $this->makeTask('Citrus_Purge', array(
                    'description' => null,
                    'uris' => $uris,
                    'debug' => $debug,
                ));
            }

            if ($bans !== []) {
                $tasks[] = $this->makeTask('Citrus_Ban', array(
                    'description' => null,
                    'bans' => $bans,
                    'debug' => $debug,
                ));
            }
        }

        return $tasks;
    }

    public function purgeURI($uri, $hostId = null): array
    {
        $purgeHelper = new PurgeHelper();

        return $purgeHelper->purge($this->makeVarnishUri(
            $uri,
            null,
            Citrus::URI_ELEMENT,
            $hostId
        ));
    }

    public function banQuery($query, $isFullQuery = false, $hostId = null): array
    {
        $banHelper = new BanHelper();

        return $banHelper->ban(array(
            'query' => $query,
            'full' => $isFullQuery,
            'hostId' => $hostId,
        ));
    }

    /**
     * Gets URIs from section/entryType bindings
     * @return mixed[]
     */
    public function getBindingQueries($sectionId, $typeId, $bindType = null): array
    {
        $queries = array();
        $bindings = Citrus::getInstance()->bindings->getBindings(
            $sectionId,
            $typeId,
            $bindType
        );

        foreach ($bindings as $binding) {
            $isCorrectType = (
                $binding->bindType === BindingsRecord::TYPE_PURGE &&
                $bindType === BindingsRecord::TYPE_PURGE
            );

            if ($isCorrectType) {
                // A single PURGE type is requested
                $queries[] = $this->makeVarnishUri(
                    $binding->query,
                    null,
                    Citrus::URI_BINDING
                );
            } elseif (is_array($bindType)) {
                // Multiple bind types are requested
                $queries[] = array(
                    'query' => $binding->query,
                    'full' => ($binding->bindType === BindingsRecord::TYPE_FULLBAN),
                );
            } else {
                // One bind type is requested (but not purge)
                $queries[] = $binding->query;
            }
        }

        return $queries;
    }

    public function makeVarnishUri(
        $uri,
        $locale = null,
        $type = Citrus::URI_ELEMENT,
        $hostId = null,
    ): array {
        if ($locale instanceof LocaleModel) {
            $locale = $locale->id;
        }

        // Sanity check beginning slashes
        $uri = '/' . ltrim($uri, '/');

        return array(
            'uri' => $uri,
            'locale' => $locale,
            'host' => $hostId,
            'type' => $type,
        );
    }

    /**
     * Get URIs to purge from $element in $locale.
     *
     * Adds the URI of the $element, and all related elements
     *
     * @param $element
     * @param $locale
     */
    private function getElementUris($element, $locale, $getRelated = true): array
    {
        $uris = array();

        foreach (Craft::$app->sites->getAllSiteIds() as $locale) {
            if ($element->uri) {
                $uris[] = $this->makeVarnishUri(
                    Craft::$app->elements->getElementUriForSite($element->id, $locale),
                    $locale
                );
            }

            // If this is a matrix block, get the uri of matrix block owner
            if (Craft::$app->getElements()->getElementTypeById($element->id) == "craft\elements\MatrixBlock" && $element->owner->uri != '') {
                $uris[] = $this->makeVarnishUri($element->owner->uri, $locale);
            }

            // Get related elements and their uris
            if ($getRelated) {
                // get directly related entries
                $relatedEntries = $this->getRelatedElementsOfType($element, $locale, 'entry');
                foreach ($relatedEntries as $relatedEntry) {
                    if ($relatedEntry->uri != '') {
                        $uris[] = $this->makeVarnishUri($relatedEntry->uri, $locale);
                    }
                }
                unset($relatedEntries);

                // get directly related categories
                $relatedCategories = $this->getRelatedElementsOfType($element, $locale, 'category');
                foreach ($relatedCategories as $relatedCategory) {
                    if ($relatedCategory->uri != '') {
                        $uris[] = $this->makeVarnishUri($relatedCategory->uri, $locale);
                    }
                }
                unset($relatedCategories);

                // get directly related matrix block and its owners uri
                $relatedMatrixes = $this->getRelatedElementsOfType($element, $locale, 'matrixblock');
                foreach ($relatedMatrixes as $relatedMatrix) {
                    if ($relatedMatrix->owner->uri != '') {
                        $uris[] = $this->makeVarnishUri($relatedMatrix->owner->uri, $locale);
                    }
                }
                unset($relatedMatrixes);
            }
        }

        return $uris;
    }

    /**
     * Gets URIs from tags attached to the front-end
     * @return mixed[]
     */
    private function getTagUris(?int $elementId): array
    {
        $uris = array();
        $tagUris = Citrus::getInstance()->uri->getAllURIsByEntryId($elementId);

        foreach ($tagUris as $tagUri) {
            $uris[] = $this->makeVarnishUri(
                $tagUri->uri,
                $tagUri->locale,
                Citrus::URI_TAG
            );
            $tagUri->delete();
        }

        return $uris;
    }

    /**
     * Gets elements of type $elementType related to $element in $locale
     *
     * @param $element
     * @param $locale
     * @param $elementType
     * @return mixed
     */
    private function getRelatedElementsOfType($element, $locale, string $elementTypeHandle)
    {
        $elementType = Craft::$app->elements->getElementTypeByRefHandle($elementTypeHandle);
        if (!$elementType) {
            return array();
        }

        switch ($elementTypeHandle) {
            case 'category':
                $criteria = Category::find();
                break;
            case 'entry':
                $criteria = Entry::find();
                break;
            case 'matrixblock':
                $criteria = MatrixBlock::find();
                break;
        }

        $criteria->relatedTo = $element;
        $criteria->siteId = $locale;
        return $criteria->all();
    }

    /**
     *
     *
     * @param $uris
     */
    private function getMappedUris($uris): array
    {
        $mappedUris = array();
        $map = $this->getSetting('purgeUriMap');

        if (is_array($map)) {
            foreach ($uris as $uri) {
                if (isset($map[$uri->uri])) {
                    $mappedVal = $map[$uri->uri];

                    if (is_array($mappedVal)) {
                        $mappedUris = array_merge($mappedUris, $mappedVal);
                    } else {
                        $mappedUris[] = $mappedVal;
                    }
                }
            }
        }

        return $mappedUris;
    }

    /**
     * Create task for purging urls
     *
     * @param $taskName
     * @param $uris
     * @param $locale
     */
    private function makeTask(string $taskName, array $settings = array())
    {
        $job = false;
        Citrus::log(
            'Created task (' . $taskName . ')',
            'info',
            Citrus::getInstance()->settings->logAll
        );

        switch ($taskName) {
            case 'Citrus_Purge':
                $job = new PurgeJob($settings);
                break;
            case 'Citrus_Ban':
                $job = new BanJob($settings);
                break;
        }

        if ($job) {
            return Craft::$app->queue->push($job);
        }
        return null;
    }

    /**
     * Gets a plugin setting
     *
     * @param $name String Setting name
     * @return mixed Setting value
     * @author André Elvan
     */
    public function getSetting($name)
    {
        return Craft::$app->config->get($name, 'citrus');
    }

    /**
     * @return mixed[]
     */
    private function uniqueUris(array $uris): array
    {
        $found = array();
        $result = array();

        foreach ($uris as $uri) {
            if (!isset($uri['locale']) || empty($uri['locale'])) {
                $uri['locale'] = '<none>';
            }

            if (!isset($found[$uri['locale']])) {
                $found[$uri['locale']] = array();
            }

            if (isset($uri['uri']) && !in_array($uri['uri'], $found[$uri['locale']])) {
                $found[$uri['locale']][] = $uri['uri'];
                // reset any locales to null if required
                if ($uri['locale'] === '<none>') {
                    $uri['locale'] = null;
                }
                $result[] = $uri;
            }
        }

        return $result;
    }
}
