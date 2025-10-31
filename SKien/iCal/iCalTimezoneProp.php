<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Class representing an iCal timezone component that specifies daylight/standard time definitions.
 *
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.5
 * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalTimezoneProp extends iCalComponent
{
    /** Defines the changes for daylight time   */
    public const DAYLIGHT = 'DAYLIGHT';
    /** Defines the changes for standard time   */
    public const STANDARD = 'STANDARD';

    /** @var string TZNAME          */
    protected string $strName = '';
    /** @var string TZOFFSETFROM             */
    protected string $strOffsetFrom = '';
    /** @var string TZOFFSETTO             */
    protected string $strOffsetTo = '';
    /** @var string RRULE     */
    protected string $strRRule = '';
    /** @var array<int> RDATE     */
    protected array $aRDate = [];

    /**
     * Creates a instance of a iCalTimezoneProp property.
     * @param iCalTimezone $oTimezone   parent timezone component
     * @param string $strType           either iCalTimezoneProp::DAYLIGHT or iCalTimezoneProp::STANDARD
     */
    public function __construct(iCalTimezone $oTimezone, string $strType)
    {
        parent::__construct($strType, $oTimezone->getICalendar());

        /*
         * After quite a few iCalendar files surfaced for testing, where the definition
         * of the changes between Daylight/Standard Time started in the 16th century
         * and earlier (which often led to 700 or more date changes...), I decided to
         * start with a minimum date of UNIX timestamp 0 (1970-01-01) for performance
         * reasons.
         */
        $this->uxtsMinStart = 0;
    }

    /**
     * Gets the type of this component.
     * @return string   iCalTimezoneProp::DAYLIGHT or iCalTimezoneProp::STANDARD
     */
    public function getType() : string
    {
        return $this->strComponentName;
    }

    /**
     * Sets the name of this timezone property.
     * @param string $strName
     */
    public function setName(string $strName) : void
    {
        $this->strName = $strName;
    }

    /**
     * Gets the name of this timezone property.
     * @return string
     */
    public function getName() : string
    {
        return $this->strName;
    }

    /**
     * Sets the start datetime for this property.
     * In case of  a string (usually from an import – the string is specified in local
     * time). <br>
     * Since the order in which the properties must be specified in this component is not
     * prescribed, there are two scenarios:  <br>
     * 1. If the 'offsetTo' is already set, we simply append it to the string value and
     *    parse it into a timestamp.
     * 2. If the 'offsetTo' is not set so far, we parse the value as UTC time and add the
     *    offset when it will be set.
     * @param string|int $start
     */
    public function setStart($start) : void
    {
        if (is_string($start)) {
            if (empty($this->strOffsetTo)) {
                $start .= 'Z';
            } else {
                $start .= $this->strOffsetTo;
            }
            $dtStart = new \DateTime($start);
            $this->uxtsStart = $dtStart->getTimestamp();
        } else {
            $this->uxtsStart = $start;
        }
    }

    /**
     * Sets the time offset this timezone changes from.
     * If an int value is passed, it is treated as seconds and converted to the
     * corresponding string offset, which can be appended to a DateTime string
     * @param string|int $offsetFrom
     */
    public function setOffsetFrom($offsetFrom) : void
    {
        if (is_int($offsetFrom)) {
            $this->strOffsetFrom = $this->getOffsetString($offsetFrom);
        } else {
            $this->strOffsetFrom = $offsetFrom;
        }
    }

    /**
     * Gets the time offset this timezone changes from.
     * @return string
     */
    public function getOffsetFrom() : string
    {
        return $this->strOffsetFrom;
    }

    /**
     * Sets the time offset this timezone changes to.
     * If an int value is passed, it is treated as seconds and converted to the
     * corresponding string offset, which can be appended to a DateTime string. <br>
     * If we are 'waiting' for this value, we have to adjust all already readed
     * dtStart/rDate/exDate values by this offset.
     * @see iCalTimezoneProp::setStart()
     * @param string|int $offsetTo
     */
    public function setOffsetTo($offsetTo) : void
    {
        if (is_int($offsetTo)) {
            $this->strOffsetTo = $this->getOffsetString($offsetTo);
        } else {
            $this->strOffsetTo = $offsetTo;
            $iOffset = $this->parseOffset($offsetTo);
            // adjust already existing values...
            if (isset($this->uxtsStart)) {
                $this->uxtsStart -= $iOffset;
            }
            foreach (array_keys($this->aRDate) as $i) {
                $this->aRDate[$i] -= $iOffset;
            }
        }
    }

    /**
     * Gets the time offset this timezone changes to.
     * @return string
     */
    public function getOffsetTo() : string
    {
        return $this->strOffsetTo;
    }

    /**
     * Adds another RDate value to the list.
     * Note: In the context of the timezone, this value MUST contain DATE-TIME(s)
     * without timezone-specifier. <br>
     * The same problem exists concerning the succession of 'RDate(s)' and
     * 'offsetTo'. <br>
     * @see iCalTimezoneProp::setStart()
     * @see iCalTimezoneProp::setOffsetTo()
     * @param string|int $rdate
     */
    public function setRDate($rdate) : void
    {
        if (is_string($rdate)) {
            $strOffset = $this->strOffsetTo;
            if (empty($this->strOffsetTo)) {
                $strOffset = 'Z';
            }
            $aRDate = explode(',', $rdate);
            foreach ($aRDate as $strRDate) {
                $dtRDate = new \DateTime($strRDate . $strOffset);
                $this->aRDate[] = $dtRDate->getTimestamp();
            }
        } else {
            $this->aRDate[] = $rdate;
        }
    }

    /**
     * Write the component data to the Writer instance.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::writeData()
     */
    public function writeData(Writer $oWriter, string $strTZID = '') : void
    {
        $oWriter->addProperty('BEGIN', $this->strComponentName);
        $oWriter->addProperty('TZOFFSETFROM', $this->strOffsetFrom);
        $oWriter->addProperty('TZOFFSETTO', $this->strOffsetTo);
        $oWriter->addProperty('TZNAME', $this->strName);
        $oWriter->addProperty('DTSTART', date('Ymd\THis', $this->uxtsStart));
        if (!empty($this->strRRule)) {
            $oWriter->addProperty('RRULE', $this->strRRule, false);
        }
        foreach ($this->aRDate as $uxtsRDate) {
            $oWriter->addProperty('RDATE', date('Ymd\THis', $uxtsRDate));
        }
        $oWriter->addProperty('END', $this->strComponentName);
    }
}
