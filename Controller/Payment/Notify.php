<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 12:38 PM
 */

namespace Saulmoralespa\PayuLatam\Controller\Payment;

use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\OrderRepository;

class Notify  extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Saulmoralespa\PayuLatam\Helper\Data
     */
    protected $_helperData;
    /**
     * @var
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;
    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;
    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $_transactionRepository;
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
    protected $_orderRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\App\Request\Http $request,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        Order $order,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        OrderRepository $orderRepository
    )
    {
        parent::__construct($context);
        $this->_helperData = $helperData;
        $this->_paymentHelper = $paymentHelper;
        $this->_url = $context->getUrl();
        $this->_transactionRepository = $transactionRepository;
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


        $referenceCode = $request->getParam('reference_sale');
        $statusTransaction = $request->getParam('state_pol');
        $signaturePayuLatam = $request->getParam('sign');
        $value = $request->getParam('value');
        $transactionId = $request->getParam('transaction_id');

        $amount = $methodInstance->formattedAmount($value, 1);

        $dataSign = [
            'referenceCode' =>  $referenceCode,
            'amount' =>  $amount,
            'currency' => $order->getOrderCurrencyCode(),
            'state_pol' => $statusTransaction
        ];

        $signatureOrder = $methodInstance->getSignValidate($dataSign);


        if ($signatureOrder !== $signaturePayuLatam)
            return;

        $payment = $order->getPayment();

        $statuses = $this->_helperData->getOrderStates();

        $pendingOrder = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $failedOrder = \Magento\Sales\Model\Order::STATE_CANCELED;
        $aprovvedOrder =  \Magento\Sales\Model\Order::STATE_PROCESSING;

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId()
        );

        if ($order->getState() === $pendingOrder && $statusTransaction === '7'){
            return;
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
            //$transaction->close();
            $order->cancel()->save();
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
                __('Invoice #%1.', $invoice->getId())
            )
                ->setIsCustomerNotified(true);

            $message = __("transaction ID:%1", $transactionId);
            $payment->addTransactionCommentsToOrder($transaction, $message);
            //$transaction->save();
            $order->save();
        }
    }
}
