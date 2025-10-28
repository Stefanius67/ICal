<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Class representing a ical conform timezone (VTIMEZONE).
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.5
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalTimezone extends iCalComponent
{
    /** @var string     the iCal timezone ID      */
    protected string $strTZID = '';
    /** @var array<iCalTimezoneProp>      containing all timezoneproperties DAYLIGHT / STANDARD     */
    protected array $aTimezoneProp = [];
    /** @var array<int,string>      timeoffset per date resulting from all timezoneproperties    */
    protected array $aTimeOffsetList = [];

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar $oICalendar)
    {
        parent::__construct('VTIMEZONE', $oICalendar);
    }

    /**
     * Creates the full iCal timezone description for the given PHP timezone and datespan.
     * Since the time boundaries generally represent the earliest and latest dates
     * that occur in this calendar, the from/to timestamps are shifted forward/backward
     * by one year to ensure that all changes between daylight saving time and standard
     * time are taken into account.
     * Unlike "normal" time zone definitions, which usually work with recurring rules for
     * multiple time periods, this generated definition specifies a set of RDATE's for the
     * same offset changes between daylight saving and standard time within the specified
     * period (Simply because DateTimeZone::getTransitions() returns the information in this
     * form...).
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
        $aProps = [];
        foreach ($aTransitions as $aProp) {
            $iOffsetTo = $aProp['offset'];
            if ($iOffsetFrom === null) {
                $iOffsetFrom = $iOffsetTo;
                continue;
            }
            $strType = $aProp['isdst'] ? iCalTimezoneProp::DAYLIGHT : iCalTimezoneProp::STANDARD;

            $strKey = $strType . $this->getOffsetString($iOffsetFrom) . $this->getOffsetString($iOffsetTo);
            if (!isset($aProps[$strKey])) {
                $oProp = new iCalTimezoneProp($this, $strType);
                $oProp->setStart($aProp['ts']);
                $oProp->setOffsetFrom($iOffsetFrom);
                $oProp->setOffsetTo($iOffsetTo);
                $oProp->setName($aProp['abbr']);
                $this->addTimezoneProp($oProp);
                $aProps[$strKey] = $oProp;
            } else {
                $oProp = $aProps[$strKey];
            }
            $oProp->setRDate($aProp['ts']);

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
                $strLine = $oReader->nextLine($aLines, $iLine);
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

        foreach ($this->aTimezoneProp as $oTZProp) {
            $aDates = $oTZProp->getRecurrentDates();
            foreach ($aDates as $uxtsDate) {
                $this->aTimeOffsetList[$uxtsDate] = $oTZProp->getOffsetTo();
            }
        }
        ksort($this->aTimeOffsetList);

        if ($this->oICalendar->getOption('logTimezoneOffsetList') === true) {
            $aContext = [];
            foreach ($this->aTimeOffsetList as $uxts => $strOffset) {
                $aContext[date('Y-m-d H:i:s', $uxts)] = $strOffset;
            }
            $this->oICalendar->log(LogLevel::INFO, "Timezone [{$this->strTZID}]: created TimeoffsetList.", $aContext);
        }
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
     * Write the component data to the Writer instance.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::writeData()
     */
    public function writeData(Writer $oWriter, string $strTZID = '') : void
    {
        $oWriter->addProperty('BEGIN', 'VTIMEZONE');
        $oWriter->addProperty('TZID', $this->strTZID);
        if (!empty($this->strComment)) {
            $oWriter->addProperty('X-EM-DISPLAYNAME', $this->strComment);
            $oWriter->addProperty('X-LIC-LOCATION', $this->strComment);
        }
        foreach ($this->aTimezoneProp as $oProp) {
            $oProp->writeData($oWriter);
        }
        $oWriter->addProperty('END', 'VTIMEZONE');
    }
}
