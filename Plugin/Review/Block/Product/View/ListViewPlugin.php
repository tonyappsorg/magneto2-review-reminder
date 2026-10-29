<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Plugin\Review\Block\Product\View;

use Magento\Review\Block\Product\View\ListView;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Tonyapps\ReviewReminder\Model\ResourceModel\VerifiedReview;

class ListViewPlugin
{
    public function __construct(private readonly VerifiedReview $verifiedReview)
    {
    }

    public function afterGetReviewsCollection(ListView $subject, Collection $collection): Collection
    {
        $items = $collection->getItems();
        $ids = array_map(static fn ($review): int => (int) $review->getId(), $items);
        $verifiedIds = array_flip($this->verifiedReview->getVerifiedIds($ids));
        foreach ($items as $review) {
            $review->setData('tonyapps_verified_buyer', isset($verifiedIds[(int) $review->getId()]));
        }
        return $collection;
    }
}
