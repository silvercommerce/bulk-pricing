<?php

namespace SilverCommerce\BulkPricing\Helpers;

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
     * Modify a provided price based on the current bracket settings
     *
     * @param float $price
     *
     * @return float
     */
    protected function getPriceModifier(
        BulkPricingBracket $bracket,
        float $price
    ): float {
        $allow_negative = Config::inst()->get(
            BulkPricingBracket::class,
            'allow_negative'
        );
        $modify = 0;

        if ($bracket->Reduce == true
            && !$allow_negative
            && $bracket->Price > $price
        ) {
            $modify = 0 - $price;
        } elseif ($bracket->Reduce == true) {
            $modify = 0 - $bracket->Price;
        } else {
            $modify = $bracket->Price;
        }

        return $modify;
    }

    protected function cleanBulkModifiers(LineItem $item): void
    {
        $modifiers = PriceModifier::get()
            ->filter('LineItem.ID', $item->ID);

        foreach ($modifiers as $modifier) {
            if ($modifier instanceof BulkPricingBracket) {
                $modifier->remove();
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

        $modify = $this->getPriceModifier(
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