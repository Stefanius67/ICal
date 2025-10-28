<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\Writer;
use SKien\iCal\iCalAlarm;
use SKien\iCal\iCalEvent;
use SKien\iCal\iCalHelper;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalAlarm class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalAlarmTest extends TestCase
{
    use iCalHelper;

    protected iCalendar $oICal;
    protected iCalEvent $oEvent;
    protected iCalAlarm $oAlarm;
    protected Writer $oWriter;
    protected string $strOldTZ = '';

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->oICal = new iCalendar();
        $this->oEvent = new iCalEvent($this->oICal);
        $this->oAlarm = $this->oEvent->createAlarm();
        $this->oAlarm->setAction(iCalAlarm::DISPLAY);
        $this->oWriter = new Writer($this->oICal);
    }

    /**
     */
    public function tearDown() : void
    {
        if (!empty($this->strOldTZ)) {
            date_default_timezone_set($this->strOldTZ);
        }
    }

    public function test_setAction() : void
    {
        $this->assertEquals(iCalAlarm::DISPLAY, $this->oAlarm->getAction());
        $this->fetchAndAssert('strAction', 'ACTION', iCalAlarm::DISPLAY);
        // critical error because no trigger is set so far!
        $this->oAlarm->validate();
        $aLogCount = $this->oICal->getLogCount();
        $this->assertArrayHasKey('critical', $aLogCount);
        $this->assertEquals(1, $aLogCount['critical']);
    }

    public function test_setTrigger() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $dtTrigger = new \DateTime('2025-10-11 19:30:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oEvent->setDuration(5400);
        $this->oAlarm->setTrigger('-PT30M', 'START');
        $this->oEvent->validate();
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(-1800, $aData['iTrigger']);
        $this->assertEquals($dtTrigger->getTimestamp(), $this->oAlarm->getTriggerTime());
        $this->assertEquals('START', $aData['strRelated']);
        $this->assertEquals('START', $this->oAlarm->relatesTo());
        $this->assertStringContainsString('TRIGGER;RELATED=START:-PT30M', $strData);
    }

    public function test_setTriggerTime1() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $dtTrigger = new \DateTime('2025-10-11 19:30:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oEvent->setDuration(5400);
        $this->oAlarm->setTriggerTime($dtTrigger->getTimestamp());
        $this->oEvent->validate();
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(-1800, $aData['iTrigger']);
        $this->assertEquals($dtTrigger->getTimestamp(), $this->oAlarm->getTriggerTime());
        $this->assertStringContainsString('TRIGGER;VALUE=DATE-TIME:' . gmdate('YmdTHisZ', $dtTrigger->getTimestamp()), $strData);
    }

    public function test_setTriggerTime2() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $dtTrigger = new \DateTime('2025-10-11 19:30:00');
        $this->oEvent->setStart($dtStart);
        $this->oEvent->setDuration(5400);
        $this->oAlarm->setTriggerTime($dtTrigger);
        $this->oEvent->validate();
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(-1800, $aData['iTrigger']);
        $this->assertEquals($dtTrigger->getTimestamp(), $this->oAlarm->getTriggerTime());
        $this->assertStringContainsString('TRIGGER;VALUE=DATE-TIME:' . gmdate('YmdTHisZ', $dtTrigger->getTimestamp()), $strData);
    }

    public function test_setTriggerFrom() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $dtEnd = new \DateTime('2025-10-11 21:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oEvent->setEnd($dtEnd->getTimestamp());
        $this->oAlarm->setTriggerFrom(60, 'END');
        $this->oEvent->validate();
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(60, $aData['iTrigger']);
        $strRelated = '';
        $this->assertEquals(60, $this->oAlarm->getTriggerFrom($strRelated));
        $this->assertEquals('END', $strRelated);
        $this->assertEquals('END', $this->oAlarm->relatesTo());
        $this->assertStringContainsString('TRIGGER;RELATED=END:PT1M', $strData);
    }

    public function test_setTriggerFromMissingStart() : void
    {
        $this->oAlarm->setTriggerFrom(-1800, 'START');
        $this->oEvent->validate();
        $aLogCount = $this->oICal->getLogCount();
        $this->assertArrayHasKey('critical', $aLogCount);
        $this->assertEquals(1, $aLogCount['critical']);
    }

    public function test_setTriggerFromMissingEnd() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oAlarm->setTriggerFrom(-600, 'END');
        $this->oEvent->validate();
        $aLogCount = $this->oICal->getLogCount();
        $this->assertArrayHasKey('critical', $aLogCount);
        $this->assertEquals(1, $aLogCount['critical']);
    }

    public function test_setRepeatIntervalInt() : void
    {
        $this->oAlarm->setRepeatInterval(600);
        $this->oAlarm->setRepeatCount(3);
        $this->assertEquals(600, $this->oAlarm->getRepeatInterval());
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(600, $aData['iRepeatInterval']);
        $this->assertStringContainsString('DURATION:PT10M', $strData);
    }

    public function test_setRepeatIntervalString() : void
    {
        $this->oAlarm->setRepeatInterval('PT10M');
        $this->oAlarm->setRepeatCount(3);
        $this->assertEquals(600, $this->oAlarm->getRepeatInterval());
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(600, $aData['iRepeatInterval']);
        $this->assertStringContainsString('DURATION:PT10M', $strData);
    }

    public function test_setRepeatCount() : void
    {
        $this->oAlarm->setRepeatInterval('PT10M');
        $this->oAlarm->setRepeatCount(3);
        $this->assertEquals(3, $this->oAlarm->getRepeatCount());
        $this->fetchAndAssert('iRepeatCount', 'REPEAT', 3);
    }

    public function test_setRepeatIntervalWithoutCount() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oAlarm->setTrigger('-PT30M', 'START');
        $this->oAlarm->setRepeatInterval(600);
        // $this->oAlarm->setRepeatCount(3);
        $this->oEvent->validate();
        $aLogCount = $this->oICal->getLogCount();
        $this->assertArrayHasKey('critical', $aLogCount);
        $this->assertEquals(1, $aLogCount['critical']);
    }

    public function test_setRepeatCountWithoutInterval() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oAlarm->setTrigger('-PT30M', 'START');
        // $this->oAlarm->setRepeatInterval(600);
        $this->oAlarm->setRepeatCount(3);
        $this->oEvent->validate();
        $aLogCount = $this->oICal->getLogCount();
        $this->assertArrayHasKey('critical', $aLogCount);
        $this->assertEquals(1, $aLogCount['critical']);
    }

    protected function fetchAndAssert($strDataField, $strProperty, $value) : void
    {
        $aData = $this->oAlarm->fetchData();
        $this->oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals($value, $aData[$strDataField]);
        $strTest = $this->oWriter->foldLine($strProperty . ':' . $value);
        $this->assertStringContainsString($strTest, $strData);
    }

}

