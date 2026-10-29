<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\Plugin;

use Magento\Framework\DataObject;
use Magento\Review\Block\Product\View\ListView;
use Magento\Review\Model\ResourceModel\Review\Collection;
use PHPUnit\Framework\TestCase;
use Tonyapps\ReviewReminder\Model\ResourceModel\VerifiedReview;
use Tonyapps\ReviewReminder\Plugin\Review\Block\Product\View\ListViewPlugin;

class ListViewPluginTest extends TestCase
{
    public function testLeavesUnloadedCollectionAloneSoThePagerCanLimitIt(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('isLoaded')->willReturn(false);
        $collection->expects(self::never())->method('getItems');
        $verified = $this->createMock(VerifiedReview::class);
        $verified->expects(self::never())->method('getVerifiedIds');

        (new ListViewPlugin($verified))->afterGetReviewsCollection($this->createMock(ListView::class), $collection);
    }

    public function testFlagsVerifiedReviewsOnLoadedCollection(): void
    {
        $verifiedReview = new DataObject(['id' => 1]);
        $otherReview = new DataObject(['id' => 2]);
        $collection = $this->createMock(Collection::class);
        $collection->method('isLoaded')->willReturn(true);
        $collection->method('getItems')->willReturn([1 => $verifiedReview, 2 => $otherReview]);
        $verified = $this->createMock(VerifiedReview::class);
        $verified->method('getVerifiedIds')->with([1 => 1, 2 => 2])->willReturn([1]);

        (new ListViewPlugin($verified))->afterGetReviewsCollection($this->createMock(ListView::class), $collection);

        self::assertTrue($verifiedReview->getData('tonyapps_verified_buyer'));
        self::assertFalse($otherReview->getData('tonyapps_verified_buyer'));
    }
}
