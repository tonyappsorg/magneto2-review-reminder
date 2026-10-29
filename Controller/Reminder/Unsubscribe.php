<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Controller\Reminder;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Tonyapps\ReviewReminder\Model\ResourceModel\Invitation;

class Unsubscribe implements HttpGetActionInterface, HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Invitation $invitation,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function execute()
    {
        $token = (string) $this->request->getParam('token', '');
        $row = $this->invitation->getByToken($token);
        if ($this->request->isPost() && $row) {
            $this->invitation->unsubscribe((string) $row['email']);
            return $this->redirectFactory->create()->setPath('reviewreminder/reminder/unsubscribed');
        }
        return $this->pageFactory->create();
    }
}
