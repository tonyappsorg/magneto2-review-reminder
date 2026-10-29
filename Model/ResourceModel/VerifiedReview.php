<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class VerifiedReview
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function associate(int $reviewId, int $customerId, int $productId): void
    {
        if ($reviewId < 1 || $customerId < 1 || $productId < 1) {
            return;
        }
        $connection = $this->resource->getConnection();
        $orders = $this->resource->getTableName('sales_order');
        $items = $this->resource->getTableName('sales_order_item');
        $shipments = $this->resource->getTableName('sales_shipment');
        $shipmentItems = $this->resource->getTableName('sales_shipment_item');
        $select = $connection->select()->from(['i' => $items], ['item_id', 'order_id'])
            ->joinInner(['o' => $orders], 'o.entity_id = i.order_id', [])
            ->joinInner(['si' => $shipmentItems], 'si.order_item_id = i.item_id', [])
            ->joinInner(['s' => $shipments], 's.entity_id = si.parent_id', ['shipped_at' => 'created_at'])
            ->where('o.customer_id = ?', $customerId)->where('i.product_id = ?', $productId)
            ->where('o.state <> ?', 'canceled')->order('s.created_at DESC')->limit(1);
        $row = $connection->fetchRow($select);
        if ($row) {
            $connection->insertOnDuplicate($this->resource->getTableName('tonyapps_review_reminder_verified_review'), [
                'review_id' => $reviewId,
                'order_id' => (int) $row['order_id'],
                'order_item_id' => (int) $row['item_id'],
            ], ['order_id', 'order_item_id']);
        }
    }

    /** @param int[] $reviewIds @return int[] */
    public function getVerifiedIds(array $reviewIds): array
    {
        if (!$reviewIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->resource->getTableName('tonyapps_review_reminder_verified_review'), ['review_id'])->where('review_id IN (?)', $reviewIds)
        ));
    }
}
