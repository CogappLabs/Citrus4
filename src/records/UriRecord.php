<?php
/**
 * citrus plugin for Craft CMS 3.x
 *
 * Automatically purge and ban cached elements in Varnish
 *
 * @link      https://www.dentsucreative.com
 * @copyright Copyright (c) 2018 Whitespace
 */

namespace dentsucreativeuk\citrus\records;

use craft\db\ActiveRecord;

use dentsucreativeuk\citrus\Citrus;

/**
 * UriRecord Record
 *
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * Active Record implements the [Active Record design pattern](http://en.wikipedia.org/wiki/Active_record).
 * The premise behind Active Record is that an individual [[ActiveRecord]] object is associated with a specific
 * row in a database table. The object's attributes are mapped to the columns of the corresponding table.
 * Referencing an Active Record attribute is equivalent to accessing the corresponding table column for that record.
 *
 * http://www.yiiframework.com/doc-2.0/guide-db-active-record.html
 *
 * @author    Whitespace
 * @package   Citrus
 * @since     0.0.1
 */
class UriRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
    * Declares the name of the database table associated with this AR class.
    * By default this method returns the class name as the table name by calling [[Inflector::camel2id()]]
    * with prefix [[Connection::tablePrefix]]. For example if [[Connection::tablePrefix]] is `tbl_`,
    * `Customer` becomes `tbl_customer`, and `OrderItem` becomes `tbl_order_item`. You may override this method
    * if the table is not named after this convention.
    *
    * By convention, tables created by plugins should be prefixed with the plugin
    * name and an underscore.
    *
    * @return string the table name
    */
    #[\Override]
    public static function tableName()
    {
        return '{{%citrus_uri}}';
    }

    #[\Override]
    public function afterDelete()
    {
        foreach ($this->entries as $entry) {
            $entry->delete();
        }

        return parent::afterDelete();
    }
}
