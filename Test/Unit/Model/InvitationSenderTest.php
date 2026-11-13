<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Model;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Math\Random;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\ReviewReminder\Helper\Config;
use Tonyapps\ReviewReminder\Model\InvitationSender;
use Tonyapps\ReviewReminder\Model\ResourceModel\Invitation;

class InvitationSenderTest extends TestCase
{
    public function testFailedSendIsCountedInsteadOfMarkedSent(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $config = $this->createMock(Config::class);
        $config->method('enabled')->willReturn(true);
        $config->method('reviewsEnabled')->willReturn(true);
        $config->method('delayDays')->willReturn(7);

        $invitation = $this->createMock(Invitation::class);
        $invitation->method('getDueOrders')->willReturn([['entity_id' => 5, 'token' => 'abc', 'customer_email' => 'a@example.com']]);
        $invitation->expects(self::never())->method('markSent');
        $invitation->expects(self::once())->method('recordFailure')->with(5);

        $orderFactory = $this->getMockBuilder(OrderFactory::class)->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $orderFactory->method('create')->willThrowException(new \RuntimeException('SMTP down'));
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects(self::once())->method('stopEnvironmentEmulation');

        (new InvitationSender(
            $invitation,
            $config,
            $orderFactory,
            $this->createMock(Random::class),
            $storeManager,
            $emulation,
            $this->createMock(TransportBuilder::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Image::class)
        ))->send();
    }

    public function testEmailGetsProductImageAndStoreName(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getFrontendName')->willReturn('Luma');
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getUrl')->willReturn('https://shop/unsubscribe');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $storeManager->method('getStore')->willReturn($store);
        $config = $this->createMock(Config::class);
        $config->method('enabled')->willReturn(true);
        $config->method('reviewsEnabled')->willReturn(true);

        $invitation = $this->createMock(Invitation::class);
        $invitation->method('getDueOrders')->willReturn([[
            'entity_id' => 5, 'token' => 'abc', 'customer_email' => 'a@example.com',
            'customer_firstname' => 'Ann', 'increment_id' => '000000005',
        ]]);
        $invitation->expects(self::once())->method('markSent')->with(5);

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(9);
        $product->method('getProductUrl')->willReturn('https://shop/tank.html');
        $product->method('getStatus')->willReturn(1);
        $product->method('isVisibleInSiteVisibility')->willReturn(true);
        $product->method('getWebsiteIds')->willReturn(['1']);
        $item = $this->createMock(Item::class);
        $item->method('getQtyShipped')->willReturn(1.0);
        $item->method('getProduct')->willReturn($product);
        $item->method('getName')->willReturn('Tank');
        $order = $this->createMock(Order::class);
        $order->method('load')->willReturnSelf();
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $orderFactory = $this->getMockBuilder(OrderFactory::class)->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $orderFactory->method('create')->willReturn($order);

        $image = $this->createMock(Image::class);
        $image->expects(self::once())->method('init')->with($product, 'category_page_grid')->willReturnSelf();
        $image->method('getUrl')->willReturn('https://shop/media/tank.jpg');

        $transportBuilder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $transportBuilder->method($method)->willReturnSelf();
        }
        $transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));
        $transportBuilder->expects(self::once())->method('setTemplateVars')
            ->with(self::callback(static fn(array $vars): bool => $vars['storeName'] === 'Luma'
                && $vars['reviewButtonLabel'] === 'Write a review'
                && $vars['products'] === [[
                    'name' => 'Tank',
                    'url' => 'https://shop/tank.html#review-form',
                    'image' => 'https://shop/media/tank.jpg',
                ]]))
            ->willReturnSelf();

        (new InvitationSender(
            $invitation,
            $config,
            $orderFactory,
            $this->createMock(Random::class),
            $storeManager,
            $this->createMock(Emulation::class),
            $transportBuilder,
            $this->createMock(LoggerInterface::class),
            $image
        ))->send();
    }

    /** @return array<string, array{int, bool, string[]}> */
    public static function unreviewableProducts(): array
    {
        return [
            'disabled' => [2, true, ['1']],
            'not visible individually' => [1, false, ['1']],
            'not in this website' => [1, true, ['2']],
        ];
    }

    /**
     * @dataProvider unreviewableProducts
     * @param string[] $websiteIds
     */
    public function testOrderWithNothingToReviewIsSkippedNotMarkedSent(int $status, bool $visible, array $websiteIds): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $storeManager->method('getStore')->willReturn($store);
        $config = $this->createMock(Config::class);
        $config->method('enabled')->willReturn(true);
        $config->method('reviewsEnabled')->willReturn(true);
        $invitation = $this->createMock(Invitation::class);
        $invitation->method('getDueOrders')->willReturn([['entity_id' => 5, 'token' => 'abc', 'customer_email' => 'a@example.com']]);
        $invitation->expects(self::never())->method('markSent');
        $invitation->expects(self::once())->method('markSkipped')->with(5);

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(9);
        $product->method('getStatus')->willReturn($status);
        $product->method('isVisibleInSiteVisibility')->willReturn($visible);
        $product->method('getWebsiteIds')->willReturn($websiteIds);
        $item = $this->createMock(Item::class);
        $item->method('getQtyShipped')->willReturn(1.0);
        $item->method('getProduct')->willReturn($product);
        $order = $this->createMock(Order::class);
        $order->method('load')->willReturnSelf();
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $orderFactory = $this->getMockBuilder(OrderFactory::class)->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $orderFactory->method('create')->willReturn($order);
        $transportBuilder = $this->createMock(TransportBuilder::class);
        $transportBuilder->expects(self::never())->method('getTransport');

        (new InvitationSender(
            $invitation,
            $config,
            $orderFactory,
            $this->createMock(Random::class),
            $storeManager,
            $this->createMock(Emulation::class),
            $transportBuilder,
            $this->createMock(LoggerInterface::class),
            $this->createMock(Image::class)
        ))->send();
    }

    public function testStoreWithReviewsSwitchedOffIsNotProcessed(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $config = $this->createMock(Config::class);
        $config->method('enabled')->willReturn(true);
        $config->method('reviewsEnabled')->willReturn(false);
        $invitation = $this->createMock(Invitation::class);
        $invitation->expects(self::never())->method('getDueOrders');

        (new InvitationSender(
            $invitation,
            $config,
            $this->getMockBuilder(OrderFactory::class)->disableOriginalConstructor()->onlyMethods(['create'])->getMock(),
            $this->createMock(Random::class),
            $storeManager,
            $this->createMock(Emulation::class),
            $this->createMock(TransportBuilder::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Image::class)
        ))->send();
    }
}
