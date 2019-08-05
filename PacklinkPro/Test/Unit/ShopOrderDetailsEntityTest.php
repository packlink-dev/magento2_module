<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Test\Unit;

use Packlink\PacklinkPro\Entity\ShopOrderDetails;
use PHPUnit\Framework\TestCase;

/**
 * Class ShopOrderDetailsEntityTest
 *
 * @package Packlink\PacklinkPro\Test\Unit
 */
class ShopOrderDetailsEntityTest extends TestCase
{
    /**
     * Tests if all properties within Packlink order details entity are being properly set and returned.
     */
    public function testProperties()
    {
        $orderDetails = $this->getTestOrderDetails();
        $this->validateOrderDetails($orderDetails);
    }

    /**
     * Tests conversion of Packlink order details entity object from array.
     */
    public function testFromArray()
    {
        $orderDetails = ShopOrderDetails::fromArray(
            [
                'orderId' => 5,
                'shipmentReference' => 'DE2019PRO0000309473',
                'dropOffId' => 23,
                'shipmentLabels' => [
                    [
                        'link' => 'test1.dev',
                        'printed' => true,
                        'createTime' => 1554192735,
                    ],
                    [
                        'link' => 'test2.dev',
                        'printed' => false,
                        'createTime' => 1554192735,
                    ],
                ],
                'status' => 'pending',
                'lastStatusUpdateTime' => 1554192735,
                'carrierTrackingNumbers' => $this->getTestTrackingNumbers(),
                'carrierTrackingUrl' => 'https://www.ups.com/track?loc=it_IT&requester=WT/',
                'packlinkShippingPrice' => 12.99,
                'taskId' => 312,
            ]
        );

        $this->validateOrderDetails($orderDetails);
    }

    /**
     * Tests conversion of Packlink order details entity object to array.
     */
    public function testToArray()
    {
        $orderDetails = $this->getTestOrderDetails();
        $orderDetailsArray = $orderDetails->toArray();

        self::assertEquals(5, $orderDetailsArray['orderId']);
        self::assertEquals('DE2019PRO0000309473', $orderDetailsArray['shipmentReference']);
        self::assertEquals(23, $orderDetailsArray['dropOffId']);
        $labels = $orderDetailsArray['shipmentLabels'];
        self::assertCount(2, $labels);
        self::assertThat($labels[0], self::arrayHasKey('link'));
        self::assertEquals('test1.dev', $labels[0]['link']);
        self::assertThat($labels[1], self::arrayHasKey('link'));
        self::assertEquals('test2.dev', $labels[1]['link']);
        self::assertEquals('pending', $orderDetailsArray['status']);
        self::assertEquals(1554192735, $orderDetailsArray['lastStatusUpdateTime']);
        self::assertEquals($this->getTestTrackingNumbers(), $orderDetailsArray['carrierTrackingNumbers']);
        self::assertEquals(
            'https://www.ups.com/track?loc=it_IT&requester=WT/',
            $orderDetailsArray['carrierTrackingUrl']
        );
        self::assertEquals(12.99, $orderDetailsArray['packlinkShippingPrice']);
        self::assertEquals(312, $orderDetailsArray['taskId']);
    }

    /**
     * Returns order details entity with test data properties.
     *
     * @return ShopOrderDetails
     */
    private function getTestOrderDetails()
    {
        $orderDetails = new ShopOrderDetails();

        $orderDetails->setOrderId(5);
        $orderDetails->setShipmentReference('DE2019PRO0000309473');
        $orderDetails->setDropOffId(23);
        $orderDetails->setShipmentLabels(['test1.dev', 'test2.dev']);
        $orderDetails->setShippingStatus('pending', 1554192735);
        $orderDetails->setCarrierTrackingNumbers($this->getTestTrackingNumbers());
        $orderDetails->setCarrierTrackingUrl('https://www.ups.com/track?loc=it_IT&requester=WT/');
        $orderDetails->setPacklinkShippingPrice(12.99);
        $orderDetails->setTaskId(312);

        return $orderDetails;
    }

    /**
     * Validates if values in provided order details object match expected ones.
     *
     * @param ShopOrderDetails $orderDetails Packlink order details entity.
     */
    private function validateOrderDetails(ShopOrderDetails $orderDetails)
    {
        self::assertEquals(5, $orderDetails->getOrderId());
        self::assertEquals('DE2019PRO0000309473', $orderDetails->getShipmentReference());
        self::assertEquals(23, $orderDetails->getDropOffId());
        $labels = $orderDetails->getShipmentLabels();
        self::assertCount(2, $labels);
        self::assertEquals('test1.dev', $labels[0]->getLink());
        self::assertEquals('test2.dev', $labels[1]->getLink());
        self::assertEquals('pending', $orderDetails->getShippingStatus());
        self::assertEquals(1554192735, $orderDetails->getLastStatusUpdateTime()->getTimestamp());
        self::assertEquals($this->getTestTrackingNumbers(), $orderDetails->getCarrierTrackingNumbers());
        self::assertEquals(
            'https://www.ups.com/track?loc=it_IT&requester=WT/',
            $orderDetails->getCarrierTrackingUrl()
        );
        self::assertEquals(12.99, $orderDetails->getPacklinkShippingPrice());
        self::assertEquals(312, $orderDetails->getTaskId());
    }

    /**
     * Returns a set of test tracking numbers.
     *
     * @return array Order tracking numbers.
     */
    private function getTestTrackingNumbers()
    {
        return [
            '1Z204E380338943508',
            '1ZXF38300382722839',
            '1ZW6897XYW00098770',
        ];
    }
}
