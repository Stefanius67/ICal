<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Class representing a single todo element of an iCalendar (VTODO)
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.2
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalToDo extends iCalComponent implements iCalAlarmParentInterface
{
    /**
     * Values for the status property for todo's
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.1.11
     */
    /** The todo item has been cancelled     */
    public const STAT_TODO_CANCELLED        = 'CANCELLED';
    /** The todo item has been completed     */
    public const STAT_TODO_COMPLETED        = 'COMPLETED';
    /** The todo item is currently in progress     */
    public const STAT_TODO_IN_PROCESS       = 'IN-PROCESS';
    /** The todo item is needs action (... not started so far)     */
    public const STAT_TODO_NEEDS_ACTION     = 'NEEDS-ACTION';

    /** @var int    unix timestamp the todo is due to be completed         */
    protected ?int $uxtsDue = null;
    /** @var int    unix timestamp the todo has been completed         */
    protected ?int $uxtsCompleted = null;
    /** @var int    percent complete     */
    protected ?int $iPercentComplete = null;

    /**
     * Creates a new todo instance.
     * @param iCalendar $oICalendar  The iCalendar instance the todo belongs to.
     */
    public function __construct(iCalendar $oICalendar)
    {
        parent::__construct('VTODO', $oICalendar);
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
        } else {
            if ($this->hasRecurrentItems()) {
                $this->oICalendar->log(LogLevel::WARNING, 'VTODO: For recurent TODO a start date-time MUST be set (RRULE will be ignored)!');
                $this->strRRule = '';
                $this->aRDate = [];
            }
        }
    }

    /**
     * Returns an event as associative array.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::fetchData()
     */
    public function fetchData() : array
    {
        $aValues = [
            'dateBegin'             => $this->uxtsStart ? date('Y-m-d', $this->uxtsStart) : '',
            'timeBegin'             => $this->uxtsStart ? date('H:i:s', $this->uxtsStart) : '',
            'dtBegin'               => $this->uxtsStart ? date('Y-m-d H:i:s', $this->uxtsStart) : '',
            'uxtsBegin'             => $this->uxtsStart,
            'dateDue'               => $this->uxtsDue ? date('Y-m-d', $this->uxtsDue) : '',
            'timeDue'               => $this->uxtsDue ? date('H:i:s', $this->uxtsDue) : '',
            'dtDue'                 => $this->uxtsDue ? date('Y-m-d H:i:s', $this->uxtsDue) : '',
            'uxtsDue'               => $this->uxtsDue,
            'iDuration'             => $this->iDuration,
            'iPercentComplete'      => $this->iPercentComplete,
            'dtCompleted'           => $this->uxtsCompleted ? date('Y-m-d H:i:s', $this->uxtsCompleted) : '',
            'uxtsCompleted'         => $this->uxtsCompleted,
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
     * Sets the due date-time of the todo.
     * The value can be a unix timestamp or a DateTime instance.
     * @param int|\DateTime|null $due    unix timestamp or DateTime the todo is due to be completed.
     */
    public function setDue($due) : void
    {
        if ($due instanceof \DateTime) {
            $this->uxtsDue = $due->getTimestamp();
        } else {
            $this->uxtsDue = $due;
        }
    }

    /**
     * Gets the due date-time of the todo.
     * @return int  unix timestamp
     */
    public function getDue() : ?int
    {
        return $this->uxtsDue;
    }

    /**
     * Gets the end date-time of the todo.
     * Since the todo has no explicit end date-time, the due date-time is returned.
     * @return int  unix timestamp
     */
    public function getEnd() : ?int
    {
        return $this->getDue();
    }

    /**
     * Sets the completed date-time of the todo.
     * The value can be passed as unix timestamp or as DateTime instance.
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
     * Gets the completed date-time of the todo.
     * @return int  unix timestamp
     */
    public function getCompleted() : ?int
    {
        return $this->uxtsCompleted;
    }

    /**
     * Sets the percentage the todo is complete.
     * Only integer values between 0 and 100 are accepted.
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
     * Gets the percentage the todo is complete.
     * @return int  percentage 0 ... 100
     */
    public function getPercentComplete() : ?int
    {
        return $this->iPercentComplete;
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

        $oWriter->addProperty('RRULE', $this->strRRule, false);

        $oWriter->addProperty('SUMMARY', $this->strSubject);
        $oWriter->addProperty('LOCATION', $this->strLocation);
        $oWriter->addProperty('COMMENT', $this->strComment);
        $oWriter->addProperty('CATEGORIES', $this->strCategories, false);
        $oWriter->addProperty('PRIORITY', (string) $this->iPriority, false);
        $oWriter->addDescription($this->strDescription, $this->strHtmlDescription);
        $oWriter->addOrganizer($this->strOrganizerName, $this->strOrganizerEMail);

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
