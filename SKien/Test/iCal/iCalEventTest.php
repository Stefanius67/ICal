<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use PHPUnit\Framework\TestCase;
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
    protected string $strOldTZ = '';

    /**
     */
    public function setUp() : void
    {
        $this->strOldTZ = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        $this->oICal = new iCalendar();
        $this->oEvent = new iCalEvent($this->oICal);
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
        $this->fetchAndAssert('strSubject', 'SUMMARY', 'Testsubject');
    }

    public function test_setUID() : void
    {
        $this->oEvent->setUID('unique.id');
        $this->fetchAndAssert('strUID', 'UID', 'unique.id');
    }

    public function test_setDescription() : void
    {
        $strDescr  = 'some Testdescription. This linebreak is at pos 74 for test:   \n';
        $strDescr .= 'This iCal line and have to be folded!\n';
        $strDescr .= '... and more to produce at least 2 linebreaks in the resulting output.';
        $this->oEvent->setDescription($strDescr);
        $this->fetchAndAssert('strDescription', 'DESCRIPTION', $strDescr);
    }

    public function test_setStart() : void
    {
        $dtStart = new \DateTime('2025-10-11 20:00:00');
        $this->oEvent->setStart($dtStart->getTimestamp());
        $aData = $this->oEvent->fetchData();
        $strData = $this->oEvent->buildData('Europe/Berlin');
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
        $strData = $this->oEvent->buildData('Europe/Berlin');
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
        $strData = $this->oEvent->buildData('Europe/Berlin');
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
        $strData = $this->oEvent->buildData('Europe/Berlin');
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
        $strData = $this->oEvent->buildData('Europe/Berlin');
        $this->assertEquals('2025-10-11 00:00:00', $aData['dtBegin']);
        $this->assertEquals('2025-10-11', $aData['dateBegin']);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin;VALUE=DATE:20251011', $strData);
    }

    public function test_setPriority() : void
    {
        $this->oEvent->setPriority(1);
        $this->fetchAndAssert('iPriority', 'PRIORITY', 1);
    }

    public function test_setCategories() : void
    {
        $this->oEvent->setCategories('Category1');
        $this->oEvent->setCategories('Category2');
        $this->fetchAndAssert('strCategories', 'CATEGORIES', 'Category1,Category2');
    }

    public function test_setLocation() : void
    {
        $this->oEvent->setLocation('The location');
        $this->fetchAndAssert('strLocation', 'LOCATION', 'The location');
    }

    public function test_setState() : void
    {
        $this->oEvent->setState(iCalEvent::STATE_CONFIRMED);
        $this->fetchAndAssert('strState', 'STATUS', iCalEvent::STATE_CONFIRMED);
    }

    public function test_setTransparency() : void
    {
        $this->oEvent->setTransparency(iCalEvent::TRANSP_OPAQUE);
        $this->fetchAndAssert('strTrans', 'TRANSP', iCalEvent::TRANSP_OPAQUE);
    }

    public function test_setOrganizer() : void
    {
        $this->oEvent->setOrganizer('Organizer Name', 'mail@organizer.de');
        $aData = $this->oEvent->fetchData();
        $strData = $this->oEvent->buildData('Europe/Berlin');
        $this->assertEquals('Organizer Name', $aData['strOrganizerName']);
        $this->assertEquals('mail@organizer.de', $aData['strOrganizerEMail']);
        $this->assertStringContainsString('ORGANIZER;CN="Organizer Name":MAILTO:mail@organizer.de', $strData);
    }

    public function test_buildData() : void
    {
        $strData = trim($this->oEvent->buildData('Europe/Berlin'));
        $this->assertMatchesRegularExpression('/^BEGIN:VEVENT(?s).*END:VEVENT$/', $strData);
    }

    public function test_validateNoStart() : void
    {
        $this->oEvent->validate();
        $this->assertGreaterThan(0, count($this->oICal->getLogCount()));
    }

    protected function fetchAndAssert($strDataField, $strProperty, $value) : void
    {
        $aData = $this->oEvent->fetchData();
        $strData = $this->oEvent->buildData('Europe/Berlin');
        $this->assertEquals($value, $aData[$strDataField]);
        $strTest = $this->foldLine($strProperty . ':' . $value);
        $this->assertStringContainsString($strTest, $strData);
    }

}

