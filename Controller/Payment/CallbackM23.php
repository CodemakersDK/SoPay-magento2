<?php

namespace Codemakers\SoPay\Controller\Payment;

use Magento\Sales\Model\Order;
use Zend\Json\Json;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Callback extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    const PUBLIC_KEY_XML_PATH = 'payment/sopay/apikey';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $order;

    /**
     * @var Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var QuickPay\Gateway\Model\Adapter\QuickPayAdapter
     */
    protected $adapter;

    /**
     * @var Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * Class constructor
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Codemakers\SoPay\Model\Adapter\SoPayAdapter $adapter,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\App\Filesystem\DirectoryList $dir
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->dir = $dir;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->adapter = $adapter;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;

        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($this->dir->getRoot().'/var/log/quickpay.log'));

        parent::__construct($context);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ? bool
    {
        return true;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    /**
     * Handle callback from QuickPay
     *
     * @return string
     */
    public function execute()
    {
        $body = $this->getRequest()->getContent();
        $this->logger->debug('CALLBACK RESPONSE:');
        $this->logger->debug($body);
        try {
            $response = json_decode($body);

            $externalId = $response->order->externalId;

            $resp = $this->adapter->GatewayAuthenticate();

            if(isset($resp['token'])) {
                $accessToken = $resp['token'];

                $resp = $this->adapter->GatewayPaymentData($accessToken, $externalId);

                if($resp->status == 'CAPT'){
                    $order = $this->order->loadByIncrementId($externalId);

                    //Set order to processing
                    $stateProcessing = \Magento\Sales\Model\Order::STATE_PROCESSING;

                    if ($order->getState() !== $stateProcessing) {
                        $order->setState($stateProcessing)
                            ->setStatus($order->getConfig()->getStateDefaultStatus($stateProcessing))
                            ->save();
                    }

                    $invoice = $this->invoiceService->prepareInvoice($order);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);

                    //SEND INVOICE EMAIL
                    $this->invoiceSender->send($invoice);

                    $transactionSave = $this->_objectManager->create(
                        \Magento\Framework\DB\Transaction::class
                    )->addObject(
                        $invoice
                    )->addObject(
                        $invoice->getOrder()
                    );
                    $transactionSave->save();
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Send order confirmation email
     *
     * @param \Magento\Sales\Model\Order $order
     */
    private function sendOrderConfirmation($order)
    {
        try {
            $this->orderSender->send($order);
            $order->addStatusHistoryComment(__('Order confirmation email sent to customer'))
                ->setIsCustomerNotified(true)
                ->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment(__('Failed to send order confirmation email: %s', $e->getMessage()))
                ->setIsCustomerNotified(false)
                ->save();
        }
    }
}
