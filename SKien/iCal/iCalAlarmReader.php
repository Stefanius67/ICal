<?php

declare(strict_types=1);

namespace SKien\iCal;


/**
 * Helper class to read lines from a iCal inside of a VALARM component.
 *
 * @see iCalAlarm
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class iCalAlarmReader extends Reader
{
    public const COMPONENT_NAME = 'VALARM';

    /** @var iCalAlarmParentInterface  the parent the alarm is embeded in     */
    protected iCalAlarmParentInterface $oParent;
    /** @var iCalAlarm  the alarm component      */
    protected iCalAlarm $oAlarm;

    /**
     * Create a alarm reader instance.
     * @param iCalAlarmParentInterface $oParent
     */
    function __construct(iCalAlarmParentInterface $oParent)
    {
        parent::__construct($oParent->getICalendar());
        $this->oParent = $oParent;
        $this->oAlarm = $oParent->createAlarm();
    }

    /**
     * Checks whether the end of the component has been reached.
     * The validation must be performed by the parent element, as there is no guarantee
     * that all required properties of the parent element (DTSTART, ..) are set at this
     * point (although it is common practice to define a VALARM component at the end of
     * a component, there is no specification about the order in which a component's
     * properties are listed in the iCalendar file).
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
        // or (string) property from iCalAlarm ($this->oAlarm)
        //
        //      settername(string strValue);
        //
        $aMethodOrProperty = [
            // iCalAlarmReader methods
            'TRIGGER'       => 'parseTrigger',
            // iCalAlarm setters
            'ACTION'        => 'setAction',
            'DESCRIPTION'   => 'setDescription',
            'SUMMARY'       => 'setSubject',
            'DURATION'      => 'setRepeatInterval',
            'REPEAT'        => 'setRepeatCount',
            'ATTENDEE'      => 'addAttendee',
        ];

        if (isset($aMethodOrProperty[$strName])) {
            $strPtr = $aMethodOrProperty[$strName];
            $ownMethod = [$this, $strPtr];
            $childMethod = [$this->oAlarm, $strPtr];
            if (is_callable($ownMethod)) {
                // call own method
                call_user_func_array($ownMethod, array($strName, $strValue, $aParams));
            } elseif (is_callable($childMethod)) {
                // call setter from contact with unmasket value
                call_user_func_array($childMethod, array($this->unmaskString($strValue)));
            }
        }
    }

    /**
     * Parse the TRIGGER value.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.6.3
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseTrigger(string $strName, string $strValue, array $aParams) : void
    {
        $strType = $aParams['VALUE'] ?? 'DURATION';
        if (in_array($strType, ['DURATION', 'DATE-TIME'])) {
            if ($strType == 'DURATION') {
                $this->oAlarm->setTrigger($strValue, $aParams['RELATED'] ?? 'START');
            } else {
                $uxtsTrigger = $this->parseDateTimeValue($strValue, $aParams);
                if ($uxtsTrigger !== null) {
                    $this->oAlarm->setTriggerTime($uxtsTrigger);
                }
            }
        }
    }
}
