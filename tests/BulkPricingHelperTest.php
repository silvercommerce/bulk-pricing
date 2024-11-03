<?php

namespace SilverCommerce\BulkPricing\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\OrdersAdmin\Tests\Model\TestProduct;
use SilverCommerce\BulkPricing\Helpers\BulkPricingHelper;
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

    public function testGetPriceModifier()
    {
        $helper = BulkPricingHelper::singleton();
        $bracket = $this->objFromFixture(
            BulkPricingBracket::class,
            'product_one_bk_one'
        );
        $price = 10.00;
        $modified = $this->callNonPublicMethod(
            $helper,
            'getPriceModifier',
            [$bracket, $price]
        );

        $this->assertEquals(5.00, $modified);
    }

    public function testCleanBulkModifiers()
    {

    }

    public function testModifyPrice()
    {

    }

    public function testModifyItemPrice()
    {

    }
}