<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 12:38 PM
 */

namespace Saulmoralespa\PayuLatam\Controller\Payment;

use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Sales\Model\OrderRepository;

class Complete extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Saulmoralespa\PayuLatam\Helper\Data
     */
    protected $_helperData;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Saulmoralespa\PayuLatam\Logger\Logger
     */
    protected $_payuLatamLogger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;
    /**
     * @var \Magento\Framework\Data\Form\FormKey
     */
    protected $formKey;
    /**
     * @var Order
     */
    protected $_order;
    /**
     * @var InvoiceService
     */
    protected $_invoiceService;
    /**
     * @var DbTransaction
     */
    protected $_dbTransaction;
    /**
     * @var OrderRepository
     */
    private $_orderRepository;

    public function __construct(
        \Saulmoralespa\PayuLatam\Logger\Logger $payuLatamLogger,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        Order $order,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        OrderRepository $orderRepository
    )
    {
        parent::__construct($context);

        $this->_scopeConfig = $scopeConfig;
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_helperData = $helperData;
        $this->_paymentHelper = $paymentHelper;
        $this->_transactionRepository = $transactionRepository;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_payuLatamLogger = $payuLatamLogger;
        $this->_url = $context->getUrl();
        $this->request = $request;
        $this->formKey = $formKey;
        $this->request->setParam('form_key', $this->formKey->getFormKey());
        $this->_order = $order;
        $this->_invoiceService = $invoiceService;
        $this->_dbTransaction = $dbTransaction;
        $this->_orderRepository = $orderRepository;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params)) return;

        $orderId = $request->getParam('extra1');

        /* @var Order $order */
        $order = $this->_orderRepository->get($orderId);
        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);

        $referenceCode = $request->getParam('referenceCode');
        $statusTransaction = $request->getParam('transactionState');
        $signaturePayuLatam = $request->getParam('signature');
        $value = $request->getParam('TX_VALUE');
        $amount = $methodInstance->formattedAmount($value, 1);
        $transactionId = $request->getParam('transactionId');

        $dataSign = [
          'referenceCode' =>  $referenceCode,
          'amount' =>  $amount,
          'currency' => $order->getOrderCurrencyCode(),
          'state_pol' => $statusTransaction
        ];

        $signatureOrder = $methodInstance->getSignValidate($dataSign);

        if ($signatureOrder !== $signaturePayuLatam){
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }

        $payment = $order->getPayment();

        $statuses = $this->_helperData->getOrderStates();

        $pendingOrder = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $failedOrder = \Magento\Sales\Model\Order::STATE_CANCELED;
        $aprovvedOrder =  \Magento\Sales\Model\Order::STATE_PROCESSING;

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId()
        );

        $pathRedirect = "checkout/onepage/success";

        if ($order->getState() === $pendingOrder && $statusTransaction === '7'){
            $pathRedirect = "payulatam/payment/pending";
        }elseif ($order->getState() === $pendingOrder && $statusTransaction !== '7'  && $statusTransaction !== '4' ){
            $payment->setIsTransactionClosed(1);
            $payment->setIsTransactionPending(false);
            $payment->setIsTransactionDenied(true);
            $status = $statuses["rejected"];
            $state = $failedOrder;
            $order->setState($state)->setStatus($status);
            $payment->setSkipOrderProcessing(true);
            $payment->setIsTransactionDenied(true);
            $message = $request->getParam('message');
            $payment->addTransactionCommentsToOrder($transaction, $message);
            $order->cancel()->save();
            $pathRedirect = "checkout/onepage/failure";
        }elseif ($order->getState() === $pendingOrder && $statusTransaction === '4'){
            $payment->setIsTransactionClosed(1);
            $payment->setIsTransactionPending(false);
            $payment->setIsTransactionApproved(true);
            $payment->setSkipOrderProcessing(false);

            $status = $statuses["approved"];

            $order->setState($aprovvedOrder)->setStatus($status);

            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice = $invoice->setTransactionId($transactionId)
                ->addComment(__("Invoice created"))
                ->register()
                ->pay()
                ->save();

            // Save the invoice to the order
            $this->_dbTransaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $order->addStatusHistoryComment(
                __('Invoice #%1', $invoice->getId())
            )
                ->setIsCustomerNotified(true);

            $message = __("transaction ID:%1", $transactionId);
            $payment->addTransactionCommentsToOrder($transaction, $message);
            $order->save();
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($pathRedirect);
        return $resultRedirect;
    }
}
