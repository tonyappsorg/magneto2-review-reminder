<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Tonyapps\ReviewReminder\Model\ResourceModel\VerifiedReview;

class MarkVerifiedBuyer implements ObserverInterface
{
    public function __construct(private readonly VerifiedReview $verifiedReview)
    {
    }

    public function execute(Observer $observer): void
    {
        $review = $observer->getEvent()->getDataObject();
        if (!$review instanceof Review || (int) $review->getData('entity_id') !== (int) $review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE)) {
            return;
        }
        $customerId = (int) $review->getData('customer_id');
        if ($customerId < 1) {
            return;
        }
        $this->verifiedReview->associate((int) $review->getId(), $customerId, (int) $review->getData('entity_pk_value'));
    }
}
