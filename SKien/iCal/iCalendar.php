<?php

declare(strict_types=1);

namespace SKien\iCal;


use Psr\Log\LogLevel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class to generate calendar in the `iCal` format.
 *
 * <a href="https://www.rfc-editor.org/rfc/rfc5545.html">RFC-5545: Internet Calendaring and Scheduling Core Object Specification (iCalendar)</a> <br>
 * <a href="https://www.rfc-editor.org/rfc/rfc7986.html">RFC-7986: New Properties for iCalendar</a>
 *
 * > Note: The timezone handling when reading is different from that when creating
 * > iCalendar files.
 * > This is because the time zones used by iCalendar files do not match the PHP time zones
 * > (which are based on the time zone identifiers published in the IANA time zone database).
 *
 * 1. When generating a calendar, the (PHP) timezone that is set when the iCalender instance is
 *    created is generally used. An iCal time zone with the same name is automatically
 *    created.
 *
 * 2. When reading a calendar, the timezone definitions contained in the file are taken
 *    into account, and all datetime values ​​are saved as UNIX timestamps. Since these
 *    values ​​are generally UTC-based, it is up to the processing code to decide which
 *    time zone to use to display the data.
 *
 *
 * <a href="https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-oxcical/74d3bf60-f30d-4fca-84d3-cfd04da8e627">
 * Additional Information according to MS extensions to the specs</a>
 *
 * <a href="https://icalendar.org/">Official Homepage of iCalendar.org</>
 *
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright (c) Stefan Kientzler
 */
class iCalendar implements LoggerAwareInterface
{
    use iCalHelper;

    protected const PROD_ID = '-//Stefanius PHP iCalendar-Creator//v1.0.1';

    /** @var string  Name of the calendar                   */
	protected string $strName;
	/** @var array<string, iCalTimezone> all timezones specified in the iCal file */
	protected array $aTimezones = [];
	/** @var iCalEvent[] all events in the iCal file */
	protected array $aEvents = [];
	/** @var iCalToDo[] all todos in the iCal file */
	protected array $aToDos = [];
	/** @var int         min. date inside of the iCalendar	 */
	protected int $uxtsMinDate = 0;
	/** @var int         max. date inside of the iCalendar	 */
	protected int $uxtsMaxDate = 0;
	/** @var bool  force the inspector software to open the calendar file	 */
	protected bool $bForceopen = false;
	/** @var string     (PHP) timezone we are working in     */
	protected string $strTimezonePHP = '';
	/** @var integer    line that is currently processed (a.o. for use in logging) 	 */
	protected int $iLine = -1;
	/** @var \Psr\Log\LoggerInterface  PSR3 logger instance	 */
	protected LoggerInterface $oLogger;
	/** @var array<string,int> 	       count of logentries for eacah level */
	protected array $aLogCount = [];
	/** @var array<string,array<string,string>>   exended properties not specified by RFC5545 (X-...)       */
	protected array $aXProperties = [];
	/** @var string  encoding for values    */
	protected string $strEncoding = 'UTF-8';
	/** @var iCalTimezone   timezone ID for several calculations     */
	protected ?iCalTimezone $oCalcTimezone = null;

	/**
	 * Create the header info of the iCal file.
	 * @param string $strName
	 */
	public function __construct($strName = 'iCalendar')
	{
	    $this->strName = $strName;

	    // remember the current timezone to restore it since the timezone
	    // is need to be changed to UTC for some operations while reading a iCal file
	    $this->strTimezonePHP = date_default_timezone_get();

	    $this->oICalendar = $this;

	    // We create a NullLogger to avoid  `if ($this->oLogger) { }` code blocks if no
	    // Loger will be set (i.e. no logging is wanted...).
	    $this->oLogger = new NullLogger();
	}

	/**
	 * Get the encoding currently set.
	 * @return string
	 */
	public function getEncoding() : string
	{
	    return $this->strEncoding;
	}

	/**
	 * Set the encoding for the file.
	 * For export:
	 * - always use UTF-8 (default).
	 *   only exception i found so far is MS-Outlook - it comes in trouble with german
	 *   umlauts, so use 'Windows-1252' instead.
	 *   please send note to s.kientzler@online.de if you found any further exceptions...
	 *
	 * For import:
	 * -  feel free to use your preferred charset (may depends on configuration of your system)
	 * @param string $strEncoding
	 */
	public function setEncoding(string $strEncoding) : void
	{
	    $this->strEncoding = $strEncoding;
	}

	/**
	 * Sets a PSR3 logger instance on the object.
	 * @param LoggerInterface $oLogger
	 */
	public function setLogger(LoggerInterface $oLogger) : void
	{
	    $this->oLogger = $oLogger;
	}

	/**
	 * Create a new log entry.
	 * @param string $strLevel
	 * @param string $strMessage
	 * @param array<string,mixed> $aContext
	 */
	public function log(string $strLevel, string $strMessage, array $aContext = []) : void
	{
	    if ($this->iLine >= 0) {
	        $aContext['Line'] = $this->iLine;
	    }
	    $this->oLogger->log($strLevel, $strMessage, $aContext);
	    if (isset($this->aLogCount[$strLevel])) {
	        $this->aLogCount[$strLevel]++;
	    } else {
	        $this->aLogCount[$strLevel] = 1;
	    }
	}

	/**
	 * Gets the count of log entries for each log level.
	 * @return array<string,int>
	 */
	public function getLogCount() : array
	{
	    return $this->aLogCount;
	}

	/**
	 * Defines a additional extened property (X-...).
	 * @param string $strComponent
	 * @param string $strName
	 * @param string $strVar
	 */
	public function defineXProperty(string $strComponent, string $strName, string $strVar) : void
	{
	    if (!isset($this->aXProperties[$strComponent])) {
	        $this->aXProperties[$strComponent] = [];
	    }
	    $this->aXProperties[$strComponent][$strName] = $strVar;
	}

	/**
	 * Gets the var name for a X property.
	 * @param string $strComponent
	 * @param string $strName
	 * @return string
	 */
	public function getXProperty(string $strComponent, string $strName) : ?string
	{
	    if (isset($this->aXProperties[$strComponent][$strName])) {
	        return $this->aXProperties[$strComponent][$strName];
	    }
	    return null;
	}

	/**
	 * Sets the timezone that is needed for some timeoffset calculations.
	 * @param iCalTimezone $oCalcTimezone
	 */
	public function setCalcTimezone(?iCalTimezone $oCalcTimezone) : void
	{
	    $this->oCalcTimezone = $oCalcTimezone;
	}

	/**
	 * Gets the timezone that is needed for some timeoffset calculations.
	 * @return iCalTimezone
	 */
	public function getCalcTimezone() : ?iCalTimezone
	{
	    return $this->oCalcTimezone;
	}

	/**
	 * Sets the name of the calendar.
	 * @param string $strName
	 */
	public function setName(string $strName) : void
	{
	    $this->strName = $strName;
	}

	/**
	 * If set, this forces the opening inspector to open the event rather than directly import it.
	 * This is a MS extension that only make sense, if the file to create only contains
	 * only one single event!
	 * @param bool $bForceopen
	 */
	public function forceInspectorOpen(bool $bForceopen = true) : void
	{
	    $this->bForceopen = $bForceopen;
	}

	/**
	 * Gets the (PHP) timezone we are working in.
	 * @return string
	 */
	public function getTimezonePHP() : string
	{
	    return $this->strTimezonePHP;
	}

	/**
	 * @param iCalEvent $oEvent
	 */
	public function addEvent(iCalEvent $oEvent) : void
	{
	    $this->aEvents[] = $oEvent;
	    $uxtsStart = $oEvent->getStart();
	    if ($uxtsStart !== null) {
	        if ($this->uxtsMinDate === 0 || $uxtsStart < $this->uxtsMinDate) {
	            $this->uxtsMinDate = $uxtsStart;
	        }
	    }
	    $uxtsEnd = $oEvent->getEnd();
	    if ($uxtsEnd !== null) {
	        if ($this->uxtsMaxDate === 0 || $uxtsEnd > $this->uxtsMaxDate) {
	            $this->uxtsMaxDate = $uxtsEnd;
	        }
	    }
	}

	/**
	 * Return readed events.
	 * @return iCalEvent[]
	 */
	public function getEvents() : array
	{
	    return $this->aEvents;
	}

	/**
	 * @param iCalToDo $oToDo
	 */
	public function addToDo(iCalToDo $oToDo) : void
	{
	    $this->aToDos[] = $oToDo;
	    $uxtsStart = $oToDo->getStart();
	    if ($uxtsStart !== null) {
	        if ($this->uxtsMinDate === 0 || $uxtsStart < $this->uxtsMinDate) {
	            $this->uxtsMinDate = $uxtsStart;
	        }
	    }
	    $uxtsEnd = $oToDo->getEnd();
	    if ($uxtsEnd !== null) {
	        if ($this->uxtsMaxDate === 0 || $uxtsEnd > $this->uxtsMaxDate) {
	            $this->uxtsMaxDate = $uxtsEnd;
	        }
	    }
	}

	/**
	 * Return readed todos.
	 * @return iCalToDo[]
	 */
	public function getToDos() : array
	{
	    return $this->aToDos;
	}

	/**
	 * Adds the given timezone object to the calendar.
	 * @param iCalTimezone $oTimezone
	 */
	public function addTimezone(iCalTimezone $oTimezone) : void
	{
	    $this->aTimezones[$oTimezone->getTZID()] = $oTimezone;
	}

	/**
	 * @param string $strTZID
	 * @return iCalTimezone
	 */
	public function getTimezone(string $strTZID) : ?iCalTimezone
	{
	    $oTimezone = null;
	    if (isset($this->aTimezones[$strTZID])) {
	        $oTimezone = $this->aTimezones[$strTZID];
	    }
	    return $oTimezone;
	}

	/**
	 * Writes the data for current informations.
	 * @param Writer $oWriter
	 */
	public function writeData(Writer $oWriter) : void
	{
	    $oWriter->addProperty('BEGIN', 'VCALENDAR');
	    $oWriter->addProperty('PRODID', self::PROD_ID);
	    $oWriter->addProperty('VERSION', '2.0');

	    $oWriter->addProperty('CALSCALE', 'GREGORIAN');
	    if ($this->bForceopen) {
	        $oWriter->addProperty('X-MS-OLK-FORCEINSPECTOROPEN', 'TRUE');
	    }
	    $oWriter->addProperty('X-WR-CALNAME:', $this->strName);
	    $oWriter->addProperty('X-WR-TIMEZONE:', $this->strTimezonePHP);

        // create VTIMEZONE from current (PHP) timezone for the needed timespan
        $oTimezone = new iCalTimezone($this);
        $oTimezone->fromTimezone($this->strTimezonePHP, $this->uxtsMinDate, $this->uxtsMaxDate);

        $oTimezone->writeData($oWriter);

        // insert all events
	    foreach ($this->aEvents as $oEvent) {
	        $oEvent->writeData($oWriter, $this->strTimezonePHP);
	    }
	    // ... and all todo items
	    foreach ($this->aToDos as $oToDo) {
	        $oToDo->writeData($oWriter, $this->strTimezonePHP);
	    }
	    $oWriter->addProperty('END', 'VCALENDAR');
	}

	/**
	 * Write iCalendar to file.
	 * Build html header and echoes the internal buffer.
	 * @param string $strFilename
	 * @param bool $bTest   output to browser for 'fast' testing...
	 */
	public function write(string $strFilename = '', $bTest = false) : string
	{
	    $oWriter = new Writer($this);
	    $this->writeData($oWriter);

	    $strICal = $oWriter->getBuffer();

	    if (strlen($strFilename) == 0) {
	        $strFilename = $this->strName . '.ics';
	    }

	    // ics-file generation doesn't make sense if some errormessage generated before...
	    if (!$bTest && ob_get_contents() == '') {
	        header('Content-Type: text/calendar; charset=utf-8');
	        header('Content-Disposition: attachment; filename=' . $strFilename);
	        header('Content-Length: ' . strlen($strICal));
	        header('Connection: close');
	    } else {
	        // output for test or in case of errors
	        // @codeCoverageIgnoreStart
	        $strICal = str_replace(PHP_EOL, '<br>', $strICal);
	        echo  'Filename: ' . $strFilename . '<br><br>';
	        // @codeCoverageIgnoreEnd
	    }

	    echo $strICal;

	    return $strFilename;
	}

	/**
	 * Reads the given file using an iCalReader instance.
	 * @param string $strFilename
	 */
	public function read(string $strFilename) : int
	{
	    if (file_exists($strFilename)) {
            $aLines = @file($strFilename);
    	    /**
    	    if (((error_reporting() & E_USER_WARNING) !== 0)) {
    	       file_put_contents(Application::getInstance()->getEnv()->getDocRoot() . '/upload/log/iCalImport.ics', $aLines);
    	    }
    	    */
    	    if ($aLines !== false) {
        	    $this->iLine = 0;
        	    $oReader = new iCalReader($this);
        	    while ($this->iLine < count($aLines)) {
        	        $strLine = $oReader->nextLine($aLines, $this->iLine);
        	        if ($oReader->hasEndReached($strLine)) {
        	            break;
        	        }
        	        $oReader->parseLine($strLine);
        	    }
    	    }
	    } else {
	        $this->log(LogLevel::ERROR, 'Missing iCalendar file ' . $strFilename);
	    }
	    return count($this->aEvents) + count($this->aToDos);
	}
}
