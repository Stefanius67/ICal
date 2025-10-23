<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Helper class to read lines from a iCal inside of a VTODO property.
 *
 * @see iCalToDo
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class iCalToDoReader extends Reader
{
    public const COMPONENT_NAME = 'VTODO';

    /** @var iCalToDo  the todo item      */
    protected iCalToDo $oToDo;

    /**
     * Creates a todo reader object.
     * @param iCalendar $oICalendar
     */
    function __construct(iCalendar $oICalendar)
    {
        parent::__construct($oICalendar);
        $this->oToDo = new iCalToDo($oICalendar);
    }

    /**
     * Checks, if the end of the ctodo is reached.
     * In case of the end, the readed todo is validated and passed to the parent
     * iCalendar. For recurrent todos, all resulting todos are generated and also
     * passed to the parent.
     * {@inheritDoc}
     * @see \SKien\iCal\Reader::hasEndReached()
     */
    public function hasEndReached(string $strLine) : bool
    {
        $bEnd = ($strLine == 'END:' . self::COMPONENT_NAME);
        if ($bEnd) {
            $this->oToDo->validate();
            $this->oICalendar->addToDo($this->oToDo);
            if ($this->oToDo->hasRecurrentItems()) {
                // Build recurrent items
                $aRecurrentDates = $this->oToDo->getRecurrentDates();
                $iDuration = $this->oToDo->getDuration();
                $strUID = $this->oToDo->getUID();
                $i = 0;
                foreach ($aRecurrentDates as $uxtsStart) {
                    $oRToDo = clone $this->oToDo;
                    $oRToDo->setStart($uxtsStart);
                    $oRToDo->setDuration($iDuration);
                    $oRToDo->setUID($strUID . '-' . ++$i);
                    $oRToDo->validate();
                    $this->oICalendar->addToDo($oRToDo);
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
        // or (string) property from iCalToDo ($this->oTodo)
        //
        //      settername(string strValue);
        //
        $aMethodOrProperty = [
            // iCalToDoReader methods
            'BEGIN'             => 'beginAlarmProp',
            'DTSTART'           => 'parseDtStart',
            'DUE'               => 'parseDue',
            'DURATION'          => 'parseDuration',
            'LAST-MODIFIED'     => 'parseDtLastModified',
            'COMPLETED'         => 'parseDtCompleted',
            'ORGANIZER'         => 'parseOrganizer',
            'RDATE'             => 'parseRDate',
            'EXDATE'            => 'parseExcludeDate',
            // iCalToDo setters
            'UID'               => 'setUID',
            'PERCENT-COMPLETE'  => 'setPercentComplete',
            'DESCRIPTION'       => 'setDescription',
            'SUMMARY'           => 'setSubject',
            'COMMENT'           => 'setComment',
            'CATEGORIES'        => 'setCategories',
            'LOCATION'          => 'setLocation',
            'PRIORITY'          => 'setPriority',
            'TRANSP'            => 'setTransparency',
            'STATUS'            => 'setState',
            'CLASS'             => 'setClassification',
            'RRULE'             => 'setRRule',
        ];

        if (isset($aMethodOrProperty[$strName])) {
            $strPtr = $aMethodOrProperty[$strName];
            $ownMethod = [$this, $strPtr];
            $childMethod = [$this->oToDo, $strPtr];
            if (is_callable($ownMethod)) {
                // call own method
                call_user_func_array($ownMethod, array($strName, $strValue, $aParams));
            } elseif (is_callable($childMethod)) {
                // call setter from child with unmasket value
                call_user_func_array($childMethod, array($this->unmaskString($strValue)));
            }
        } else {
            $strExtProperty = $this->oICalendar->getXProperty(self::COMPONENT_NAME, $strName);
            if ($strExtProperty !== null) {
                $this->oToDo->setExtProperty($strExtProperty, $this->unmaskString($strValue));
            }
        }
    }

    /**
     * Parse the DTSTART value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDtStart(string $strName, string $strValue, array $aParams) : void
    {
        $uxtsStart = $this->parseDateTimeValue($strValue, $aParams);
        if ($uxtsStart !== null) {
            $this->oToDo->setStart($uxtsStart);
            if (isset($aParams['TZID'])) {
                // will may be needed if any duration is set...
                $oTimezone = $this->oICalendar->getTimezone($aParams['TZID']);
                if ($oTimezone !== null) {
                    $this->oICalendar->setCalcTimezone($oTimezone);
                }
            }
        }
    }

    /**
     * Parse the DUE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDue(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->oToDo->getDuration() !== null) {
            $this->oICalendar->log(LogLevel::WARNING, 'VTODO: DUE and DURATION MUST not be set for the same todo item (DUE is ignored!)');
            return;
        }
        $this->oToDo->setDue($this->parseDateTimeValue($strValue, $aParams));
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
            $this->oToDo->setLastModified($uxtsLastModified);
        }
    }

    /**
     * Parse the COMPLETED value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDtCompleted(string $strName, string $strValue, array $aParams) : void
    {
        $uxtsCompleted = $this->parseDateTimeValue($strValue, $aParams);
        if ($uxtsCompleted !== null) {
            $this->oToDo->setCompleted($uxtsCompleted);
        }
    }

    /**
     * Parse the DURATION value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDuration(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->oToDo->getDue() !== null) {
            $this->oICalendar->log(LogLevel::WARNING, 'VTODO: DURATION and DUE MUST not be set for the same todo item (DURATION is ignored!)');
            return;
        }
        $iDuration = $this->parseDurationString($strValue);
        if ($iDuration !== null) {
            $this->oToDo->setDuration($iDuration);
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
        $this->oToDo->setOrganizer($strOrganizerName, $strOrganizerEMail);
    }

    /**
     * Parse the RDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseRDate(string $strName, string $strValue, array $aParams) : void
    {
        $this->oToDo->addRDate($this->parseDateTimeList($strValue, $aParams));
    }

    /**
     * Parse the EXDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseExcludeDate(string $strName, string $strValue, array $aParams) : void
    {
        $this->oToDo->addExcludeDate($this->parseDateTimeList($strValue, $aParams));
    }

    /**
     * Start VALARM property.
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function beginAlarmProp(string $strName, string $strValue, array $aParams) : void
    {
        if ($strValue == 'VALARM') {
            $this->oReader = new iCalAlarmReader($this->oToDo);
        }
    }
}
