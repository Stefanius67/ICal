<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\iCalRecurrenceRule;
use SKien\iCal\iCalTimezone;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalRecurrenceRule class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalRecurrenceRuleTest extends TestCase
{
    protected string $strOldTZ = '';

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
    }

    /**
     */
    public function tearDown() : void
    {
        if (!empty($this->strOldTZ)) {
            date_default_timezone_set($this->strOldTZ);
        }
    }

    /**
     * @dataProvider providerTestCases
     */
    public function test_getDateList(string $strMsg, string $strRRule, string $strStart, string $strTimezone, string $strMax, array $aExpected) : void
    {
        $oICal = new iCalendar();

        if (!empty($strTimezone)) {
            date_default_timezone_set($strTimezone);

            $oTimezone = new iCalTimezone($oICal);
            $oTimezone->fromTimezone($strTimezone, mktime(0,0,0,1,1,1970), mktime(0,0,0,31,12,2030));
            $oICal->addTimezone($oTimezone);
        }

        $dtStart = new \DateTime($strStart);
        $dtMax = null;
        if (!empty($strMax)) {
            $dtMax = new \DateTime($strMax);
        }
        $oRRule = new iCalRecurrenceRule($oICal, $strRRule);
        $aList = $oRRule->getDateList($dtStart->getTimestamp(), $dtMax ? $dtMax->getTimestamp() : 0, $strTimezone);
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aList = array_map($func, $aList);
        $this->assertEquals($aExpected, $aList, $strMsg);
    }

    public function providerTestCases() : array
    {
        $strJSON = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'testdata' . DIRECTORY_SEPARATOR . 'RRuleTestCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    /**
     * @dataProvider providerParamError
     */
    public function test_paramError(string $strMsg, string $strRRule, string $strStart, string $strTimezone, string $strMax, string $strLevel) : void
    {
        $oICal = new iCalendar();

        if (!empty($strTimezone)) {
            date_default_timezone_set($strTimezone);

            $oTimezone = new iCalTimezone($oICal);
            $oTimezone->fromTimezone($strTimezone, mktime(0,0,0,1,1,1970), mktime(0,0,0,31,12,2030));
            $oICal->addTimezone($oTimezone);
        }

        $dtStart = new \DateTime($strStart);
        $dtMax = null;
        if (!empty($strMax)) {
            $dtMax = new \DateTime($strMax);
        }
        $oRRule = new iCalRecurrenceRule($oICal, $strRRule);
        $oRRule->getDateList($dtStart->getTimestamp(), $dtMax ? $dtMax->getTimestamp() : 0, $strTimezone);
        $this->assertArrayHasKey($strLevel, $oICal->getLogCount(), $strMsg);
    }

    public function providerParamError() : array
    {
        $strJSON = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'testdata' . DIRECTORY_SEPARATOR . 'RRuleParamError.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    public function test_addExcludeDate() : void
    {
        $oICal = new iCalendar();

        $strTimezone = 'America/New_York';
        date_default_timezone_set($strTimezone);
        $oTimezone = new iCalTimezone($oICal);
        $oTimezone->fromTimezone($strTimezone, mktime(0,0,0,1,1,1970), mktime(0,0,0,31,12,2030));
        $oICal->addTimezone($oTimezone);

        $dtStart = new \DateTime('19971020T090000');
        $oRRule = new iCalRecurrenceRule($oICal, 'FREQ=DAILY;INTERVAL=2;UNTIL=19971102T000000Z');

        $dtExclude1 = new \DateTime('19971024T090000');
        $oRRule->addExcludeDate($dtExclude1->getTimestamp());
        $dtExclude2 = new \DateTime('1997-10-30');
        $oRRule->addExcludeDate($dtExclude2->getTimestamp(), true);

        $aList = $oRRule->getDateList($dtStart->getTimestamp(), 0, $strTimezone);
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aList = array_map($func, $aList);
        $aExpected = [
            "1997-10-20 09:00:00",
            "1997-10-22 09:00:00",
            "1997-10-26 09:00:00",
            "1997-10-28 09:00:00",
            "1997-11-01 09:00:00"
        ];
        $this->assertEquals($aExpected, $aList);
    }

    public function test_setExcludeDates() : void
    {
        $oICal = new iCalendar();

        $strTimezone = 'America/New_York';
        date_default_timezone_set($strTimezone);
        $oTimezone = new iCalTimezone($oICal);
        $oTimezone->fromTimezone($strTimezone, mktime(0,0,0,1,1,1970), mktime(0,0,0,31,12,2030));
        $oICal->addTimezone($oTimezone);

        $dtStart = new \DateTime('19971020T090000');
        $oRRule = new iCalRecurrenceRule($oICal, 'FREQ=DAILY;INTERVAL=2;UNTIL=19971102T000000Z');

        $dtExclude1 = new \DateTime('19971024T090000');
        $dtExclude2 = new \DateTime('19971030T090000');
        $oRRule->setExcludeDates([$dtExclude1->getTimestamp(), $dtExclude2->getTimestamp()]);

        $aList = $oRRule->getDateList($dtStart->getTimestamp(), 0, $strTimezone);
        $func = function(int $uxts): string {
            return date('Y-m-d H:i:s', $uxts);
        };
        $aList = array_map($func, $aList);
        $aExpected = [
            "1997-10-20 09:00:00",
            "1997-10-22 09:00:00",
            "1997-10-26 09:00:00",
            "1997-10-28 09:00:00",
            "1997-11-01 09:00:00"
        ];
        $this->assertEquals($aExpected, $aList);
    }
}
