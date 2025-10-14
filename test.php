<?php

declare(strict_types=1);

use SKien\Test\iCal\UnitTestLogger;
use SKien\iCal\iCalendar;

include 'autoloader.php';



date_default_timezone_set('Europe/Berlin');

$oICal = new iCalendar();

$oLogger = new UnitTestLogger();

$oICal->setLogger($oLogger);
$oICal->defineXProperty('VEVENT', 'X-LOCATION-URL', 'strLocationURL');
$oICal->read('./SKien/Test/iCal/testdata/Testcase6.ics');

$aEvents = $oICal->getEvents();
echo '<pre>';
$i = 0;
foreach ($aEvents as $oEvent) {
    // echo $oEvent->buildData('');
    echo sprintf('%02d', ++$i) . ': ' . date('d.m.Y H:i', $oEvent->getStart()) . ' - ' . date('d.m.Y H:i', $oEvent->getEnd());
    echo PHP_EOL;
}
echo '<br><br>';
print_r($oLogger->getLog());
echo '</pre>';


/*



$oICal = new iCalendar();

$oTimezone = new iCalTimezone($oICal);
$oTimezone->fromTimezone('America/New_York', mktime(0,0,0,1,1,1970), mktime(0,0,0,31,12,2030));
$oICal->addTimezone($oTimezone);
// $oTimezone = iCalTimezone::fromFile('./SKien/Test/iCal/testdata/NewYork.txt', $oICal);

$oTZ = new \DateTimeZone('America/New_York');
$dtStart = new \DateTime('20251102T010000', $oTZ);
$dtMax = null;
// $dtMax = new \DateTime('20050101T000000', $oTZ);
$oRRule = new iCalRecurrenceRule($oICal, 'FREQ=MINUTELY;INTERVAL=10;COUNT=12');

// $dtExclude = new \DateTime('19971024T090000', $oTZ);
// $oRRule->addExcludeDate($dtExclude->getTimestamp());

$aList = $oRRule->getDateList($dtStart->getTimestamp(), $dtMax ? $dtMax->getTimestamp() : 0, $oTimezone->getTZID());

echo '<br>';
echo '<br>';

foreach ($aList as $uxts) {
    $dt = new DateTime();
    $dt->setTimezone($oTZ);
    $dt->setTimestamp($uxts);
    echo '"' . $dt->format('Y-m-d H:i:s') . '",<br>';
}
*/