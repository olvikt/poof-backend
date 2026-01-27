<?php

namespace App\Support;

class UaPlural
{
    /**
     * Базове правило українського множинного числа
     */
    protected static function form(
        int $number,
        string $one,
        string $few,
        string $many
    ): string {
        $n = abs($number);

        if ($n % 10 === 1 && $n % 100 !== 11) {
            return $one;
        }

        if (
            in_array($n % 10, [2, 3, 4], true)
            && ! in_array($n % 100, [12, 13, 14], true)
        ) {
            return $few;
        }

        return $many;
    }

    /* =======================
     |  КОНКРЕТНІ СУТНОСТІ
     | ======================= */

    public static function days(int $number): string
    {
        return self::form($number, 'день', 'дні', 'днів');
    }

    public static function bags(int $number): string
    {
        return self::form($number, 'мішок', 'мішки', 'мішків');
    }

    public static function minutes(int $number): string
    {
        return self::form($number, 'хвилина', 'хвилини', 'хвилин');
    }

    public static function orders(int $number): string
    {
        return self::form($number, 'замовлення', 'замовлення', 'замовлень');
    }
}
