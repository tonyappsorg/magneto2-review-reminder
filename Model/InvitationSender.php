<?php
declare(strict_types=1);

namespace Tonyapps\ReviewReminder\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Math\Random;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\ReviewReminder\Helper\Config;
use Tonyapps\ReviewReminder\Model\ResourceModel\Invitation;

class InvitationSender
{
    public function __construct(
        private readonly Invitation $invitation,
        private readonly Config $config,
        private readonly OrderFactory $orderFactory,
        private readonly Random $random,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation,
        private readonly TransportBuilder $transportBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$this->config->enabled($storeId)) {
                continue;
            }

            // Process stores individually because delay is store scoped.
            foreach ($this->invitation->getDueOrders([$storeId], $this->config->delayDays($storeId)) as $row) {
                $orderId = (int) $row['entity_id'];
                $token = (string) ($row['token'] ?? '');
                if ($token === '') {
                    $token = $this->random->getUniqueHash();
                    $this->invitation->create($orderId, $storeId, (string) $row['customer_email'], $token);
                }
                $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                try {
                    $this->sendOrder($row, $token, $storeId);
                    $this->invitation->markSent($orderId);
                } catch (\Throwable $e) {
                    $this->invitation->recordFailure($orderId);
                    $this->logger->error('Review invitation for order ' . $orderId . ' failed: ' . $e->getMessage());
                } finally {
                    $this->emulation->stopEnvironmentEmulation();
                }
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function sendOrder(array $row, string $token, int $storeId): void
    {
        /** @var Order $order */
        $order = $this->orderFactory->create()->load((int) $row['entity_id']);
        $products = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if (!$item instanceof Item || (float) $item->getQtyShipped() <= 0) {
                continue;
            }
            $product = $item->getProduct();
            if (!$product || !$product->getId()) {
                continue;
            }
            $products[] = [
                'name' => (string) $item->getName(),
                'url' => $product->getProductUrl() . '#review-form',
            ];
        }
        if (!$products) {
            return;
        }
        $store = $this->storeManager->getStore($storeId);
        $unsubscribeUrl = $store->getUrl('reviewreminder/reminder/unsubscribe', ['token' => $token, '_nosid' => true]);
        $this->transportBuilder->setTemplateIdentifier($this->config->template($storeId))
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'customerName' => (string) $row['customer_firstname'] ?: (string) __('there'),
                'products' => $products,
                'unsubscribeUrl' => $unsubscribeUrl,
                'orderIncrementId' => (string) $row['increment_id'],
            ])
            ->setFromByScope($this->config->identity($storeId), $storeId)
            ->addTo((string) $row['customer_email'])
            ->getTransport()->sendMessage();
    }
}
