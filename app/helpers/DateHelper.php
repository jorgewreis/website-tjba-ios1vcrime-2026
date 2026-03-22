<?php
declare(strict_types=1);

final class DateHelper
{
    private const ISO_FORMAT = 'Y-m-d';
    private const ALT_FORMAT = 'd/m/Y';

    public static function normalizeIsoDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat(self::ISO_FORMAT, $value);
        if ($date === false) {
            return null;
        }
        return $date->format(self::ISO_FORMAT);
    }

    public static function formatForInput(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || $value === '-' || strcasecmp($value, 'null') === 0) {
            return '';
        }
        $date = self::parseDate($value);
        if ($date === null) {
            return '';
        }
        return $date->format(self::ISO_FORMAT);
    }

    private static function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(self::ISO_FORMAT, $value);
        if ($date !== false) {
            return $date;
        }
        $date = \DateTimeImmutable::createFromFormat(self::ALT_FORMAT, $value);
        if ($date !== false) {
            return $date;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return new \DateTimeImmutable('@' . $timestamp);
    }
}
