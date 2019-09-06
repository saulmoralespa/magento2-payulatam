<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 07:47 AM
 */

namespace Saulmoralespa\PayuLatam\Model;

class PayuLatam extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'payulatam';

    protected $_code = self::CODE;

    protected $_isGateway = true;

    protected $_canOrder = true;

    protected $_canAuthorize = true;

    protected $_canCapture = true;

    protected $_canCapturePartial = true;

    protected $_canRefund = false;

    protected $_canRefundInvoicePartial = false;

    protected $_canVoid = true;

    protected $_canFetchTransactionInfo = true;

    protected $_canReviewPayment = true;

    protected $_supportedCurrencyCodes = array('ARS','BRL','CLP','COP','MXN','PEN','USD');

    protected $_helperData;

    public function __construct(
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_helperData = $helperData;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        $payuLatamActive = $this->_helperData->getActive();
        if ($payuLatamActive) return true;
        return false;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && (
                $quote->getBaseGrandTotal() < $this->_helperData->getMinOrderTotal()
                || ($this->_helperData->getMaxOrderTotal() && $quote->getBaseGrandTotal() > $this->_helperData->getMaxOrderTotal()))
        ) {
            return false;
        }

        if (!$this->_helperData->getMerchantId() ||
            !$this->_helperData->getAccountId() ||
            !$this->_helperData->getApiKey() ||
            !$this->_helperData->getApiLogin()){
            return false;
        }

        return true;
    }

    /**
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }


    public function getAmount($order)
    {

        $amount = $this->formattedAmount($order->getGrandTotal());
        return $amount;
    }


    public function formattedAmount($amount, $decimals = 2)
    {
        $amount = number_format($amount, $decimals,'.','');
        return $amount;
    }


    public function getSignCreate(array $data = [])
    {
        return md5(
            $this->_helperData->getApiKey() . "~" .
            $this->_helperData->getMerchantId() . "~" .
            $data['referenceCode'] ."~".
            $data['amount']."~".
            $data['currency']
        );
    }

    public function getSignValidate(array $data = [])
    {
        return md5(
            $this->_helperData->getApiKey() . "~" .
            $this->_helperData->getMerchantId() . "~" .
            $data['referenceCode'] . "~" .
            $data['amount'] . "~" .
            $data['currency'] . "~" .
            $data['state_pol']
        );
    }

}