<?php

declare(strict_types=1);

namespace Model\Participant\Payment;

use Consistence\Enum\Enum;

/**
 * @method string getValue()
 */
final class EventType extends Enum
{
    public const CAMP      = 'camp';
    public const GENERAL   = 'general';
    public const EDUCATION = 'education';

    public static function CAMP() : self
    {
        return self::get(self::CAMP);
    }

    public static function GENERAL() : self
    {
        return self::get(self::GENERAL);
    }

    public static function EDUCATION() : self
    {
        return self::get(self::EDUCATION);
    }

    public function toString() : string
    {
        return $this->getValue();
    }
}
