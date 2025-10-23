<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Class representing a single todo element of an iCalendar (VTODO)
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.2
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalToDo extends iCalRecurrentComponent implements iCalAlarmParentInterface
{
    /** @var int    unix timestamp the todo is due to be completed         */
    protected ?int $uxtsDue = null;
    /** @var int    unix timestamp the todo has been completed         */
    protected ?int $uxtsCompleted = null;
    /** @var int    duration in seconds     */
    protected ?int $iDuration = null;
    /** @var int    percent complete     */
    protected ?int $iPercentComplete = null;
    /** @var iCalAlarm  an embedded VALARM component     */
    protected ?iCalAlarm $oAlarm = null;

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar $oICalendar)
    {
        parent::__construct('VTODO', $oICalendar, false);
    }

    /**
     * Validates a instance.
     */
    public function validate() : void
    {
        if ($this->uxtsStart !== null) {
            if ($this->iDuration !== null) {
                $this->uxtsDue = $this->addDate($this->uxtsStart, "PT{$this->iDuration}S");
            } else if ($this->uxtsDue !== null) {
                $this->iDuration = $this->calcDuration($this->uxtsStart, $this->uxtsDue);
            }
            if ($this->oAlarm !== null) {
                $this->oAlarm->validate();
            }
        }
    }

    /**
     * Returns a todo item as associative array.
     * @return array<string, mixed>
     */
    public function fetchData() : array
    {
        $aValues = [
            'dateBegin'         => $this->uxtsStart ? date('Y-m-d', $this->uxtsStart) : '',
            'timeBegin'         => $this->uxtsStart ? date('H:i:s', $this->uxtsStart) : '',
            'dtBegin'           => $this->uxtsStart ? date('Y-m-d H:i:s', $this->uxtsStart) : '',
            'uxtsBegin'         => $this->uxtsStart,
            'dateDue'           => $this->uxtsDue ? date('Y-m-d', $this->uxtsDue) : '',
            'timeDue'           => $this->uxtsDue ? date('H:i:s', $this->uxtsDue) : '',
            'dtDue'             => $this->uxtsDue ? date('Y-m-d H:i:s', $this->uxtsDue) : '',
            'uxtsDue'           => $this->uxtsDue,
            'iDuration'         => $this->iDuration,
            'iPercentComplete'  => $this->iPercentComplete,
            'dtCompleted'       => $this->uxtsCompleted ? date('Y-m-d H:i:s', $this->uxtsCompleted) : '',
            'uxtsCompleted'     => $this->uxtsCompleted,
            'dtLastModified'    => $this->uxtsLastModified ? date('Y-m-d H:i:s', $this->uxtsLastModified) : '',
            'strUID'            => $this->strUID,
            'strSubject'        => $this->strSubject,
            'strLocation'       => $this->strLocation,
            'strDescription'    => $this->strDescription,
            'iPriority'         => (string) $this->iPriority,
            'strCategories'     => $this->strCategories,
            'strState'          => $this->strState,
            'strTrans'          => $this->strTrans,
            'strOrganizerName'  => $this->strOrganizerName,
            'strOrganizerEMail' => $this->strOrganizerEMail,
            'strClassification' => $this->strClassification,
            'aAlarm'            => $this->oAlarm ? $this->oAlarm->fetchData() : [],
        ];
        $aValues = array_merge($aValues, $this->aExtProp);

        return $aValues;
    }

    /**
     * @param int $iDuration  Duration in seconds.
     */
    public function setDuration(?int $iDuration) : void
    {
        $this->iDuration = $iDuration;
    }

    /**
     * @return int  Duration in seconds
     */
    public function getDuration() : ?int
    {
        return $this->iDuration;
    }

    /**
     * @param int $uxtsDue    unix timestamp the todo is due to be completed.
     */
    public function setDue(?int $uxtsDue) : void
    {
        $this->uxtsDue = $uxtsDue;
    }

    /**
     * @return int  unix timestamp
     */
    public function getDue() : ?int
    {
        return $this->uxtsDue;
    }

    /**
     * @return int  unix timestamp
     */
    public function getEnd() : ?int
    {
        return $this->getDue();
    }

    /**
     * @param int $uxtsCompleted    unix timestamp the todo haas been completed.
     */
    public function setCompleted(?int $uxtsCompleted) : void
    {
        $this->uxtsCompleted = $uxtsCompleted;
        if ($uxtsCompleted !== null) {
            $this->iPercentComplete = 100;
        }
    }

    /**
     * @return int  unix timestamp
     */
    public function getCompleted() : ?int
    {
        return $this->uxtsCompleted;
    }

    /**
     * @param int|string|null $percentComplete
     */
    public function setPercentComplete($percentComplete) : void
    {
        if ($percentComplete !== null) {
            $iPercentComplete = is_string($percentComplete) ? intval($percentComplete) : $percentComplete;
            if ($iPercentComplete >= 0 && $iPercentComplete <= 100) {
                $this->iPercentComplete = $iPercentComplete;
            }
        }
    }

    /**
     * @return int  percentage 0 ... 100
     */
    public function getPercentComplete() : ?int
    {
        return $this->iPercentComplete;
    }

    /**
     * Adds further RDate value(s) to the recurrent list.
     * @param array<int> $aRDate
     */
    public function addRDate(array $aRDate) : void
    {
        $this->aRDate = array_merge($this->aRDate, $aRDate);
    }

    /**
     * Adds further date(s) to exclude from the recurrent list.
     * @param array<int> $aExdate
     */
    public function addExcludeDate(array $aExdate) : void
    {
        $this->aExcludeDates = array_merge($this->aExcludeDates, $aExdate);
    }

    /**
     * Checks, if the item has further, recurrent siblings.
     * @return bool
     */
    public function hasRecurrentItems() : bool
    {
        return $this->strRRule !== null;
    }

    /**
     * Creates and embed an alarm component.
     * @return iCalAlarm
     */
    public function createAlarm(): iCalAlarm
    {
        $this->oAlarm = new iCalAlarm($this);
        return $this->oAlarm;
    }

    /**
     * Gets an embedded alarm component.
     * @return iCalAlarm
     */
    public function getAlarm() : ?iCalAlarm
    {
        return $this->oAlarm;
    }

    /**
     * Write the component data to the Writer instance.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::writeData()
     */
    public function writeData(Writer $oWriter, string $strTZID = '') : void
    {
        $oWriter->addProperty('BEGIN', 'VTODO');
        $oWriter->addProperty('UID', $this->strUID ?? $this->createUID());
        $oWriter->addDateTimeProperty('DTSTAMP', time(), 'GMT');

        $oWriter->addProperty('PERCENT-COMPLETE', (string) ($this->iPercentComplete ?? 0));
        $oWriter->addDateTimeProperty('COMPLETED', $this->uxtsCompleted, 'GMT');

        $oWriter->addDateTimeProperty('DTSTART', $this->uxtsStart, $strTZID);
        if ($this->iDuration !== null) {
            $oWriter->addProperty('DURATION', $this->getDurationString($this->iDuration));
        } else {
            $oWriter->addDateTimeProperty('DUE', $this->uxtsDue, $strTZID);
        }

        $oWriter->addDateTimeProperty('LAST-MODIFIED', $this->uxtsLastModified, 'GMT');
        $oWriter->addProperty('LOCATION', $this->strLocation);
        if (!empty($this->strOrganizerName) && !empty($this->strOrganizerEMail)) {
            $aParams = ['CN' => $this->strOrganizerName];
            $strValue = 'mailto:' . $this->strOrganizerEMail;
            $oWriter->addProperty('ORGANIZER', $strValue, true, $aParams);
        }
        $oWriter->addProperty('DESCRIPTION', $this->strDescription);
        $oWriter->addProperty('SUMMARY', $this->strSubject);
        $oWriter->addProperty('COMMENT', $this->strComment);
        $oWriter->addProperty('CATEGORIES', $this->strCategories, false);
        $oWriter->addProperty('PRIORITY', (string) $this->iPriority, false);
        $oWriter->addProperty('STATUS', $this->strState, false);
        $oWriter->addProperty('CLASS', $this->strClassification, false);
        foreach ($this->aAttendee as $strAttendee) {
            $oWriter->addProperty('ATTENDEE', 'mailto:' . $strAttendee, false);
        }
        if ($this->oAlarm !== null) {
            $this->oAlarm->writeData($oWriter);
        }
        $oWriter->addProperty('END', 'VTODO');
    }
}
