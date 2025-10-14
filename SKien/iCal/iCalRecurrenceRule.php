<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;


/**
 * Class representing a an iCalendar recurrence rule (RRULE)
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalRecurrenceRule
{
    protected const MAX_YEAR = 2050;
    protected const MAX_INTERVAL = 2000;

    use iCalHelper;

    /** @var string             one of "SECONDLY", "MINUTELY", "HOURLY", "DAILY", "WEEKLY", "MONTHLY", "YEARLY"     */
    protected string $strFreq = '';
    /** @var int                bound of the recurrence rule in an inclusive manner (DATE or DATE-TIME)     */
    protected ?int $uxtsUntil = null;
    /** @var int                positive number of occurrences      */
    protected ?int $iCount = null;
    /** @var int                positive integer representing at which intervals the recurrence rule repeats     */
    protected int $iInterval = 1;
    /** @var array<int>         array of seconds values (0...60)               */
    protected ?array $aBySecond = null;
    /** @var array<int>         array of miutes values (0...59)               */
    protected ?array $aByMinute = null;
    /** @var array<int>         array of hours values (0...23)               */
    protected ?array $aByHour = null;
    /** @var array<string>      array of weekday values (+/- Digit "SU", "MO", "TU", "WE", "TH", "FR", "SA" )     */
    protected ?array $aByDay = null;
    /** @var array<int>         array of monthday values (+/- 1...31)     */
    protected ?array $aByMonthday = null;
    /** @var array<int>         array of yearday values (+/- 1...366)     */
    protected ?array $aByYearday = null;
    /** @var array<int>         array of week no values (+/- 1...53)     */
    protected ?array $aByWeekNo = null;
    /** @var array<int>         array of months (1...12)     */
    protected ?array $aByMonth = null;
    /** @var int                yearday value (+/- 1...366)     */
    protected ?int $iBySetpos = null;

    /** @var array<int>         dates to exclude from list     */
    protected array $aExclude = [];
    /** @var int                begin of the week (0 => SO; 1 => MO     */
    protected int $iWKST = 0;
    /** @var array<string,int>  weekdaynames => numbers     */
    protected array $aWeekdayNames = [];
    /** @var array<int,string>  weekdaynumbers => names     */
    protected array $aWeekdayNo = [];
    /** @var string             format character for the weekday ('N' or 'w')    */
    protected string $strWeekdayFormat;
    /** @var array<int>         array containing dates for current intervall     */
    protected array $aIntervall = [];
    /** @var array<int>         array containing generated dates as unix timestamps     */
    protected array $aResult = [];
    /** @var bool               set to true if a critical error occurs that would prevent the recurrence from being created.     */
    protected bool $bCriticalError = false;

    /**
     * @param iCalendar $oICalendar
     * @param string $strProperty
     */
    public function __construct(iCalendar &$oICalendar, string $strProperty)
    {
        $this->oICalendar = $oICalendar;
        $this->parseProperty($strProperty);
        $this->validate();
    }

    /**
     * Parse the property into its components.
     * The format and/or value range of parameters is validated direcly while parsing
     * it. <br>
     * The existence of mandatory parameters and validations that describes dependencies
     * between several parameters are done after parsing in the `validate()` method. <br>
     * Multiple occurrences of a parameter that may only occur once are not checked - the
     * last set value is used!
     * @param string $strProperty
     */
    protected function parseProperty(string $strProperty) : void
    {
        $aValueParams = explode(';', $strProperty);
        foreach ($aValueParams as $strParam) {
            $aSplit = explode('=', $strParam);
            if (count($aSplit) == 2) {
                $strName = strtoupper($aSplit[0]);
                $strValue = trim($aSplit[1]);

                switch ($strName) {
                    case 'FREQ':
                        // one of "SECONDLY", "MINUTELY", "HOURLY", "DAILY", "WEEKLY", "MONTHLY", "YEARLY"
                        $this->strFreq = $strValue;
                        $aValidFreq = ['SECONDLY', 'MINUTELY', 'HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
                        $strFreq = $this->strFreq == '' ? '[not set]' : $this->strFreq;
                        if (!in_array($strFreq, $aValidFreq)) {
                            $this->logError(LogLevel::ERROR, 'FREQ', $strFreq);
                            $this->strFreq = '';
                        }
                        break;
                    case 'UNTIL':
                        // bound of the recurrence rule in an inclusive manner (DATE or DATE-TIME)
                        if (preg_match('/^\d{8}(T\d{6}Z{0,1}){0,1}$/m', $strValue)) {
                            if (strlen($strValue) == 8) {
                                // enhance date to date-time
                                $strValue .= 'T235959Z';
                            }
                            $dtStart = new \DateTime($strValue);
                            $this->uxtsUntil = $dtStart->getTimestamp();
                        } else {
                            $this->logError(LogLevel::ERROR, $strName, $strValue);
                        }
                        break;
                    case 'COUNT':
                        // positive number of occurrences
                        $this->iCount = intval($strValue);
                        if ($this->iCount <= 0) {
                            $this->iCount = 1;
                            $this->logError(LogLevel::ERROR, $strName, $strValue);
                        }
                        break;
                    case 'INTERVAL':
                        // positive integer representing at which intervals the recurrence rule repeats
                        $this->iInterval = intval($strValue);
                        if ($this->iInterval <= 0) {
                            $this->iInterval = 1;
                            $this->logError(LogLevel::ERROR, $strName, $strValue);
                        }
                        break;
                    case 'BYSECOND':
                        // array of seconds values (0...59)
                        $this->aBySecond = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aBySecond, 0, 59, true, $strName, $strValue);
                        break;
                    case 'BYMINUTE':
                        // array of miutes values (0...59)
                        $this->aByMinute = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByMinute, 0, 59, true, $strName, $strValue);
                        break;
                    case 'BYHOUR':
                        // array of hours values (0...23)
                        $this->aByHour = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByHour, 0, 23, true, $strName, $strValue);
                        break;
                    case 'BYDAY':
                        // array of weekday values (+/- Digit "SU", "MO", "TU", "WE", "TH", "FR", "SA" )
                        $this->aByDay = $this->parseStringArray($strValue);
                        foreach ($this->aByDay as $strDay) {
                            // if (!preg_match('/^([+-]{0,1}[1,2,3,4,5]{0,1})(SU|MO|TU|WE|TH|FR|SA)$/m', $strDay)) {
                            if (!preg_match('/^([+-]{0,1}[0-9]{0,2})(SU|MO|TU|WE|TH|FR|SA)$/m', $strDay)) {
                                $this->logError(LogLevel::CRITICAL, $strName, $strValue);
                                break;
                            }
                        }
                        break;
                    case 'BYMONTHDAY':
                        // array of monthday values (+/- 1...31)
                        $this->aByMonthday = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByMonthday, -31, 31, false, $strName, $strValue);
                        break;
                    case 'BYYEARDAY':
                        // array of yearday values (+/- 1...366)
                        $this->aByYearday = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByYearday, -366, 366, false, $strName, $strValue);
                        break;
                    case 'BYWEEKNO':
                        // array of week no values (+/- 1...53)
                        // This rule part MUST NOT be used when the FREQ rule part is set to anything other than YEARLY.
                        $this->aByWeekNo = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByWeekNo, -53, 53, false, $strName, $strValue);
                        break;
                    case 'BYMONTH':
                        // array of months (1...12)
                        $this->aByMonth = $this->parseIntArray($strValue);
                        $this->validateIntArray($this->aByMonth, 1, 12, false, $strName, $strValue);
                        break;
                    case 'BYSETPOS':
                        // yearday value (+/- 1...366)
                        // TODO: change to array !!!
                        $this->iBySetpos = intval($strValue);
                        if ($this->iBySetpos < -366 || $this->iBySetpos > 366) {
                            $this->logError(LogLevel::CRITICAL, $strName, $strValue);
                            break;
                        }
                        break;
                    case 'WKST':
                        // weekday start ("SU", "MO")
                        $this->iWKST = $strValue == 'MO' ? 1 : 0;
                        break;
                }
            }
        }
        if ($this->iWKST > 0) {
            $this->aWeekdayNames = ["MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6, "SU" => 7];
        } else {
            $this->aWeekdayNames = ["SU" => 0, "MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6];
        }
        $this->aWeekdayNo = array_flip($this->aWeekdayNames);
        $this->strWeekdayFormat = ($this->iWKST > 0 ? 'N' : 'w');
        $this->sortByDay();
    }

    /**
     * Sorts the byday - list.
     */
    protected function sortByDay() : void
    {
        if ($this->aByDay && count($this->aByDay) > 1) {
            sort($this->aByDay);	// minus signed days first
            $aSorted = [];
            foreach ($this->aByDay as $strDay) {
                $strWeekday = substr($strDay, -2);
                if (isset($this->aWeekdayNames[$strWeekday])) {
                    $iKey = ((1 + intval($this->aWeekdayNames[$strWeekday])) * 10);
                    while (isset($aSorted[$iKey])) {
                        $iKey++;
                    }
                    $aSorted[$iKey] = $strDay;
                }
            }

            ksort($aSorted);
            $this->aByDay = array_values($aSorted);
        }
    }

    /**
     * Validates the parsed rule.
     * The existence of mandatory parameters and dependencies between several
     * parameters.
     */
    protected function validate() : void
    {
        // 1. FREQ must be set
        if (empty($this->strFreq)) {
            $this->oICalendar->log(LogLevel::CRITICAL, "RRULE: the FREQ parameter must be set!");
            $this->bCriticalError = true;
        }
        // 2. UNTIL and COUNT must not be set both within the same rule
        if ($this->uxtsUntil !== null && $this->iCount !== null) {
            $this->oICalendar->log(LogLevel::CRITICAL, "RRULE: UNTIL and COUNT must not both be set!");
            $this->bCriticalError = true;
        }
        // 3. BYWEEKNO must not be used with other FREQ value than YEARLY
        if (isset($this->aByWeekNo) && $this->strFreq !== 'YEARLY') {
            $this->oICalendar->log(LogLevel::CRITICAL, "RRULE: BYWEEKNO must not be used with other FREQ value than YEARLY!");
            $this->bCriticalError = true;
        }
    }

    /**
     * Validates elements of an integer array against given borders.
     * Creates a CRITICAL log entry since further generation of the recurrent rule
     * using invalid values cannot be guaranteed.
     * @param array<int> $aValues
     * @param int $iMin
     * @param int $iMax
     * @param bool $bWithZero
     * @param string $strName
     * @param string $strValue
     */
    protected function validateIntArray(array $aValues, int $iMin, int $iMax, bool $bWithZero, string $strName, string $strValue) : void
    {
        foreach ($aValues as $iValue) {
            if ($iValue < $iMin || $iValue > $iMax || (!$bWithZero && $iValue === 0)) {
                $this->logError(LogLevel::CRITICAL, $strName, $strValue);
                break;
            }
        }
    }

    /**
     * Adds a date to the exclude list.
     * @param int $uxtsExDate
     */
    public function addExcludeDate(int $uxtsExDate) : void
    {
        $this->aExclude[$uxtsExDate] = 1;
    }

    /**
     * Sets a complete array of dates to exclude.
     * We use the timestamp as aray-key; so later we just have to use 'isset'
     * to check, if a timestamp have to be excluded. The value itself is meaningless.
     * @param array<int> $aExclude
     */
    public function setExcludeDates(array $aExclude) : void
    {
        $aExclude = array_unique($aExclude);
        $this->aExclude = array_flip($aExclude);
    }

    /**
     * Creates the datelist that results on the specified rules.
     * @param int $uxtsStartDate
     * @param int $uxtsMaxDate
     * @return array<int>
     */
    public function getDateList(int $uxtsStartDate, int $uxtsMaxDate = 0, string $strTZID = '') : array
    {
        $this->aResult = [];
        $this->aIntervall = [];

        if ($this->bCriticalError) {
            // we are not able to generate the list with the given informations :-(
            return $this->aResult;
        }

        if (!empty($strTZID)) {
            $this->oCalcTimezone = $this->oICalendar->getTimezone($strTZID);
        }

        $iIntervalCount = 0;

        $uxtsNextDate = $uxtsEndDate = $uxtsStartDate;

        // The following loop will be terminated by 'break' as soon as one of the end
        // conditions ('COUNT', 'UNTIL', max. date) is met
        while (true) {
            $this->nextInterval($uxtsNextDate, $uxtsEndDate, ($iIntervalCount == 0));

            $bProcessed = $this->getByMonth($uxtsNextDate, $uxtsEndDate);
            if ($bProcessed) {
                $iIntervalCount++;
            }
            if ($this->iBySetpos !== null) {
                // for BYSETPOS we pick the requested positions out of the
                // current interval result.
                $iIntervallResults = count($this->aIntervall);
                sort($this->aIntervall);
                if ($this->iBySetpos > 0 && $iIntervallResults >= $this->iBySetpos) {
                    $this->aResult[] = $this->aIntervall[$this->iBySetpos - 1];
                } else if ($iIntervallResults >= (-1 * $this->iBySetpos)) {
                    $this->aResult[] = $this->aIntervall[$iIntervallResults + $this->iBySetpos];
                }
            } else {
                if (!$bProcessed) {
                    // There has no processing by a BYxxx rule took place - obviously it is a
                    // simple rule defined only by the frequency and possibly an interval
                    $this->addResult($uxtsNextDate);
                    $iIntervalCount++;
                }
                $this->aResult = array_merge($this->aResult, $this->aIntervall);
                sort($this->aResult);
            }
            $this->aIntervall = [];
            if ($uxtsMaxDate > 0 && $uxtsNextDate > $uxtsMaxDate) {
                break;
            }
            if ($this->endReached()) {
                break;
            }
            if (intval(date("Y", $uxtsNextDate)) > self::MAX_YEAR) {
                break;
            }
            if ($iIntervalCount > self::MAX_INTERVAL) {
                $this->oICalendar->log(LogLevel::CRITICAL, "RRULE: Infinite loop detected in getDateList()!");
                break;
            }
        }
        $this->cleanupResult($uxtsMaxDate);
        $this->oCalcTimezone = null;

        return $this->aResult;
    }

    /**
     *
     * @param int $uxtsNextDate
     * @param int $uxtsEndDate
     * @param bool $bFirst
     */
    protected function nextInterval(int &$uxtsNextDate, int &$uxtsEndDate, bool $bFirst) : void
    {
        switch ($this->strFreq) {
            case "YEARLY":
                if (!$bFirst) {
                    $uxtsNextDate = $this->addDate($uxtsNextDate, "P{$this->iInterval}Y");
                    if (isset($this->aByMonth) || isset($this->aByWeekNo) || isset($this->aByYearday)) {
                        $uxtsNextDate = $this->setDateTimePart($uxtsNextDate, 'mon', 1);
                    }
                    if (isset($this->aByDay)) {
                        $uxtsNextDate = $this->setDateTimePart($uxtsNextDate, 'mday', 1);
                    }
                }
                $uxtsEndDate = $this->addDate($uxtsNextDate, "P1Y");
                break;
            case "MONTHLY":
                if (isset($this->aByMonthday) || isset($this->aByDay)) {
                    $uxtsMonthStart = $this->setDateTimePart($uxtsNextDate, 'mday', 1);
                    if ($this->iBySetpos !== null && $this->iBySetpos > 0) {
                        // for pos. BYSETPOS we have to start from the begin of the month
                        // to get the full resultlist...
                        $uxtsNextDate = $uxtsMonthStart;
                    }
                    if (!$bFirst) {
                        // From the second run, when determining a specific day of the month, we
                        // make sure that we start with the 1st of the month!
                        $uxtsNextDate = $this->addDate($uxtsMonthStart, "P{$this->iInterval}M");
                        $uxtsNextDate = $this->setDateTimePart($uxtsNextDate, 'mday', 1);
                        $uxtsMonthStart = $uxtsNextDate;
                    }
                    $uxtsEndDate = $this->addDate($uxtsMonthStart, "P1M");
                } else {
                    if (!$bFirst) {
                        $uxtsNextDate = $this->addDate($uxtsNextDate, "P{$this->iInterval}M");
                    }
                    $uxtsEndDate = $this->addDate($uxtsNextDate, "P1M");
                }
                break;
            case "WEEKLY":
                if (isset($this->aByDay)) {
                    if (!$bFirst) {
                        $uxtsNextDate = $this->addDate($uxtsNextDate, "P{$this->iInterval}W");
                        $uxtsNextDate = $this->getWeekNoStart($uxtsNextDate, 0, $this->iWKST) ?? $uxtsNextDate;
                        $uxtsEndDate = $this->addDate($uxtsNextDate, "P1W");
                    } else {
                        $uxtsWeekStart = $this->getWeekNoStart($uxtsNextDate, 0, $this->iWKST) ?? $uxtsNextDate;
                        $uxtsEndDate = $this->addDate($uxtsWeekStart, "P1W");
                    }
                } else {
                    if (!$bFirst) {
                        $uxtsNextDate = $this->addDate($uxtsNextDate, "P{$this->iInterval}W");
                    }
                    $uxtsEndDate = $this->addDate($uxtsNextDate, "P1W");
                }
                break;
            case 'DAILY':
                if (!$bFirst) {
                    $uxtsNextDate = $this->addDate($uxtsNextDate, "P{$this->iInterval}D");
                }
                $uxtsEndDate = $this->addDate($uxtsNextDate, "P1D");
                break;
            case 'HOURLY':
            case 'MINUTELY':
            case 'SECONDLY':
                $aPart = [
                    'SECONDLY'  => "PT%dS",
                    'MINUTELY'  => "PT%dM",
                    'HOURLY'    => "PT%dH",
                    'DAILY'     => "P%dD",
                ];
                // Note:
                // For hourly or smaller freqency, no correction is made regarding a
                // time offset resulting from the change between daylight and standard
                // time, since the resulting gaps are desired and a correction of
                // -1 hour could potentially result in an endless loop.
                $dtDate = new \DateTime();
                $dtDate->setTimezone(new \DateTimeZone('UTC'));
                $dtDate->setTimestamp($uxtsNextDate);
                if (!$bFirst) {
                    $strInterval = sprintf($aPart[$this->strFreq], $this->iInterval);
                    $dtDate->add(new \DateInterval($strInterval));
                    $uxtsNextDate = $dtDate->getTimestamp();
                }
                $strInterval = sprintf($aPart[$this->strFreq], 1);
                $dtDate->add(new \DateInterval($strInterval));
                $uxtsEndDate = $dtDate->getTimestamp();
                break;
        }
    }

    /**
     * Cleans the result from dates outside of the defined range.
     * @param int $uxtsMaxDate
     */
    protected function cleanupResult(int $uxtsMaxDate) : void
    {
        while ($this->iCount !== null && count($this->aResult) > $this->iCount) {
            array_pop($this->aResult);
        }
        while ($uxtsMaxDate > 0 && end($this->aResult) !== false && end($this->aResult) > $uxtsMaxDate) {
            array_pop($this->aResult);
        }
        while ($this->uxtsUntil !== null && end($this->aResult) !== false && end($this->aResult) > $this->uxtsUntil) {
            array_pop($this->aResult);
        }
        $this->aResult = array_unique($this->aResult);
        sort($this->aResult);
    }

    /**
     * Check if end of a rule has been reached
     * @return bool
     */
    protected function endReached() : bool
    {
        $bEnd = false;

        // prevent count of duplicate dates
        $this->aResult = array_unique($this->aResult);

        if ($this->iCount !== null) {
            // exceeded count
            $bEnd =  count($this->aResult) >= $this->iCount;
        } elseif ($this->uxtsUntil !== null) {
            // past until
            $bEnd = count($this->aResult) > 0 && end($this->aResult) > $this->uxtsUntil;
        }
        return $bEnd;
    }

    /**
     * Get repeating dates by month.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByMonth(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByMonth)) {
            foreach ($this->aByMonth as $iMonth) {
                $uxtsDate = $this->setDateTimePart($uxtsStartDate, 'mon', $iMonth);
                $uxtsFrom = $this->setDateTimePart($uxtsDate, 'mday', 1);
                $uxtsTo = $this->addDate($uxtsFrom, 'P1M');
                if ($uxtsFrom < $uxtsStartDate) {
                    // The start date must not be exceeded!
                    $uxtsFrom = $uxtsStartDate;
                }
                if ($uxtsStartDate <= $uxtsDate && $uxtsDate < $uxtsEndDate) {
                    $bProcessed = $this->getByWeekNo($uxtsFrom, $uxtsTo);
                    if (!$bProcessed) {
                        $this->addResult($uxtsDate);
                        $bProcessed = true;
                    }
                } else {
                    $bProcessed = true;
                }
            }
        } else {
            $bProcessed = $this->getByWeekNo($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by week no.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByWeekNo(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByWeekNo)) {
            foreach ($this->aByWeekNo as $iWeekNo) {
                $uxtsFrom = $this->getWeekNoStart($uxtsStartDate, $iWeekNo);
                if ($uxtsFrom !== null) {
                    $uxtsTo = $this->addDate($uxtsFrom, 'P1W');
                    $uxtsDate = $uxtsFrom;
                    if ($uxtsDate < $uxtsStartDate) {
                        $uxtsDate = $uxtsStartDate;
                    }
                    if ($uxtsStartDate <= $uxtsDate && $uxtsDate <= $uxtsEndDate) {
                        $bProcessed = $this->getByYearDay($uxtsDate, $uxtsTo);
                    }
                }
            }
            $bProcessed = true;
        } else {
            $bProcessed = $this->getByYearDay($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by year day.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByYearDay(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByYearday)) {
            foreach ($this->aByYearday as $iDay) {
                // start from Jan 1'st of actual year
                $uxtsDate = $this->setDateTimePart($uxtsStartDate, 'mday', 1);
                $uxtsDate = $this->setDateTimePart($uxtsDate, 'mon', 1);
                if ($iDay > 1) {
                    // 'pos' days we need to subtract 1 (Jan 1'st is already the first day of the year.. )
                    $iDay--;
                    $uxtsDate = $this->addDate($uxtsDate, "P{$iDay}D");
                } else if ($iDay < 0) {
                    $uxtsDate = $this->addDate($uxtsDate, "P1Y");
                    $iDay *= -1;
                    $uxtsDate = $this->subDate($uxtsDate, "P{$iDay}D");
                }
                if ($uxtsStartDate <= $uxtsDate && $uxtsDate <= $uxtsEndDate) {
                    // don't call $this->getByMonthDay since a combination of BYYEARDAY / BYMONTHDAY don't work
                    $bProcessed = $this->getByMonthDay($uxtsDate, $uxtsEndDate);
                    if (!$bProcessed) {
                        $this->addResult($uxtsDate);
                        $bProcessed = true;
                    }
                } else {
                    $bProcessed = true;
                }
            }
        } else {
            $bProcessed = $this->getByMonthDay($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by month day.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByMonthDay(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByMonthday)) {
            foreach ($this->aByMonthday as $iDay) {
                if ( $iDay < 0) {
                    // 'negative' days we have to count from following month...
                    $uxtsDate = $this->addDate($uxtsStartDate, 'P1M');
                    $iDay++;
                } else {
                    $uxtsDate = $uxtsStartDate;
                }
                $uxtsDate = $this->setDateTimePart($uxtsDate, 'mday', $iDay);
                if ($uxtsStartDate <= $uxtsDate && $uxtsDate <= $uxtsEndDate) {
                    $bProcessed = $this->getByDay($uxtsDate, $this->addDate($uxtsDate, 'P1D'));
                    if (!$bProcessed) {
                        $this->addResult($uxtsDate);
                        $bProcessed = true;
                    }
                }
            }
        } else {
            $bProcessed = $this->getByDay($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by day.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByDay(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByDay)) {
            $aDayNr = ["SU" => 0, "MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6];

            foreach($this->aByDay as $strDay){
                $strDayName = substr($strDay, -2);
                $iDay = $aDayNr[$strDayName];
                if (strlen($strDay) > 2) {
                    $i = intval(substr($strDay, 0, strlen($strDay) - 2));
                    if ($this->strFreq == "YEARLY" && $this->aByMonth === null) {
                        $uxtsDate = $this->getDateFromYearDay($uxtsStartDate, $i - 1, $iDay);
                    } else {
                        $uxtsDate = $this->getDateFromMonthDay($uxtsStartDate, $i - 1, $iDay);
                    }
                    if ($uxtsStartDate <= $uxtsDate && $uxtsDate < $uxtsEndDate) {
                        $bProcessed = $this->getByHour($uxtsDate, $uxtsEndDate);
                        if (!$bProcessed) {
                            $this->addResult($uxtsDate);
                            $bProcessed = true;
                        }
                    }
                } else {
                    // day of week version
                    $uxtsDate = $uxtsStartDate;
                    while ($uxtsDate < $uxtsEndDate) {
                        $uxtsNext = $this->addDate($uxtsDate, 'P1D');
                        $iDateDay = $this->getDateTimePart($uxtsDate, 'wday');
                        if ($iDay == $iDateDay) {
                            $bProcessed = $this->getByHour($uxtsDate, $uxtsNext);
                            if (!$bProcessed) {
                                $this->addResult($uxtsDate);
                                $bProcessed = true;
                            }
                        }
                        $uxtsDate = $uxtsNext;
                    }
                }
            }
            $bProcessed = true;
        } else {
            $bProcessed = $this->getByHour($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by hour.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByHour(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByHour)) {
            foreach ($this->aByHour as $iHour) {
                $uxtsDate = $this->setDateTimePart($uxtsStartDate, 'hours', $iHour);
                // adjust to timezone offset
                $uxtsDate -= $this->getTimezoneOffset($uxtsDate);

                if ($uxtsStartDate <= $uxtsDate && $uxtsDate < $uxtsEndDate) {
                    $bProcessed = $this->getByMinute($uxtsDate, $uxtsEndDate);
                    if (!$bProcessed) {
                        $this->addResult($uxtsDate);
                        $bProcessed = true;
                    }
                }
            }
            $bProcessed = true;
        } else {
            $bProcessed = $this->getByMinute($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by minute.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getByMinute(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aByMinute)) {
            foreach ($this->aByMinute as $iMinute) {
                $uxtsDate = $this->setDateTimePart($uxtsStartDate, 'minutes', $iMinute);
                if ($uxtsStartDate <= $uxtsDate && $uxtsDate < $uxtsEndDate) {
                    $bProcessed = $this->getBySecond($uxtsDate, $uxtsEndDate);
                    if (!$bProcessed) {
                        $this->addResult($uxtsDate);
                        $bProcessed = true;
                    }
                }
                $bProcessed = true;
            }
        } else {
            $bProcessed = $this->getBySecond($uxtsStartDate, $uxtsEndDate);
        }
        return $bProcessed;
    }

    /**
     * Get repeating dates by second.
     * @param int $uxtsStartDate
     * @param int $uxtsEndDate
     * @return bool
     */
    protected function getBySecond(int $uxtsStartDate, int $uxtsEndDate) : bool
    {
        $bProcessed = false;
        if (isset($this->aBySecond)) {
            foreach ($this->aBySecond as $iSecond) {
                $uxtsDate = $this->setDateTimePart($uxtsStartDate, 'seconds', $iSecond);
                if ($uxtsStartDate <= $uxtsDate && $uxtsDate < $uxtsEndDate) {
                    $this->addResult($uxtsDate);
                    $bProcessed = true;
                }
            }
            $bProcessed = true;
        }
        return $bProcessed;
    }

    /**
     * Adds the given date to the resultlist.
     * Checks for dates to exclude...
     * @param int $uxtsDate
     */
    protected function addResult(int $uxtsDate) : void
    {
        if (count($this->aExclude) > 0) {
            // skip, if date contains to the exclude list
            if (isset($this->aExclude[$uxtsDate])) {
                return;
            }
        }
        $this->aIntervall[] = $uxtsDate;
    }

    /**
     * Log invalid or missing data.
     * @param string $strLevel
     * @param string $strName
     * @param mixed $value
     */
    private function logError(string $strLevel, string $strName, mixed $value) : void
    {
        $this->oICalendar->log($strLevel, "RRULE: invalid parameter {$strName}!", ['value' => $value]);
        if ($strLevel == LogLevel::CRITICAL) {
            $this->bCriticalError = true;
        }
    }
}
