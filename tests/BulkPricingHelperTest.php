<?php

namespace SilverCommerce\BulkPricing\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\OrdersAdmin\Model\PriceModifier;
use SilverCommerce\OrdersAdmin\Factory\LineItemFactory;
use SilverCommerce\OrdersAdmin\Tests\Model\TestProduct;
use SilverCommerce\BulkPricing\Model\BulkPricingBracket;
use SilverCommerce\BulkPricing\Helpers\BulkPricingHelper;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use SilverCommerce\OrdersAdmin\Tests\Model\TestCustomisation;
use SilverCommerce\OrdersAdmin\Tests\Model\TestCustomisationOption;

class BulkPricingHelperTest extends SapphireTest
{
    protected static $fixture_file = 'BulkPricing.yml';

    public function setUp(): void
    {
        // Ensure we setup a session and the current request
        $request = new HTTPRequest('GET', '/');
        $session = new Session(null);
        $session->init($request);
        $request->setSession($session);
        Injector::inst()
            ->registerService($request, HTTPRequest::class);

        parent::setUp();
    }

    /**
     * Call a private/protected method from a class
     * 
     * @param Object $obj  Base object
     * @param string $name Method name to call
     * @param array  $args Optional arguments
     * 
     * @return mixed
     */
    protected function callNonPublicMethod($obj, $name, array $args = null)
    {
        $ref = new \ReflectionClass($obj);
        $method = $ref->getMethod($name);
        $method->setAccessible(true);

        if (isset($args)) {
            return $method->invokeArgs($obj, $args);
        } else {
            return $method->invoke($obj);
        }
    }

    public function testGetModificationAmount()
    {
        $curr_negative = Config::inst()->get(
            BulkPricingBracket::class,
            'allow_negative'
        );

        $helper = BulkPricingHelper::singleton();
        $price = 10.00;

        $bracket = $this->objFromFixture(
            BulkPricingBracket::class,
            'product_one_bk_one'
        );
        $modified = $this->callNonPublicMethod(
            $helper,
            'getModificationAmount',
            [$bracket, $price]
        );

        $this->assertEquals(-6.00, $modified);

        $price = 5.00;

        $bracket = $this->objFromFixture(
            BulkPricingBracket::class,
            'product_one_bk_two'
        );
        $modified = $this->callNonPublicMethod(
            $helper,
            'getModificationAmount',
            [$bracket, $price]
        );

        $this->assertEquals(-2.00, $modified);

        $bracket = $this->objFromFixture(
            BulkPricingBracket::class,
            'cat_one_bk_two'
        );
        $modified = $this->callNonPublicMethod(
            $helper,
            'getModificationAmount',
            [$bracket, $price]
        );

        $this->assertEquals(-6.00, $modified);
    }

    public function testCleanBulkModifiers()
    {
        $helper = BulkPricingHelper::singleton();

        $item = LineItem::create();
        $item->write();
        $item_id = $item->ID;

        $bracket = BulkPricingBracket::create();
        $bracket->write();

        $modifier = PriceModifier::create();
        $modifier->LineItemID = $item->ID;
        $modifier->RelatedObjectID = $bracket->ID;
        $modifier->RelatedObjectClass = $bracket->ClassName;
        $modifier->write();

        $this->callNonPublicMethod(
            $helper,
            'cleanBulkModifiers',
            [$item]
        );

        $item = LineItem::get()->byID($item_id);

        $this->assertEquals(0, $item->PriceModifications()->count());

        $modifier = PriceModifier::create();
        $modifier->LineItemID = $item->ID;
        $modifier->write();

        $this->callNonPublicMethod(
            $helper,
            'cleanBulkModifiers',
            [$item]
        );

        $item = LineItem::get()->byID($item_id);

        $this->assertEquals(1, $item->PriceModifications()->count());
    }

    public function testModifyItem()
    {
        $item = $this->objFromFixture(
            LineItem::class,
            'line_item_one'
        );

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(5.00, $item->getNoTaxPrice());
        $this->assertEquals(0, $item->PriceModifications()->count());

        $item->Quantity = 6;
        $item->write();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(4.00, $item->getNoTaxPrice());
        $this->assertEquals(1, $item->PriceModifications()->count());

        $item = $this->objFromFixture(
            LineItem::class,
            'line_item_five'
        );

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(12.00, $item->getNoTaxPrice());
        $this->assertEquals(0, $item->PriceModifications()->count());

        $item->Quantity = 4;
        $item->write();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(11.00, $item->getNoTaxPrice());
        $this->assertEquals(1, $item->PriceModifications()->count());

        $item->Quantity = 6;
        $item->write();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(11.00, $item->getNoTaxPrice());
        $this->assertEquals(1, $item->PriceModifications()->count());

        $item->Quantity = 8;
        $item->write();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::singleton();
        $item = $this->callNonPublicMethod(
            $helper,
            'modifyItem',
            [$factory]
        );

        $this->assertEquals(10.00, $item->getNoTaxPrice());
        $this->assertEquals(1, $item->PriceModifications()->count());
    }

    public function testModifyItemPrice()
    {
        $estimate = $this->objFromFixture(
            Estimate::class,
            'estimate_one'
        );

        $item = $estimate
            ->Items()
            ->first();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::create();
        $helper->modifyItemPrice($factory);

        $item = $estimate
            ->Items()
            ->first();

        $this->assertEquals(12.00, $item->getNoTaxPrice());
        $this->assertEquals(0, $item->PriceModifications()->count());

        $item = $estimate
            ->Items()
            ->last();

        $this->assertEquals(18.00, $item->getNoTaxPrice());
        $this->assertEquals(0, $item->PriceModifications()->count());

        $item = $estimate
            ->Items()
            ->first();

        $item->Quantity = 5;
        $item->write();

        $factory = LineItemFactory::create()
            ->setItem($item);

        $helper = BulkPricingHelper::create();
        $helper->modifyItemPrice($factory);

        $this->assertEquals(11.00, $item->getNoTaxPrice());
        $this->assertEquals(1, $item->PriceModifications()->count());

        $item = $estimate
            ->Items()
            ->last();

        $this->assertEquals(18.00, $item->getNoTaxPrice());
        $this->assertEquals(0, $item->PriceModifications()->count());
    }
}