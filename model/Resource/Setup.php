<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to a Commercial Software License.
 * No sharing - This file cannot be shared, published or
 * distributed outside of the licensed organisation.
 * No Derivatives - You can make changes to this file for your own use,
 * but you cannot share or redistribute the changes.
 * This Copyright Notice must be retained in its entirety.
 * The full text of the license was supplied to your organisation as
 * part of the licensing agreement with mVetory.
 *
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license Commercial
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

/**
 * Resource serup model
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Resource_Setup
  extends Mage_Catalog_Model_Resource_Setup
{
  private $_fieldMap = array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'backend' => 'backend_model',
    'type' => 'backend_type',
    'table' => 'backend_table',
    'frontend' => 'frontend_model',
    'input' => 'frontend_input',
    'label' => 'frontend_label',
    'frontend_class' => 'frontend_class',
    'source' => 'source_model',
    'required' => 'is_required',
    'user_defined' => 'is_user_defined',
    'default' => 'default_value',
    'unique' => 'is_unique',
    'note' => 'note',
    'global' => 'is_global',

    //Fields from Mage_Catalog_Model_Resource_Setup
    'input_renderer' => 'frontend_input_renderer',
    'visible' => 'is_visible',
    'searchable' => 'is_searchable',
    'filterable' => 'is_filterable',
    'comparable' => 'is_comparable',
    'visible_on_front' => 'is_visible_on_front',
    'wysiwyg_enabled' => 'is_wysiwyg_enabled',
    'is_html_allowed_on_front' => 'is_html_allowed_on_front',
    'visible_in_advanced_search' => 'is_visible_in_advanced_search',
    'filterable_in_search' => 'is_filterable_in_search',
    'used_in_product_listing' => 'used_in_product_listing',
    'used_for_sort_by' => 'used_for_sort_by',
    'apply_to' => 'apply_to',
    'position' => 'position',
    'is_configurable' => 'is_configurable',
    'used_for_promo_rules' => 'is_used_for_promo_rules'
  );

  public function addAttributes ($attrs) {
    $entityTypeId = $this->getEntityTypeId('catalog_product');
    $setId = $this->getDefaultAttributeSetId($entityTypeId);

    $this->addAttributeGroup($entityTypeId, $setId, 'TM');
    $groupId = $this->getAttributeGroupId($entityTypeId, $setId, 'TM');

    foreach ($attrs as $name => $attr)
      $this
        ->addAttribute($entityTypeId, $name, $attr)
        ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
  }

  public function updateAttributes ($attrs) {
    $entityTypeId = $this->getEntityTypeId('catalog_product');

    foreach ($attrs as $name => $attr)
      foreach ($attr as $field => $value)
        $this->updateAttribute(
          $entityTypeId,
          $name,
          isset($this->_fieldMap[$field]) ? $this->_fieldMap[$field] : $field,
          $value
        );
  }

  /**
   * Remove attributes and their data if requested
   *
   * @param array $attrs List of attributes IDs or codes
   * @param bool $removeData Remove attribute data
   * @return MVentory_TradeMe_Model_Resource_Setup
   */
  public function removeAttributes ($attrs, $removeData = false) {
    $entityTypeId = $this->getEntityTypeId('catalog_product');

    foreach ($attrs as $attr) {
      if ($removeData) {
        $_attr = Mage::getModel('eav/entity_attribute')->loadByCode(
          $entityTypeId,
          $attr
        );

        $this->deleteTableRow(
          $_attr->getBackendTable(),
          'attribute_id',
          $_attr->getAttributeId()
        );
      }

      $this->removeAttribute($entityTypeId, $attr);
    }

    return $this;
  }

  /**
   * Include file with PHP code to create table by table name
   *
   * @param string $name Name of table
   * @return bool Result of including file
   */
  public function createTable ($name) {
    $tableName = $this->getTable('trademe/' . $name);
    $module = (string) $this->_moduleConfig[0]->getName();

    $file = Mage::getModuleDir('sql', $module)
            . DS . $this->_resourceName
            . DS . 'tables'
            . DS . $name . '.php';

    return file_exists($file) ? $this->_incl($file, $tableName) : false;
  }

  /**
   * Include specified file
   *
   * @param string $file Path to file
   * @param string $tableName Full name of table
   * @return bool Result of including file
   */
  protected function _incl ($file, $tableName) {
    return include $file;
  }
}
