<?php
namespace Codemakers\SoPay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'sopay';

    protected $_assetRepo;

    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) {
        $this->_assetRepo = $assetRepo;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'redirectUrl' => 'sopay/payment/redirect',
                    'paymentLogoSrc' => $this->_assetRepo->getUrl("Codemakers_SoPay::images/sopay-logo.png")
                ]
            ]
        ];
    }
}
