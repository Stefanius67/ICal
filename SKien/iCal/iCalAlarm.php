<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Class representing an alarm component from an iCalendar (VALARM)
 *
 * An alarm can be set to notify the user.
 * You can configure:
 * <ul><li>
 *   The action to be performed (display, audio signal, email)
 * </li><li>
 *   The time
 * </li><li>
 *   An interval and a number of possible repetitions
 * </li></ul>
 *
 * An instance of an alarm component must only appear within either a "VEVENT" or
 * "VTODO" calendar component.
 *
 * In most cases, the time at which an alarm notification should be triggered is set
 * in relation to the start or end time of the embedded element. However, it is also
 * possible to set an absolute time independent of the parent element. It should be
 * noted that this can lead to undesirable effects with recurring elements.
 *
 * > When configuring a 'VALARM', it should always be noted that the real triggered
 * > action depends on both the processing application program and the capabilities
 * > of the device on which the data is processed.
 *
 *  @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.6
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalAlarm extends iCalComponent
{
    use iCalHelper;

    /** Audio notification at trigger time     */
    public const AUDIO      = 'AUDIO';
    /** Visual notification at trigger time     */
    public const DISPLAY    = 'DISPLAY';
    /** E-Mail notification at trigger time     */
    public const EMAIL      = 'EMAIL';

    /** @var iCalAlarmParentInterface  the parent, this alarm belongs to     */
    protected iCalAlarmParentInterface $oParent;

    /** @var string|null action to perform on the alarm ('AUDIO', 'DISPLAY' or 'EMAIL')  */
    protected ?string $strAction = null;
    /** @var int|null    unix timestamp for the alarm trigger         */
    protected ?int $uxtsTrigger = null;
    /** @var int|null    duration in seconds     */
    protected ?int $iTrigger = null;
    /** @var string      trigger type ('DURATION' / 'DATE-TIME')     */
    protected ?string $strTriggerType = null;
    /** @var string edge the trigger duration is related to ('START', 'END')     */
    protected string $strRelated = 'START';
    /** @var int|null    intervall in seconds the alarm hava to be repeated    */
    protected ?int $iRepeatInterval = null;
    /** @var int|null    count of repetitions    */
    protected ?int $iRepeatCount = null;

    /**
     * Creates a new VALARM.
     * The item (VEVENT or VTODO), the new alarm must be passed.
     * @param iCalAlarmParentInterface $oParent item, this alarm is embedded in.
     */
    public function __construct(iCalAlarmParentInterface $oParent)
    {
        $this->oParent = $oParent;
        parent::__construct('VALARM', $oParent->getICalendar());
    }

    /**
     * Validates an instance.
     * This method should be called from the parent as last call within its own
     * `validate()` method after all informations verified that may be needed
     * to callculate the trigger time!
     */
    public function validate() : void
    {
        if ($this->strAction === null) {
            $this->oICalendar->log(LogLevel::CRITICAL, 'VALARM: the action MUST be set!');
        }
        if ($this->iTrigger !== null) {
            $strMsg = '';
            if ($this->strRelated == 'START') {
                $this->uxtsTrigger = $this->oParent->getStart();
                $strMsg = 'VALARM: the trigger relates to not set START from the embedding Component!';
            } else {
                $this->uxtsTrigger = $this->oParent->getEnd();
                $strMsg = 'VALARM: the trigger relates to not set END or DURATION from the embedding Component!';
            }
            if ($this->uxtsTrigger === null) {
                $this->oICalendar->log(LogLevel::CRITICAL, $strMsg);
            } else {
                $strDuration = $this->getDurationString(abs($this->iTrigger));
                if ($this->iTrigger > 0) {
                    $this->uxtsTrigger = $this->addDate($this->uxtsTrigger, $strDuration);
                } else {
                    $this->uxtsTrigger = $this->subDate($this->uxtsTrigger, $strDuration);
                }
            }
        } else if ($this->uxtsTrigger !== null) {
            $uxtsRelated = $this->oParent->getStart();
            if ($uxtsRelated !== null) {
                // if possible, determine duration an relation
                $this->iTrigger = $this->calcDuration($uxtsRelated, $this->uxtsTrigger);
                $this->strRelated = 'START';
            }
        } else {
            $this->oICalendar->log(LogLevel::CRITICAL, 'VALARM: the trigger MUST be set!');
        }
        if ($this->iRepeatInterval !== null && $this->iRepeatCount === null) {
            $this->oICalendar->log(LogLevel::CRITICAL, 'VALARM: repeat time is set without repeat count!');
        } else if ($this->iRepeatInterval === null && $this->iRepeatCount !== null) {
            $this->oICalendar->log(LogLevel::CRITICAL, 'VALARM: repeat count is set without repeat time!');
        }
    }

    /**
     * Returns an event as associative array.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::fetchData()
     */
    public function fetchData() : array
    {
        $aValues = [
            'dtTrigger'         => $this->uxtsTrigger ? date('Y-m-d H:i:s', $this->uxtsTrigger) : '',
            'uxtsTrigger'       => $this->uxtsTrigger,
            'iTrigger'          => $this->iTrigger,
            'strTriggerType'    => $this->strTriggerType,
            'strAction'         => $this->strAction,
            'strRelated'        => $this->strRelated,
            'iRepeatCount'      => $this->iRepeatCount,
            'iRepeatInterval'   => $this->iRepeatInterval,
        ];

        return $aValues;
    }

    /**
     * Sets the action to perform at the alarms trigger time.
     * It's on the agent if and how to perform the action at designated time.
     * @param string $strAction     Action ('AUDIO', 'DISPLAY' or 'EMAIL')
     */
    public function setAction(string $strAction) : void
    {
        if (in_array($strAction, ['AUDIO', 'DISPLAY', 'EMAIL'])) {
            $this->strAction = $strAction;
        }
    }

    /**
     * Gets the action to perform at the alarms trigger time.
     * @see iCalAlarm::setAction
     * @return string       Action ('AUDIO', 'DISPLAY' or 'EMAIL')
     */
    public function getAction() : ?string
    {
        return $this->strAction;
    }

    /**
     * Sets the trigger as ISO 8601 duration string related to start/end of the event.
     * A negative value (a `-` character before the complete string / f.i. `-PT15M`
     * 15 min **before**...) indicates a time before the specified reference point.
     * @link https://en.wikipedia.org/wiki/ISO_8601#Durations
     * @param string $strTrigger    trigger as ISO 8601 duration
     * @param string $strRelated    trigger relates to ('START' or 'END'; default: 'START')
     */
    public function setTrigger(string $strTrigger, string $strRelated = 'START') : void
    {
        $this->iTrigger = $this->parseDurationString($strTrigger);
        if ($this->iTrigger !== null && in_array($strRelated, ['START', 'END'])) {
            $this->strRelated = $strRelated;
            $this->strTriggerType = 'DURATION';
        }
    }

    /**
     * Sets the trigger as absolute timestamp.
     * The absolute trigger is independent from any sttings of the embedding item.
     * @param int|\DateTime $trigger
     */
    public function setTriggerTime($trigger) : void
    {
        if ($trigger instanceof \DateTime) {
            $this->uxtsTrigger = $trigger->getTimestamp();
        } else {
            $this->uxtsTrigger = $trigger;
        }
        $this->strTriggerType = 'DATE-TIME';
    }

    /**
     * Sets the trigger in seconds related to start/end of the event.
     * A negative value indicates a trigger time **before** the specified reference
     * point.
     * @param int $iTrigger         seconds related to the specified reference
     * @param string $strRelated    trigger relates to ('START' or 'END'; default: 'START')
     */
    public function setTriggerFrom(int $iTrigger, string $strRelated = 'START') : void
    {
        $this->iTrigger = $iTrigger;
        $this->strRelated = $strRelated;
        $this->strTriggerType = 'DURATION';
    }

    /**
     * Returns the absolute trigger timestamp if set.
     * @return int  unix timestamp of the alarm trigger
     */
    public function getTriggerTime() : ?int
    {
        return $this->uxtsTrigger;
    }

    /**
     * Gets the trigger related to the event start/end.
     * @param string $strRelated    reference param!
     * @return int|null
     */
    public function getTriggerFrom(string &$strRelated) : ?int
    {
        if ($this->iTrigger !== null) {
            $strRelated = $this->strRelated;
        }
        return $this->iTrigger;
    }

    /**
     * Reference point of the trigger.
     * @return string   'START' or 'END'
     */
    public function relatesTo() : string
    {
        return $this->strRelated;
    }

    /**
     * Sets the repeat interval time.
     * This interval allows to set the alarm to be repeated after the specified
     * time. <br>
     * The interval can be passed as integer (in seconds) or as a ISO 8601
     * duration string.
     * @link https://en.wikipedia.org/wiki/ISO_8601#Durations
     * @param int|string $repeatInterval
     */
    public function setRepeatInterval($repeatInterval) : void
    {
        if (is_numeric($repeatInterval)) {
            $this->iRepeatInterval = intval($repeatInterval);
        } else {
            $this->iRepeatInterval = $this->parseDurationString($repeatInterval);
        }
    }

    /**
     * Gets the repeat interval time.
     * @see iCalAlarm::setRepeatInterval()
     * @return int  repeat interval in seconds
     */
    public function getRepeatInterval() : ?int
    {
        return $this->iRepeatInterval;
    }

    /**
     * Sets the repeat count.
     * This  is the count, how often the alarm will be repeated until it has
     * been conformed by the user.
     * @param int $iRepeatCount
     */
    public function setRepeatCount(int $iRepeatCount) : void
    {
        $this->iRepeatCount = $iRepeatCount;
    }

    /**
     * Gets the repeat count.
     * @return int
     */
    public function getRepeatCount() : ?int
    {
        return $this->iRepeatCount;
    }

    /**
     * Write the component data to the Writer instance.
     * {@inheritDoc}
     * @see \SKien\iCal\iCalComponent::writeData()
     */
    public function writeData(Writer $oWriter, string $strTZID = '') : void
    {
        $oWriter->addProperty('BEGIN', 'VALARM');
        $oWriter->addProperty('ACTION', $this->strAction ?? '');
        if ($this->strTriggerType === 'DATE-TIME' && $this->uxtsTrigger !== null) {
            $oWriter->addProperty('TRIGGER', gmdate('YmdTHisZ', $this->uxtsTrigger), false, ['VALUE' => 'DATE-TIME']);
        } else if ($this->iTrigger !== null) {
            $oWriter->addProperty('TRIGGER', $this->getDurationString($this->iTrigger), false, ['RELATED' => $this->strRelated]);
        }
        $oWriter->addProperty('DESCRIPTION', $this->strDescription ?? '');
        $oWriter->addProperty('SUMMARY', $this->strSubject ?? '');
        if ($this->iRepeatInterval !== null && $this->iRepeatCount !== null) {
            $oWriter->addProperty('REPEAT', (string) $this->iRepeatCount);
            $oWriter->addProperty('DURATION', $this->getDurationString($this->iRepeatInterval), false);
        }
        $oWriter->addProperty('END', 'VALARM');
    }
}
