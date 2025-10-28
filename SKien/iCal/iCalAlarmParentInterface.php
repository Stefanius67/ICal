<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Components that can contain VALARM properties must implement this interface.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
interface iCalAlarmParentInterface
{
    /**
     * @return iCalendar
     */
    public function getICalendar() : iCalendar;

    /**
     * Creates and embed an alarm component.
     * @return iCalAlarm
     */
    public function createAlarm() : iCalAlarm;

    /**
     * @return int|null start unix timestamp of the parnet component - null if not set
     */
    public function getStart() : ?int;

    /**
     * @return int|null end unix timestamp of the parnet component - null if not set
     */
    public function getEnd() : ?int;
}
