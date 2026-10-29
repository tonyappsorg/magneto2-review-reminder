<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Controller\Reminder;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Unsubscribed implements HttpGetActionInterface
{
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    public function execute()
    {
        return $this->pageFactory->create();
    }
}
