<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class Invitation
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function getDueOrders(array $storeIds, int $delayDays, int $limit = 500): array
    {
        if (!$storeIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $order = $this->resource->getTableName('sales_order');
        $shipment = $this->resource->getTableName('sales_shipment');
        $invitation = $this->resource->getTableName('tonyapps_review_reminder');
        $select = $connection->select()->from(['o' => $order], ['entity_id', 'store_id', 'customer_email', 'customer_firstname', 'customer_id', 'increment_id', 'invitation_id' => 'r.entity_id', 'token' => 'r.token'])
            ->joinInner(['s' => $shipment], 's.order_id = o.entity_id', ['shipped_at' => new \Magento\Framework\DB\Sql\Expression('MAX(s.created_at)')])
            ->joinLeft(['r' => $invitation], 'r.order_id = o.entity_id', [])
            ->where('o.store_id IN (?)', $storeIds)->where('o.customer_email IS NOT NULL')->where("o.customer_email <> ''")
            ->where('o.state <> ?', 'canceled')->where('(r.entity_id IS NULL OR r.status = 0)')
            ->where('NOT EXISTS (?)', $connection->select()->from(['optout' => $invitation], [new \Magento\Framework\DB\Sql\Expression('1')])
                ->where('optout.email = o.customer_email')->where('optout.status = 2'))
            ->group(['o.entity_id', 'r.entity_id', 'r.token'])->having('MAX(s.created_at) <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', $delayDays)
            ->order('MAX(s.created_at) ASC')->limit($limit);
        return $connection->fetchAll($select);
    }

    public function create(int $orderId, int $storeId, string $email, string $token): void
    {
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate($this->resource->getTableName('tonyapps_review_reminder'), [
            'order_id' => $orderId, 'store_id' => $storeId, 'email' => $email, 'token' => $token, 'status' => 0,
        ], ['order_id']);
    }

    public function markSent(int $orderId): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName('tonyapps_review_reminder'),
            ['status' => 1, 'sent_at' => new \Magento\Framework\DB\Sql\Expression('UTC_TIMESTAMP()')],
            ['order_id = ?' => $orderId, 'status = ?' => 0]
        );
    }

    public function getByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $row = $this->resource->getConnection()->fetchRow(
            $this->resource->getConnection()->select()->from($this->resource->getTableName('tonyapps_review_reminder'))->where('token = ?', $token)
        );
        return $row ?: null;
    }

    public function unsubscribe(string $email): void
    {
        $this->resource->getConnection()->update($this->resource->getTableName('tonyapps_review_reminder'), ['status' => 2], ['email = ?' => $email]);
    }
}
