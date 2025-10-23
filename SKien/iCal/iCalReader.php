<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Helper class to read global lines from a iCal.
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.7
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class iCalReader extends Reader
{
    public const COMPONENT_NAME = 'VCALENDAR';

    /** @var bool   is set to true as soon as BEGIN:VCALENDAR is found     */
    protected bool $bStarted = false;

    /**
     * Create a reader object.
     * @param iCalendar $oICalendar
     */
    function __construct(iCalendar $oICalendar)
    {
        parent::__construct($oICalendar);
    }

    /**
     * {@inheritDoc}
     * @see \SKien\iCal\Reader::isEnd()
     */
    public function hasEndReached(string $strLine) : bool
    {
        $bEnd = ($strLine == 'END:' . self::COMPONENT_NAME);
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
        // or (string) property from iCalendart ($this->oICal)
        //
        //      settername(string strValue);
        //
        $aMethodOrProperty = [
            // iCalEventReader methods
            'BEGIN'         => 'beginProp',
            'CALSCALE'      => 'checkCalscale',
            // iCalendar setters
            'NAME'          => 'setName',
            'X-WR-CALNAME'  => 'setName',
        ];

        if (isset($aMethodOrProperty[$strName])) {
            $strPtr = $aMethodOrProperty[$strName];
            $ownMethod = [$this, $strPtr];
            $childMethod = [$this->oICalendar, $strPtr];
            if (is_callable($ownMethod)) {
                // call method
                call_user_func_array($ownMethod, array($strName, $strValue, $aParams));
            } elseif (is_callable($childMethod)) {
                // call setter from contact with unmasket value
                call_user_func_array($childMethod, array($this->unmaskString($strValue)));
            }
        }
    }

    /**
     * Start a nested property.
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function beginProp(string $strName, string $strValue, array $aParams) : void
    {
        if (!$this->bStarted) {
            if ($strValue == self::COMPONENT_NAME) {
                $this->bStarted = true;
            }
        } else {
            if ($strValue == 'VEVENT') {
                $this->oReader = new iCalEventReader($this->oICalendar);
            } elseif ($strValue == 'VTODO') {
                $this->oReader = new iCalToDoReader($this->oICalendar);
            } elseif ($strValue == 'VTIMEZONE') {
                $this->oReader = new iCalTimezoneReader($this->oICalendar);
            } else {
                $this->oICalendar->log(LogLevel::CRITICAL, "Not supportet component {$strValue} found!");
            }
        }
    }

    /**
     * Check for supported calscale.
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function checkCalscale(string $strName, string $strValue, array $aParams) : void
    {
        if ($strValue !== 'GREGORIAN') {
            $this->oICalendar->log(LogLevel::ALERT, "File uses not supportet CALSCALE: {$strValue}!");
        }
    }
}
