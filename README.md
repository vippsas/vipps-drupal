CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Contribution
 * Support

INTRODUCTION
------------

Vipps is a Norwegian payment service, used by more than 3.5 million people.
Vipps was originally developed by DNB, but is now a separate company, which
includes BankID and BankAxept.

REQUIREMENTS
------------

* [Composer]
* [Drupal 8.7+][Drupal installation guide]
* [Commerce 2.x]
* [Commerce Vipps module][Commerce Vipps]

INSTALLATION
------------

Before you install commerce_vipps module you must first install [Commerce 2.x]
on your Drupal website. Please follow [this guide][Commerce installation guide]
in order to learn how to install Commerce.

Please remember that commerce_vipps module, similarly to other payment gateways
integrations, is using Payment API provided by commerce_payment submodule - make
sure you have installed that module as well.


CONTRIBUTION
------------

In order to download the module and it's dependencies use the following [Composer] command:
```bash
composer require drupal/commerce_vipps
```
Enable module either via Drupal UI (navigate to /admin/modules) or CLI -
read more about enabling the module on https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules#s-step-2-enable-the-module

SUPPORT
-------

* For technical support regarding the module reach out to module maintainer via issue queue - https://www.drupal.org/node/add/project-issue/commerce_vipps
* If you have technical problems regarding Drupal or Drupal Commerce, use https://drupal.stackexchange.com/
* For merchant support contact VIPPS customer support - https://www.vipps.no/kontakt-oss/

[Composer]: https://getcomposer.org/
[Drupal installation guide]: https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies#download-core
[Commerce installation guide]: https://docs.drupalcommerce.org/commerce2/developer-guide/install-update
[Commerce 2.x]: http://drupal.org/project/commerce
[Commerce Vipps]: http://drupal.org/project/commerce_vipps
