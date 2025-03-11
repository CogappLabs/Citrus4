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

/**
 * EntryService Service
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
class EntryService extends Component
{
    // Public Methods
    // =========================================================================

    public function getAllByEntryId($entryId)
    {
        return Citrus_EntryRecord::model()->findAllByAttributes(array(
          'entryId' => $entryId,
        ));
    }

    public function saveEntry(
        Citrus_EntryRecord $citrusEntryRecord,
    ): void {
        $citrusEntryRecord->save();
    }
}
