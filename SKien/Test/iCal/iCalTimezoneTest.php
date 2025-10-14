<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\iCalTimezone;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalTimezone class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalTimezoneTest extends TestCase
{
    protected iCalendar $oICal;

    /**
     */
    public function setUp() : void
    {
        $this->oICal = new iCalendar();
    }

    public function test_fromTimezone() : void
    {
        $oTZ = new iCalTimezone($this->oICal);
        $oTZ->fromTimezone('Europe/Berlin', mktime(0,0,0,1,1,1995), mktime(0,0,0,31,12,2030));
        $this->assertIsObject($oTZ);
    }

    public function test_fromFile() : void
    {
        $oTZ = iCalTimezone::fromFile(__DIR__ . '/testdata/NewYork.txt', $this->oICal);
        $this->assertIsObject($oTZ);
        $this->assertEquals('New York City', $oTZ->getTZID());
        $this->assertEquals('America/New_York', $oTZ->getComment());
    }

    public function test_getTimezoneProps() : void
    {
        $oTZ = iCalTimezone::fromFile(__DIR__ . '/testdata/NewYork.txt', $this->oICal);
        $this->assertIsArray($oTZ->getTimezoneProps());
    }

    public function test_findTimeOffsetInt() : void
    {
        $oTZ = new iCalTimezone($this->oICal);
        $oTZ->fromTimezone('Europe/Berlin', mktime(0,0,0,1,1,1995), mktime(0,0,0,31,12,2030));
        $this->assertEquals('+0200', $oTZ->findTimeOffset(mktime(0,0,0,7,1,1999)));
    }

    public function test_findTimeOffsetString() : void
    {
        $oTZ = new iCalTimezone($this->oICal);
        $oTZ->fromTimezone('Europe/Berlin', mktime(0,0,0,1,1,1995), mktime(0,0,0,31,12,2030));
        $this->assertEquals('+0100', $oTZ->findTimeOffset('1999-11-01 12:00:00'));
    }

    public function test_buildData() : void
    {
        $oTZ = new iCalTimezone($this->oICal);
        $oTZ->fromTimezone('Europe/Berlin', mktime(0,0,0,1,1,2025), mktime(0,0,0,31,12,2025));
        $strTimezone = file_get_contents(__DIR__ . '/testdata/BerlinFromTimezone.txt');
        $this->assertEquals($strTimezone, $oTZ->buildData());
    }
}

