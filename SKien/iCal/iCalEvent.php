<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 *  Class representing a single event of an iCalendar (VEVENT)
 *
 *  @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.1
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalEvent extends iCalComponent implements iCalAlarmParentInterface
{
    /** @var int    unix timestamp event end         */
    protected ?int $uxtsEnd = null;
    /** @var bool   all day event (only the date-component of dtStart and dtEnd is used)         */
    protected bool $bAllDay = false;

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar $oICalendar)
    {
        parent::__construct('VEVENT', $oICalendar);
    }

    /**
     * Validates a instance.
     */
    public function validate() : void
    {
        if ($this->uxtsStart === null) {
            $this->oICalendar->log(LogLevel::CRITICAL, 'VEVENT: a start date-time MUST be set!');
        } else {
            if ($this->iDuration !== null) {
                $this->uxtsEnd = $this->addDate($this->uxtsStart, "PT{$this->iDuration}S");
            } else if ($this->uxtsEnd !== null) {
                $this->iDuration = $this->calcDuration($this->uxtsStart, $this->uxtsEnd);
            } else if ($this->bAllDay) {
                $this->uxtsEnd = $this->addDate($this->uxtsStart, 'P1D');
                $this->iDuration = $this->calcDuration($this->uxtsStart, $this->uxtsEnd);
            }
            if ($this->oAlarm !== null) {
                $this->oAlarm->validate();
            }
        }
    }

    /**
     * Returns an imported event as associative array.
     * @return array<string, mixed>
     */
    public function fetchData() : array
    {
        $uxtsEnd = $this->uxtsEnd;
        $aValues = [
            'dateBegin'             => $this->uxtsStart ? date('Y-m-d', $this->uxtsStart) : '',
            'timeBegin'             => $this->uxtsStart ? date('H:i:s', $this->uxtsStart) : '',
            'dtBegin'               => $this->uxtsStart ? date('Y-m-d H:i:s', $this->uxtsStart) : '',
            'uxtsBegin'             => $this->uxtsStart,
            'dateEnd'               => $this->uxtsEnd ? date('Y-m-d', $uxtsEnd) : '',
            'timeEnd'               => $this->uxtsEnd ? date('H:i:s', $uxtsEnd) : '',
            'dtEnd'                 => $this->uxtsEnd ? date('Y-m-d H:i:s', $uxtsEnd) : '',
            'uxtsEnd'               => $this->uxtsEnd,
            'iDuration'             => $this->iDuration,
            'bAllDay'               => $this->bAllDay ? '1' : '0',
            'dtLastModified'        => $this->uxtsLastModified ? date('Y-m-d H:i:s', $this->uxtsLastModified) : '',
            'strUID'                => $this->strUID,
            'strSubject'            => $this->strSubject,
            'strLocation'           => $this->strLocation,
            'strDescription'        => $this->strDescription,
            'strHtmlDescription'    => $this->strHtmlDescription,
            'iPriority'             => (string) $this->iPriority,
            'strCategories'         => $this->strCategories,
            'strState'              => $this->strState,
            'strTrans'              => $this->strTrans,
            'strOrganizerName'      => $this->strOrganizerName,
            'strOrganizerEMail'     => $this->strOrganizerEMail,
            'strClassification'     => $this->strClassification,
            'aAlarm'                => $this->oAlarm ? $this->oAlarm->fetchData() : [],
        ];
        $aValues = array_merge($aValues, $this->aExtProp);

        return $aValues;
    }

    /**
     * @param int|\DateTime|null $end    unix timestamp or DateTime of the events end.
     */
    public function setEnd($end) : void
    {
        if ($end instanceof \DateTime) {
            $this->uxtsEnd = $end->getTimestamp();
        } else {
            $this->uxtsEnd = $end;
        }
    }

    /**
     * @return int  unix timestamp
     */
    public function getEnd() : ?int
    {
        return $this->uxtsEnd;
    }

    /**
     * @param bool $bAllDay
     */
    public function setAllDay(bool $bAllDay) : void
    {
        $this->bAllDay = $bAllDay;
    }

    /**
     * @return bool true, if allday event
     */
    public function getAllDay() : bool
    {
        return $this->bAllDay;
    }

    /**
     * Write the component data to the Writer instance.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::writeData()
     */
    public function writeData(Writer $oWriter, string $strTZID = '') : void
    {
        $oWriter->addProperty('BEGIN', 'VEVENT');
        $oWriter->addProperty('UID', $this->strUID ?? $this->createUID());
        $oWriter->addDateTimeProperty('DTSTAMP', time(), 'GMT');

        $oWriter->addDateTimeProperty('DTSTART', $this->uxtsStart, $strTZID, $this->bAllDay);
        $oWriter->addDateTimeProperty('DTEND', $this->uxtsEnd, $strTZID, $this->bAllDay);
        $oWriter->addDateTimeProperty('LAST-MODIFIED', $this->uxtsLastModified, 'GMT');

        $oWriter->addProperty('RRULE', $this->strRRule, false);

        $oWriter->addProperty('SUMMARY', $this->strSubject);
        $oWriter->addProperty('LOCATION', $this->strLocation);
        $oWriter->addProperty('COMMENT', $this->strComment);
        $oWriter->addProperty('CATEGORIES', $this->strCategories, false);
        $oWriter->addProperty('PRIORITY', (string) $this->iPriority, false);
        $oWriter->addDescription($this->strDescription, $this->strHtmlDescription);
        $oWriter->addOrganizer($this->strOrganizerName, $this->strOrganizerEMail);

        $oWriter->addProperty('TRANSP', $this->strTrans, false);
        $oWriter->addProperty('STATUS', $this->strState, false);
        $oWriter->addProperty('CLASS', $this->strClassification, false);
        foreach ($this->aAttendee as $strAttendee) {
            $oWriter->addProperty('ATTENDEE', 'mailto:' . $strAttendee, false);
        }
        if ($this->oAlarm !== null) {
            $this->oAlarm->writeData($oWriter);
        }
        $oWriter->addProperty('END', 'VEVENT');
    }
}
