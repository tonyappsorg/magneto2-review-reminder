# Review Reminder for Magento 2

Ask customers for a product review a few days after their order ships, and mark reviews from real buyers as "Verified Buyer".

Magento Open Source supports product reviews, but it never asks for one after a purchase and can't tell a buyer's review from anyone else's. This module sends a review invitation after shipment and adds a "Verified Buyer" label to reviews written by customers who bought the product.

## Features

- **Timed invitation**: one email per order, sent a set number of days (default 7) after the order's most recent shipment. If another shipment follows, the invitation waits for it.
- **Guests and customers**: anyone who placed an order with an email address is invited.
- **Straight to the review form**: each shipped product in the email links to the product's review form; the page opens the Reviews tab and scrolls to the form.
- **Verified Buyer label**: reviews from signed-in customers who received the product show a "Verified Buyer" label on the product page.
- **Safe to switch on**: orders whose last shipment is more than 30 days past the delay are skipped, so enabling the module doesn't email your order history.
- **Skips what shouldn't be mailed**: canceled and fully refunded (closed) orders are not invited, and the email lists only products customers can still open and review. No reviewable products, no email.
- **Unsubscribe that respects the shopper**: a link in every email opts the address out of all future invitations, after a confirm click (so mail scanners can't unsubscribe anyone).
- **Reliable sending**: a failed send is retried on the next cron run, up to 3 times.
- **Repeat customers welcome**: every new order gets its own invitation, even for a product bought before.

## How it works

1. You ship an order as usual.
2. Every 15 minutes, cron finds orders whose last shipment is old enough and emails the customer an invitation listing the shipped products.
3. The customer clicks a product and writes the review in Magento's standard review form; reviews are moderated as usual.
4. When a signed-in customer's review matches a product they received, the review shows "Verified Buyer".

## Screenshots

Emails as sent (Luma theme, 2026-11-09).

**Review invitation**

![Review invitation](docs/emails/invitation.png)

## Good to know

- Off by default; enable it per store.
- One invitation per order: items shipped after the invitation was sent don't trigger a second one.
- Guest reviews can't be marked "Verified Buyer", because Magento doesn't keep a guest reviewer's email.
- "Verified Buyer" is not removed if the order is refunded later.
- The email link doesn't log the shopper in or write anything on their behalf.
- Requires Magento cron to be running.
- Built and tested on the Luma theme; Hyvä has not been checked.

## Requirements

- Magento Open Source 2.4.8
- PHP 8.3 or 8.4
- Magento modules: Sales, Review

## Installation

```sh
composer require tonyapps/module-review-reminder
bin/magento module:enable Tonyapps_ReviewReminder
bin/magento setup:upgrade
```

Make sure Magento cron is running.

## Configuration

- **Stores › Configuration › Sales › Review Reminder**: enable, days after shipment, sender and email template.

### Redesigning the email

The email can be redesigned visually with the [Email Designer](https://github.com/tonyappsorg/magento2-email-designer) (`Tonyapps_EmailDesigner`): open **Marketing › Communications › Email Designer**, start a design for the email and save it, then pick the saved design in the **Template** setting under **Stores › Configuration**. The default template is used until a design is picked. A new design starts from a ready-made copy of the invitation, with the products to review as a drag-and-drop block.

## For developers

Architecture, decisions, data model and tests are in [AGENTS.md](AGENTS.md).
