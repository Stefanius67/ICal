<?php

declare(strict_types=1);

include 'autoloader.php';

use SKien\iCal\iCalEvent;
use SKien\iCal\iCalendar;

/**
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */

date_default_timezone_set('Europe/Berlin');

$oICal = new iCalendar('Test');

$oEvent = $oICal->createEvent();
$oEvent->setStart(new DateTime('2025-02-23 14:00'));
$oEvent->setDuration(3600);
$oEvent->setSubject('This is the first Testevent to create');
$oEvent->setDescription("The sample demonstrates, how to create a Event\nwith a multiline description!");
$oEvent->setTransparency(iCalEvent::TRANSP_OPAQUE);
$oEvent->setAlarm('-PT30M');
$oEvent->setRRule('FREQ=WEEKLY;INTERVAL=2;COUNT=10');

$oICal->addItem($oEvent);

$oICal->write();
