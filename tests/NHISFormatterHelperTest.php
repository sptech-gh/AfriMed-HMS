<?php

use PHPUnit\Framework\TestCase;

class NHISFormatterHelperTest extends TestCase
{
    /** @test */
    public function nhis_date_formats_dates_as_dd_mm_yyyy()
    {
        $this->assertSame('01/01/2025', nhis_date('2025-01-01'));
        $this->assertSame('31/12/2025', nhis_date('31-12-2025'));
    }

    /** @test */
    public function nhis_date_rejects_invalid_or_empty()
    {
        $this->expectException(InvalidArgumentException::class);
        nhis_date('');
    }

    /** @test */
    public function nhis_decimal_formats_with_two_places_and_no_negatives()
    {
        $this->assertSame('0.00', nhis_decimal(0));
        $this->assertSame('10.50', nhis_decimal(10.5));
        $this->assertSame('10.00', nhis_decimal('10'));
        $this->assertSame('15.52', nhis_decimal(15.515));
    }

    /** @test */
    public function nhis_decimal_throws_on_negative_or_null()
    {
        $this->expectException(InvalidArgumentException::class);
        nhis_decimal(-1);
    }

    /** @test */
    public function nhis_bool_returns_yes_or_no()
    {
        $this->assertSame('Yes', nhis_bool(true));
        $this->assertSame('Yes', nhis_bool(1));
        $this->assertSame('No', nhis_bool(false));
        $this->assertSame('No', nhis_bool(0));

        // String representations
        $this->assertSame('Yes', nhis_bool('yes'));
        $this->assertSame('No', nhis_bool('no'));
        $this->assertSame('Yes', nhis_bool('true'));
        $this->assertSame('No', nhis_bool('false'));
        $this->assertSame('Yes', nhis_bool('1'));
        $this->assertSame('No', nhis_bool('0'));

        // Null must throw
        $this->expectException(InvalidArgumentException::class);
        nhis_bool(null);
    }

    /** @test */
    public function nhis_gender_normalises_male_and_female_and_rejects_other()
    {
        $this->assertSame('M', nhis_gender('M'));
        $this->assertSame('M', nhis_gender('male'));
        $this->assertSame('F', nhis_gender('F'));
        $this->assertSame('F', nhis_gender('Female'));

        $this->expectException(InvalidArgumentException::class);
        nhis_gender('X');
    }
}
