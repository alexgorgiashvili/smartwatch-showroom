<?php

namespace Tests\Unit;

use App\Support\GeorgianTransliterator;
use PHPUnit\Framework\TestCase;

class GeorgianTransliteratorTest extends TestCase
{
    public function testItTransliteratesCourierCityPathsWithoutGeorgianCharacters(): void
    {
        $this->assertSame('Tbilisi > Kojori', GeorgianTransliterator::transliterate('თბილისი > კოჯორი'));
        $this->assertSame('Stepantsminda', GeorgianTransliterator::transliterate('სტეფანწმინდა'));
        $this->assertSame('Lagodekhi > Tsodniskari', GeorgianTransliterator::transliterate('ლაგოდეხი > ცოდნისკარი'));
        $this->assertDoesNotMatchRegularExpression('/\p{Georgian}/u', GeorgianTransliterator::transliterate('ქობულეთი > ციხისძირი'));
    }
}
