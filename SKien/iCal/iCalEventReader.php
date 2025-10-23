<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Helper class to read lines from a iCal inside of a VEVENT property.
 *
 * @see iCalEvent
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class iCalEventReader extends Reader
{
    public const COMPONENT_NAME = 'VEVENT';

    /** @var iCalEvent  the event      */
    protected iCalEvent $oEvent;

    /**
     * Create a event reader object.
     * @param iCalendar $oICalendar
     */
    function __construct(iCalendar $oICalendar)
    {
        parent::__construct($oICalendar);
        $this->oEvent = new iCalEvent($oICalendar);
    }

    /**
     * Checks, if the end of the event is reached.
     * In case of the end, the readed event is validated and passed to the parent
     * iCalendar. For recurrent events, all resulting events are generated and also
     * passed to the parent.
     * {@inheritDoc}
     * @see \SKien\iCal\Reader::isEnd()
     */
    public function hasEndReached(string $strLine) : bool
    {
        $bEnd = ($strLine == 'END:' . self::COMPONENT_NAME);
        if ($bEnd) {
            $this->oEvent->validate();
            $this->oICalendar->addItem($this->oEvent);
            if ($this->oEvent->hasRecurrentItems()) {
                if ($this->oICalendar->getOption('createRecurrentItems', true) == true) {
                    $this->oEvent->createRecurrentItems();
                }
            }
        }
        return $bEnd;
    }

    /**
     * Add property from import file.
     * {@inheritDoc}
     * @see \SKien\iCal\Reader::addProperty()
     */
    public function addProperty(string $strName, array $aParams, string $strValue) : void
    {
        // table to parse property depending on propertyname.
        // value have to be either name of method of this class with signature
        //
        //      methodname(string strValue, array aParams)
        //
        // or (string) property from iCalEvent ($this->oEvent)
        //
        //      settername(string strValue);
        //
        $aMethodOrProperty = [
            // iCalEventReader methods
            'BEGIN'         => 'beginAlarmProp',
            'DTSTART'       => 'parseDtStart',
            'DTEND'         => 'parseDtEnd',
            'DURATION'      => 'parseDuration',
            'LAST-MODIFIED' => 'parseDtLastModified',
            'ORGANIZER'     => 'parseOrganizer',
            'RDATE'         => 'parseRDate',
            'EXDATE'        => 'parseExcludeDate',
            // iCalEvent setters
            'UID'           => 'setUID',
            'DESCRIPTION'   => 'setDescription',
            'SUMMARY'       => 'setSubject',
            'COMMENT'       => 'setComment',
            'CATEGORIES'    => 'setCategories',
            'LOCATION'      => 'setLocation',
            'PRIORITY'      => 'setPriority',
            'TRANSP'        => 'setTransparency',
            'STATUS'        => 'setState',
            'CLASS'         => 'strClassification',
            'RRULE'         => 'setRRule',
        ];

        if (isset($aMethodOrProperty[$strName])) {
            $strPtr = $aMethodOrProperty[$strName];
            $ownMethod = [$this, $strPtr];
            $childMethod = [$this->oEvent, $strPtr];
            if (is_callable($ownMethod)) {
                // call own method
                call_user_func_array($ownMethod, array($strName, $strValue, $aParams));
            } elseif (is_callable($childMethod)) {
                // call setter from contact with unmasket value
                call_user_func_array($childMethod, array($this->unmaskString($strValue)));
            }
        } else {
            $strExtProperty = $this->oICalendar->getXProperty(self::COMPONENT_NAME, $strName);
            if ($strExtProperty !== null) {
                $this->oEvent->setExtProperty($strExtProperty, $this->unmaskString($strValue));
            }
        }
    }

    /**
     * Parse the DTSTART value.
     * For cases where a "VEVENT" calendar component specifies a "DTSTART" property
     * with a DATE value type but no "DTEND" nor "DURATION" property, the event's
     * duration is taken to be one day.
     * For cases where a "VEVENT" calendar component specifies a "DTSTART" property
     * with a DATE-TIME value type but no "DTEND" property, the event ends on the
     * same calendar date and time of day specified by the "DTSTART" property.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#page-52
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDtStart(string $strName, string $strValue, array $aParams) : void
    {
        $uxtsStart = $this->parseDateTimeValue($strValue, $aParams);
        if ($uxtsStart !== null) {
            $this->oEvent->setStart($uxtsStart);
            if (isset($aParams['TZID'])) {
                // will may be needed if any duration is set...
                $oTimezone = $this->oICalendar->getTimezone($aParams['TZID']);
                if ($oTimezone !== null) {
                    $this->oICalendar->setCalcTimezone($oTimezone);
                } else {
                    $this->oICalendar->log(LogLevel::ERROR, 'VEVENT: Invalid TZID specified!');
                }
            }
            if (($aParams['VALUE'] ?? '') === 'DATE') {
                // if no time is set, we have an allday event
                $this->oEvent->setAllDay(true);
            }
        }
    }

    /**
     * Parse the DTEND value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDtEnd(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->oEvent->getDuration() !== null) {
            $this->oICalendar->log(LogLevel::WARNING, 'VEVENT: DTEND and DURATION MUST not be set for the same event (DTEND is ignored!)');
            return;
        }
        $uxtsEnd = $this->parseDateTimeValue($strValue, $aParams);
        if ($uxtsEnd !== null) {
            $this->oEvent->setEnd($uxtsEnd);
        }
    }

    /**
     * Parse the LAST_MODIFIED value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDtLastModified(string $strName, string $strValue, array $aParams) : void
    {
        $uxtsLastModified = $this->parseDateTimeValue($strValue, $aParams);
        if ($uxtsLastModified !== null) {
            $this->oEvent->setLastModified($uxtsLastModified);
        }
    }

    /**
     * Parse the DURATION value.
     * > https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.6 :
     * > In the case of discontinuities in the time scale, such as the change from
     * > standard time to daylight time and back, the computation of the exact
     * > duration requires the subtraction or addition of the change of duration of
     * > the discontinuity.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDuration(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->oEvent->getEnd() !== null) {
            $this->oICalendar->log(LogLevel::WARNING, 'VEVENT: DURATION and DTEND MUST not be set for the same event (DURATION is ignored!)');
            return;
        }
        $iDuration = $this->parseDurationString($strValue, $this->oEvent->getAllDay());
        if ($iDuration !== null) {
            $this->oEvent->setDuration($iDuration);
        }
    }

    /**
     * Parsing of the organizer.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.4.3
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseOrganizer(string $strName, string $strValue, array $aParams) : void
    {
        $strOrganizerName = $aParams['CN'] ?? '';
        $strOrganizerEMail = '';
        $iPos = strpos(strtolower($strValue), 'mailto:');
        if ($iPos !== false) {
            $strOrganizerEMail = substr($strValue, $iPos + 7);
        }
        $this->oEvent->setOrganizer($strOrganizerName, $strOrganizerEMail);
    }

    /**
     * Parse the RDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseRDate(string $strName, string $strValue, array $aParams) : void
    {
        $this->oEvent->addRDate($this->parseDateTimeList($strValue, $aParams));
    }

    /**
     * Parse the EXDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseExcludeDate(string $strName, string $strValue, array $aParams) : void
    {
        $this->oEvent->addExcludeDate($this->parseDateTimeList($strValue, $aParams));
    }

    /**
     * Start VALARM property.
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function beginAlarmProp(string $strName, string $strValue, array $aParams) : void
    {
        if ($strValue == 'VALARM') {
            $this->oReader = new iCalAlarmReader($this->oEvent);
        }
    }
}
