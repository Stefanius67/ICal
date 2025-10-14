<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\iCalHelper;
use SKien\iCal\iCalTimezone;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalHelper trait.
 *
 * For simply access to the trait methods, we 'use' the trait directly in this
 * TestCase class.
 *
 * For all tests concerning the timezone adjustments, we use the 'Europe/Berlin'
 * timezone.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalHelperTest extends TestCase
{
    use iCalHelper;

    protected string $strOldTZ = '';
    protected iCalendar $oICal;
    protected iCalTimezone $oTZ;

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->oICal = new iCalendar();
        $this->oTZ = new iCalTimezone($this->oICal);
        $this->oTZ->fromTimezone('Europe/Berlin', gmmktime(0,0,0,1,1,1995), gmmktime(0,0,0,31,12,2030));
        $this->oCalcTimezone = $this->oTZ;
    }

    /**
     */
    public function tearDown() : void
    {
        if (!empty($this->strOldTZ)) {
            date_default_timezone_set($this->strOldTZ);
        }
    }

    public function test_subDate() : void
    {
        // At So. 26'th Oct 03:00 change from daylight to standard time (from -+0200 to +0100)
        $uxtsTest = mktime(10,0,0,10,26,2025);
        $uxtsResult = $this->subDate($uxtsTest, 'P1D');
        $this->assertEquals('2025-10-25 10:00:00', date('Y-m-d H:i:s', $uxtsResult));
    }

    public function test_calcDuration() : void
    {
        // At So. 26'th Oct 03:00 change from daylight to standard time (from -+0200 to +0100)
        $uxtsFrom = mktime(10,0,0,10,25,2025);
        $uxtsTo = mktime(10,0,0,10,26,2025);
        $iDuration = $this->calcDuration($uxtsFrom, $uxtsTo);
        $this->assertEquals(86400, $iDuration);
    }
}

