<?php
/**
 * Created by Andrew Stepanchuk.
 * Date: 08.04.19
 * Time: 14:25
 */

namespace Netzexpert\TableRatesConverter\Model;

use Amasty\ShippingTableRates\Helper\Config as HelperConfig;
use Amasty\ShippingTableRates\Model\ConfigProvider;
use Amasty\ShippingTableRates\Model\Rate as AmastyRate;
use Amasty\ShippingTableRates\Model\Rate\ItemsTotalCalculator;
use Amasty\ShippingTableRates\Model\Rate\ItemValidator;
use Amasty\ShippingTableRates\Model\ResourceModel\Method\CollectionFactory as MethodCollectionFactory;
use Amasty\ShippingTableRates\Model\ResourceModel\Rate\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Quote\Api\Data\CartItemExtensionInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;

class Rate extends AmastyRate
{
    /** @var ProductRepositoryInterface  */
    private $productRepository;

    /** @var HelperConfig  */
    private $helperConfig;




    /**
     * Rate constructor.
     * @param Registry $coreRegistry
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $rateCollectionFactory
     * @param MethodCollectionFactory $methodCollectionFactory
     * @param ConfigProvider $configProvider
     * @param ItemsTotalCalculator $itemsTotalCalculator
     * @param ItemValidator $itemValidator
     * @param HelperConfig $helperConfig
     */
    public function __construct(
        Registry $coreRegistry,
        Context $context,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $rateCollectionFactory,
        MethodCollectionFactory $methodCollectionFactory,
        ConfigProvider $configProvider,
        ItemsTotalCalculator $itemsTotalCalculator,
        ItemValidator $itemValidator,
        HelperConfig $helperConfig
    ) {
        $this->productRepository    = $productRepository;
        $this->helperConfig         = $helperConfig;
        AmastyRate::__construct(
            $coreRegistry,
            $context,
            $productRepository,
            $rateCollectionFactory,
            $methodCollectionFactory,
            $configProvider,
            $itemsTotalCalculator,
            $itemValidator
        );
    }

    /**
     * @param Item $item
     * @return void|null
     */
    protected function getShippingTypes($item)
    {
        /** @var CartItemExtensionInterface $itemExtensions */
        $itemExtensions = $item->getExtensionAttributes();
        $itemExtensions->setParent($item);
        $shipmentType = $itemExtensions->getShipmentType();
        if ($shipmentType) {
            $this->_existingShippingTypes[] = $shipmentType;
            return null;
        } else {
            return parent::getShippingTypes($item);
        }
    }

    /**
     * @param RateRequest $request
     * @param int $ignoreVirtual
     * @param int $allowFreePromo
     * @param int $shippingType
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function calculateTotals($request, $ignoreVirtual, $allowFreePromo, $shippingType)
    {
        $totals = $this->initTotals();

        //reload child items
        $configurableSetting = $this->_scopeConfig->getValue(
            'carriers/amstrates/configurable_child',
            ScopeInterface::SCOPE_STORE
        );
        $bundleSetting = $this->_scopeConfig->getValue(
            'carriers/amstrates/bundle_child',
            ScopeInterface::SCOPE_STORE
        );
        $afterDiscount = $this->_scopeConfig->getValue(
            'carriers/amstrates/after_discount',
            ScopeInterface::SCOPE_STORE
        );
        $includingTax = $this->_scopeConfig->getValue(
            'carriers/amstrates/including_tax',
            ScopeInterface::SCOPE_STORE
        );

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($request->getAllItems() as $item) {
            if ($this->_needSkipItem($item, $ignoreVirtual)) {
                continue;
            }

            $typeId = $item->getProduct()->getTypeId();
            /** @var CartItemExtensionInterface $itemExtensions */
            $itemExtensions = $item->getExtensionAttributes();
            $itemExtensions->setParent($item);
            $itemShipmentType = $itemExtensions->getShipmentType();
            if (!$itemShipmentType) {
                $shipmentType = $item->getProduct()->getShipmentType();
            } else {
                $shipmentType = $itemShipmentType;
            }
            $flagOfPersist = false;

            if (($item->getHasChildren() && $typeId == 'configurable' && $configurableSetting == '0')
                || ($item->getHasChildren() && $typeId == 'bundle' && $bundleSetting == '2')
                || ($item->getHasChildren() && $typeId == 'bundle' && $bundleSetting == '0' && $shipmentType == '1')
            ) {
                $qty = 0;
                $notFreeQty = 0;
                $price = 0;
                $weight = 0;
                $itemQty = 0;

                foreach ($item->getChildren() as $child) {
                    $product = $this->productRepository->getById(
                        $child->getProduct()->getEntityId()
                    );

                    if (($itemShipmentType != $shippingType) && ($shippingType != 0)) {
                        continue;
                    }

                    $flagOfPersist = true;
                    $itemQty = $child->getQty() * $item->getQty();
                    $qty += $itemQty;
                    $notFreeQty += ($itemQty - $this->getFreeQty($child, $allowFreePromo));
                    $price += $child->getPrice() * $itemQty;
                    $weight += $this->calculateWeight($child) * $itemQty;
                    $totals['tax_amount'] += $child->getBaseTaxAmount() + $child->getBaseHiddenTaxAmount()
                        + $item->getWeeeTaxAppliedAmount();
                    $totals['discount_amount'] += $child->getBaseDiscountAmount();
                }

                if ($typeId == 'bundle') {
                    if ($flagOfPersist == false) {
                        continue;
                    }
                    //  $qty        = $item->getQty();

                    if ($item->getProduct()->getWeightType() == 1) {
                        $weight = $item->getWeight();
                    }

                    if ($item->getProduct()->getPriceType() == 1) {
                        $price = $item->getPrice();
                    }

                    if ($item->getProduct()->getSkuType() == 1) {
                        $totals['tax_amount'] += $item->getBaseTaxAmount() + $item->getBaseHiddenTaxAmount()
                            + $item->getWeeeTaxAppliedAmount();
                        $totals['discount_amount'] += $item->getBaseDiscountAmount();
                    }

                    $notFreeQty = ($qty - $this->getFreeQty($item, $allowFreePromo));
                    $totals['qty'] += $qty;
                    $totals['not_free_qty'] += $notFreeQty;
                    $totals['not_free_price'] += $price;
                    $totals['not_free_weight'] += $weight;
                } elseif ($typeId == 'configurable') {
                    if ($flagOfPersist == false) {
                        continue;
                    }

                    $qty = $item->getQty();
                    $price = $item->getPrice();
                    $weight = $this->calculateWeight($item);
                    $notFreeQty = ($qty - $this->getFreeQty($item, $allowFreePromo));
                    $totals['qty'] += $qty;
                    $totals['not_free_qty'] += $notFreeQty;
                    $totals['not_free_price'] += $price * $notFreeQty;
                    $totals['not_free_weight'] += $weight * $notFreeQty;
                    $totals['tax_amount'] += $item->getBaseTaxAmount() + $item->getBaseHiddenTaxAmount()
                        + $item->getWeeeTaxAppliedAmount();
                    $totals['discount_amount'] += $item->getBaseDiscountAmount();
                } else { // for grouped and custom not simple products
                    $qty = $item->getQty();
                    $price = $item->getPrice();
                    $weight = $this->calculateWeight($item);

                    $notFreeQty = ($qty - $this->getFreeQty($item, $allowFreePromo));
                    $totals['qty'] += $qty;
                    $totals['not_free_qty'] += $notFreeQty;
                    $totals['not_free_price'] += $price * $notFreeQty;
                    $totals['not_free_weight'] += $weight * $notFreeQty;
                }
            } else {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productRepository->getById(
                    $item->getProduct()->getEntityId()
                );

                if ($this->_needSkipSimpleItem($product, $shippingType, $item)) {
                    continue;
                }

                $qty = $item->getQty();
                $notFreeQty = ($qty - $this->getFreeQty($item, $allowFreePromo));
                $totals['not_free_price'] += $item->getBasePrice() * $notFreeQty;
                $weight = $this->calculateWeight($item);
                $totals['not_free_weight'] += $weight * $notFreeQty;
                $totals['qty'] += $qty;
                $totals['not_free_qty'] += $notFreeQty;
                $totals['tax_amount'] += $item->getBaseTaxAmount() + $item->getBaseHiddenTaxAmount()
                    + $item->getWeeeTaxAppliedAmount();
                $totals['discount_amount'] += $item->getBaseDiscountAmount();
            }

            // Fix for correct calculation subtotal for shipping method
            if ($afterDiscount || $includingTax) {
                $totals['not_free_price'] += $item->getBaseDiscountTaxCompensationAmount();
            }
        }

        // fix magento bug
        if ($totals['not_free_qty'] > 0) {
            $request->setFreeShipping(false);
        }

        if ($afterDiscount) {
            $totals['not_free_price'] -= $totals['discount_amount'];
        }

        if ($includingTax) {
            $totals['not_free_price'] += $totals['tax_amount'];
        }

        if ($totals['not_free_price'] < 0) {
            $totals['not_free_price'] = 0;
        }

        if ($request->getFreeShipping() && $allowFreePromo) {
            $totals['not_free_price'] = $totals['not_free_weight'] = $totals['not_free_qty'] = 0;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }

    /**
     * The method get value of weight depends on attribute
     * from 'volumetric weight attribute'
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function calculateWeight($item = null)
    {
        $calculatedWeight = $item ? $item->getWeight() : 0;
        $selectedWeightAttributeCodes = $this->helperConfig->getSelectedWeightAttributeCode();

        if (!empty($selectedWeightAttributeCodes)) {
            $productId = $item->getProduct()->getId();
            $volumeWeight = $this->prepareVolumeWeight($productId, $selectedWeightAttributeCodes);
            $volumetricWeight = $this->helperConfig->calculateVolumetricWeightWithShippingFactor($volumeWeight);

            if ((float)$volumetricWeight > (float)$calculatedWeight) {
                $calculatedWeight = $volumetricWeight;
            }
        }

        return $calculatedWeight;
    }

    /**
     * The method gathers attribute from product
     *
     * @param int $productId
     * @param array $selectedWeightAttributeCodes
     *
     * @return float|int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function prepareVolumeWeight($productId = 0, $selectedWeightAttributeCodes = [])
    {
        if (empty($selectedWeightAttributeCodes)) {
            return 0;
        }

        $product = $this->productRepository->getById($productId);
        $weightAttributeCode = array_shift($selectedWeightAttributeCodes);
        $volumeWeight = $product->getData($weightAttributeCode);

        if (!empty($selectedWeightAttributeCodes)) {
            foreach ($selectedWeightAttributeCodes as $attributeCode) {
                $volumeWeight *= (float)$product->getData($attributeCode);
            }
        }

        return $volumeWeight;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param int $shippingType
     * @param \Magento\Quote\Model\Quote\Item $item
     *
     * @return bool
     */
    protected function _needSkipSimpleItem($product, $shippingType, $item)
    {
        $needSkipSimpleItem = false;

        /** @var CartItemExtensionInterface $itemExtensions */
        $itemExtensions = $item->getExtensionAttributes();
        $itemExtensions->setParent($item);
        $itemShipmentType = $itemExtensions->getShipmentType();

        if (($itemShipmentType != $shippingType) && ($shippingType != 0)) {
            $needSkipSimpleItem = true;
        }

        if ($item->getParentItemId()) {
            $needSkipSimpleItem = true;
        }

        return $needSkipSimpleItem;
    }
}
