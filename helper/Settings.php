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
 * part of the licensing agreement with mVentory.
 *
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2015 mVentory Ltd. (http://mventory.com)
 * @license Commercial
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

/**
 * Settings import/export helper
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

class MVentory_TradeMe_Helper_Settings extends MVentory_TradeMe_Helper_Data
{
  const NUM_OF_COLUMNS = 14;

  /**
   * Upload TradeMe optons file and import data from it
   *
   * @param Varien_Object $object
   * @throws Mage_Core_Exception
   */
  public function import ($data) {
    $scopeId = $data->getScopeId();
    $website = Mage::app()->getWebsite($scopeId);

    $shippingTypes = $this->getShippingTypes($website->getDefaultStore());

    if (!$shippingTypes)
      Mage::throwException($this->__('There\'re no available shipping types'));

    $accounts = $this->getAccounts($website);

    if (!$accounts)
      Mage::throwException($this->__('There\'re no accounts in this website'));

    foreach ($accounts as $id => $account)
      $accountMap[strtolower($account['name'])] = $id;

    /**
     * Map of shipping type labels to shipping type IDs
     * There's 2 special values:
     *   - * - means any shipping type in product
     *   - <empty> - means no shipping type is set in product
     *
     * @var array
     */
    $shippingTypeMap = array(
      '*' => '*',
      '' => ''
    );

    foreach ($shippingTypes as $id => $label)
      $shippingTypeMap[strtolower(trim($label))] = $id;

    $groupId = $data->getGroupId();
    $field = $data->getField();

    if (empty($_FILES['groups']
                     ['tmp_name']
                     [$groupId]
                     ['fields']
                     [$field]
                     ['value']))
      return;

    $file = $_FILES['groups']['tmp_name'][$groupId]['fields'][$field]['value'];

    $info = pathinfo($file);

    $io = new Varien_Io_File();

    $io->open(array('path' => $info['dirname']));
    $io->streamOpen($info['basename'], 'r');

    //Check and skip headers
    $headers = $io->streamReadCsv();

    if ($headers === false || count($headers) < self::NUM_OF_COLUMNS) {
      $io->streamClose();

      Mage::throwException(
        $this->__('Invalid TradeMe options file format')
      );
    }

    $rowNumber = 1;
    $data = array();

    $params = array(
      'account' => $accountMap,
      'type' => $shippingTypeMap,
      'hash' => array(),
      'errors' => array()
    );

    try {
      while (false !== ($line = $io->streamReadCsv())) {
        $rowNumber ++;

        if (empty($line))
          continue;

        $row = $this->_getImportRow($line, $rowNumber, $params);

        if ($row !== false)
          $data[] = $row;
      }

      $io->streamClose();

      if ($data) {
        $data = $this->_restructureImportedOptions($data);
        $this->_saveImportedOptions($data, $website);

        $missingSettings = $this->_checkMissingSettings($data, $shippingTypes);
      } else
        $params['errors'][] = $this->__('No options in the file');

    } catch (Mage_Core_Exception $e) {
      $io->streamClose();

      Mage::throwException($e->getMessage());
    } catch (Exception $e) {
      $io->streamClose();

      Mage::logException($e);

      $params['errors'][] = $this->__(
        'An error occurred while processing file. See logs for more info'
      );
    }

    if ($params['errors']) {
      $newLine = '<br />&nbsp;&nbsp;&nbsp;&nbsp;*&nbsp;';
      $isSingle = count($params['errors']) == 1;

      $error = $this->__($isSingle ? 'Error' : 'Errors') . ':';
      $error .= $isSingle
                  ? ' ' . $params['errors'][0]
                    : $newLine . implode($newLine, $params['errors']);

      Mage::throwException(
        $this->__('File has not been imported') . '<br />' . $error
      );
    }

    if ($missingSettings) {
      foreach ($missingSettings as $accountId => $shippingTypes) {
        $name = $accounts[$accountId]['name'];

        foreach ($shippingTypes as $type)
          $msgs[] = $this->__(
            'Shipping type %s not configured for account %s.',
            $type,
            $name
          );
      }

      if (isset($msgs))
        Mage::getSingleton('adminhtml/session')->addWarning(
          implode('<br />', $msgs)
        );
    }
  }

  /**
   * Validate row for import and return options array or false
   *
   * @param array $row
   * @param int $rowNumber
   *
   * @return array|false
   */
  protected function _getImportRow ($row, $rowNumber = 0, &$params) {

    //Validate row
    if (count($row) < self::NUM_OF_COLUMNS) {
      $msg = 'Invalid TradeMe options format in row %s';
      $params['errors'][] = $this->__($msg, $rowNumber);

      return false;
    }

    //Strip whitespace from the beginning and end of each column
    foreach ($row as $k => $v)
      $row[$k] = trim($v);

    $account = strtolower($row[0]);

    if (!isset($params['account'][$account])) {
      $msg = 'Invalid account ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[0], $rowNumber);

      return false;
    }

    $account = $params['account'][$account];

    $shippingType = strtolower($row[1]);

    if (!isset($params['type'][$shippingType])) {
      $msg = 'Invalid shipping type ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[1], $rowNumber);

      return false;
    }

    $shippingType = $params['type'][$shippingType];

    //Validate maximum weight
    $weight = $this->_parseWeight($row[2]);

    if ($weight === false) {
      $msg = 'Invalid Maximum weight value ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[2], $rowNumber);

      return false;
    }

    //Validate minimal price
    $minimalPrice = $this->_parsePrice($row[3]);

    if ($minimalPrice === false) {
      $msg = 'Invalid Minimal price value ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[3], $rowNumber);

      return false;
    }

    $freeShippingCost = $this->_parseDecimalValue($row[4]);

    if ($freeShippingCost === false) {
      $msg = 'Invalid Free shipping cost value ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[4], $rowNumber);

      return false;
    }

    $addFees = $this->_parseAddfeesValue($row[7]);

    if ($addFees === false) {
      $msg = 'Invalid Add fees value ("%s") in row %s.';
      $params['errors'][] = $this->__($msg, $row[7], $rowNumber);

      return false;
    }

    //Validate listing duration value
    $listingDuaration = (int) $row[11];

    //!!!TODO: use getDuration() method
    if (!$listingDuaration || $listingDuaration > self::LISTING_DURATION_MAX)
      $listingDuaration = self::LISTING_DURATION_MAX;
    else if ($listingDuaration < self::LISTING_DURATION_MIN)
      $listingDuaration = self::LISTING_DURATION_MIN;

    //Protect from duplicate
    $hash = sprintf('%s-%s-%s', $account, $shippingType, $weight);

    if (isset($params['hash'][$hash])) {
      $msg = 'Duplicate row %s.';

      $params['errors'][] = $this->__($msg, $rowNumber);

      return false;
    }

    $params['hash'][$hash] = true;

    return array(
      'account' => $account,
      'shipping_type' => $shippingType,
      'weight' => $weight,
      'minimal_price' => $minimalPrice,
      'free_shipping_cost' => $freeShippingCost,
      'allow_buy_now' => (bool) $row[5],
      'avoid_withdrawal' => (bool) $row[6],
      'add_fees' => $addFees,
      'allow_pickup' => (bool) $row[8],
      'category_image' => (bool) $row[9],
      'buyer' => (int) $row[10],
      'duration' => $listingDuaration,
      'shipping_options' => $this->_parseShippingOptionsValue($row[12]),
      'footer' => $row[13]
    );
  }

  /**
   * Parse and validate positive decimal value
   * Return false if value is not decimal or is not positive
   *
   * @param string $value
   * @return bool|float
   */
  protected function _parseDecimalValue ($value) {
    if (!is_numeric($value))
      return false;

    $value = (float) sprintf('%.2F', $value);

    if ($value < 0.0000)
      return false;

    return $value;
  }

  /**
   * Parse weight option from TradeMe config file
   *
   * @param string $value
   *   Raw value of the weight option
   *
   * @return string|float|false
   *   Returns false value if passed raw values is not numeric string,
   *   otherwise return float number or empty string for empty weight option
   */
  protected function _parseWeight ($value) {
    if ($value === '')
      return $value;

    return is_numeric($value) ? (float) $value : false;
  }

  /**
   * Parse price option from TradeMe config file
   *
   * @param string $value
   *   Raw value of the price option
   *
   * @return string|float|false
   *   Returns false value if passed raw values is not numeric string
   *   or value is negative, otherwise return float number or empty string
   *   for empty price option
   */
  protected function _parsePrice ($value) {
    if ($value === '')
      return $value;

    if (!is_numeric($value))
      return false;

    return (($value = (float) sprintf('%.2F', $value)) >= 0) ? $value : false;
  }

  protected function  _parseAddfeesValue ($value) {
    if (!$value = trim($value))
      return MVentory_TradeMe_Model_Config::FEES_NO;

    if (strlen($value) == 1) {
      $value = (int) $value;

      $values = Mage::getModel('trademe/attribute_source_addfees')
        ->toOptionArray();

      return isset($values[$value])
               ? $value
                 : MVentory_TradeMe_Model_Config::FEES_NO;
    }

    $value = strtolower($value);

    if (stripos($value, 'always') !== false)
      return MVentory_TradeMe_Model_Config::FEES_ALWAYS;

    if (stripos($value, 'special') !== false)
      return MVentory_TradeMe_Model_Config::FEES_SPECIAL;

    return MVentory_TradeMe_Model_Config::FEES_NO;
  }

  /**
   * Parse string with shipping options in following format
   *
   *   <price>,<method>\r\n
   *   ...
   *   <price>,<method>
   *
   * to a list with shipping options in following format
   *
   *   array(
   *     array(
   *       'price' => 12.5,
   *       'method' => 'Name of shipping method'
   *     ),
   *     ...
   *   )
   *
   * @param string $value Shipping options
   * @return array
   */
  protected function _parseShippingOptionsValue ($value) {
    $options = array();

    if (!$value = trim($value))
      return $options;

    foreach (explode("\n", str_replace("\r\n", "\n", $value)) as $opt)
      if (count($opt = explode(',', trim($opt, " ,\t\n\r\0\x0B"), 2)) == 2)
        $options[] = array(
          'price' => (float) rtrim($opt[0]),
          'method' => ltrim($opt[1])
        );

    return $options;
  }

  /**
   * Save parsed options in Magento config
   *
   * @param string $value
   * @return bool|float
   */
  protected function _saveImportedOptions ($accounts, $website) {
    $websiteId = $website->getId();
    $config = Mage::getConfig();

    foreach ($accounts as $id => $data)
      $config->saveConfig(
        'trademe/' . $id . '/shipping_types',
        serialize($data),
        'websites',
        $websiteId
      );

    $config->reinit();

    Mage::app()->reinitStores();
  }

  protected function _restructureImportedOptions ($data) {
    $accounts = array();

    foreach ($data as $options) {
      $accountId = $options['account'];
      unset($options['account']);

      $accounts[$accountId][] = $options;
    }

    return $accounts;
  }

  protected function _checkMissingSettings ($accounts, $shippingTypes) {
    $result = array();

    foreach ($accounts as $id => $data) {
      foreach ($data as $settings) {
        if ($settings['shipping_type'] == '*')
          continue 2;

        if ($settings['shipping_type'] == '')
          continue;

        $types[$settings['shipping_type']] = true;
      }

      if (isset($types) && ($missing = array_diff_key($shippingTypes, $types)))
        $result[$id] = $missing;
    }

    return $result;
  }
}
