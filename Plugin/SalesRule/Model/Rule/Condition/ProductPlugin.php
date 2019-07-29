<?php
/**
 * Created by Andrew Stepanchuk.
 * Date: 29.07.19
 * Time: 9:18
 */

namespace Netzexpert\TableRatesConverter\Plugin\SalesRule\Model\Rule\Condition;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\AttributeRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\Rule\Condition\Product;
use Netzexpert\ProductConfigurator\Api\ConfiguratorOptionRepositoryInterface;
use Netzexpert\ProductConfigurator\Model\Product\Type\Configurator;
use Psr\Log\LoggerInterface;

class ProductPlugin
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
     * @param Product $subject
     * @param AbstractModel $model
     * @return array
     */
    public function beforeValidate(
        Product $subject,
        AbstractModel $model
    ) {
        try {
            $option = $this->optionRepository->getByCode('am_shipping_type');
            /** @var Attribute $attribute */
            $attribute = $this->attributeRepository->get(
                'catalog_product',
                'am_shipping_type'
            );
            /** @var Item $item */
            $itemOptionCode = Configurator::CONFIGURATOR_OPTION_PREFIX . $option->getId();
            $itemOption = $model->getOptionByCode($itemOptionCode);
            if (!$itemOption) {
                return [$model];
            }
            $shippingType =  $attribute->getSource()->getOptionId($itemOption->getValue());
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $model->getProduct();
            $product->setData('am_shipping_type', $shippingType);
            $model->setProduct($product);

            return [$model];
        } catch (NoSuchEntityException $exception) {
            $this->logger->error($exception->getMessage());
            return [$model];
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            return [$model];
        }
    }
}
