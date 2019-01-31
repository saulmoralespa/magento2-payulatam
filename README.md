WebCheckout payU latam  Magento 2
============================================================

## Description ##
payU latam gateway payment available for Argentina, Brasil, Chile, Colombia, México, Panamá, Perú

## Table of Contents

* [Installation](#installation)
* [Configuration](#configuration)


## Installation ##

Use composer package manager

```bash
composer require saulmoralespa/magento2-payulatam
```

Execute the commands

```bash
php bin/magento module:enable Saulmoralespa_PayuLatam --clear-static-content
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy en_US #on i18n
```

## Configuration ##

### 1. Enter the configuration menu of the payment method ###
![Enter the configuration menu of the payment method](https://4.bp.blogspot.com/-vPfP40YDaPE/XCZpZS32NaI/AAAAAAAACnA/DGA4AibYG6ETZxmv5gwm4bq3fXszxE_0ACLcBGAs/s1600/configurationmenuofthepaymentmethod.png)