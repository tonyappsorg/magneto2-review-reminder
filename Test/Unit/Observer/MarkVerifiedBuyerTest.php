<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Review\Model\Review;
use PHPUnit\Framework\TestCase;
use Tonyapps\ReviewReminder\Model\ResourceModel\VerifiedReview;
use Tonyapps\ReviewReminder\Observer\MarkVerifiedBuyer;

class MarkVerifiedBuyerTest extends TestCase
{
    public function testAssociatesProductReviewForSignedInCustomer(): void
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->with(Review::ENTITY_PRODUCT_CODE)->willReturn(1);
        $review->method('getId')->willReturn(42);
        $review->method('getData')->willReturnCallback(static fn (string $key): mixed => [
            'entity_id' => 1, 'customer_id' => 15, 'entity_pk_value' => 88,
        ][$key] ?? null);

        $verified = $this->createMock(VerifiedReview::class);
        $verified->expects(self::once())->method('associate')->with(42, 15, 88);

        (new MarkVerifiedBuyer($verified))->execute($this->observer($review));
    }

    public function testDoesNotAssociateGuestReview(): void
    {
        $review = $this->createMock(Review::class);
        $review->method('getEntityIdByCode')->with(Review::ENTITY_PRODUCT_CODE)->willReturn(1);
        $review->method('getData')->willReturnCallback(static fn (string $key): mixed => [
            'entity_id' => 1, 'customer_id' => 0,
        ][$key] ?? null);

        $verified = $this->createMock(VerifiedReview::class);
        $verified->expects(self::never())->method('associate');

        (new MarkVerifiedBuyer($verified))->execute($this->observer($review));
    }

    private function observer(Review $review): Observer
    {
        return new Observer(['event' => new Event(['data_object' => $review])]);
    }
}
