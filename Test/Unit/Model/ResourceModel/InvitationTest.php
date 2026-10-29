<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;
use Tonyapps\ReviewReminder\Model\ResourceModel\Invitation;

class InvitationTest extends TestCase
{
    public function testNoStoreIdsReturnsWithoutOpeningConnection(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        $this->assertSame([], (new Invitation($resource))->getDueOrders([], 7));
    }
}
