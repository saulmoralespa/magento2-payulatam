<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 06:54 PM
 */

namespace Saulmoralespa\PayuLatam\Controller\Payment;

use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Payment\Helper\Data as PaymentHelper;

class Data extends \Magento\Framework\App\Action\Action
{
    protected $_helperData;

    protected $_checkoutSession;

    protected $_resultJsonFactory;

    protected $_url;

    protected $_transactionBuilder;

    protected $_paymentHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        PaymentHelper $paymentHelper
    )
    {
        parent::__construct($context);

        $this->_checkoutSession = $checkoutSession;
        $this->_helperData = $helperData;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_url = $context->getUrl();
        $this->_transactionBuilder = $transactionBuilder;
        $this->_paymentHelper = $paymentHelper;
    }

    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function execute()
    {

        $order = $this->_getCheckoutSession()->getLastRealOrder();

        $referenceCode  = time();

        $payment = $order->getPayment();
        $payment->setTransactionId($referenceCode)
            ->setIsTransactionClosed(0);

        $payment->setParentTransactionId($order->getId());
        $payment->setIsTransactionPending(true);
        $transaction = $this->_transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->build(Transaction::TYPE_ORDER);

        $payment->addTransactionCommentsToOrder($transaction, __('pending'));
        $statuses = $this->_helperData->getOrderStates();
        $status = $statuses["pending"];
        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $order->setState($state)->setStatus($status);
        $payment->setSkipOrderProcessing(true);
        $order->save();


        $result = $this->_resultJsonFactory->create();
        return $result->setData([
            'data' => $this->getDataParamsPayment($order, $referenceCode)
        ]);
    }


    public function getDataParamsPayment($order, $referenceCode)
    {
        $incrementId = $order->getIncrementId();
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();


        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);

        $addresLine1 = empty($shippingAddress->getStreetLine(1)) ? $billingAddress->getStreet() : $shippingAddress->getStreetLine(1);
        $city = empty($shippingAddress->getCity()) ? $billingAddress->getCity() : $shippingAddress->getCity();
        $country = empty($shippingAddress->getCountryId())  ? $billingAddress->getCountryId() : $shippingAddress->getCountryId();
        $phone = empty($shippingAddress->getTelephone())  ? $billingAddress->getTelephone() : $shippingAddress->getTelephone();
        $currencyCode = $order->getOrderCurrencyCode();
        $amount = $methodInstance->getAmount($order);

        $taxReturnBase = number_format(($order->getGrandTotal() - $order->getTaxAmount()),2,'.','');
        if($order->getTaxAmount() == 0) $taxReturnBase = 0;

        $dataSignCreate = [
            'referenceCode' => $referenceCode,
            'amount' => $amount,
            'currency' => $currencyCode
        ];

        return [
            'action' => $this->_helperData->getUrlPayment(),
            'fields' => [
                'merchantId' => $this->_helperData->getMerchantId(),
                'accountId' => $this->_helperData->getAccountId(),
                'amount' => $amount,
                'description' => __('Order # %1', [$incrementId]) . " ",
                'extra1' => $incrementId,
                'buyerFullName' => $billingAddress->getFirstname(). ' '.$billingAddress->getLastname(),
                'buyerEmail' => $order->getCustomerEmail(),
                'telephone' => $phone,
                'shippingAddress' => $addresLine1,
                'shippingCity' => $city,
                'shippingCountry' => $country,
                'referenceCode' => $referenceCode,
                'currency' => $order->getOrderCurrencyCode(),
                'signature' => $methodInstance->getSignCreate($dataSignCreate),
                'tax' => number_format($order->getTaxAmount(),2,'.',''),
                'taxReturnBase' => $taxReturnBase,
                'responseUrl' => $this->_url->getUrl('payulatam/payment/complete'),
                'confirmationUrl' => $this->_url->getUrl('payulatam/payment/notify'),
                'test' => (int)$this->_helperData->getEnviroment()
            ]
        ];
    }
}