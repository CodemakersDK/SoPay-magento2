<?php

namespace Codemakers\SoPay\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Returns extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
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
     * Redirect to to checkout success
     *
     * @return void
     */
    public function execute()
    {
        if ($this->_getCheckout()->getLastRealOrderId()) {
            $this->_redirect('checkout/onepage/success');
        }
    }
}