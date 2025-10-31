<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Reader class that parses lines from a iCal file into a iCalTimezone object.
 * Nested iCalTimezoneProp instances (daylight-/standard time) will also
 * be parsed and the according objects are attached to the iCalTimezone.
 *
 * @see \SKien\iCal\iCalTimezone
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class iCalTimezoneReader extends Reader
{
    public const COMPONENT_NAME = 'VTIMEZONE';

    /** @var iCalTimezone the timezone      */
    protected iCalTimezone $oTimezone;
    /** @var iCalTimezoneProp the timezone      */
    protected ?iCalTimezoneProp $oTimezoneProp = null;

    /**
     * Creates a timezone reader object.
     * @param iCalendar $oICalendar
     */
    function __construct(iCalendar $oICalendar)
    {
        parent::__construct($oICalendar);
        $this->oTimezone = new iCalTimezone($oICalendar);
    }

    /**
     * After the end of this component is reached, the parent reader will destroy
     * this instance an goes on with the next component.
     * {@inheritDoc}
     * @see \SKien\iCal\Reader::isEnd()
     */
    public function hasEndReached(string $strLine) : bool
    {
        $bEnd = ($strLine == 'END:' . self::COMPONENT_NAME);
        if ($bEnd) {
            $this->oTimezone->createTimeoffsetList();
            $this->oICalendar->addTimezone($this->oTimezone);
        }
        return $bEnd;
    }

    /**
     * Add property from import file.
     * @param string $strName
     * @param array<string,string> $aParams
     * @param string $strValue
     */
    public function addProperty(string $strName, array $aParams, string $strValue) : void
    {
        // table to parse property depending on propertyname.
        // The key is the prperty name.
        // The value have to be either the method name of this class with signature
        //
        //      `methodname(string $strName, string $strValue, array $aParams)`
        //
        // or a (string) property from iCalTimezone / iCalTimezoneProp from ($this->oTimezone
        // $this->oTimezoneProp) dependent, what we currently parsing.
        //
        //      settername(string $strValue);
        //
        $aMethodOrProperty = [
            // iCalTimezoneReader methods
            'BEGIN'             => 'beginTimezoneProp',
            'END'               => 'endTimezoneProp',
            // iCalTimezone setters
            'TZID'              => 'setTZID',
            'X-LIC-LOCATION'    => 'setComment',
            'X-EM-DISPLAYNAME'  => 'setComment',
            // iCalTimezoneProp setters
            'TZNAME'            => 'setName',
            'DTSTART'           => 'setStart',
            'TZOFFSETFROM'      => 'setOffsetFrom',
            'TZOFFSETTO'        => 'setOffsetTo',
            'RRULE'             => 'setRRule',
            'RDATE'             => 'setRDate',
            'EXDATE'            => 'setExcludeDate',
        ];

        if (isset($aMethodOrProperty[$strName])) {
            $strPtr = $aMethodOrProperty[$strName];
            $ownMethod = [$this, $strPtr];
            $objectMethod = $this->oTimezoneProp ? [$this->oTimezoneProp, $strPtr] :  [$this->oTimezone, $strPtr];
            if (is_callable($ownMethod)) {
                // call method
                call_user_func_array($ownMethod, array($strName, $strValue, $aParams));
            } elseif (is_callable($objectMethod)) {
                // call setter from iCalTimezone / iCalTimezoneProp with unmasket value
                call_user_func_array($objectMethod, array($this->unmaskString($strValue)));
            }
        }
    }

    /**
     * Start of a timezone component daylight / standard.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function beginTimezoneProp(string $strName, string $strValue, array $aParams) : void
    {
        $this->oTimezoneProp = new iCalTimezoneProp($this->oTimezone, $strValue);
    }

    /**
     * End of the current timezone component.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function endTimezoneProp(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->oTimezoneProp) {
            $this->oTimezone->addTimezoneProp($this->oTimezoneProp);
            $this->oTimezoneProp = null;
        }
    }
}
