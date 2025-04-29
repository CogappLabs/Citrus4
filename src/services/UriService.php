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

use craft\base\Component;
use dentsucreativeuk\citrus\Citrus;
use dentsucreativeuk\citrus\helpers\BaseHelper;
use dentsucreativeuk\citrus\records\EntryRecord;
use dentsucreativeuk\citrus\records\UriRecord;

/**
 * UriService Service
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
class UriService extends Component
{
    use BaseHelper;

    // Public Methods
    // =========================================================================

    public function saveURIEntry(string $pageUri, int $entryId, string $locale): void
    {
        $uriHash = $this->hash($pageUri);

        // Save URI record
        $uri = $this->getURIByURIHash(
            $uriHash
        );

        $uri->uri = $pageUri;
        $uri->uriHash = $uriHash;
        $uri->locale = ($locale === '' || $locale === '0' ? null : $locale);

        $this->saveURI($uri);

        // Save Entry record
        $entryRecord = new EntryRecord();

        $entryRecord->uriId = $uri->id;
        $entryRecord->entryId = $entryId;

        $entryRecord->save();
    }

    public function deleteURI(string $pageUri): void
    {
        $uriHash = $this->hash($pageUri);

        // Save URI record
        $uri = $this->getURIByURIHash(
            $uriHash
        );

        if (!$uri->isNewRecord) {
            $uri->delete();
        }
    }

    public function getURI($id)
    {
        return UriRecord::model()->findAllByPk($id);
    }

    public function getURIByURIHash($uriHash = '')
    {
        if (empty($uriHash)) {
            throw new Exception('$uriHash cannot be blank.');
        }

        $uri = UriRecord::model()->findByAttributes(array(
          'uriHash' => $uriHash,
        ));

        if ($uri !== null) {
            return $uri;
        }

        return new UriRecord();
    }

    public function getAllURIsByEntryId(int $entryId)
    {
        return UriRecord::find()->with(array(
            'entries' => array(
                'select' => false,
                'condition' => 'entryId = ' . $entryId,
            ),
        ))->all();
    }

    public function saveURI(
        UriRecord $uriRecord,
    ): void {
        $uriRecord->save();
    }
}
