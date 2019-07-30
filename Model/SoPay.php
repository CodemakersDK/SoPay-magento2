<?php

namespace Codemakers\SoPay\Model;

class SoPay extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'sopay';

    /**
     * @var string
     */
    protected $_title = 'SoPay';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $adapter = $objectManager->get(\Codemakers\SoPay\Model\Adapter\SoPayAdapter::class);

        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        $response = $adapter->refund($order, $amount);

        if(isset($response['error'])){
            throw new \Magento\Framework\Exception\LocalizedException(__('SoPay refund generation error'));
        }

        return $this;
    }
}
