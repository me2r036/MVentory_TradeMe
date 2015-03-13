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
 * Source model for pickup field
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Attribute_Source_Pickup
  extends MVentory_TradeMe_Model_Attribute_Source_WithDefault
{
  /**
   * Generate array of options
   *
   * @return array
   */
  protected function _getOptions () {
    return array(
      MVentory_TradeMe_Model_Config::PICKUP_ALLOW => 'Buyer can pickup',
      MVentory_TradeMe_Model_Config::PICKUP_FORBID => 'No pickups',
    );
  }
}

?>
