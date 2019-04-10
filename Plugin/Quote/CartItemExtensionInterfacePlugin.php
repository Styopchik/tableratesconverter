<?php
/**
 * Created by Andrew Stepanchuk.
 * Date: 09.04.19
 * Time: 16:10
 */

namespace Netzexpert\TableRatesConverter\Plugin\Quote;

use Magento\Eav\Model\AttributeRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Quote\Api\Data\CartItemExtensionInterface;
use Magento\Quote\Model\Quote\Item;
use Netzexpert\ProductConfigurator\Api\ConfiguratorOptionRepositoryInterface;
use Netzexpert\ProductConfigurator\Model\Product\Type\Configurator;
use Psr\Log\LoggerInterface;

class CartItemExtensionInterfacePlugin
{
    /** @var ConfiguratorOptionRepositoryInterface  */
    private $optionRepository;

    /** @var AttributeRepository  */
    private $attributeRepository;

    /** @var LoggerInterface  */
    private $logger;

    /**
     * CartItemExtensionInterfacePlugin constructor.
     * @param ConfiguratorOptionRepositoryInterface $optionRepository
     * @param AttributeRepository $attributeRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfiguratorOptionRepositoryInterface $optionRepository,
        AttributeRepository $attributeRepository,
        LoggerInterface $logger
    ) {
        $this->optionRepository     = $optionRepository;
        $this->attributeRepository  = $attributeRepository;
        $this->logger               = $logger;
    }

    /**
     * @param CartItemExtensionInterface $itemExtension
     * @param $result
     * @return string|null
     */
    public function afterGetShipmentType(CartItemExtensionInterface $itemExtension, $result)
    {
        try {
            $option = $this->optionRepository->getByCode('am_shipping_type');
            /** @var Attribute $attribute */
            $attribute = $this->attributeRepository->get(
                'catalog_product',
                'am_shipping_type'
            );
            /** @var Item $item */
            $item = $itemExtension->getParent();
            $itemOptionCode = Configurator::CONFIGURATOR_OPTION_PREFIX . $option->getId();
            $itemOption = $item->getOptionByCode($itemOptionCode);
            return $attribute->getSource()->getOptionId($itemOption->getValue());
        } catch (NoSuchEntityException $exception) {
            $this->logger->error($exception->getMessage());
            return $result;
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            return $result;
        }
    }
}
