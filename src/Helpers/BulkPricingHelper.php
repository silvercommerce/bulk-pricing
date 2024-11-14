<?php

namespace SilverCommerce\BulkPricing\Helpers;

use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use SilverCommerce\BulkPricing\Model\BulkPricingBracket;
use SilverStripe\Core\Injector\Injectable;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\OrdersAdmin\Factory\LineItemFactory;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\OrdersAdmin\Interfaces\LineItemPricable;
use SilverCommerce\OrdersAdmin\Model\PriceModifier;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;
use SilverStripe\Core\Config\Config;

class BulkPricingHelper implements LineItemPricable
{
    use Injectable;

    /**
     * Find how much to modify the base price
     * based on the current bracket
     *
     * @param float $price
     *
     * @return float
     */
    protected function getModificationAmount(
        BulkPricingBracket $bracket,
        float $price
    ): float {
        $modify = 0;

        // If we are to reduce the price, pass the reduction amount,
        // else pass a modifier generated from the new price from the
        // bracket
        if ($bracket->Reduce == true) {
            $modify = 0 - $bracket->Price;
        } else {
            $modify = 0 - $price + $bracket->Price;
        }

        return $modify;
    }

    protected function cleanBulkModifiers(LineItem $item): void
    {
        $modifiers = PriceModifier::get()
            ->filter('LineItem.ID', $item->ID);

        foreach ($modifiers as $modifier) {
            if ($modifier->RelatedObject() instanceof BulkPricingBracket) {
                $modifier->delete();
            }
        }

        return;
    }

    protected function modifyItem(LineItemFactory $factory): LineItem
    {
        /** @var LineItem */
        $item = $factory->getItem();

        $qty = $item->Quantity;
        /** @var CatalogueProduct */
        $product = $item->FindStockItem();

        if (empty($product) || !$product->hasMethod('getValidPricingBrackets')) {
            return $item;
        }

        $this->cleanBulkModifiers($item);

        $bracket = $product
            ->getValidPricingBrackets($qty)
            ->first();

        if (empty($bracket)) {
            return $item;
        }

        $modify = $this->getModificationAmount(
            $bracket,
            $product->getBasePrice()
        );

        $factory->modifyPrice(
            $bracket->Name,
            $modify,
            $bracket
        );

        return $item;
    }

    public function modifyItemPrice(
        LineItemFactory $factory,
        array $data = []
    ): void {
        $curr_item = $factory->getItem();

        // First, modify main item
        $this->modifyItem($factory);

        // Next, collect all items on this order and determine any
        // BulkPricingGroups that are relevent
        $cart = ShoppingCartFactory::create();

        foreach ($cart->getOrder()->Items() as $item) {

            // Skip current item
            if ($item->ID == $curr_item->ID) {
                continue;
            }

            $next_factory = LineItemFactory::create()
                ->setItem($item, false);

            $item = $this->modifyItem($next_factory);
        }

        return;
    }
}