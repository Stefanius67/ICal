<?php

declare(strict_types=1);

namespace SKien\iCal;


/**
 * Class representing a ical conform timezone (VTIMEZONE).
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.5
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalTimezone
{
    use iCalHelper;

    /** @var string     the iCal timezone ID      */
    protected string $strTZID = '';
    /** @var string     a description of the timezone (usually resulting from `DateTimeZone::getLocation()`)     */
    protected string $strComment = '';
    /** @var array<iCalTimezoneProp>      containing all timezoneproperties DAYLIGHT / STANDARD     */
    protected array $aTimezoneProp = [];
    /** @var array<int,string>      timeoffset per date resulting from all timezoneproperties    */
    protected array $aTimeOffsetList = [];

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar &$oICalendar)
    {
        $this->oICalendar = $oICalendar;
    }

    /**
     * Creates the full iCal timezone description for the given PHP timezone and datespan.
     * Since the time boundaries generally represent the earliest and latest dates
     * that occur in this calendar, the from/to timestamps are shifted forward/backward
     * by one year to ensure that all changes between daylight saving time and standard
     * time are taken into account.
     * Unlike "normal" time zone definitions, which usually work with recurring rules for
     * multiple time periods, this generated definition specifies a single start date with
     * corresponding time offset for each change between daylight saving and standard time
     * within the specified period (Simply because DateTimeZone::getTransitions() returns
     * the information in this form...).
     * @link https://www.php.net/manual/en/timezones.php
     * @param string $strTZID
     * @param int $uxtsFrom
     * @param int $uxtsTo
     */
    public function fromTimezone(string $strTZID, int $uxtsFrom, int $uxtsTo) : void
    {
        // we set the ical TZID equal to the PHP timezone
        $this->strTZID = $strTZID;

        // shift boundaries by one year (365*24*60*60) forward/backward
        $uxtsFrom -= 31536000;
        $uxtsTo += 31536000;

        $oTZ = new \DateTimeZone($strTZID);

        $this->strComment = '';
        $aLocation = $oTZ->getLocation();
        if ($aLocation !== false) {
            $this->strComment = $aLocation['comments'];
        }

        $aTransitions = $oTZ->getTransitions($uxtsFrom, $uxtsTo);
        $iOffsetFrom = null;
        $iOffsetTo = null;
        foreach ($aTransitions as $aProp) {
            $iOffsetTo = $aProp['offset'];
            if ($iOffsetFrom === null) {
                $iOffsetFrom = $iOffsetTo;
                continue;
            }
            $strType = $aProp['isdst'] ? iCalTimezoneProp::DAYLIGHT : iCalTimezoneProp::STANDARD;

            $oProp = new iCalTimezoneProp($this->oICalendar, $strType);

            $oProp->setStart($aProp['ts']);
            $oProp->setOffsetFrom($iOffsetFrom);
            $oProp->setOffsetTo($iOffsetTo);
            $oProp->setName($aProp['abbr']);

            $this->addTimezoneProp($oProp);
            $iOffsetFrom = $iOffsetTo;
        }
    }

    /**
     * Creates an iCal timezone from the first VTIMEZONE definition within a file.
     * @param string $strFilename
     * @param iCalendar $oICalendar
     * @return iCalTimezone   created instance
     */
    public static function fromFile(string $strFilename, iCalendar $oICalendar) : ?iCalTimezone
    {
        $aLines = @file($strFilename);
        $oTimezone = null;
        if ($aLines !== false) {
            $oReader = new iCalTimezoneReader($oICalendar);
            $iLine = 0;
            $bStarted = false;
            while ($iLine < count($aLines)) {
                $strLine = Reader::nextLine($aLines, $iLine);
                if (!$bStarted) {
                    if ($strLine == 'BEGIN:VTIMEZONE') {
                        $bStarted = true;
                    }
                } else {
                    if ($oReader->hasEndReached($strLine)) {
                        break;
                    }
                    $oReader->parseLine($strLine);
                }
            }
            $oTimezone = $oReader->getTimezone();
        }
        return $oTimezone;
    }

    /**
     * Sets the iCal timezone ID.
     * NOTE: This is not identical to the corresponding PHP timezone!!
     * (usually from the IANA timezone database)
     * @param string $strTZID
     */
    public function setTZID(string $strTZID) : void
    {
        $this->strTZID = $strTZID;
    }

    /**
     * Returns the iCal timezone ID.
     * @see iCalTimezone::setTZID()
     * @return string
     */
    public function getTZID() : string
    {
        return $this->strTZID;
    }

    /**
     * Sets a comment to this timezone instance.
     * @param string $strComment
     */
    public function setComment(string $strComment) : void
    {
        $this->strComment = $strComment;
    }

    /**
     * Returns the comment.
     * @return string
     */
    public function getComment() : string
    {
        return $this->strComment;
    }

    /**
     * Adds a further Timezone Property (daylight or standard).
     * @param iCalTimezoneProp $oProp
     */
    public function addTimezoneProp(iCalTimezoneProp $oProp) : void
    {
        $this->aTimezoneProp[] = $oProp;
    }

    /**
     * Get all Timezone Property (daylight or standard) for this timezone.
     * @return array<iCalTimezoneProp>
     */
    public function getTimezoneProps() : array
    {
        return $this->aTimezoneProp;
    }

    /**
     * Creates a list containing all changes between daylight saving time.
     * The array contains the pairs DateTimeFrom => TimeOffset in ascending
     * order.
     * @see iCalTimezone::$aTimeOffsetList
     */
    public function createTimeoffsetList() : void
    {
        $this->aTimeOffsetList = [];
        $uxtsMin = PHP_INT_MAX;
        $strOffsetMin = '';

        foreach ($this->aTimezoneProp as $oTZProp) {
            $aDates = $oTZProp->getRecurrentDates();
            if (count($aDates) > 0 && $aDates[0] < $uxtsMin) {
                // We determine the earliest zone change so that the 'offsetFrom'
                // can be returned for date values ​​that lie before this change
                $uxtsMin = $aDates[0];
                $strOffsetMin = $oTZProp->getOffsetFrom();
            }
            foreach ($aDates as $uxtsDate) {
                $this->aTimeOffsetList[$uxtsDate] = $oTZProp->getOffsetTo();
            }
        }
        if (!empty($strOffsetMin)) {
            $this->aTimeOffsetList[PHP_INT_MIN] = $strOffsetMin;
        }
        ksort($this->aTimeOffsetList);

        /*
        $aContext = [];
        foreach ($this->aTimeOffsetList as $uxts => $strOffset) {
            $aContext[date('Y-m-d H:i:s', $uxts)] = $strOffset;
        }
        $this->oICalendar->log(LogLevel::INFO, "Timezone [{$this->strTZID}]: created TimeoffsetList.", $aContext);
        */
    }

    /**
     * Finds the matching timeoffset for the given date.
     * @param string|int $dateTime
     * @return string
     */
    public function findTimeOffset($dateTime) : string
    {
        if (count($this->aTimeOffsetList) == 0) {
            $this->createTimeoffsetList();
        }
        $uxtsFind = 0;
        if (is_string($dateTime)) {
            $iYear  = intval(substr($dateTime, 0, 4));
            $iMonth = intval(substr($dateTime, 4, 2));
            $iDay   = intval(substr($dateTime, 6, 2));
            $iHour  = intval(substr($dateTime, 9, 2));
            $iMin   = intval(substr($dateTime, 11, 2));
            $iSec   = intval(substr($dateTime, 13, 2));

            $uxtsFind = gmmktime($iHour, $iMin, $iSec, $iMonth, $iDay, $iYear);
        } else {
            $uxtsFind = $dateTime;
        }
        $strFoundOffset = '';
        foreach ($this->aTimeOffsetList as $uxtsDate => $strDateOffset) {
            if ($uxtsDate < $uxtsFind) {
                $strFoundOffset = $strDateOffset;
            } else {
                break;
            }
        }
        return $strFoundOffset;
    }

    /**
     * Builds the data buffer to insert in the iCalendar
     * @return string
     */
    public function buildData() : string
    {
        $buffer  = 'BEGIN:VTIMEZONE' . PHP_EOL;
        $buffer .= 'TZID:' . $this->strTZID . PHP_EOL;
        if (!empty($this->strComment)) {
            $buffer .= 'X-EM-DISPLAYNAME:' . $this->maskString($this->strComment) . PHP_EOL;
            $buffer .= 'X-LIC-LOCATION:' . $this->maskString($this->strComment) . PHP_EOL;
        }
        foreach ($this->aTimezoneProp as $oProp) {
            $buffer .= $oProp->buildData();
        }
        $buffer .= 'END:VTIMEZONE' . PHP_EOL;

        return $buffer;
    }
}
