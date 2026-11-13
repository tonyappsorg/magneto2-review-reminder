<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    public function enabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag('tonyapps_reviewreminder/general/enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function delayDays(int $storeId): int
    {
        return max(1, (int) $this->scopeConfig->getValue('tonyapps_reviewreminder/general/delay_days', ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function identity(int $storeId): string
    {
        return (string) $this->scopeConfig->getValue('tonyapps_reviewreminder/email/identity', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function template(int $storeId): string
    {
        return (string) $this->scopeConfig->getValue('tonyapps_reviewreminder/email/template', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Core's Catalog > Product Reviews > Enable Reviews. */
    public function reviewsEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag('catalog/review/active', ScopeInterface::SCOPE_STORE, $storeId);
    }
}
