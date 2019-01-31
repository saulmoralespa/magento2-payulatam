<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 09:36 AM
 */

namespace Saulmoralespa\PayuLatam\Helper;

use Magento\Framework\View\LayoutFactory;

class Data extends \Magento\Payment\Helper\Data
{
    protected $_payuLatamLogger;

    protected $_enviroment;

    public function __construct(
        \Saulmoralespa\PayuLatam\Logger\Logger $payuLatamLogger,
        \Magento\Framework\App\Helper\Context $context,
        LayoutFactory $layoutFactory,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig
    )
    {
        parent::__construct(
            $context,
            $layoutFactory,
            $paymentMethodFactory,
            $appEmulation,
            $paymentConfig,
            $initialConfig
        );

        $this->_payuLatamLogger = $payuLatamLogger;

        $this->_enviroment = (bool)(int)$this->scopeConfig->getValue('payment/payulatam/environment', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

    }

    public function log($message, $array = null)
    {
        if (!is_null($array)) {
            $message .= " - " . json_encode($array);
        }

        $this->_payuLatamLogger->debug($message);
    }

    public function getEnviroment()
    {
        return $this->_enviroment;
    }

    public function getActive()
    {
        return (bool)(int)$this->scopeConfig->getValue('payment/payulatam/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getMerchantId()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/development/merchantId', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/production/merchantId', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getAccountId()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/development/accountId', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/production/accountId', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getApiKey()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/development/apiKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/production/apiKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getApiLogin()
    {
        if ($this->_enviroment){
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/development/apiLogin', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }else{
            return $this->scopeConfig->getValue('payment/payulatam/enviroment_g/production/apiLogin', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        }
    }

    public function getMinOrderTotal()
    {
        return $this->scopeConfig->getValue('payment/payulatam/min_order_total', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getMaxOrderTotal()
    {
        return $this->scopeConfig->getValue('payment/payulatam/max_order_total', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getOrderStates()
    {
        return [
            'pending' => $this->scopeConfig->getValue('payment/payulatam/states/pending', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'approved' => $this->scopeConfig->getValue('payment/payulatam/states/approved', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'rejected' => $this->scopeConfig->getValue('payment/payulatam/states/rejected', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ];
    }

    public function getUrlPayment()
    {
        if ($this->_enviroment)
            return 'https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/';
        return 'https://checkout.payulatam.com/ppp-web-gateway-payu/';
    }
}