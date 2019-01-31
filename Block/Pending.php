<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 02:31 PM
 */

namespace Saulmoralespa\PayuLatam\Block;


class Pending extends \Magento\Framework\View\Element\Template
{
    public function getMessage()
    {
        return __('The status of the order is pending, waiting to process the payment by  payU latam');
    }

    public function getUrlHome()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}