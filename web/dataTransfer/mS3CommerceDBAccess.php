<?php
require_once dirname(__DIR__) . '/../vendor/autoload.php';
require '../../config/application.php';
use function Env\env;

function MS3C_DB_ACCESS(): array
{
    return array(
        'ms3magento' => array(
            'host' => env('MS3_DB_HOST'),
            'username' => env('MS3_DB_USER'),
            'password' => env('MS3_DB_PASSWORD'),
            'database' => env('MS3_DB_NAME'),
        )
    );
}
