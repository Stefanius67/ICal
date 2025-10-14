<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SKien\iCal\iCalEvent;
use SKien\iCal\iCalendar;

/**
 * Test of the main class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalendarTest extends TestCase
{
    protected string $strOldTZ = '';

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
    }

    /**
     */
    public function tearDown() : void
    {
        if (!empty($this->strOldTZ)) {
            date_default_timezone_set($this->strOldTZ);
        }
    }

    public function test_setgetEncoding() : void
    {
        $oICal = new iCalendar();
        $oICal->setEncoding('Windows-1252');
        $this->assertEquals('Windows-1252', $oICal->getEncoding());
    }

    public function test_setLogger() : void
    {
        $oICal = new iCalendar();
        $oLogger = new UnitTestLogger();

        $oICal->setLogger($oLogger);
        $oICal->read(__DIR__ . '/testdata/Invalid1.ics');

        $this->assertArrayHasKey(LogLevel::CRITICAL, $oLogger->getLog());
    }

    /**
     * @dataProvider providerTestCases
     */
    public function test_read(string $strMsg, string $strFilename, array $aXProp,  array $aExpected) : void
    {
        $oICal = new iCalendar();
        foreach ($aXProp as $aProp) {
            $oICal->defineXProperty($aProp[0], $aProp[1], $aProp[2]);
        }
        $this->assertEquals(1, $oICal->read(__DIR__ . '/testdata/' . $strFilename), $strMsg);
        $aActual = $oICal->getEvents()[0]->fetchData();
        foreach ($aExpected as $strKey => $value) {
            $this->assertEquals($value, $aActual[$strKey], $strKey . ' - ' . $strMsg);
        }
    }

    public function providerTestCases() : array
    {
        $strJSON = file_get_contents(__DIR__ . '/testdata/VEventTestCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    /**
     * @dataProvider providerNotSupportedCases
     */
    public function test_readNotSupported(string $strMsg, string $strFilename, string $strLevel) : void
    {
        $oICal = new iCalendar();
        $oICal->read(__DIR__ . '/testdata/' . $strFilename);
        $this->assertArrayHasKey($strLevel, $oICal->getLogCount(), $strMsg);
    }

    public function providerNotSupportedCases() : array
    {
        $strJSON = file_get_contents(__DIR__ . '/testdata/VEventNotSupportedCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    /**
     * @dataProvider providerInvalidCases
     */
    public function test_readInvalid(string $strMsg, string $strFilename, string $strLevel) : void
    {
        $oICal = new iCalendar();
        $oICal->read(__DIR__ . '/testdata/' . $strFilename);
        $this->assertArrayHasKey($strLevel, $oICal->getLogCount(), $strMsg);
    }

    public function providerInvalidCases() : array
    {
        $strJSON = file_get_contents(__DIR__ . '/testdata/VEventInvalidCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    public function test_forceInspectorOpen() : void
    {
        $oICal = new iCalendar();
        $oICal->forceInspectorOpen();
        $strData = $oICal->buildData();
        $this->assertStringContainsString('X-MS-OLK-FORCEINSPECTOROPEN:TRUE', $strData);
    }

    public function test_getTimezonePHP() : void
    {
        $oICal = new iCalendar();
        $this->assertEquals('Europe/Berlin', $oICal->getTimezonePHP());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_write() : void
    {
        $oICal = new iCalendar();
        $oICal->addEvent(new iCalEvent($oICal));
        ob_start();
        $strFilename = $oICal->write();
        $strEcho = ob_get_contents();
        ob_end_clean();
        $this->assertEquals('iCalendar.ics', $strFilename);
        $this->assertMatchesRegularExpression('/^BEGIN:VCALENDAR(?s).*END:VCALENDAR$/', trim($strEcho));
    }

    public function test_readRRuleEvent() : void
    {
        $oICal = new iCalendar();
        $this->assertEquals(12, $oICal->read(__DIR__ . '/testdata/Testcase6.ics'));
    }

    public function test_buildImportedEvent() : void
    {
        $oICal = new iCalendar();
        $oICal->read(__DIR__ . '/testdata/Testcase5.ics');
        $oEvent = $oICal->getEvents()[0];
        $strData = $oEvent->buildData('');
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin;VALUE=DATE:20250716', $strData);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin;VALUE=DATE:20250718', $strData);
    }
}

