# Tonyapps_ReviewReminder — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

## Status

Package `tonyapps/module-review-reminder` 0.1.0. WORKPLAN shortlist #1 (quick win): "built, manual QA pending".

| Phase | Scope | Status |
|---|---|---|
| 1. Invitation lifecycle | Schema, store configuration, cron selection, shipment-based due date, email with core review links, unsubscribe, focused unit tests. | Built. |
| 2. Verified buyer | Associate reviews from signed-in customers with matching shipped order items, storefront badge, customer/guest handling covered by unit tests. Core does not retain guest reviewer emails, so guest reviews remain unmarked. | Implemented. |
| 3. Manual verification | Install/upgrade check, exercise cron and review flow on a Magento instance, inspect storefront output. | Partly done 2026-11-08 (invitation email with review + unsubscribe links verified in DDEV, see ../WORKPLAN.md QA log); still listed as pending in WORKPLAN. |

Why it exists: Magento Open Source supports product reviews, but it does not send post-purchase review requests or label a review as coming from a verified purchaser.

## Scope and decisions

| Feature | Decision |
|---|---|
| Who qualifies | Customer orders with a shipment and at least one shipped, reviewable product. Guests and registered customers are eligible. |
| Schedule | One message per order, sent N days after its most recent shipment. If a shipment is added later, the due date moves to N days after that shipment. Orders whose last shipment is more than N + 30 days old are skipped, so enabling the module never mails historical orders. A failed send is retried on the next cron run, at most 3 times, then marked failed (status 3). |
| Review link | Each product link opens Magento's core product review form. The invitation token is opaque and order-scoped; it does not log the shopper in or submit a review on their behalf. |
| Verified buyer | Associate logged-in customer reviews with matching shipped order items for the same customer and product. Display a "Verified Buyer" label on associated reviews. Guest reviews cannot be matched because core does not retain a reviewer email. |
| Repeat orders | A new order can receive its own invitation, including for a product previously purchased. |
| Opt-out | Tokenized unsubscribe link. GET only displays confirmation; POST opts that email address out of all future invitations. |
| Configuration | Store-scoped enable switch, delay in days, sender identity, and email template. Disabled by default. |
| Data | Dedicated invitation table with unique order and token. Core order, shipment, and review records stay the source of truth. |

## Architecture

- Cron `tonyapps_review_reminder_send` → `Model\InvitationSender::send`, `*/15 * * * *`, group `default`. Per store (delay is store-scoped): `Model\ResourceModel\Invitation::getDueOrders($storeIds, $delayDays, $limit = 500)`, creates the invitation row with a `Random::getUniqueHash()` token if missing, sends in frontend store emulation, then `markSent()`; on any `Throwable` `recordFailure()` (status becomes 3 once `attempts + 1 >= MAX_ATTEMPTS` (3), else stays 0) and logs.
- Due-orders query: `sales_order` ⋈ `sales_shipment` ⟕ invitation; store in list, non-empty `customer_email`, state not `canceled`/`closed`, no invitation or status 0, `s.created_at > UTC_TIMESTAMP() - (delay + MAX_AGE_DAYS(30)) DAY` in `WHERE`, email not opted out (`NOT EXISTS` row with same email and status 2), grouped per order `HAVING MAX(s.created_at) <= now - delay`, oldest first.
- Email products: visible order items with `qty_shipped > 0` and an existing product; link = product URL + `#review-form`, image `category_page_grid`. Template vars: `customerName` (fallback "there"), `products`, `unsubscribeUrl`, `orderIncrementId`.
- Unsubscribe: `Controller\Reminder\Unsubscribe` (GET confirm page, POST → `Invitation::unsubscribe($email)` sets status 2) then `Controller\Reminder\Unsubscribed`. Layouts `reviewreminder_reminder_unsubscribe(d).xml`, templates `unsubscribe.phtml`, `unsubscribed.phtml`.
- Verified buyer: `Observer\MarkVerifiedBuyer` on `review_save_after` → `Model\ResourceModel\VerifiedReview::associate($reviewId, $customerId, $productId)`: latest shipped order item of that customer and product (order not canceled) is stored in `tonyapps_review_reminder_verified_review`. Display: `Plugin\Review\Block\Product\View\ListViewPlugin` (frontend `di.xml`, on `Magento\Review\Block\Product\View\ListView`) fetches `getVerifiedIds()`; template `product/view/list.phtml` replaces core's via `review_product_list.xml` and `review_product_listajax.xml` (block `product.info.product_additional_data`) and prints the "Verified Buyer" span.
- Review-form opener: `view/frontend/web/js/open-review-form.js`, loaded on product pages by `catalog_product_view.xml` (`before.body.end`, `product/open-review-form.phtml`); opens the Reviews tab and scrolls to the form when the URL hash is `#review-form`.
- Config reader: `Helper\Config` (`enabled`, `delayDays`, `identity`, `template`).

## Data model

- `tonyapps_review_reminder`: `entity_id`, `order_id` (unique `TONYAPPS_REVIEW_REMINDER_ORDER`, no FK), `store_id`, `email`, `token` (unique, 64), `status` (0 pending, 1 sent, 2 email opted out, 3 failed), `attempts`, `sent_at`; index (`email`, `status`). Opt-out is a status-2 row, matched by email.
- `tonyapps_review_reminder_verified_review`: `review_id` (PK, FK `review` CASCADE), `order_id` (FK `sales_order` CASCADE), `order_item_id` (FK `sales_order_item` CASCADE).

## Configuration and ACL

Section `tonyapps_reviewreminder` (**Stores > Configuration > Sales > Review Reminder**), ACL `Tonyapps_ReviewReminder::config`. All fields default/website/store scope.

| Path | Default |
|---|---|
| `general/enabled` | 0 |
| `general/delay_days` | 7 (required, digits > 0; 1 is the minimum) |
| `email/identity` | `sales` |
| `email/template` | `tonyapps_reviewreminder_email_template` |

Email template ID: `tonyapps_reviewreminder_email_template` (file `invitation.html`), formerly `tonyapps_reviewreminder_invitation`.

## Decisions and constraints

- Security and privacy: invitation tokens are random, high-entropy credentials and are stored uniquely. They grant access only to the invitation and unsubscribe action, never to an account or order management. Escape customer and product data in email and storefront output. Do not persist review text outside Magento's review tables.
- One invitation per order stays the design (2026-10-30): items shipped after the invitation was sent get no second invitation.
- "Verified Buyer" is not re-checked after a refund (product decision, 2026-10-30).

## Known limitations and open points

- Guest reviews are never marked verified (core keeps no reviewer email).
- "Reviewable" is not checked beyond `qty_shipped > 0` and the product still existing; no check of product visibility or review settings.
- If an order has no qualifying product at send time, no email is sent but the row is still marked sent.
- Hyvä not checked.

## Tests

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/ReviewReminder/Test/Unit
```

Unit tests: InvitationSender, Invitation resource, MarkVerifiedBuyer, ListViewPlugin, invitation email template, open-review-form script. Elsewhere: `../EmailDesigner/Test/Integration/Model/ModuleEmailsTest.php` (template setting) and Playwright `../e2e/tests/review-reminder.spec.js`.

## Operations

```sh
bin/magento module:enable Tonyapps_ReviewReminder
bin/magento setup:upgrade
```

QA tip from 2026-11-08: set the delay to 1 day and backdate a test shipment's `sales_shipment.created_at` to trigger an invitation on the next cron run.

## Change history

- **2026-11-11 template ID rename**: default template ID is `tonyapps_reviewreminder_email_template` (was `tonyapps_reviewreminder_invitation`). The template setting lists the default under an ID made from its config path, so the old ID made the setting fail with "Email template is not defined" and no other template could be picked. Theme overrides keep working (they go by file name). Lets stores pick Email Designer designs.
- **2026-11-10 QA fix**: the invitation's product links end in `#review-form`, but Luma marks the Reviews tab active for that hash and keeps its content hidden, so a shopper arriving from the email saw no form. `view/frontend/web/js/open-review-form.js` (loaded on product pages) opens the Reviews tab and scrolls to the form when the page is reached with that hash.
- **2026-11-09 email screenshot** added: `docs/emails/invitation.png` (Luma, emails as sent 2026-11-09). The email itself was redesigned during the 2026-11-08 QA (`04442bb`, per ../WORKPLAN.md).
- **2026-10-30 review fixes**:
  - The due-orders query filters `sales_shipment.created_at` in `WHERE` (not only `HAVING`), so the cron no longer scans the whole shipment history every 15 minutes.
  - Closed (fully refunded) orders are skipped as well as canceled ones.
  - Not changed: one invitation per order; "Verified Buyer" not re-checked after a refund (product decision).
