<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('nhis_date')) {
    /**
     * Format a date into DD/MM/YYYY for NHIS.
     * Accepts strings or DateTime; throws InvalidArgumentException on failure.
     */
    function nhis_date($value)
    {
        if ($value instanceof DateTimeInterface) {
            $ts = $value;
        } else {
            $str = trim((string)$value);
            if ($str === '') {
                throw new InvalidArgumentException('nhis_date requires a non-empty date value');
            }
            $ts = date_create($str);
            if (!$ts) {
                throw new InvalidArgumentException('nhis_date could not parse date: ' . $str);
            }
        }

        $formatted = $ts->format('d/m/Y');
        if (!preg_match('/^[0-3][0-9]\/[0-1][0-9]\/[1-9][0-9]{3}$/', $formatted)) {
            throw new InvalidArgumentException('nhis_date produced invalid format: ' . $formatted);
        }
        return $formatted;
    }
}

if (!function_exists('nhis_decimal')) {
    /**
     * Format numeric input as NHIS decimal with exactly two places.
     * Throws InvalidArgumentException on null, non-numeric, or negative.
     */
    function nhis_decimal($value)
    {
        if ($value === null) {
            throw new InvalidArgumentException('nhis_decimal does not accept null');
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('nhis_decimal requires a numeric value');
        }

        $num = (float)$value;
        if ($num < 0) {
            throw new InvalidArgumentException('nhis_decimal does not accept negative values');
        }

        return number_format($num, 2, '.', '');
    }
}

if (!function_exists('nhis_bool')) {
    /**
     * Normalise any truthy/falsy input to 'Yes' or 'No'.
     */
    function nhis_bool($value)
    {
        if (is_null($value)) {
            throw new InvalidArgumentException('nhis_bool() received null \\u2014 field value is required');
        }

        // Normalise common string representations first
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['yes', 'true', '1'], true)) {
                return 'Yes';
            }
            if (in_array($normalized, ['no', 'false', '0'], true)) {
                return 'No';
            }
        }

        // Fallback to PHP truthiness rules for other scalar types
        return $value ? 'Yes' : 'No';
    }
}

if (!function_exists('nhis_gender')) {
    /**
     * Normalise gender strings to 'M' or 'F'.
     * Throws InvalidArgumentException on anything else.
     */
    function nhis_gender($value)
    {
        $str = strtolower(trim((string)$value));

        if ($str === 'm' || $str === 'male') {
            return 'M';
        }

        if ($str === 'f' || $str === 'female') {
            return 'F';
        }

        throw new InvalidArgumentException('nhis_gender unsupported value: ' . $value);
    }
}
