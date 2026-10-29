<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Cron;

use Tonyapps\ReviewReminder\Model\InvitationSender;

class SendInvitations
{
    public function __construct(private readonly InvitationSender $sender)
    {
    }

    public function execute(): void
    {
        $this->sender->send();
    }
}
