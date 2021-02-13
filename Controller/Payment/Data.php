<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 06:54 PM
 */

namespace Saulmoralespa\PayuLatam\Controller\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Payment\Helper\Data as PaymentHelper;

class Data extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Saulmoralespa\PayuLatam\Helper\Data
     */
    protected $_helperData;
    /**
     * @var \Saulmoralespa\PayuLatam\Logger\Logger
     */
    protected $_payuLatamLogger;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_resultJsonFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;
    /**
     * @var Transaction\BuilderInterface
     */
    protected $_transactionBuilder;
    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        \Saulmoralespa\PayuLatam\Logger\Logger $payuLatamLogger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        PaymentHelper $paymentHelper
    )
    {
        parent::__construct($context);

        $this->_checkoutSession = $checkoutSession;
        $this->_helperData = $helperData;
        $this->_payuLatamLogger = $payuLatamLogger;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_url = $context->getUrl();
        $this->_transactionBuilder = $transactionBuilder;
        $this->_paymentHelper = $paymentHelper;
    }

    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $referenceCode  = time();

        $payment = $order->getPayment();
        $payment->setTransactionId($referenceCode)
            ->setIsTransactionClosed(0);

        $payment->setParentTransactionId($order->getId());
        $payment->setIsTransactionPending(true);

        /* @var Transaction $transaction */
        $transaction = $this->_transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($payment->getTransactionId())
            ->build(Transaction::TYPE_ORDER);

        $payment->addTransactionCommentsToOrder($transaction, __('Pending payment'));
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


    public function getAddress($order)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if ($billingAddress){
            return $billingAddress;
        }

        return $shippingAddress;

    }

    public function getDataParamsPayment(Order $order, $referenceCode)
    {
        $orderId = $order->getId();

        $address = $this->getAddress($order);

        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);

        $addresLine1 = $address->getData("street");
        $city = $address->getCity();
        $country = $address->getCountryId();
        $phone = $address->getTelephone();
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
                'description' => __('Order # %1', $orderId),
                'extra1' => $orderId,
                'buyerFullName' => $address->getFirstname(). ' ' . $address->getLastname(),
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
