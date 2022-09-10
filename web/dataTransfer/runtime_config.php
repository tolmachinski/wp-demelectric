<?php
/***************************************************************
 * Part of mS3 Commerce
 * Copyright (C) 2019 Goodson GmbH <http://www.goodson.at>
 *  All rights reserved
 *
 * Dieses Computerprogramm ist urheberrechtlich sowie durch internationale
 * Abkommen geschützt. Die unerlaubte Reproduktion oder Weitergabe dieses
 * Programms oder von Teilen dieses Programms kann eine zivil- oder
 * strafrechtliche Ahndung nach sich ziehen und wird gemäß der geltenden
 * Rechtsprechung mit größtmöglicher Härte verfolgt.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

// Database setup
define('MS3COMMERCE_STAGETYPE', 'DATABASES');	// Alternatives: DATABASES (recommended), TABLES
define('MS3C_DB_BACKEND', 'mysqli');	// Alternatives: mysqli, mysql (deprecated)
define('MS3C_DB_SWEEP_ALWAYS_ACROSS', false);
define('MS3C_DB_USE_NEW_LINK', false);

// Extensions / Plugins
// CMS:
define('MS3C_CMS_TYPE', 'Magento');	// Alternatives: None, Typo3, OXID, Magento, Shopware

/// For Typo3:
define('MS3C_TYPO3_RELEASE', '7'); // Alternatives: 6, 7
define('MS3C_TYPO3_TYPE', 'FX'); // Alternatives: FX, PI

/// For OXID:
define('MS3C_OXID_ONLY', false);	// Can be omitted if not OXID ONLY

/// For Magento:
define('MS3C_MAGENTO_ONLY', true);	// Can be omitted

/// For Shopware:
define('MS3C_SHOPWARE_ONLY', false);	// Can be omitted

// SMZ
define('MS3C_NO_SMZ', false); // Can be omitted if SMZ is used

// RealURL/Pivot:
define('MS3C_REALURL_SHOP_CHECK_TYPE', 'None');	// Alternatives: None, SysLanguageUid, ShopId, ContextId
define('RealURLMap_TABLE', 'RealURLMap');	// Can be omitted if not using Pivot mapping table
// Shop:
define('MS3C_SHOP_SYSTEM', 'None');	// Alternatives: None, tt_products (requires Typo3)
define('MS3C_SHOP_USE_ORDER_BILLING_ADDRESS', false);
//define('MS3C_OVERWRITE_BASKET_MAIL_RECV', '');	// For testing, omit in production
//define('MS3C_OVERWRITE_MAIL_RECV', '');	// For testing, omit in production
// OCI:
define('MS3C_ENABLE_OCI', false);	// Can be omitted if OCI is not used (requires tt_products)

// Search:
define('MS3C_SEARCH_BACKEND', 'None');	// Alternatives: None, ElasticSearch, MySQL
define('MS3C_QUICK_COMPLETE', false);

// Customization:
define('MS3C_DEFAULT_FROM_EMAIL', 'noreply@goodson.at');

// disable services 1 = off, 0 = on
define('MS3C_DISABLEREQUEST_SQL', 0);
define('MS3C_DISABLEREQUEST_MEDIA', 0);
define('MS3C_ALLOWCREATE_SQL', 0);

// Email list for Notifications read out of the dataTransfer Log file
define('MS3C_LOG_NOTIFICATION_ADDRESSES', '');
define('MS3C_LOG_EMAIL_SENDER', 'importMaster@goodson.at');

// For Scheduler tasks
define('MS3C_TASK_MAIL_RECEIVER','');
define('MS3C_TASK_NOTIFY_ON_SUCCESS', false);
define('MS3C_PHP_BINARY', 'php5');
