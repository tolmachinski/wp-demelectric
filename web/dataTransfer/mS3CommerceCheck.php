<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('MS3C_EXT_ROOT', realpath(dirname(dirname(__FILE__))));
// Must not include dataTransfer! Check tool loads it on demand
require_once(MS3C_EXT_ROOT . '/../vendor/ms3commerce/dataTransfer/mS3CommerceCheck.php');
