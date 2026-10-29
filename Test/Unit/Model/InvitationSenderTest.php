<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Math\Random;
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
            $this->createMock(LoggerInterface::class)
        ))->send();
    }
}
