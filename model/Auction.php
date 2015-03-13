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
 */

/**
 * Auction model
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Auction extends Mage_Core_Model_Abstract
{

  /**
   * Model of assigned product
   *
   * @var Mage_Catalog_Model_Product
   */
  protected $_product = null;

  /**
   * Initialise resources
   */
  protected function _construct () {
    $this->_init('trademe/auction');
  }

  /**
   * Load auction data by product
   *
   * @param mixed $product
   * @return MVentory_TradeMe_Model_Auction
   */
  public function loadByProduct ($product) {
    $this
      ->_getResource()
      ->loadByProductId(
          $this,
          $product instanceof Mage_Catalog_Model_Product
            ? $product->getId()
            : $product
        );

    return $this->setOrigData();
  }

  /**
   * Assign product to auction
   *
   * @param  Mage_Catalog_Model_Product $product Product
   * @return MVentory_TradeMe_Model_Auction
   */
  public function assignProduct ($product) {
    $this->_product = $product;

    return $this;
  }

  /**
   * Return URL to auction
   *
   * @param Mage_Core_Model_Website $website Website
   * @return string Auction URL
   */
  public function getUrl ($website = null) {
    if (!$website)
      $website = $this->_getWebsite();

    return
      ($id = $this->_data['listing_id'])
        ? 'http://www.'
          . (Mage::helper('trademe')->isSandboxMode($website)
               ? 'tmsandbox'
               : 'trademe'
            )
          . '.co.nz/Browse/Listing.aspx?id='
          . $id
        : '';
  }

  /**
   * Withdraw auction
   *
   * @return MVentory_TradeMe_Model_Auction
   */
  public function withdraw () {
    //!!!TODO: implement withdrawal by listing ID only
    if (!$this['product_id'])
      return $this;

    //Withdrawal for auctions with fixed end date ($1 auctions) are temporarily
    //disabled
    if ($this['type'] == MVentory_TradeMe_Model_Config::AUCTION_FIXED_END_DATE)
      return $this;

    $product = Mage::getModel('catalog/product')->load($this['product_id']);

    if (!$product->getId())
      throw new Mage_Core_Exception('Can\'t load product');

    $this->assignProduct($product);

    $connector = new MVentory_TradeMe_Model_Api();

    $result = $connector
      ->setWebsiteId($this->_getWebsite())
      ->remove($this);

    if ($result !== true)
      throw new Mage_Core_Exception($result);

    return $this;
  }

  /**
   * Return current website.
   * If MVentory API extension is installed it returns website of assigned
   * product
   *
   * @return Mage_Core_Model_Website Website
   */
  protected function _getWebsite () {
    return $this->_product
             ? Mage::helper('mventory/product')->getWebsite($this->_product)
             : Mage::app()->getWebsite();
  }
}
