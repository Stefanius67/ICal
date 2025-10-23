<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\Writer;
use SKien\iCal\iCalTimezone;
use SKien\iCal\iCalTimezoneProp;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalTimezoneProp class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalTimezonePropTest extends TestCase
{
    protected iCalendar $oICal;
    protected iCalTimezone $oTimezone;
    protected Writer $oWriter;

    /**
     */
    public function setUp() : void
    {
        $this->oICal = new iCalendar();
        $this->oTimezone = new iCalTimezone($this->oICal);
        $this->oWriter = new Writer($this->oICal);
    }

    public function test_getType() : void
    {
        $oICalTZ = iCalTimezone::fromFile(__DIR__ . '/testdata/NewYork.txt', $this->oICal);
        $aProps = $oICalTZ->getTimezoneProps();
        $this->assertEquals(iCalTimezoneProp::STANDARD, $aProps[0]->getType());
    }

    public function test_setgetName() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setName('Testname');
        $this->assertEquals('Testname', $oProp->getName());
    }

    public function test_setStartStringBeforeOffsetTo() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250327T020000');
        $this->assertEquals(gmmktime(2, 0, 0, 3, 27, 2025), $oProp->getStart());
    }

    public function test_setStartStringAfterOffsetTo() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setOffsetTo('-0200');
        $oProp->setStart('20250327T020000');
        $this->assertEquals(gmmktime(4, 0, 0, 3, 27, 2025), $oProp->getStart());
    }

    public function test_setStartInt() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart(gmmktime(2, 0, 0, 3, 27, 2025));
        $this->assertEquals(gmmktime(2, 0, 0, 3, 27, 2025), $oProp->getStart());
    }

    public function test_setOffsetFromString() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setOffsetFrom('-0200');
        $this->assertEquals('-0200', $oProp->getOffsetFrom());
    }

    public function test_setOffsetFromInt() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setOffsetFrom(-7200);
        $this->assertEquals('-0200', $oProp->getOffsetFrom());
    }

    public function test_setOffsetToString() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setOffsetTo('-0200');
        $this->assertEquals('-0200', $oProp->getOffsetTo());
    }

    public function test_setOffsetToInt() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setOffsetTo(-7200);
        $this->assertEquals('-0200', $oProp->getOffsetTo());
    }

    public function test_setOffsetToAfterStart() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250327T020000');
        $this->assertEquals(gmmktime(2, 0, 0, 3, 27, 2025), $oProp->getStart());
        $oProp->setOffsetTo('-0200');
        $this->assertEquals(gmmktime(4, 0, 0, 3, 27, 2025), $oProp->getStart());
    }

    public function test_setOffsetToAfterRDate() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250327T020000');
        $oProp->addRDate('20260328T020000');
        $aExpected = [
            gmmktime(2, 0, 0, 3, 27, 2025),
            gmmktime(2, 0, 0, 3, 28, 2026),
        ];
        $this->assertEquals($aExpected, $oProp->getRecurrentDates());
        $oProp->setOffsetTo('-0200');
        $aExpected = [
            gmmktime(4, 0, 0, 3, 27, 2025),
            gmmktime(4, 0, 0, 3, 28, 2026),
        ];
        $this->assertEquals($aExpected, $oProp->getRecurrentDates());
    }

    public function test_setOffsetToAfterExDate() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250101T020000');
        $oProp->setRRule('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3;UNTIL=20280401T000000Z');
        $oProp->addExcludeDate(gmmktime(2, 0, 0, 3, 29, 2026));
        $oProp->addExcludeDate('2027-03-28 02:00:00');
        $oProp->setOffsetTo('-0200');
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aDates = array_map($func, $oProp->getRecurrentDates());

        $aExpected = [
            '2025-03-30 04:00:00',
            '2028-03-26 04:00:00',
        ];
        $this->assertEquals($aExpected, $aDates);
    }

    public function test_setRRule() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setRRule('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3');
        $this->assertEquals('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3', $oProp->getRRule());
    }

    public function test_addRDate() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250327T020000');
        $oProp->addRDate(gmmktime(2, 0, 0, 3, 28, 2026));
        $aExpected = [
            gmmktime(2, 0, 0, 3, 27, 2025),
            gmmktime(2, 0, 0, 3, 28, 2026),
        ];
        $this->assertEquals($aExpected, $oProp->getRecurrentDates());
    }

    public function test_addExcludeDate() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250101T020000');
        $oProp->setRRule('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3;UNTIL=20280401T000000Z');
        $oProp->addExcludeDate(gmmktime(2, 0, 0, 3, 29, 2026));
        $oProp->addExcludeDate('2027-03-28 02:00:00');
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aDates = array_map($func, $oProp->getRecurrentDates());

        $aExpected = [
            '2025-03-30 02:00:00',
            '2028-03-26 02:00:00',
        ];
        $this->assertEquals($aExpected, $aDates);
    }

    public function test_getRecurrentDates() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250101T020000');
        $oProp->setRRule('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3;UNTIL=20270330T000000Z');
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aDates = array_map($func, $oProp->getRecurrentDates());

        $aExpected = [
            '2025-03-30 02:00:00',
            '2026-03-29 02:00:00',
            '2027-03-28 02:00:00',
        ];
        $this->assertEquals($aExpected, $aDates);
    }

    public function test_writeData() : void
    {
        $oProp = new iCalTimezoneProp($this->oTimezone, iCalTimezoneProp::DAYLIGHT);
        $oProp->setStart('20250101T020000');
        $oProp->setName('TestTZ');
        $oProp->setOffsetFrom('-0200');
        $oProp->setOffsetTo('-0100');
        $oProp->addRDate('20260328T020000');
        $oProp->addExcludeDate('2027-03-28 02:00:00');
        $oProp->setRRule('FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3;UNTIL=20270330T000000Z');
        $strTimezone = <<<TZ_DEF
            BEGIN:DAYLIGHT
            TZOFFSETFROM:-0200
            TZOFFSETTO:-0100
            TZNAME:TestTZ
            DTSTART:20250101T030000
            RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3;UNTIL=20270330T000000Z
            END:DAYLIGHT
            TZ_DEF;
        $strTimezone = str_replace("\r", "", $strTimezone);
        $oProp->writeData($this->oWriter);
        $strData = trim($this->oWriter->getBuffer());
        $this->assertEquals(trim($strTimezone), trim($strData));
    }
}

