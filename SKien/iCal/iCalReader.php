<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Helper class to read global lines from a iCal.
 *
 * Since there are no rules regarding the order of components in an iCalendar,
 * it is unfortunately also allowed that the time zone definition(s) occur AFTER
 * other components (even if these other components reference a subsequent time
 * zone!), this reader must be called twice:
 * <ul><li>
 *  first run to search and read all contained timezones    </li><li>
 *  second run to read all other components                 </li></ul>
 * <br>
 * > In fact, I also found some examples where the time zone definitions were   <br>
 * > placed at the end of the file AFTER the other components (whatever the     <br>
 * > point of that is...) - otherwise, I probably wouldn't have even thought    <br>
 * > of considering this possibility in the implementation ;-)
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
    /** @var bool   set to true, if we want to read existing timezones     */
    protected bool $bReadTimezones = false;
    /** @var string name of the component currently to skip...     */
    protected string $strSkipComponent = '';

    /**
     * Create a reader object.
     * @param iCalendar $oICalendar
     */
    function __construct(iCalendar $oICalendar, bool $bReadTimezones)
    {
        $this->bReadTimezones = $bReadTimezones;
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
            'END'           => 'endProp',
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
            } elseif (empty($this->strSkipComponent) && is_callable($childMethod)) {
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
        } else if (empty($this->strSkipComponent)) {
            if ($this->bReadTimezones) {
                if ($strValue == 'VTIMEZONE') {
                    $this->oReader = new iCalTimezoneReader($this->oICalendar);
                } else {
                    $this->strSkipComponent = $strValue;
                }
            } else {
                switch ($strValue) {
                    case 'VEVENT':
                        $this->oReader = new iCalEventReader($this->oICalendar);
                        break;
                    case 'VTODO':
                        $this->oReader = new iCalToDoReader($this->oICalendar);
                        break;
                    case 'VTIMEZONE':
                        $this->strSkipComponent = $strValue;
                        break;
                    default:
                        $this->oICalendar->log(LogLevel::CRITICAL, "Not supportet component {$strValue} found!");
                        $this->strSkipComponent = $strValue;
                        break;
                }
            }
        }
    }

    /**
     * Start a nested property.
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function endProp(string $strName, string $strValue, array $aParams) : void
    {
        if ($this->bStarted && !empty($strValue) && $strValue == $this->strSkipComponent) {
            $this->strSkipComponent = '';
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
