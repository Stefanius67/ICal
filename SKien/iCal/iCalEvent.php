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
class iCalEvent extends iCalRecurrenceBase
{
    const   STATE_TENTATIVE     = 'TENTATIVE';      // undefiniert
    const   STATE_CONFIRMED     = 'CONFIRMED';      // bestätigt
    const   STATE_CANCELLED     = 'CANCELLED';      // abgesagt

    const   TRANSP_OPAQUE       = 'OPAQUE';         // geblockt, belegt
    const   TRANSP_TRANSPARENT  = 'TRANSPARENT';    // frei, nicht sichtbar

    /** @var string unique ID          */
    protected ?string $strUID = null;
    /** @var string subject          */
    protected string $strSubject = '';
    /** @var string description                  */
    protected string $strDescription = '';
    /** @var int    unix timestamp event end         */
    protected ?int $uxtsEnd = null;
    /** @var int    duration in seconds     */
    protected ?int $iDuration = null;
    /** @var int    unix timestamp last modified        */
    protected ?int $uxtsLastModified = null;
    /** @var bool   all day event (only the date-component of dtStart and dtEnd is used)         */
    protected bool $bAllDay = false;
    /** @var int    priority        */
    protected ?int $iPriority = 0;
    /** @var string categories           */
    protected string $strCategories = '';
    /** @var string location             */
    protected string $strLocation = '';
    /** @var string state of event (default: STATE_CONFIRMED)    */
    protected string $strState = self::STATE_CONFIRMED;
    /** @var string transparency (default: TRANSP_OPAQUE)        */
    protected string $strTrans = self::TRANSP_OPAQUE;
    /** @var string organizer name       */
    protected string $strOrganizerName = '';
    /** @var string organizer e-mail     */
    protected string $strOrganizerEMail = '';
    /** @var array<string,string>   additional properties that arn't included in the iCal spec (X-...)	 */
    protected array $aExtProp = [];

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar &$oICalendar)
    {
        parent::__construct($oICalendar, false);
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
            'dateBegin'         => $this->uxtsStart ? date('Y-m-d', $this->uxtsStart) : '',
            'timeBegin'         => $this->uxtsStart ? date('H:i:s', $this->uxtsStart) : '',
            'dtBegin'           => $this->uxtsStart ? date('Y-m-d H:i:s', $this->uxtsStart) : '',
            'dateEnd'           => $this->uxtsEnd ? date('Y-m-d', $uxtsEnd) : '',
            'timeEnd'           => $this->uxtsEnd ? date('H:i:s', $uxtsEnd) : '',
            'dtEnd'             => $this->uxtsEnd ? date('Y-m-d H:i:s', $uxtsEnd) : '',
            'iDuration'         => $this->iDuration,
            'bAllDay'           => $this->bAllDay ? '1' : '0',
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
            ];
        $aValues = array_merge($aValues, $this->aExtProp);

        return $aValues;
    }

    /**
     * Sets an extended property.
     * @param string $strName
     * @param string $strValue
     */
    public function setExtProperty(string $strName, string $strValue) : void
    {
        $this->aExtProp[$strName] = $strValue;
    }

    /**
     * @param string $strSubject
     */
    public function setSubject(string $strSubject) : void
    {
        $this->strSubject = $strSubject;
    }

    /**
     * @param string $strUID
     */
    public function setUID(string $strUID) : void
    {
        $this->strUID = $strUID;
    }

    /**
     * @return string
     */
    public function getUID() : string
    {
        return $this->strUID;
    }

    /**
     * @param string $strDescription
     */
    public function setDescription(?string $strDescription) : void
    {
        $this->strDescription = $strDescription ?? '';
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
     * @param int $uxtsEnd    unix timestamp of the events end.
     */
    public function setEnd(?int $uxtsEnd) : void
    {
        $this->uxtsEnd = $uxtsEnd;
    }

    /**
     * @return int  unix timestamp
     */
    public function getEnd() : ?int
    {
        return $this->uxtsEnd;
    }

    /**
     * @param int $uxtsLastModified    unix timestamp the event been last modified.
     */
    public function setLastModified(?int $uxtsLastModified) : void
    {
        $this->uxtsLastModified = $uxtsLastModified;
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
     * @param int|string $priority
     */
    public function setPriority($priority) : void
    {
        if (is_string($priority)) {
            $this->iPriority = intval($priority);
        } else {
            $this->iPriority = $priority;
        }
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
     * Set/add categories to the event.
     * The CATEGORIES property can contain multiple categories separated by comma and
     * can also be specified multiple times within an event
     * @param string $strCategories
     */
    public function setCategories(?string $strCategories) : void
    {
        if (!empty($this->strCategories)) {
            $this->strCategories .= ',';
        }
        $this->strCategories .= $strCategories ?? '';
    }

    /**
     * @param string $strLocation
     */
    public function setLocation(?string $strLocation) : void
    {
        $this->strLocation = $strLocation ?? '';
    }

    /**
     * @param string $strState
     */
    public function setState(string $strState) : void
    {
        $this->strState = $strState;
    }

    /**
     * @param string $strTrans
     */
    public function setTransparency(string $strTrans) : void
    {
        $this->strTrans = $strTrans;
    }

    /**
     * set organizer of event
     * @param string $strName
     * @param string $strEMail
     */
    public function setOrganizer(string $strName, string $strEMail) : void
    {
        $this->strOrganizerName = $strName;
        $this->strOrganizerEMail = $strEMail;
    }

    /**
     * Checks, if the event has further, recurrent events.
     * @return bool
     */
    public function hasRecurrentEvents() : bool
    {
        return $this->strRRule !== null;
    }

    /**
     * Build the data buffer to insert in the iCalendar
     * @param string $strTZID
     * @return string
     */
    public function buildData(string $strTZID) : string
    {
        $strLastModified = gmdate('Ymd\THis\Z', $this->uxtsLastModified);
        $buffer  = 'BEGIN:VEVENT' . PHP_EOL;
        $buffer .= $this->buildProperty('UID', $this->strUID ?? $this->createUID());

        $strDateFormat = 'Ymd\THis';
        $strValueParam = ':';
        if ($this->bAllDay) {
            $strDateFormat = 'Ymd';
            $strValueParam = ';VALUE=DATE:';
        }

        $strTimezone = '';
        if (isset($this->oCalcTimezone)) {
            $strTimezone = ';TZID=' . $this->oCalcTimezone->getTZID();
            $buffer .= 'DTSTART' . $strTimezone . $strValueParam . $this->formatDate($strDateFormat, $this->uxtsStart) . PHP_EOL;
            if ($this->uxtsEnd != null) {
                $buffer .= 'DTEND' . $strTimezone . $strValueParam . $this->formatDate($strDateFormat, $this->uxtsEnd) . PHP_EOL;
            }
        } else {
            if (!empty($strTZID)) {
                $strTimezone = ';TZID=' . $strTZID;
            }
            $buffer .= 'DTSTART' . $strTimezone . $strValueParam . date($strDateFormat, $this->uxtsStart) . PHP_EOL;
            if ($this->uxtsEnd != null) {
                $buffer .= 'DTEND' . $strTimezone . $strValueParam . date($strDateFormat, $this->uxtsEnd) . PHP_EOL;
            }
        }

        $buffer .= 'DTSTAMP:' . $strLastModified . PHP_EOL;
        $buffer .= 'LAST-MODIFIED:' . $strLastModified . PHP_EOL;
        $buffer .= 'CREATED:' . $strLastModified . PHP_EOL;
        if (!empty($this->strOrganizerName) && !empty($this->strOrganizerEMail)) {
            $strOrganizer  = 'ORGANIZER;CN="' . $this->maskString($this->strOrganizerName) . '"';
            $strOrganizer .= ':MAILTO:' . $this->maskString($this->strOrganizerEMail);
            $buffer .= $this->foldLine($strOrganizer);
        }

        $buffer .= $this->buildProperty('DESCRIPTION', $this->strDescription);
        $buffer .= $this->buildProperty('SUMMARY', $this->strSubject);
        if (!empty($this->strLocation)) {
            $buffer .= $this->buildProperty('LOCATION', $this->strLocation);
        }
        if (!empty($this->strCategories)) {
            $buffer .= $this->buildProperty('CATEGORIES', $this->strCategories, false);
        }
        if ($this->iPriority > 0) {
            $buffer .= 'PRIORITY:' . $this->iPriority . PHP_EOL;
        }
        $buffer .= 'TRANSP:' . $this->strTrans . PHP_EOL;
        $buffer .= 'STATUS:' . $this->strState . PHP_EOL;
        $buffer .= 'SEQUENCE:0' . PHP_EOL;
        /*
        if ($this->iAlarm != null) {
            $buffer .= 'BEGIN:VALARM' . PHP_EOL;
            $buffer .= 'TRIGGER:-PT' . $this->iAlarm . 'M' . PHP_EOL;
            $buffer .= 'ACTION:DISPLAY' . PHP_EOL;
            $buffer .= 'DESCRIPTION:Reminder' . PHP_EOL;
            $buffer .= 'END:VALARM' . PHP_EOL;
        }
        */
        $buffer .= 'END:VEVENT' . PHP_EOL;

        return $buffer;
    }
}
