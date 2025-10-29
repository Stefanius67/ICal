<?php

declare(strict_types=1);

use SKien\Test\iCal\UnitTestLogger;
use SKien\iCal\iCalendar;

require_once 'autoloader.php';

date_default_timezone_set('Europe/Berlin');

?>
<!DOCTYPE html>
<html lang="de">
<head><title>iCalendar Importtest Display</title>
<meta charset="UTF8">
</head>
<body>
<?php

$strFilename = 'test.ics';
if (isset($_FILES['icsFile']) && $_FILES['icsFile']['tmp_name'] != '') {
	// to test different own files use ImportSelect.html...)
	$strFilename = $_FILES['icsFile']['tmp_name'];
}
/*
if (isset($_REQUEST['encoding'])) {
	$strEncoding = $_REQUEST['encoding'];
}
*/

$oICal = new iCalendar('test');
$oLogger = new UnitTestLogger();
$oICal->setLogger($oLogger);

$oICal->read($strFilename);
$aEvents = $oICal->getEvents();
if (count($aEvents) > 0) {
    echo '<h1>Events</h1>' . PHP_EOL;
    foreach ($aEvents as $oItem) {
        echo '<h2>' . $oItem->getSubject() . '</h2>' . PHP_EOL;
    	$strFormat = $oItem->getAllDay() ? 'Y-m-d' : 'Y-m-d H:i';
    	$strFromTo = date($strFormat, $oItem->getStart());
    	if ($oItem->getEnd() !== null) {
    	    $strFromTo .= ' - ' . date($strFormat, $oItem->getEnd());
    	}
    	echo $strFromTo . '<br>' . PHP_EOL;
    	echo '<hr>' . PHP_EOL;
    	if ($oItem->hasHtmlDescription()) {
    	    echo '<div>' . PHP_EOL;
    	    echo $oItem->getHtmlDescription() . PHP_EOL;
    	    echo '</div>' . PHP_EOL;
    	} else {
    	    $strDescription = $oItem->getDescription();
        	if (!empty($strDescription)) {
        	    echo '<pre>' . PHP_EOL;
        	    echo $strDescription . PHP_EOL;
        	    echo '</pre>' . PHP_EOL;
        	}
    	}
    }
}
$aToDos = $oICal->getToDos();
if (count($aToDos) > 0) {
    echo "<h1>ToDo's</h1>" . PHP_EOL;
    foreach ($aToDos as $oItem) {
        echo '<h2>' . $oItem->getSubject() . '</h2>' . PHP_EOL;
        $strFormat = 'Y-m-d H:i';
        if ($oItem->getStart() !== null) {
            echo 'Start: ' . date($strFormat, $oItem->getStart()) . '<br>' . PHP_EOL;
        }
        if ($oItem->getDue() !== null) {
            echo 'Due: ' . date($strFormat, $oItem->getDue()) . '<br>' . PHP_EOL;
        }
        if ($oItem->getCompleted() !== null) {
            echo 'Completed: ' . date($strFormat, $oItem->getCompleted()) . '<br>' . PHP_EOL;
        }
        echo '<hr>' . PHP_EOL;
        if ($oItem->hasHtmlDescription()) {
            echo '<div>' . PHP_EOL;
            echo $oItem->getHtmlDescription() . PHP_EOL;
            echo '</div>' . PHP_EOL;
        } else {
            $strDescription = $oItem->getDescription();
            if (!empty($strDescription)) {
                echo '<pre>' . PHP_EOL;
                echo $strDescription . PHP_EOL;
                echo '</pre>' . PHP_EOL;
            }
        }
    }
}

$aLog = $oLogger->getLog();
if (count($aLog) > 0) {
    echo "<h1>Log</h1>" . PHP_EOL;
    echo '<pre>' . PHP_EOL;
    foreach ($aLog as $aLevel) {
        foreach ($aLevel as $strLine) {
            echo $strLine . PHP_EOL;;
        }
    }
    echo '</pre>' . PHP_EOL;
}
?>
</body>
</html>
