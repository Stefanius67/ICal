<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SKien\iCal\Writer;
use SKien\iCal\iCalEvent;
use SKien\iCal\iCalToDo;
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
        $oICal->read(__DIR__ . '/testdata/InvalidEvent1.ics');

        $this->assertArrayHasKey(LogLevel::CRITICAL, $oLogger->getLog());
    }

    /**
     * @dataProvider providerTestEvents
     */
    public function test_readEvents(string $strMsg, string $strFilename, array $aXProp,  array $aExpected) : void
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

    public function providerTestEvents() : array
    {
        $strJSON = file_get_contents(__DIR__ . '/testdata/VEventTestCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    /**
     * @dataProvider providerTestTodos
     */
    public function test_readToDos(string $strMsg, string $strFilename, array $aXProp,  array $aExpected) : void
    {
        $oICal = new iCalendar();
        foreach ($aXProp as $aProp) {
            $oICal->defineXProperty($aProp[0], $aProp[1], $aProp[2]);
        }
        $this->assertEquals(1, $oICal->read(__DIR__ . '/testdata/' . $strFilename), $strMsg);
        $aActual = $oICal->getToDos()[0]->fetchData();
        foreach ($aExpected as $strKey => $value) {
            if (is_array($value)) {
                foreach ($value as $strSubKey => $subvalue) {
                    $this->assertEquals($subvalue, $aActual[$strKey][$strSubKey], $strSubKey . ' - ' . $strMsg);
                }
            } else {
                $this->assertEquals($value, $aActual[$strKey], $strKey . ' - ' . $strMsg);
            }
        }
    }

    public function providerTestTodos() : array
    {
        $strJSON = file_get_contents(__DIR__ . '/testdata/VToDoTestCases.json');
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
        $strJSON = file_get_contents(__DIR__ . '/testdata/NotSupportedCases.json');
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
        $strJSON = file_get_contents(__DIR__ . '/testdata/InvalidCases.json');
        $aTestArray = json_decode($strJSON, true);

        return $aTestArray;
    }

    public function test_forceInspectorOpen() : void
    {
        $oICal = new iCalendar();
        $oICal->forceInspectorOpen();
        $oWriter = new Writer($oICal);
        $oICal->writeData($oWriter);
        $strData = $oWriter->getBuffer();
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
        $oICal->addToDo(new iCalToDo($oICal));
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
        $this->assertEquals(12, $oICal->read(__DIR__ . '/testdata/TestEventRRule.ics'));
    }

    public function test_readRRuleToDo() : void
    {
        $oICal = new iCalendar();
        $this->assertEquals(12, $oICal->read(__DIR__ . '/testdata/TestToDoRRule.ics'));
    }

    public function test_buildImportedEvent() : void
    {
        $oICal = new iCalendar();
        $oICal->read(__DIR__ . '/testdata/TestEvent5.ics');
        $oWriter = new Writer($oICal);
        $oEvent = $oICal->getEvents()[0];
        $oEvent->writeData($oWriter);
        $strData = $oWriter->getBuffer();
        $this->assertStringContainsString('DTSTART;VALUE=DATE;TZID=Europe/Berlin:20250716', $strData);
        $this->assertStringContainsString('DTEND;VALUE=DATE;TZID=Europe/Berlin:20250718', $strData);
    }
}

