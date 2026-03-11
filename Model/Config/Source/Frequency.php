<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DamConsultants\Ahfproducts\Model\Config\Source;

class Frequency extends \Magento\Cron\Model\Config\Source\Frequency
{
    /**
     * @var array
     */
    protected static $_options;

    public const CRON_DAILY = 'D';

    public const EVERY_TEN_TIME = 'E';

    public const CRON_WEEKLY = 'W';

    public const CRON_MONTHLY = 'M';

    /**
     * To Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = [
                ['label' => __('Daily'), 'value' => self::CRON_DAILY],
                ['label' => __('Weekly'), 'value' => self::CRON_WEEKLY],
                ['label' => __('Monthly'), 'value' => self::CRON_MONTHLY],
                ['label' => __('Every Minutes'), 'value' => self::EVERY_TEN_TIME],
            ];
        }
        return self::$_options;
    }
}
