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
 * Test of the iCalEvent class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalEventTest extends TestCase
{
    use iCalHelper;

    protected iCalendar $oICal;
    protected iCalEvent $oEvent;
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

    public function test_setSubject() : void
    {
        $this->oEvent->setSubject('Testsubject');
        $this->assertEquals('Testsubject', $this->oEvent->getSubject());
        $this->fetchAndAssert('strSubject', 'SUMMARY', 'Testsubject');
    }

    public function test_setUID() : void
    {
        $this->oEvent->setUID('unique.id');
        $this->assertEquals('unique.id', $this->oEvent->getUID());
        $this->fetchAndAssert('strUID', 'UID', 'unique.id');
    }

    public function test_setDescription() : void
    {
        $strDescr  = 'some Testdescription. This linebreak is at pos 74 for test:   \n';
        $strDescr .= 'This iCal line and have to be folded!\n';
        $strDescr .= '... and more to produce at least 2 linebreaks in the resulting output.';
        $this->oEvent->setDescription($strDescr);
        $this->assertEquals($strDescr, $this->oEvent->getDescription());
        $this->fetchAndAssert('strDescription', 'DESCRIPTION', $strDescr);
    }

    public function test_setStart() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(20, 0, 0, 10, 11, 2025), $this->oEvent->getStart());
        $this->assertEquals('2025-10-11 20:00:00', $aData['dtBegin']);
        $this->assertEquals('2025-10-11', $aData['dateBegin']);
        $this->assertEquals('20:00:00', $aData['timeBegin']);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20251011T200000', $strData);
    }

    public function test_setDuration() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oEvent->setDuration(5400);
        $this->oEvent->validate();
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(5400, $this->oEvent->getDuration());
        $this->assertEquals('2025-10-11 21:30:00', $aData['dtEnd']);
        $this->assertEquals('2025-10-11', $aData['dateEnd']);
        $this->assertEquals('21:30:00', $aData['timeEnd']);
        $this->assertEquals(5400, $aData['iDuration']);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20251011T213000', $strData);
    }

    public function test_setEnd() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $dtEnd = new \DateTime('2025-10-11 21:30:00');
        $this->oEvent->setEnd($dtEnd->getTimestamp());
        $this->oEvent->validate();
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(21, 30, 0, 10, 11, 2025), $this->oEvent->getEnd());
        $this->assertEquals('2025-10-11 21:30:00', $aData['dtEnd']);
        $this->assertEquals('2025-10-11', $aData['dateEnd']);
        $this->assertEquals('21:30:00', $aData['timeEnd']);
        $this->assertEquals(5400, $aData['iDuration']);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20251011T213000', $strData);
    }

    public function test_setLastModified() : void
    {
        $dtMod = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setLastModified($dtMod->getTimestamp());
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(20, 0, 0, 10, 11, 2025), $this->oEvent->getLastModified());
        $this->assertEquals('2025-10-11 20:00:00', $aData['dtLastModified']);
        $this->assertStringContainsString('LAST-MODIFIED:20251011T180000Z', $strData);
    }

    public function test_setAllDay() : void
    {
        $dtStart = new \DateTime('2025-10-11');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $this->oEvent->setAllDay(true);
        $this->oEvent->validate();
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(true, $this->oEvent->getAllDay());
        $this->assertEquals('2025-10-11 00:00:00', $aData['dtBegin']);
        $this->assertEquals('2025-10-11', $aData['dateBegin']);
        $this->assertStringContainsString('DTSTART;VALUE=DATE;TZID=Europe/Berlin:20251011', $strData);
    }

    public function test_setPriority() : void
    {
        $this->oEvent->setPriority(1);
        $this->assertEquals(1, $this->oEvent->getPriority());
        $this->fetchAndAssert('iPriority', 'PRIORITY', 1);
    }

    public function test_setCategories() : void
    {
        $this->oEvent->setCategories('Category1');
        $this->oEvent->setCategories('Category2');
        $this->assertEquals('Category1,Category2', $this->oEvent->getCategories());
        $this->fetchAndAssert('strCategories', 'CATEGORIES', 'Category1,Category2');
    }

    public function test_setLocation() : void
    {
        $this->oEvent->setLocation('The location');
        $this->assertEquals('The location', $this->oEvent->getLocation());
        $this->fetchAndAssert('strLocation', 'LOCATION', 'The location');
    }

    public function test_setState() : void
    {
        $this->oEvent->setState(iCalEvent::STATE_CONFIRMED);
        $this->assertEquals(iCalEvent::STATE_CONFIRMED, $this->oEvent->getState());
        $this->fetchAndAssert('strState', 'STATUS', iCalEvent::STATE_CONFIRMED);
    }

    public function test_setTransparency() : void
    {
        $this->oEvent->setTransparency(iCalEvent::TRANSP_OPAQUE);
        $this->assertEquals(iCalEvent::TRANSP_OPAQUE, $this->oEvent->getTransparency());
        $this->fetchAndAssert('strTrans', 'TRANSP', iCalEvent::TRANSP_OPAQUE);
    }

    public function test_setClassification() : void
    {
        $this->oEvent->setClassification('PRIVATE');
        $this->assertEquals('PRIVATE', $this->oEvent->getClassification());
        $this->fetchAndAssert('strClassification', 'CLASS', 'PRIVATE');
    }

    public function test_setOrganizer() : void
    {
        $this->oEvent->setOrganizer('Kientzler, Stefan', 'mail@organizer.de');
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals('Kientzler, Stefan', $aData['strOrganizerName']);
        $this->assertEquals('mail@organizer.de', $aData['strOrganizerEMail']);
        $this->assertStringContainsString('ORGANIZER;CN="Kientzler, Stefan":mailto:mail@organizer.de', $strData);
    }

    public function test_addAttendee() : void
    {
        $aAttendees = [
            'attendee1@test.de',
            'attendee2@test.de',
        ];
        $this->oEvent->addAttendee($aAttendees[0]);
        $this->oEvent->addAttendee($aAttendees[1]);
        $this->assertEquals($aAttendees, $this->oEvent->getAttendees());
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertStringContainsString('ATTENDEE:mailto:attendee1@test.de', $strData);
        $this->assertStringContainsString('ATTENDEE:mailto:attendee2@test.de', $strData);
    }

    public function test_getAlarm() : void
    {
        $this->assertEquals(null, $this->oEvent->getAlarm());
        $oAlarm = $this->oEvent->createAlarm();
        $this->assertEquals($oAlarm, $this->oEvent->getAlarm());
        $oAlarm->setAction(iCalAlarm::DISPLAY);
        $oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertMatchesRegularExpression('/^BEGIN:VALARM(?s).*END:VALARM$/', $strData);
        $this->assertStringContainsString('ACTION:DISPLAY', $strData);
    }

    public function test_writeData() : void
    {
        $oAlarm = $this->oEvent->createAlarm();
        $oAlarm->setAction(iCalAlarm::DISPLAY);
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = trim($this->oWriter->getBuffer());
        $this->assertMatchesRegularExpression('/^BEGIN:VEVENT(?s).*END:VEVENT$/', $strData);
    }

    public function test_validateNoStart() : void
    {
        $this->oEvent->validate();
        $this->assertGreaterThan(0, $this->oICal->getLogCount()['critical']);
    }

    protected function fetchAndAssert($strDataField, $strProperty, $value) : void
    {
        $aData = $this->oEvent->fetchData();
        $this->oEvent->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals($value, $aData[$strDataField]);
        $strTest = $this->oWriter->foldLine($strProperty . ':' . $value);
        $this->assertStringContainsString($strTest, $strData);
    }
}

