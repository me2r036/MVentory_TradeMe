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
 */

/**
 * TradeMe Account exception
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

class MVentory_TradeMe_AccountException extends MVentory_TradeMe_Exception
{
  /**
   * Constructor
   *
   * @param array $account
   *   Account data
   *
   * @param string $msg
   *   Additional message
   */
  public function __construct ($account = null, $msg = '') {
    if ($account)
      $msg = 'Account: '
             . (isset($account['name']) ? $account['name'] : 'UNKNOWN')
             . '. '
             . $msg;

    parent::__construct($msg);
  }
}
