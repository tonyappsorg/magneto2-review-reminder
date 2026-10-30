<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
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

    public function testDueOrdersSkipShipmentsOlderThanTheInvitationWindow(): void
    {
        [$where, $having] = $this->captureDueOrdersQuery();

        self::assertContains(['s.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', 7 + Invitation::MAX_AGE_DAYS], $where);
        self::assertSame([['MAX(s.created_at) <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', 7]], $having);
    }

    public function testDueOrdersSkipCanceledAndClosedOrders(): void
    {
        [$where] = $this->captureDueOrdersQuery();

        self::assertContains(['o.state NOT IN (?)', ['canceled', 'closed']], $where);
    }

    /** @return array{0: array<int, array{0: string, 1: mixed}>, 1: array<int, array{0: string, 1: mixed}>} */
    private function captureDueOrdersQuery(): array
    {
        $where = $having = [];
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinInner', 'joinLeft', 'group', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use (&$where, $select) {
            $where[] = [(string) $cond, $value];
            return $select;
        });
        $select->method('having')->willReturnCallback(function (string $cond, $value) use (&$having, $select) {
            $having[] = [$cond, $value];
            return $select;
        });
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);

        (new Invitation($this->resource($connection)))->getDueOrders([1], 7);

        return [$where, $having];
    }

    public function testRecordFailureRetiresInvitationAfterMaxAttempts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('update')->with(
            'tonyapps_review_reminder',
            self::callback(static fn (array $bind): bool => array_keys($bind) === ['status', 'attempts']
                && $bind['status'] instanceof Expression
                && (string) $bind['status'] === 'IF(attempts + 1 >= ' . Invitation::MAX_ATTEMPTS . ', 3, 0)'
                && (string) $bind['attempts'] === 'attempts + 1'),
            ['order_id = ?' => 5, 'status = ?' => 0]
        );

        (new Invitation($this->resource($connection)))->recordFailure(5);
    }

    private function resource(AdapterInterface $connection): ResourceConnection
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return $resource;
    }
}
