# Subscribe plugin

Subscription membership system for October. The Subscribe plugin adds the following features to October:

- Automatic invoice generation for expiring subscriptions
- CMS components for organising subscription-based website membership
- API extensions, allowing you to check if a subscription is active for a given user

You can use the subscription plugin for:

- Selling regular products that are delivered periodically
- Paid services that require regular renewal
- Website membership

> **Note**: Subscriptions only work with registered users. This means you need to register them before purchase. Also if you have members only pages on your website, users need to sign in before they can access these pages.

This plugin requires the following plugins:

- [Responsiv.Currency](http://octobercms.com/plugin/responsiv-currency)
- [Responsiv.Pay](http://octobercms.com/plugin/responsiv-pay)
- [RainLab.User](http://octobercms.com/plugin/rainlab-user)
- [RainLab.Location](http://octobercms.com/plugin/rainlab-location)

These payment gateways are supported to use payment profiles:

- Stripe
- PayPal Adaptive

### What is a membership and subscription plan

A membership is a container owned by a user and has a subscription plan assigned to it. Before you can create a membership, you need to define a subscription plan. You can access the list of subscription plans defined in your system by clicking the Subscribers > Plans menu item in the back-end area.

A subscription plan has the following properties:

- **Name** - a name used to identify the plan in the user interface.
- **API code** - a unique code for identifying the plan using PHP code.
- **Price** - the price charged for this plan at every renewal interval.
- **Setup price** - a one time fee added when activating or switching to the plan.
- **Membership schedule** - the frequency the subscription will be renewed. Supported schedules are: Daily, Monthly, Yearly, Lifetime.
- **Renewal interval** - number of days, months or years before the next renewal. You can also call this value the subscription length.
- **Monthly behavior** (applicable for Monthly schedule only) - specifies when the next interval should occur. Supported behaviors are: Signup Date, Prorated, Free Days, No Start.
- **Day of month** (applicable for Monthly schedule only) - supporting the monthly behavior, which day of the month should a subscription renew.
- **Renewal cycles** - number of renewals before the subscription ends. Leave the field empty for unlimited renewals. This option is not applicable for Lifetime subscriptions.
- **Tax class** - specify a custom tax class to apply when invoicing for this plan.
- **Custom membership rules** - activates advanced settings that override the system defaults. See Settings > Membership Settings to set the baseline default values.
- **Membership price** (settings override) - a one time fee when establishing a new membership with this plan selected.
- **Grace period** - number of days subscription will stay active after it has expired. Leave the field empty to cancel the grace period.
- **Trial period** - number of days a new unpaid subscription is active. There can be only a single trial period per membership (one per user).

### How the subscription engine works

The subscription engine uses services and invoices to determine whether a specific subscription is active for a user. When someone purchases a subscription, the system creates a membership for them, if one does not exist already. An instance of the subscription plan, called a service, will be added to the membership, along with the first invoice.

Once the customer pays the invoice, the service and membership become active for the plan interval. 