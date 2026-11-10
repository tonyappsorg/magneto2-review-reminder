<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class OpenReviewFormTest extends TestCase
{
    /** The invitation links to #review-form; the product page must open the Reviews tab for it. */
    public function testProductPageLoadsTheScriptThatOpensTheReviewForm(): void
    {
        $root = __DIR__ . '/../../..';
        $layout = simplexml_load_file($root . '/view/frontend/layout/catalog_product_view.xml');
        $block = $layout->xpath('//block[@name="reviewreminder.open.review.form"]')[0] ?? null;

        self::assertNotNull($block);
        self::assertSame('Tonyapps_ReviewReminder::product/open-review-form.phtml', (string) $block['template']);
        self::assertStringContainsString(
            '"Tonyapps_ReviewReminder/js/open-review-form"',
            (string) file_get_contents($root . '/view/frontend/templates/product/open-review-form.phtml')
        );
        $script = (string) file_get_contents($root . '/view/frontend/web/js/open-review-form.js');
        self::assertStringContainsString("window.location.hash !== '#review-form'", $script);
        self::assertStringContainsString('#tab-label-reviews-title', $script);
        self::assertStringContainsString('#review-form', (string) file_get_contents($root . '/Model/InvitationSender.php'));
    }
}
