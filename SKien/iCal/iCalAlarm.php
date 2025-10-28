<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 *  Class representing a alarm component from an iCalendar (VALARM)
 *
 *  An instance of an alarm component must only appear within either a "VEVENT" or
 *  "VTODO" calendar component.
 *
 *  @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.6
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class iCalAlarm extends iCalComponent
{
    use iCalHelper;

    public const AUDIO      = 'AUDIO';
    public const DISPLAY    = 'DISPLAY';
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
     * @param iCalAlarmParentInterface $oParent
     */
    public function __construct(iCalAlarmParentInterface $oParent)
    {
        $this->oParent = $oParent;
        parent::__construct('VALARM', $oParent->getICalendar());
    }

    /**
     * Validates a instance.
     * This method should be called from the parent as last call within
     * its `validate()` method!
     * TODO: adjust trigger if a recurring event is created, because $this->uxtsTrigger
     * relates to date-times of the origin event!
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
     * Returns an alarm item as associative array.
     * @return array<string, mixed>
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
     * @return string
     */
    public function getAction() : ?string
    {
        return $this->strAction;
    }

    /**
     * Sets the trigger as duration string related to start/end of the event.
     * @param string $strTrigger
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
     * Sets the trigger to an absolute timestamp.
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
     * @param int $iTrigger
     * @param string $strRelated
     */
    public function setTriggerFrom(int $iTrigger, string $strRelated = 'START') : void
    {
        $this->iTrigger = $iTrigger;
        $this->strRelated = $strRelated;
        $this->strTriggerType = 'DURATION';
    }

    /**
     * @return int
     */
    public function getTriggerTime() : ?int
    {
        return $this->uxtsTrigger;
    }

    /**
     * Gets the trigger related to the event start/end.
     * @param string $strRelated    reference param!
     * @return int|NULL
     */
    public function getTriggerFrom(string &$strRelated) : ?int
    {
        if ($this->iTrigger !== null) {
            $strRelated = $this->strRelated;
        }
        return $this->iTrigger;
    }

    /**
     * @return string
     */
    public function relatesTo() : string
    {
        return $this->strRelated;
    }

    /**
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
     * @return int
     */
    public function getRepeatInterval() : ?int
    {
        return $this->iRepeatInterval;
    }

    /**
     * @param int $iRepeatCount
     */
    public function setRepeatCount(int $iRepeatCount) : void
    {
        $this->iRepeatCount = $iRepeatCount;
    }

    /**
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
