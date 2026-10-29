<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Test\Unit\View;

use Magento\Framework\Filter\Template;
use PHPUnit\Framework\TestCase;

class InvitationEmailTest extends TestCase
{
    public function testProductLoopUsesTheCoreForDirective(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../view/frontend/email/invitation.html');

        self::assertSame(1, preg_match(Template::LOOP_PATTERN, $html, $match));
        self::assertSame('products', trim($match['loopData']));
        self::assertStringContainsString('{{var product.url}}', $match['loopBody']);
    }
}
