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

    protected $_url;

    protected $_paymentHelper;

    protected $_transactionRepository;

    protected $request;
    protected $formKey;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\App\Request\Http $request,
        \Saulmoralespa\PayuLatam\Helper\Data $helperData,
        PaymentHelper $paymentHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
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
    }

    public function execute()
    {
        $request = $this->getRequest();
        $params = $request->getParams();

        if (empty($params))
            return;

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
            $payment->getId(),
            $payment->getOrder()->getId()
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

            $invoice = $objectManager->create('Magento\Sales\Model\Service\InvoiceService')->prepareInvoice($order);
            $invoice = $invoice->setTransactionId($transactionId)
                ->addComment(__("Invoice created"))
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

            $message = __("transaction ID:%1", $transactionId);
            $payment->addTransactionCommentsToOrder($transaction, $message);
            //$transaction->save();
            $order->save();
        }
    }
}