<!-- START_METADATA
---
title: Commerce Vipps
sidebar_position: 1
description: Offer Vipps as a payment method on your Drupal website.
pagination_next: null
pagination_prev: null
---
END_METADATA -->

# Commerce Vipps

![Support and development by Ny Media ](./docs/images/nymedia.svg#gh-light-mode-only)![Support and development by Ny Media](./docs/images/nymedia_dark.svg#gh-dark-mode-only)

![Vipps](./docs/images/vipps.png) *Available for Vipps.*

![MobilePay](./docs/images/mp.png) *Availability for MobilePay has not yet been determined.*

*This plugin is built and maintained by [Ny Media](https://www.nymedia.no/en) and hosted on [drupal.org][Commerce Vipps].*

<!-- START_COMMENT -->
💥 Please use the plugin pages on [https://developer.vippsmobilepay.com](https://developer.vippsmobilepay.com/docs/plugins-ext/drupal/). 💥

<!-- END_COMMENT -->


Offer Vipps as a payment method on your Drupal website.

All development is happening via [issue queue on drupal.org][Issue queue].
See the [Contribution](#contribution) and [Support](#support) sections
below for more information.

<!-- START_COMMENT -->
## Table of contents

* [Introduction](#introduction)
* [Requirements](#requirements)
* [Installation](#installation)
* [Contribution](#contribution)
* [Support](#support)

## Introduction

Vipps is a Norwegian payment service, used by more than 3.5 million people.
Vipps was originally developed by DNB, but is now a separate company, which
includes BankID and BankAxept.
<!-- END_COMMENT -->

## Requirements

* [Composer]
* [Drupal 8.7+][Drupal installation guide]
* [Commerce 2.x]
* [Commerce Vipps module][Commerce Vipps]

## Installation

Before you install `commerce_vipps` module, you must first install [Commerce 2.x]
on your Drupal website. Please follow the [Commerce installation guide][Commerce installation guide].

Please remember that `commerce_vipps` module, similar to other payment gateway
integrations, is using Payment API provided by the `commerce_payment` submodule. Make
sure you have installed that module.

In order to download the module and its dependencies, use the following [Composer][Composer] command:

```bash
composer require drupal/commerce_vipps
```

Enable the module either via Drupal UI (navigate to */admin/modules*) or CLI.
Read more about enabling the module on the [Drupal help pages](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module).

## Contribution

You found a bug? You'd like to request a feature? You'd like to
contribute a code, visit the [issue queue on drupal.org][Issue queue].

## Support

* For technical support regarding the module, reach out to the module maintainer via issue queue at [https://www.drupal.org/node/add/project-issue/commerce_vipps][Create an issue].
* If you have technical problems regarding Drupal or Drupal Commerce, use [Drupal Answers](https://drupal.stackexchange.com/).
* For merchant support, contact [Vipps customer support][Vipps Support].


[Composer]: https://getcomposer.org/
[Drupal installation guide]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies#download-core
[Commerce installation guide]: https://docs.drupalcommerce.org/commerce2/developer-guide/install-update
[Commerce 2.x]: http://drupal.org/project/commerce
[Commerce Vipps]: http://drupal.org/project/commerce_vipps
[Commerce Vipps on Github]: https://github.com/vippsas/vipps-drupal
[Issue queue]: https://www.drupal.org/project/issues/commerce_vipps
[Create an issue]: https://www.drupal.org/node/add/project-issue/commerce_vipps
[Vipps Support]: https://vippsmobilepay.com/help
