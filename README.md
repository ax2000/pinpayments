## Pin Payments

Pin Payments gateway integration for Craft Commerce 4.

## Requirements

This plugin requires Craft CMS 4.4.5 or later, Craft Commerce 4.0.0 or later and PHP 8.0.2 or later.

Please note that in order to use the version 3 of Omnipay you'll need to set the minimum stability setting of your composer.json to `dev` as per below
```
...
"minimum-stability": "dev",
 "prefer-stable": true,
...
```
## Installation

DDEV

```
ddev composer require pixelpie/craft-pin-payments && ddev exec php craft plugin/install pin-payments
```

SHELL

```
composer require pixelpie/craft-pin-payments && php craft plugin/install pin-payments
```