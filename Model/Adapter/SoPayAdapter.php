<?php
namespace Codemakers\SoPay\Model\Adapter;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;

/**
 * Class SoPayAdapter
 */
class SoPayAdapter
{
    const API_KEY_XML_PATH = 'payment/sopay/apikey';
    const TEST_XML_PATH = 'payment/sopay/test';
    const PAYMENT_DESCRIPTION_XML_PATH = 'payment/sopay/payment_description';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $url;

    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * QuickPayAdapter constructor.
     *
     * @param LoggerInterface $logger
     * @param UrlInterface $url
     * @param ScopeConfigInterface $scopeConfig
     * @param ResolverInterface $resolver
     */
    public function __construct(
        LoggerInterface $logger,
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig,
        ResolverInterface $resolver,
        OrderRepositoryInterface $orderRepository,
        BuilderInterface $transactionBuilder,
        TransactionRepositoryInterface $transactionRepository,
        DirectoryList $dir,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory
    )
    {
        $this->logger = $logger;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->resolver = $resolver;
        $this->orderRepository = $orderRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->dir = $dir;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;

        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($this->dir->getRoot().'/var/log/sopay.log'));
    }

    /**
     * @return string
     */
    public function GatewayLink(){
        $test = $this->scopeConfig->getValue(self::TEST_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if($test){
            return 'https://sandbox.sopayapp.com/';
        }
        return 'https://api.sopayapp.com/';
    }

    /**
     * @return mixed
     */
    public function GatewayAuthenticate(){
        $apiKey = $this->scopeConfig->getValue(self::API_KEY_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gatewayLink = $this->GatewayLink();
        $authentication = ['apiKey' => $apiKey];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $gatewayLink . 'mmmp/api/v1/auth',
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_POSTFIELDS => json_encode($authentication)
        ]);

        $resp = json_decode(curl_exec($curl), true);

        curl_close($curl);

        return $resp;
    }

    /**
     * @param $accessToken
     * @param $orderData
     * @return mixed
     */
    public function GatewayOrderCreate($accessToken, $orderData){
        $gatewayLink = $this->GatewayLink();
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $gatewayLink . 'mmmp/api/v1/payment/weborder/create',
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "Authorization: Bearer " . $accessToken
            ),
            CURLOPT_POSTFIELDS => json_encode($orderData)
        ]);

        $resp = json_decode(curl_exec($curl), true);

        curl_close($curl);

        $this->logger->debug('CREATE ORDER RESPONSE: ');
        $this->logger->debug(print_r($resp, true));

        return $resp;
    }

    /**
     * @param $accessToken
     * @param $externalId
     * @return mixed
     */
    public function GatewayPaymentData($accessToken, $externalId){
        $gatewayLink = $this->GatewayLink();
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $gatewayLink . 'mmmp/api/v1/payment/externalid/' . $externalId,
            CURLOPT_HTTPHEADER => array("Authorization: Bearer " . $accessToken)
        ]);

        $resp = json_decode(curl_exec($curl));

        curl_close($curl);

        $this->logger->debug('GET PAYMENT RESPONSE: '.$externalId);
        $this->logger->debug(print_r($resp, true));

        return $resp;
    }

    /**
     * @param $accessToken
     * @param $postData
     * @return mixed
     */
    public function GatewayRefund($accessToken, $postData){
        $gatewayLink = $this->GatewayLink();
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $gatewayLink . 'mmmp/api/v1/payment/refund',
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "Authorization: Bearer " . $accessToken
            ),
            CURLOPT_POSTFIELDS => json_encode($postData)
        ]);

        $resp = json_decode(curl_exec($curl), true);

        curl_close($curl);

        $this->logger->debug('GET REFUND RESPONSE: ');
        $this->logger->debug(print_r($resp, true));

        return $resp;
    }

    /**
     * create payment link
     *
     * @param array $attributes
     * @return array|bool
     */
    public function CreatePaymentLink($order)
    {
        try {
            $response = [];
            $this->logger->debug('CREATE PAYMENT');

            $resp = $this->GatewayAuthenticate();

            if(isset($resp['token'])) {
                $accessToken = $resp['token'];
                $gatewayLink = $this->GatewayLink();

                $orderData = [
                    'amount' => (float)$order->getTotalDue(),
                    'currency' => 'USD',
                    'externalId' => $order->getIncrementId(),
                    'description' => $this->scopeConfig->getValue(self::PAYMENT_DESCRIPTION_XML_PATH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'callbackUrl' => $this->url->getUrl('sopay/payment/callback'),
                    'returnUrl' => $this->url->getUrl('sopay/payment/returns'),
                    'cancelUrl' => $this->url->getUrl('sopay/payment/cancel')
                ];

                $resp = $this->GatewayOrderCreate($accessToken, $orderData);

                if(isset($resp['order'])){
                    $response['url'] = $gatewayLink."mmmp/checkout/v1/start?order=".$resp['order'];
                } else {
                    $response['message'] = $resp['error'];
                }

            } else {
                $response['message'] = $resp['error'];
            }

            return $response;
        } catch (\Exception $e) {

            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * Refund payment
     *
     * @param array $attributes
     * @return array|bool
     */
    public function refund($order)
    {
        $this->logger->debug("Refund payment");

        try {
            $resp = $this->GatewayAuthenticate();

            if(isset($resp['token'])) {
                $accessToken = $resp['token'];
                $externalId = $order->getIncrementId();

                $resp = $this->GatewayPaymentData($accessToken, $externalId);

                $txnId = $resp->id;
                $externalId = $externalId.$txnId;
                $origin = 'sopay-checkout-sample-php';
                $postData = [
                    'header' => [
                        "origin" => $origin,
                        "retry" => false,
                        "trackingId" => uniqid()
                    ],
                    'body' => [
                        "autoCapture" => true,
                        'externalId' => $externalId,
                        'message' => 'Test refund',
                        'paymentId' => $txnId
                    ]
                ];

                $resp = $this->GatewayRefund($accessToken, $postData);

                return $resp;
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return true;
    }
}
