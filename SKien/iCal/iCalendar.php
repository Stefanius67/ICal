<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class to create or read calendar data in the `iCal` format.
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
 *    created is generally used. An iCal TIMEZONE component with the same name is automatically
 *    inserted.
 *
 * 2. When reading a calendar, the timezone definitions contained in the file are taken
 *    into account, and all datetime values ​​are saved as UNIX timestamps. Since these
 *    values ​​are generally UTC-based, it is up to the processing code to decide which
 *    time zone to use to display the data.
 *
 * ## currently supported options <br>
 * - createRecurrentItems, import   (bool, default: true)   <br>
 *   creates the resulting events/todos at import for contained recurrent items     <br>
 * - logTimezoneOffsetList, import  (bool, default: false)  <br>
 *   creates an 'INFO' logentry containing the found and computed TIMEZONE offsetlist(s)    <br>
 * - removeHtmlBody, import         (bool, default: true)   <br>
 *   removes surounding html body from HTML descriptions    <br>
 * - autoCreateHTML, export         (bool, default true)    <br>
 *   create a rudimental HTML description
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
	/** @var iCalComponent[] all items (without timezones) in the iCal file */
	protected array $aItems = [];
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
	/** @var array<string,mixed>     options for the current instance	 */
	protected array $aOptions = [];

	/**
	 * Create the header info of the iCal file.
	 * @param string $strName
	 * @param array<string,mixed> $aOptions
	 */
	public function __construct($strName = 'iCalendar', array $aOptions = [])
	{
	    $this->strName = $strName;
	    $this->aOptions = $aOptions;

	    // remember the current timezone to restore it since the timezone
	    // is need to be changed to UTC for some operations while reading a iCal file
	    $this->strTimezonePHP = date_default_timezone_get();

	    $this->oICalendar = $this;

	    // We create a NullLogger to avoid  `if ($this->oLogger) { }` code blocks if no
	    // Loger will be set (i.e. no logging is wanted...).
	    $this->oLogger = new NullLogger();
	}

	/**
	 * Gets the requested option.
	 * Returns the given defaultvlaue, if the requested option ist not set.
	 * @param string $strName
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption(string $strName, $default = null)
	{
	    $value = $this->aOptions[$strName] ?? $default;
	    return $value;
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
	 * Get the encoding currently set.
	 * @return string
	 */
	public function getEncoding() : string
	{
	    return $this->strEncoding;
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
	        $strMessage = 'Line ' . sprintf('%03d', $this->iLine) . ': ' . $strMessage;
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
     * Creates a new event.
     * @return iCalEvent
     */
	public function createEvent() : iCalEvent
    {
        $oEvent = new iCalEvent($this);
        return $oEvent;
    }

    /**
     * Creates a new to-do item.
     * @return iCalToDo
     */
    public function createToDo() : iCalToDo
    {
        $oToDo = new iCalToDo($this);
        return $oToDo;
    }

	/**
	 * @param iCalComponent $oItem
	 */
	public function addItem(iCalComponent $oItem) : void
	{
	    $oItem->validate();
	    $this->aItems[] = $oItem;
	    $uxtsStart = $oItem->getStart();
	    if ($uxtsStart !== null) {
	        if ($this->uxtsMinDate === 0 || $uxtsStart < $this->uxtsMinDate) {
	            $this->uxtsMinDate = $uxtsStart;
	        }
	    }
	    $uxtsEnd = $oItem->getEnd();
	    if ($uxtsEnd !== null) {
	        if ($this->uxtsMaxDate === 0 || $uxtsEnd > $this->uxtsMaxDate) {
	            $this->uxtsMaxDate = $uxtsEnd;
	        }
	    }
	}

	/**
	 * Return readed items.
	 * @return iCalComponent[]
	 * @codeCoverageIgnore
	 */
	public function getItems() : array
	{
	    return $this->aItems;
	}

	/**
	 * Return readed events.
	 * @return iCalEvent[]
	 */
	public function getEvents() : array
	{
	    $aEvents = [];
	    foreach ($this->aItems as $oItem) {
	        if (get_class($oItem) == 'SKien\iCal\iCalEvent') {
	            $aEvents[] = $oItem;
	        }
	    }
	    return $aEvents;
	}

	/**
	 * Return readed todos.
	 * @return iCalToDo[]
	 */
	public function getToDos() : array
	{
	    $aToDos = [];
	    foreach ($this->aItems as $oItem) {
	        if (get_class($oItem) == 'SKien\iCal\iCalToDo') {
	            $aToDos[] = $oItem;
	        }
	    }
	    return $aToDos;
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

        // insert all items
	    foreach ($this->aItems as $oItem) {
	        $oItem->writeData($oWriter, $this->strTimezonePHP);
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
	    if ($this->fileExists($strFilename)) {
            $aLines = @file($strFilename);
    	    /**
    	    if (((error_reporting() & E_USER_WARNING) !== 0)) {
    	       file_put_contents(Application::getInstance()->getEnv()->getDocRoot() . '/upload/log/iCalImport.ics', $aLines);
    	    }
    	    */
    	    if ($aLines !== false) {
    	        $bReadTimezones = true;
    	        for ($i = 0; $i < 2; $i++) {
            	    $this->iLine = 0;
            	    $oReader = new iCalReader($this, $bReadTimezones);
            	    while ($this->iLine < count($aLines)) {
            	        $strLine = $oReader->nextLine($aLines, $this->iLine);
            	        if ($oReader->hasEndReached($strLine)) {
            	            break;
            	        }
            	        $oReader->parseLine($strLine);
            	    }
            	    $bReadTimezones = false;
    	        }
    	    }
	    }
	    return count($this->aItems);
	}

	/**
	 * Check if the given file exists.
	 * First a local file is checked, if not found a URL is tried.
	 * @param string $strFilename
	 * @return bool
	 */
	private function fileExists(string $strFilename) : bool
    {
        $bExists = file_exists($strFilename);
        if (!$bExists) {
            /**
             * One way could be trying to get the header (@get_header()) and check for
             * the '200 OK' response. Unfortunately, this don't work for files from a
             * WEBDAV server. Since PHP doesn't support access via the WEBDAV protocoll,
             * such iCalendars have to be read through the 'normal' HTTP protocoll and
             * therefore the get_header() call results in a '301 moved permanently'
             * response...
             * So the best choice will be to use cURL with CURLOPT_FOLLOWLOCATION set.
             */
            $curl = curl_init($strFilename);
            if ($curl !== false) {
                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION , true);
                curl_exec($curl);
                $iHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $bExists = ($iHttpCode == 200);
                curl_close($curl);
            }
            if (!$bExists) {
                $this->log(LogLevel::ERROR, 'Can not load iCalendar file ' . $strFilename);
            }
        }
        return $bExists;
    }
}
