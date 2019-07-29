<?php
/**
 * Created by Andrew Stepanchuk.
 * Date: 26.07.19
 * Time: 14:38
 */

namespace Netzexpert\TableRatesConverter\Plugin\Model\Rate;

use Amasty\ShippingTableRates\Model\Rate\ItemValidator;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item;
use Netzexpert\ProductConfigurator\Api\ConfiguratorOptionRepositoryInterface;
use Netzexpert\ProductConfigurator\Model\Product\Type\Configurator;
use Psr\Log\LoggerInterface;

class ItemValidatorPlugin
{
    /** @var ConfiguratorOptionRepositoryInterface  */
    private $optionRepository;

    /** @var Config  */
    private $eavConfig;

    /** @var LoggerInterface  */
    private $logger;

    /**
     * ItemValidatorPlugin constructor.
     * @param ConfiguratorOptionRepositoryInterface $optionRepository
     * @param Config $eavConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfiguratorOptionRepositoryInterface $optionRepository,
        Config $eavConfig,
        LoggerInterface $logger
    ) {
        $this->optionRepository = $optionRepository;
        $this->eavConfig        = $eavConfig;
        $this->logger           = $logger;
    }

    /**
     * @param ItemValidator $itemValidator
     * @param callable $proceed
     * @param Item $item
     * @param int $shippingType
     * @return bool
     */
    public function aroundIsSippingTypeValid(
        ItemValidator $itemValidator,
        callable $proceed,
        Item $item,
        int $shippingType
    ) {
        try {
            $option = $this->optionRepository->getByCode('am_shipping_type');
            $itemOptionCode = Configurator::CONFIGURATOR_OPTION_PREFIX . $option->getId();
            $itemOption = $item->getOptionByCode($itemOptionCode);
            if (!$itemOption) {
                return $proceed($item, $shippingType);
            }
            $attribute = $this->eavConfig->getAttribute(
                Product::ENTITY,
                'am_shipping_type'
            );
            $itemShippingType = (int)$attribute->getSource()->getOptionId($itemOption->getValue());
            return $shippingType == 0
                || $itemShippingType == $shippingType;
        } catch (NoSuchEntityException $exception) {
            $this->logger->error($exception->getMessage());
            return $proceed($item, $shippingType);
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            return $proceed($item, $shippingType);
        }
    }
}
