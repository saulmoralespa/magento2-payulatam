<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 12:38 PM
 */

namespace Saulmoralespa\PayuLatam\Controller\Payment;

use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction;

class Notify  extends \Magento\Framework\App\Action\Action
{
    protected $_helperData;

    protected $_checkoutSession;

    protected $_resultPageFactory;

    protected $_url;

    protected $_paymentHelper;

    protected $_transactionRepository;

    protected $_payuLatamLogger;

    public function __construct(
        \Saulmoralespa\PayuLatam\Logger\Logger $payuLatamLogger,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
    )
    {
        parent::__construct($context);

        $this->_checkoutSession = $checkoutSession;
        $this->_helperData = $helperData;
        $this->_paymentHelper = $paymentHelper;
        $this->_url = $context->getUrl();
        $this->_transactionRepository = $transactionRepository;
        $this->_payuLatamLogger = $payuLatamLogger;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params))
            exit;

        $this->_payuLatamLogger->debug('notify params: ' . print_r($params), true());

        $order_id = $request->getParam('extra1');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order_model = $objectManager->get('Magento\Sales\Model\Order');
        $order = $order_model->load($order_id);

        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->_paymentHelper->getMethodInstance($method);


        $referenceCode = $request->getParam('reference_sale');
        $statusTransaction = $request->getParam('state_pol');
        $signaturePayuLatam = $request->getParam('sign');
        $value = $request->getParam('value');
        $transactionId = $request->getParam('transactionId');

        $amount = $methodInstance->formattedAmount($value, 1);

        $dataSign = [
            'referenceCode' =>  $referenceCode,
            'amount' =>  $amount,
            'currency' => $order->getOrderCurrencyCode(),
            'state_pol' => $statusTransaction
        ];

        $signatureOrder = $methodInstance->getSignValidate($dataSign);


        if ($signatureOrder !== $signaturePayuLatam)
            exit;

        $payment = $order->getPayment();

        $statuses = $this->_helperData->getOrderStates();

        $pendingOrder = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        $failedOrder = \Magento\Sales\Model\Order::STATE_CANCELED;
        $aprovvedOrder =  \Magento\Sales\Model\Order::STATE_PROCESSING;

        $transaction = $this->_transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if ($order->getState() === $pendingOrder && $statusTransaction === '7'){;
            exit;
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

            $status = $statuses["approved"];
            $state = $aprovvedOrder;

            $order->setState($state)->setStatus($status);
            $payment->setSkipOrderProcessing(true);

            $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
            $invoice = $invoice->setTransactionId($transactionId)
                ->addComment("Invoice created.")
                ->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register()
                ->pay();
            $invoice->save();

            // Save the invoice to the order
            $transactionInvoice = $this->_objectManager->create('Magento\Framework\DB\Transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionInvoice->save();

            $order->addStatusHistoryComment(
                __('Invoice #%1.', $invoice->getId())
            )
                ->setIsCustomerNotified(true);

            $message = $request->getParam('message') . " transaction_id: $transactionId" ;
            $payment->addTransactionCommentsToOrder($transaction, $message);
            //$transaction->save();
            $order->save();
        }
    }
}