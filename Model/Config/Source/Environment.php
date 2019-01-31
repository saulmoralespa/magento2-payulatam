<?php
/**
 * Created by PhpStorm.
 * User: smp
 * Date: 29/01/19
 * Time: 08:12 AM
 */

namespace Saulmoralespa\PayuLatam\Model\Config\Source;


class Environment
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Development')],
            ['value' => '0', 'label' => __('Production')]
        ];
    }
}