<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Helper trait containing methods used by multiple classes in this package.
 *
 * This trait primarily provides functions to perform arithmetic operations with date
 * values, taking into account possible changes between daylight saving time and standard
 * time (depending on defined time zones inside the iCalendar).
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
trait iCalHelper
{
    protected const MAX_LINE_LENGTH = 75;

    /** @var iCalendar      the reference to the parent iCalendar for all operations     */
    protected iCalendar $oICalendar;

    /**
     * @return iCalendar
     */
    public function getICalendar() : iCalendar
    {
        return $this->oICalendar;
    }

    /**
     * Creates a 'pseudo' UID.
     * @return string
     */
    protected function createUID()
    {
        mt_srand(intval(microtime(true)) * 10000);
        $charid = strtoupper(md5(uniqid((string) rand(), true)));
        return 	  substr($charid, 0, 8) . chr(45)
        . substr($charid, 8, 4) . chr(45)
        . substr($charid, 12, 4) . chr(45)
        . substr($charid, 16, 4) . chr(45)
        . substr($charid, 20, 12);
    }

    /**
     * Parses and converts a DURATION property into seconds value.
     * Note that unlike ISO.8601.2004, an iCal duration doesn't support the
     * "Y" and "M" designators to specify durations in terms of years and months.
     * (negative durations are allowed, for example, in a VALARM element)
     * @param string $strDuration   duration definition
     * @param bool $bAllDay         if set only the dur-day and dur-week designators used
     * @return int  duration in seconds (simply to add/sub from an UNIX timestamp)
     */
    protected function parseDurationString(string $strDuration, bool $bAllDay = false) : ?int
    {
        // check for valid duration string
        if (preg_match('/^-{0,1}P(\d{1,}W){0,1}(\d{1,}D){0,1}(T(\d{1,}H){0,1}(\d{1,}M){0,1}(\d{1,}S){0,1}){0,1}$/', $strDuration) !== 1) {
            return null;
        }
        $iDuration = 0;
        $iSign = ($strDuration[0] == '-') ? -1 : 1;
        // remove sign
        $strDuration = trim($strDuration, '+-');
        if($strDuration[0] == "P") {
            // We append a separator to all allowed designators so we can
            // easily explode the string into its components. We also remove
            // the "P" and "T"
            // Since the iCal duration doesn't support month, we doesn't need to
            // take care about 'M' for month before a 'T' and for minutes after
            // a 'T' delimiter...
            $strDuration = str_replace(['H', 'M', 'S', 'D', 'W', 'P', 'T'], ['H;', 'M;', 'S;', 'D;', 'W;', '', ''], $strDuration);
            $aValues = explode(';', $strDuration);
            $aDurations = [
                'S' => 1,
                'M' => 60,
                'H' => 60 * 60,
                'D' => 60 * 60 * 24,
                'W' => 60 * 60 * 24 * 7,
            ];
            if ($bAllDay) {
                // simply ignore the H, M and S designators
                unset($aDurations['S']);
                unset($aDurations['M']);
                unset($aDurations['H']);
            }
            foreach($aValues as $strValue){
                $strDesignator = substr($strValue, -1);
                if (!empty($strDesignator) && isset($aDurations[$strDesignator])) {
                    // the inval() function ignores the succeeding designator...
                    $iDuration += intval($strValue) * $aDurations[$strDesignator];
                }
            }
        }
        return $iDuration * $iSign;
    }

    /**
     * Build the duration string from given seconds.
     * Further information at `parseDurationString()`.
     * @see iCalHelper::parseDurationString()
     * @param int $iDuration    duration in seconds
     * @return string
     */
    protected function getDurationString(int $iDuration) : string
    {
        if ($iDuration == 0) {
            return 'P0D';
        }
        $strSign = ($iDuration < 0 ? '-' : '');
        $iDuration = abs($iDuration);
        $aParts = [
            ''  => [604800 => 'W',  86400 => 'D'],
            'T' => [  3600 => 'H',     60 => 'M',	1 => 'S']
        ];

        $strDuration = '';
        foreach ($aParts as $strPart => $aPart) {
            $strDurationPart = '';
            foreach ($aPart as $iFaktor => $strElement) {
                if ($iDuration >= $iFaktor) {
                    $iPart = floor($iDuration / $iFaktor);
                    $strDurationPart .= $iPart . $strElement;
                    $iDuration %= $iFaktor;
                }
            }
            if (!empty($strDurationPart)) {
                $strDuration .= $strPart . $strDurationPart;
            }
        }
        $strDuration = $strSign . 'P' . $strDuration;

        return $strDuration;
    }

    /**
     * Parse a time offset string into seconds.
     * @param string $strOffset
     * @return int
     */
    protected function parseOffset(string $strOffset) : int
    {
        $iOffset = 0;
        if (preg_match('/^([+-]\d{4})$|^([+-]\d{6})$/', $strOffset)) {
                // The sign is taken into account for the hours
            $iHour = intval(substr($strOffset, 0, 3));
            $iMin  = intval(substr($strOffset, 3, 2));
            $iSec  = strlen($strOffset) == 7 ? intval(substr($strOffset, 5, 2)) : 0;

            $iOffset  = $iHour * 3600;
            $iOffset += $iMin * 60;
            $iOffset += $iSec;
        }
        return $iOffset;
    }

    /**
     * @param string $strValue
     * @return array<string>
     */
    protected function parseStringArray(string $strValue) : array
    {
        $aResult = [];
        $aSplit = explode(',', $strValue);
        foreach ($aSplit as $str) {
            $aResult[] = trim($str);
        }
        return $aResult;
    }

    /**
     * @param string $strValue
     * @return array<int>
     */
    protected function parseIntArray(string $strValue) : array
    {
        $aResult = [];
        $aSplit = explode(',', $strValue);
        foreach ($aSplit as $strInt) {
            $aResult[] = intval(trim($strInt));
        }
        return $aResult;
    }

    /**
     * Gets the timeoffset for the current timezone.
     * @param int $uxtsDateTime
     * @return int
     */
    protected function getTimezoneOffset(int $uxtsDateTime) : int
    {
        $iOffset = 0;
        $oCalcTimezone = $this->oICalendar->getCalcTimezone();
        if ($oCalcTimezone) {
            $strOffset = $oCalcTimezone->findTimeOffset($uxtsDateTime);
            $iOffset = $this->parseOffset($strOffset);
        }
        return $iOffset;
    }

    /**
     * Creates the time offset string, which results from the offset in seconds.
     * @param int $iOffsetSeconds
     * @return string
     */
    protected function getOffsetString(int $iOffsetSeconds) : string
    {
        $strOffset = $iOffsetSeconds < 0 ? '-' : '+';
        $iHours = floor(abs($iOffsetSeconds) / 3600);
        $iMinutes = floor((abs($iOffsetSeconds) % 3600) / 60);

        $strOffset .= sprintf("%02d%02d", $iHours, $iMinutes);

        return $strOffset;
    }

    /**
     * Sets a specific part of an UNIX timestamp to the given value.
     * since the internal iCalendar time zone not necessarily equals any
     * PHP timezone, we are calculate within UTC timezone and have to take
     * care about changes from DAYLIGHT/STANDARD time between input- and
     * output date by our self.
     * @param int $uxtsDateTime
     * @param string $strPart
     * @param int $iValue
     * @return int
     */
    protected function setDateTimePart(int $uxtsDateTime, string $strPart, int $iValue) : int
    {
        $aDate = $this->getDate($uxtsDateTime);
        $iOffset = $this->getTimezoneOffset($uxtsDateTime);
        $aDate[$strPart] = $iValue;
        $uxtsNew = $this->mkTime($aDate);
        if ($uxtsNew === false) {       // I have no testcase for PHPUnit so far, but phpstan wants this code...
            $uxtsNew = $uxtsDateTime;   // @codeCoverageIgnore
        }
        $iNewOffset = $this->getTimezoneOffset($uxtsNew);
        if ($iOffset !== $iNewOffset) {
            $uxtsNew += $iOffset - $iNewOffset;
        }
        return $uxtsNew;
    }

    /**
     * Gets the day of the given timestamp without hours, min and seconds.
     * @param int $uxtsDateTime
     * @return int
     */
    protected function getDay(int $uxtsDateTime) : int
    {
        $aDate = $this->getDate($uxtsDateTime);
        $aDate['hours'] = 0;
        $aDate['minutes'] = 0;
        $aDate['seconds'] = 0;

        $uxtsDay = $this->mkTime($aDate);
        if ($uxtsDay === false) {       // I have no testcase for PHPUnit so far, but phpstan wants this code...
            $uxtsDay = $uxtsDateTime;   // @codeCoverageIgnore
        }
        return $uxtsDay;
    }

    /**
     * Gets a specific part of an UNIX timestamp.
     * @param int $uxtsDateTime
     * @param string $strPart
     * @return int
     */
    protected function getDateTimePart(int $uxtsDateTime, string $strPart) : int
    {
        $dtDate = new \DateTime();
        $dtDate->setTimezone(new \DateTimeZone('UTC'));
        $dtDate->setTimestamp($uxtsDateTime);
        $aParts = [
            'seconds'   => 's',
            'minutes'   => 'i',
            'hours'     => 'G',
            'mday'      => 'j',
            'wday'      => 'w',
            'week'      => 'W',
            'mon'       => 'n',
            'year'      => 'Y',
            'yday'      => 'z',
        ];
        $iValue = -1;
        if (isset($aParts[$strPart])) {
            $iValue = intval($dtDate->format($aParts[$strPart]));
        }
        return $iValue;
    }

    /**
     * Makes an UNIX timestamp from the given datetime array.
     * @link http://www.php.net/manual/en/function.getdate.php
     * @param array<string,mixed>   $date   array containg the datetime parts (see PHP getdate() function)
     * @return int|false
     */
    protected function mkTime(array $date) : int|false
    {
        $uxtsDateTime = gmmktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);
        return $uxtsDateTime;
    }

    /**
     * Gets the elements of an UNIX timestamp.
     * Since the core getdate() function is based on the current timezone. This
     * is a replacement that works with a UTC based DateTime object.
     * @param int $uxtsDateTime
     * @return string[]|number[]
     */
    protected function getDate(int $uxtsDateTime)
    {
        $dtDate = new \DateTime();
        $dtDate->setTimezone(new \DateTimeZone('UTC'));
        $dtDate->setTimestamp($uxtsDateTime);
        $aDate = [
            'seconds'   => intval($dtDate->format('s')),
            'minutes'   => intval($dtDate->format('i')),
            'hours'     => intval($dtDate->format('G')),
            'mday'      => intval($dtDate->format('j')),
            'wday'      => intval($dtDate->format('w')),
            'mon'       => intval($dtDate->format('n')),
            'year'      => intval($dtDate->format('Y')),
            'yday'      => intval($dtDate->format('z')),
            'weekday'   => 'todo', // $dtDate->format('l'),
            'month'     => 'todo', // $dtDate->format('F'),
        ];
        return $aDate;
    }

    /**
     * Adds given dateintervall to the UNIX timestamp.
     * @param int $uxtsDateTime
     * @param string $strInterval
     * @return int
     */
    protected function addDate(int $uxtsDateTime, string $strInterval) : int
    {
        $dtDate = new \DateTime();
        $dtDate->setTimezone(new \DateTimeZone('UTC'));
        $dtDate->setTimestamp($uxtsDateTime);
        $iOffset = $this->getTimezoneOffset($uxtsDateTime);

        $dtDate->add(new \DateInterval($strInterval));

        $uxtsAdd = $dtDate->getTimestamp();
        $iNewOffset = $this->getTimezoneOffset($uxtsAdd);
        if ($iOffset !== $iNewOffset) {
            // since the internal iCalendar time zone not necessarily equals any
            // PHP timezone, we are calculate within UTC timezone and have to take
            // care about changes from DAYLIGHT/STANDARD time between input- and
            // output date by our self.
            $uxtsAdd += $iOffset - $iNewOffset;
        }
        return $uxtsAdd;
    }

    /**
     * Subtracts given timeinterval from the UNIX timestamp.
     * @param int $uxtsDateTime
     * @param string $strInterval
     * @return int
     */
    protected function subDate(int $uxtsDateTime, string $strInterval) : int
    {
        $dtDate = new \DateTime();
        $dtDate->setTimezone(new \DateTimeZone('UTC'));
        $dtDate->setTimestamp($uxtsDateTime);
        $iOffset = $this->getTimezoneOffset($uxtsDateTime);

        $dtDate->sub(new \DateInterval($strInterval));

        $uxtsSub = $dtDate->getTimestamp();
        $iNewOffset = $this->getTimezoneOffset($uxtsSub);
        if ($iOffset !== $iNewOffset) {
            $uxtsSub += $iOffset - $iNewOffset;
        }
        return $uxtsSub;
    }

    /**
     * Formats a datetime using the calctimezone.
     * @param string $strFormat
     * @param int $uxtsDateTime
     * @return string
     */
    protected function formatDate(string $strFormat, int $uxtsDateTime) : string
    {
        $iOffset = $this->getTimezoneOffset($uxtsDateTime);
        $strDateTime = gmdate($strFormat, $uxtsDateTime + $iOffset);

        return $strDateTime;
    }

    /**
     * Gets the duration between two timestamps in seconds.
     * In case of a change between DAYLIGHT and STANDARD time in the
     * given span, the result is adopted to this offset change.
     * @param int $uxtsFrom
     * @param int $uxtsTo
     * @return int
     */
    protected function calcDuration(int $uxtsFrom, int $uxtsTo) : int
    {
        $iDuration = $uxtsTo - $uxtsFrom;
        $iOffsetFrom = $this->getTimezoneOffset($uxtsFrom);
        $iOffsetTo = $this->getTimezoneOffset($uxtsTo);
        if ($iOffsetTo !== $iOffsetFrom) {
            $iDuration += $iOffsetTo - $iOffsetFrom;
        }
        return $iDuration;
    }

    /**
     * Get date from n'th weekday in specific month.
     * Finds the date value for the n'th weekday (e.g. '1MO', '2DI', '5SA'...) or
     * last weekday (e.g. '-1SO') of the month, the input date lies in.
     * @param int $uxtsDateTime
     * @param int $iWeekInMonth
     * @param int $iWeekDay
     * @return int
     */
    protected function getDateFromMonthDay(int $uxtsDateTime, int $iWeekInMonth, int $iWeekDay) : int
    {
        // rewind to the 1'st of the month
        $uxtsDateTime = $this->setDateTimePart($uxtsDateTime, 'mday', 1);
        $iMonth = $this->getDateTimePart($uxtsDateTime, 'mon');

        $aMonth = [[]];
        while ($iMonth == $this->getDateTimePart($uxtsDateTime, 'mon')) {
            $aMonth[$this->getDateTimePart($uxtsDateTime, 'wday')][] = $uxtsDateTime;
            $uxtsDateTime = $this->addDate($uxtsDateTime, 'P1D');
        }

        $uxtsDayInMonth = 0;
        if ($iWeekInMonth >= 0) {
            $uxtsDayInMonth = $aMonth[$iWeekDay][$iWeekInMonth];
        } else {
            $iWeekInMonth++;
            $uxtsDayInMonth = $aMonth[$iWeekDay][count($aMonth[$iWeekDay]) + $iWeekInMonth];
        }
        return $uxtsDayInMonth;
    }

    /**
     * Get date from n'th weekday in year.
     * Finds the date value for the n'th weekday (e.g. '1MO', '2DI', '5SA'...) or
     * last weekday (e.g. '-1SO') of the year, the input date lies in.
     * @param int $uxtsDateTime
     * @param int $iWeekInYear
     * @param int $iWeekDay
     * @return int
     */
    protected function getDateFromYearDay(int $uxtsDateTime, int $iWeekInYear, int $iWeekDay) : int
    {
        $uxtsDayInYear = 0;
        if ($iWeekInYear >= 0) {
            // rewind to the 1'st of jan
            $uxtsDateTime = $this->setDateTimePart($uxtsDateTime, 'mday', 1);
            $uxtsDateTime = $this->setDateTimePart($uxtsDateTime, 'mon', 1);
            $iDateDay = $this->getDateTimePart($uxtsDateTime, 'wday');
            while ($iWeekDay != $iDateDay) {
                $uxtsDateTime = $this->addDate($uxtsDateTime, 'P1D');
                $iDateDay = $this->getDateTimePart($uxtsDateTime, 'wday');
            }
            $uxtsDayInYear = $this->addDate($uxtsDateTime, "P{$iWeekInYear}W");
        } else {
            // forward to the 31'st of dec
            $uxtsDateTime = $this->setDateTimePart($uxtsDateTime, 'mon', 12);
            $uxtsDateTime = $this->setDateTimePart($uxtsDateTime, 'mday', 31);
            $iDateDay = $this->getDateTimePart($uxtsDateTime, 'wday');
            while ($iWeekDay != $iDateDay) {
                $uxtsDateTime = $this->subDate($uxtsDateTime, 'P1D');
                $iDateDay = $this->getDateTimePart($uxtsDateTime, 'wday');
            }
            $iWeekInYear *= -1;
            $iWeekInYear -= 2;
            $uxtsDayInYear = $this->subDate($uxtsDateTime, "P{$iWeekInYear}W");
        }
        return $uxtsDayInYear;
    }

    /**
     * Gets the date of the first day of a given week no.
     * @param int $uxtsDateTime
     * @param int $iWeekNo
     * @param int $iWKST
     * @return int
     */
    protected function getWeekNoStart(int $uxtsDateTime, int $iWeekNo, int $iWKST = 1) : ?int
    {
        $uxtsWeek = 0;
        if ($iWeekNo < 0) {
            // Not sure, if this is the correct way...
            $uxtsLastWeek = $this->setDateTimePart($uxtsDateTime, 'mon', 12);
            $uxtsLastWeek = $this->setDateTimePart($uxtsLastWeek, 'mday', 31);
            $iDateWeek = $this->getDateTimePart($uxtsLastWeek, 'week');
            $iWeekNo = 53 + $iWeekNo;   // $iWeekNo is already neg!
            if ($iDateWeek == 53) {
                $iWeekNo++;
            }
        }
        if ($iWeekNo == 0) {
            $uxtsWeek = $uxtsDateTime;
        } else if ($iWeekNo == 53) {
            // ... 53't week not in every year...
            $uxtsWeek = $this->setDateTimePart($uxtsDateTime, 'mon', 12);
            $uxtsWeek = $this->setDateTimePart($uxtsWeek, 'mday', 31);
            $iDateWeek = $this->getDateTimePart($uxtsWeek, 'week');
            if ($iDateWeek != 53) {
                return null;
            }
        } else {
            // start at Jan 4'th because this date is always in KW1
            $uxtsWeek = $this->setDateTimePart($uxtsDateTime, 'mday', 4);
            $uxtsWeek = $this->setDateTimePart($uxtsWeek, 'mon', 1);
            $iDateWeek = $this->getDateTimePart($uxtsWeek, 'week');
            // find a date within the requested week
            while ($iDateWeek !== $iWeekNo && $iDateWeek <= 53) {
                $uxtsWeek = $this->addDate($uxtsWeek, 'P1W');
                $iDateWeek = $this->getDateTimePart($uxtsWeek, 'week');
            }
        }
        // ... and rewind to the weekstart
        $iWeekDay = $this->getDateTimePart($uxtsWeek, 'wday');
        $iWeekDay -= $iWKST;
        if ($iWeekDay < 0) {
            $iWeekDay = 6;
        }
        if ($iWeekDay > 0) {
            $uxtsWeek = $this->subDate($uxtsWeek, "P{$iWeekDay}D");
        }
        return $uxtsWeek;
    }
}
