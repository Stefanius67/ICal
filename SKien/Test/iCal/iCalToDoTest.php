<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
use SKien\iCal\Writer;
use SKien\iCal\iCalAlarm;
use SKien\iCal\iCalEvent;
use SKien\iCal\iCalHelper;
use SKien\iCal\iCalToDo;
use SKien\iCal\iCalendar;

/**
 * Test of the iCalToDo class.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalToDoTest extends TestCase
{
    use iCalHelper;

    protected iCalendar $oICal;
    protected iCalToDo $oToDo;
    protected string $strOldTZ = '';

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->oICal = new iCalendar();
        $this->oToDo = new iCalToDo($this->oICal);
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
        $this->oToDo->setSubject('Testsubject');
        $this->assertEquals('Testsubject', $this->oToDo->getSubject());
        $this->fetchAndAssert('strSubject', 'SUMMARY', 'Testsubject');
    }

    public function test_setUID() : void
    {
        $this->oToDo->setUID('unique.id');
        $this->assertEquals('unique.id', $this->oToDo->getUID());
        $this->fetchAndAssert('strUID', 'UID', 'unique.id');
    }

    public function test_setDescription() : void
    {
        $strDescr  = 'some Testdescription. This linebreak is at pos 74 for test:   \n';
        $strDescr .= 'This iCal line and have to be folded!\n';
        $strDescr .= '... and more to produce at least 2 linebreaks in the resulting output.';
        $this->oToDo->setDescription($strDescr);
        $this->assertEquals($strDescr, $this->oToDo->getDescription());
        $this->fetchAndAssert('strDescription', 'DESCRIPTION', $strDescr);
    }

    public function test_setStart() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oToDo->setStart($dtStart->getTimestamp());
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(20, 0, 0, 10, 11, 2025), $this->oToDo->getStart());
        $this->assertEquals('2025-10-11 20:00:00', $aData['dtBegin']);
        $this->assertEquals('2025-10-11', $aData['dateBegin']);
        $this->assertEquals('20:00:00', $aData['timeBegin']);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20251011T200000', $strData);
    }

    public function test_setDuration() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oToDo->setStart($dtStart->getTimestamp());
        $this->oToDo->setDuration(5400);
        $this->oToDo->validate();
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(5400, $this->oToDo->getDuration());
        $this->assertEquals('2025-10-11 21:30:00', $aData['dtDue']);
        $this->assertEquals('2025-10-11', $aData['dateDue']);
        $this->assertEquals('21:30:00', $aData['timeDue']);
        $this->assertEquals(5400, $aData['iDuration']);
        $this->assertStringContainsString('DURATION:PT1H30M', $strData);
    }

    public function test_setDue() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oToDo->setStart($dtStart->getTimestamp());
        $dtEnd = new \DateTime('2025-10-11 21:30:00');
        $this->oToDo->setDue($dtEnd->getTimestamp());
        $this->oToDo->validate();
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(21, 30, 0, 10, 11, 2025), $this->oToDo->getEnd());
        $this->assertEquals('2025-10-11 21:30:00', $aData['dtDue']);
        $this->assertEquals('2025-10-11', $aData['dateDue']);
        $this->assertEquals('21:30:00', $aData['timeDue']);
        $this->assertEquals(5400, $aData['iDuration']);
        $this->assertStringContainsString('DURATION:PT1H30M', $strData);
    }

    public function test_setCompleted() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oToDo->setStart($dtStart->getTimestamp());
        $dtCompleted = new \DateTime('2025-10-11 21:30:00');
        $this->oToDo->setCompleted($dtCompleted->getTimestamp());
        $this->oToDo->validate();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(21, 30, 0, 10, 11, 2025), $this->oToDo->getCompleted());
        $this->assertEquals(100, $this->oToDo->getPercentComplete());
        $this->assertStringContainsString('COMPLETED:20251011T193000Z', $strData);
    }

    public function test_setLastModified() : void
    {
        $dtMod = new \DateTime('2025-10-11 20:00:00');
        $this->oToDo->setLastModified($dtMod->getTimestamp());
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals(mktime(20, 0, 0, 10, 11, 2025), $this->oToDo->getLastModified());
        $this->assertEquals('2025-10-11 20:00:00', $aData['dtLastModified']);
        $this->assertStringContainsString('LAST-MODIFIED:20251011T180000Z', $strData);
    }

    public function test_setPriority() : void
    {
        $this->oToDo->setPriority(1);
        $this->assertEquals(1, $this->oToDo->getPriority());
        $this->fetchAndAssert('iPriority', 'PRIORITY', 1);
    }

    public function test_setCategories() : void
    {
        $this->oToDo->setCategories('Category1');
        $this->oToDo->setCategories('Category2');
        $this->assertEquals('Category1,Category2', $this->oToDo->getCategories());
        $this->fetchAndAssert('strCategories', 'CATEGORIES', 'Category1,Category2');
    }

    public function test_setLocation() : void
    {
        $this->oToDo->setLocation('The location');
        $this->assertEquals('The location', $this->oToDo->getLocation());
        $this->fetchAndAssert('strLocation', 'LOCATION', 'The location');
    }

    public function test_setState() : void
    {
        $this->oToDo->setState(iCalEvent::STATE_CONFIRMED);
        $this->assertEquals(iCalEvent::STATE_CONFIRMED, $this->oToDo->getState());
        $this->fetchAndAssert('strState', 'STATUS', iCalEvent::STATE_CONFIRMED);
    }

    public function test_setClassification() : void
    {
        $this->oToDo->setClassification('PRIVATE');
        $this->assertEquals('PRIVATE', $this->oToDo->getClassification());
        $this->fetchAndAssert('strClassification', 'CLASS', 'PRIVATE');
    }

    public function test_setOrganizer() : void
    {
        $this->oToDo->setOrganizer('Kientzler, Stefan', 'mail@organizer.de');
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
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
        $this->oToDo->addAttendee($aAttendees[0]);
        $this->oToDo->addAttendee($aAttendees[1]);
        $this->assertEquals($aAttendees, $this->oToDo->getAttendees());
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertStringContainsString('ATTENDEE:mailto:attendee1@test.de', $strData);
        $this->assertStringContainsString('ATTENDEE:mailto:attendee2@test.de', $strData);
    }

    public function test_getAlarm() : void
    {
        $this->assertEquals(null, $this->oToDo->getAlarm());
        $oAlarm = $this->oToDo->createAlarm();
        $this->assertEquals($oAlarm, $this->oToDo->getAlarm());
        $oAlarm->setAction(iCalAlarm::DISPLAY);
        $oAlarm->writeData($this->oWriter);
        $strData = $this->oWriter->getBuffer();
        $this->assertMatchesRegularExpression('/^BEGIN:VALARM(?s).*END:VALARM$/', $strData);
        $this->assertStringContainsString('ACTION:DISPLAY', $strData);
    }

    public function test_writeData() : void
    {
        $oAlarm = $this->oToDo->createAlarm();
        $oAlarm->setAction(iCalAlarm::DISPLAY);
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = trim($this->oWriter->getBuffer());
        $this->assertMatchesRegularExpression('/^BEGIN:VTODO(?s).*END:VTODO$/', $strData);
    }

    protected function fetchAndAssert($strDataField, $strProperty, $value) : void
    {
        $aData = $this->oToDo->fetchData();
        $this->oToDo->writeData($this->oWriter, 'Europe/Berlin');
        $strData = $this->oWriter->getBuffer();
        $this->assertEquals($value, $aData[$strDataField]);
        $strTest = $this->oWriter->foldLine($strProperty . ':' . $value);
        $this->assertStringContainsString($strTest, $strData);
    }
}

