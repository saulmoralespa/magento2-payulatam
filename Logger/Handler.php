<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 07:13 AM
 */

namespace Saulmoralespa\PayuLatam\Logger;

class Handler extends  \Magento\Framework\Logger\Handler\Base
{
    protected $fileName = '/var/log/payulatam/info.log';
    protected $loggerType = \Monolog\Logger::INFO;
}