<?php
/**
 * Created by Andrew Stepanchuk.
 * Date: 28.06.19
 * Time: 11:03
 */

namespace Netzexpert\TableRatesConverter\Plugin\Block\Onepage;

use Amasty\ShippingTableRates\Block\Onepage\LayoutProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;

class LayoutProcessorPlugin
{

    /** @var Manager  */
    private $moduleManager;

    /** @var ScopeConfigInterface  */
    private $scopeConfig;

    /**
     * LayoutProcessorPlugin constructor.
     * @param Manager $moduleManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Manager $moduleManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->moduleManager    = $moduleManager;
        $this->scopeConfig      = $scopeConfig;
    }

    /**
     * @param LayoutProcessor $layoutProcessor
     * @param callable $proceed
     * @param array $jsLayout
     * @return array
     */
    public function aroundProcess(LayoutProcessor $layoutProcessor, callable $proceed, $jsLayout)
    {
        if ($this->isCompatibleCheckout()) {
            return $proceed($jsLayout);
        }
        return $jsLayout;
    }

    /**
     * Check checkout is compatible with Table Rates
     *
     * @return bool
     */
    private function isCompatibleCheckout()
    {
        return !($this->moduleManager->isEnabled('MageArray_StorePickup')
            || $this->scopeConfig->getValue('storepickup/general/enable'));
    }
}
