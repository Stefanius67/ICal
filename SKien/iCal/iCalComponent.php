<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

/**
 * Abstract baseclass for all iCal components.
 *
 * For the components defined in RFC 5545, several common properties are defined,
 * which are implemented in this abstract base class. <br>
 * To prevent multiple coding, this class contains properties/methods that are supported
 * by several, but **not all**, components. In the case of invalid calls in an extending
 * class, this is checked during validation in the respective component and logged
 * accordingly.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
abstract class iCalComponent
{
    /**
     * Values for the event transparency
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.2.7
     */
    /** Blocks or opaque an event on busy time searches.     */
    public const   TRANSP_OPAQUE        = 'OPAQUE';
    /** Transparent on busy time searches.     */
    public const   TRANSP_TRANSPARENT   = 'TRANSPARENT';

    /**
     * Values for the classification
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.1.3
     */
    /** The item is classified as public     */
    public const   CLASS_PUBLIC         = 'PUBLIC';
    /** The item is classified as privte     */
    public const   CLASS_PRIVATE        = 'PRIVATE';
    /** The item is classified as confidential     */
    public const   CLASS_CONFIDENTIAL   = 'CONFIDENTIAL';

    use iCalHelper;

    /** @var string the name of the component      */
    protected string $strComponentName;
    /** @var string unique ID          */
    protected ?string $strUID = null;
    /** @var int    unix timestamp start day and time       */
    protected ?int $uxtsStart = null;
    /** @var int    duration in seconds     */
    protected ?int $iDuration = null;
    /** @var string subject          */
    protected string $strSubject = '';
    /** @var string description                  */
    protected string $strDescription = '';
    /** @var string description in HTML format                  */
    protected string $strHtmlDescription = '';
    /** @var string a description of the item     */
    protected string $strComment = '';
    /** @var int    unix timestamp last modified        */
    protected ?int $uxtsLastModified = null;
    /** @var int    priority        */
    protected ?int $iPriority = 0;
    /** @var string categories           */
    protected string $strCategories = '';
    /** @var string location             */
    protected string $strLocation = '';
    /** @var string state of item (default: STATE_CONFIRMED)    */
    protected string $strState = '';
    /** @var string transparency (default: TRANSP_OPAQUE)        */
    protected string $strTrans = '';
    /** @var string classification             */
    protected string $strClassification = '';
    /** @var string organizer name       */
    protected string $strOrganizerName = '';
    /** @var string organizer e-mail     */
    protected string $strOrganizerEMail = '';
    /** @var array<string>      attendee(s) for a email alarm     */
    protected array $aAttendee = [];
    /** @var string RRULE     */
    protected string $strRRule = '';
    /** @var array<int> RDATE     */
    protected array $aRDate = [];
    /** @var array<int> EXDATE  date-time value   */
    protected array $aExcludeDateTimes = [];
    /** @var array<int> EXDATE  date values (needs separate handling)   */
    protected array $aExcludeDates = [];
    /** @var iCalAlarm  an embedded VALARM item     */
    protected ?iCalAlarm $oAlarm = null;
    /** @var int    unix timestamp min. start for optimization       */
    protected ?int $uxtsMinStart = null;
    /** @var array<string,string>   additional properties that arn't included in the iCal spec (X-...)	 */
    protected array $aExtProp = [];

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(string $strComponentName, iCalendar $oICalendar)
    {
        $this->oICalendar = $oICalendar;
        $this->strComponentName = $strComponentName;
    }

    /**
     * Validates a instance.
     * The basic implementation does nothing, but since validation is not strictly
     * necessary in an extending class either, this method is not declared as abstract!
     */
    public function validate() : void
    {
    }

    /**
     * Returns an event as associative array.
     * For a fast access to all properties, this method returns an 'DB-record like'
     * associative array with all values of the event. <br>
     * > **Note:** <br>
     * > The string values of the date and/or time properties representing the  <br>
     * > respective unix timestamp using the current set PHP timezone!       <br>
     * @return array<string, mixed>
     */
    public function fetchData() : array
    {
        return [];  // @codeCoverageIgnore
    }

    /**
     * Sets the unique ID for this item.
     * Each entry in an "iCalendar" must be identified by a persistent, globally unique
     * identifier. <br>
     * Within an application system, each element (each data record) typically has a
     * unique ID or identifier. This ID should (must) also be unique across applications
     * to ensure that an element can always be uniquely named or referenced when exchanging
     * data. <br>
     * > A proven approach would therefore be to simply extend the existing, unique ID
     * > from the local system with your own domain to create a UUID. This also makes
     * > reverse mapping significantly easier.
     * If no UID is set, internaly an 'pseudo' UUID is generated during export.
     * @see iCalHelper::createUID
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.4.7
     * @param string $strUID
     */
    public function setUID(string $strUID) : void
    {
        $this->strUID = $strUID;
    }

    /**
     * Gets the unique ID of this item.
     * @return string   empty string if no UID is set
     */
    public function getUID() : string
    {
        return $this->strUID ?? '';
    }

    /**
     * Sets the start timestamp of the item.
     * The value can either be a unix timestamp or a DateTime instance.
     * @param int|\DateTime|null $start  unix timestamp or DateTime of the items start.
     */
    public function setStart($start) : void
    {
        if ($start instanceof \DateTime) {
            $this->uxtsStart = $start->getTimestamp();
        } else {
            $this->uxtsStart = $start;
        }
    }

    /**
     * Gets the start timestamp of the item.
     * @return int  unix timestamp
     */
    public function getStart() : ?int
    {
        return $this->uxtsStart;
    }

    /**
     * Sets the duration of the item in seconds.
     * @param int $iDuration  Duration in seconds.
     */
    public function setDuration(?int $iDuration) : void
    {
        $this->iDuration = $iDuration;
    }

    /**
     * Gets the duration of the item in seconds.
     * @return int  Duration in seconds
     */
    public function getDuration() : ?int
    {
        return $this->iDuration;
    }

    /**
     * Gets the end timestamp of the item.
     * Since the 'end' is not available or not the same in the different components,
     * the base implementation returns always `null`!
     * @return int  unix timestamp
     * @codeCoverageIgnore
     */
    public function getEnd() : ?int
    {
        return null;
    }

    /**
     * Sets the subject of the item.
     * @param string $strSubject
     */
    public function setSubject(string $strSubject) : void
    {
        $this->strSubject = $strSubject;
    }

    /**
     * Gets the subject of the item.
     * @return string
     */
    public function getSubject() : string
    {
        return $this->strSubject;
    }

    /**
     * Sets the description of the item.
     * The description SHOULD be plain text. If formatted text is available, use the
     * HTML description property. When the data is written to the iCalendar file, the
     * `Writer` class correctly assigns plain and formatted text, or generates them
     * automatically if necessary.
     * @see iCalComponent::setHtmlDescription()
     * @see Writer::addDescription()
     * @param string $strDescription
     */
    public function setDescription(?string $strDescription) : void
    {
        $this->strDescription = $strDescription ?? '';
    }

    /**
     * Gets the description as plain text.
     * @return string
     */
    public function getDescription() : string
    {
        return $this->strDescription;
    }

    /**
     * Sets the HTML description of the item.
     * @see iCalComponent::setDescription()
     * @see Writer::addDescription()
     * @param string $strDescription
     */
    public function setHtmlDescription(?string $strDescription) : void
    {
        $this->strHtmlDescription = $strDescription ?? '';
    }

    /**
     * Gets the HTML description of the item.
     * @return string
     */
    public function getHtmlDescription() : string
    {
        return $this->strHtmlDescription;
    }

    /**
     * Checks, if a HTML description of the item is available.
     * @return bool
     */
    public function hasHtmlDescription() : bool
    {
        return !empty($this->strHtmlDescription);
    }

    /**
     * Sets a comment of the item.
     * @param string $strComment
     */
    public function setComment(string $strComment) : void
    {
        $this->strComment = $strComment;
    }

    /**
     * Returns the comment of the item.
     * @return string
     */
    public function getComment() : string
    {
        return $this->strComment;
    }

    /**
     * Sets the last modified timestamp of the item.
     * The value can be passed as uinx timestamp or a DateTime instance.
     * @param int|\DateTime|null $lastModified    unix timestamp or DateTime the item been last modified.
     */
    public function setLastModified($lastModified) : void
    {
        if ($lastModified instanceof \DateTime) {
            $this->uxtsLastModified = $lastModified->getTimestamp();
        } else {
            $this->uxtsLastModified = $lastModified;
        }
    }

    /**
     * Gets the last modified timestamp of the item.
     * @return int unix timestamp the item been last modified.
     */
    public function getLastModified() : ?int
    {
        return $this->uxtsLastModified;
    }

    /**
     * Sets the priority of the item.
     * @param int|string $priority
     */
    public function setPriority($priority) : void
    {
        if (is_string($priority)) {
            $this->iPriority = intval($priority);
        } else {
            $this->iPriority = $priority;
        }
    }

    /**
     * Gets the priority of the item.
     * @return int
     */
    public function getPriority() : int
    {
        return $this->iPriority ?? 0;
    }

    /**
     * Set/add categories to the item.
     * The CATEGORIES property can contain multiple categories separated by comma and
     * can also be specified multiple times within an item.
     * @param string $strCategories
     */
    public function setCategories(?string $strCategories) : void
    {
        if (!empty($this->strCategories)) {
            $this->strCategories .= ',';
        }
        $this->strCategories .= $strCategories ?? '';
    }

    /**
     * Gets the categories of the item.
     * @return string
     */
    public function getCategories() : ?string
    {
        return $this->strCategories;
    }

    /**
     * Sets the location of the item.
     * @param string $strLocation
     */
    public function setLocation(?string $strLocation) : void
    {
        $this->strLocation = $strLocation ?? '';
    }

    /**
     * Gets the location of the item.
     * @return string
     */
    public function getLocation() : ?string
    {
        return $this->strLocation;
    }

    /**
     * Sets the state of the item.
     * Dependent on the component, different states are possible. Use the appropriate
     * class constants `iCalEvent::STAT_EVENT_...`, `iCalToDo::STAT_TODO_...`
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.1.11
     * @param string $strState
     */
    public function setState(string $strState) : void
    {
        $this->strState = $strState;
    }

    /**
     * Gets the state of the item.
     * @return string
     */
    public function getState() : string
    {
        return $this->strState;
    }

    /**
     * Sets the transparency of the item.
     * Use one of the class constants `TRANSP_OPAQUE` or `TRANSP_TRANSPARENT`.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.2.7
     * @param string $strTrans
     */
    public function setTransparency(string $strTrans) : void
    {
        $this->strTrans = $strTrans;
    }

    /**
     * Gets the transparency of the item.
     * @return string
     */
    public function getTransparency() : string
    {
        return $this->strTrans;
    }

    /**
     * Sets the classification of the item.
     * Use one of the class constants `CLASS_PUBLIC`, `CLASS_PRIVATE` or `CLASS_CONFIDENTIAL`.
     * @param string $strClassification
     */
    public function setClassification(string $strClassification) : void
    {
        $this->strClassification = $strClassification;
    }

    /**
     * Gets the classification of the item.
     * @return string
     */
    public function getClassification() : string
    {
        return $this->strClassification;
    }

    /**
     * Sets the organizer of the item.
     * @param string $strName
     * @param string $strEMail
     */
    public function setOrganizer(string $strName, string $strEMail) : void
    {
        $this->strOrganizerName = $strName;
        $this->strOrganizerEMail = $strEMail;
    }

    /**
     * Adds further attendees to the item.
     * @param string $strAttendee
     */
    public function addAttendee(string $strAttendee) : void
    {
        $this->aAttendee[] = $strAttendee;
    }

    /**
     * Gets the attendees of the item.
     * @return array<string>
     */
    public function getAttendees() : array
    {
        return $this->aAttendee;
    }

    /**
     * Sets the RRULE definition.
     * The resulting recurrent dates can be computed in the `getRecurrentDates()`
     * method.
     * @param string $strRRule
     */
    public function setRRule(string $strRRule) : void
    {
        $this->strRRule = $strRRule;
    }

    /**
     * Gets the RRULE definition.
     * @return string
     */
    public function getRRule() : string
    {
        return $this->strRRule;
    }

    /**
     * Adds further RDate value(s) for recurrent items.
     * @param array<int> $aRDate
     */
    public function addRDate(array $aRDate) : void
    {
        $this->aRDate = array_merge($this->aRDate, $aRDate);
    }

    /**
     * Adds further date(s) to exclude from the recurrent list.
     * Note that it makes a difference whether an exclude value have to be treated as
     * full timestamp or as simple date value! In case of a full timestamp, recurrent
     * datetimes are only excluded, if they exactly match this timestamp. In case of
     * a simple date (without hour, minute, second) it means that all timestamps
     * resulting from the recurrence rule and falls in this date will be excluded (f.i.:
     * if set an eclude dtae '2025-10-24', all timestamps of this date independent of
     * the time component will be excluded - '2025-10-24 14:00:00', '2025-10-24 14:30:00',
     * ...). If the excludedate should be treated as full timestamp, only the exact
     * timestamp '2025-10-24 00:00:00' will be excluded!
     * @param array<int> $aExdate
     * @param bool $bExcludeDay     if true, all timestamps of the day are excluded
     */
    public function addExcludeDate(array $aExdate, bool $bExcludeDay) : void
    {
        if ($bExcludeDay) {
            $this->aExcludeDates = array_merge($this->aExcludeDates, $aExdate);
        } else {
            $this->aExcludeDateTimes = array_merge($this->aExcludeDateTimes, $aExdate);
        }
    }

    /**
     * Checks, if the item has further, recurrent siblings.
     * @return bool
     */
    public function hasRecurrentItems() : bool
    {
        return $this->strRRule !== null;
    }

    /**
     * Builds the list of recurrent dates.
     * Recurrent dates can be specified by the RRULE property and/or one or
     * more RDATE values. <br>
     * If neither an RRULE nor RDATEs are specified, the result contains the
     * property's start date. <br><br>
     *
     * The final recurrence set is generated by gathering all of the start
     * DATE-TIME values generated by any of the specified "RRULE" and "RDATE"
     * properties, and then excluding any start DATE-TIME values specified
     * by "EXDATE" properties.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.5
     * @param bool $bIncludeStart  if true, the startdate is included in the resulting list of recurrent dates
     * @return array<int>   sorted array of UNIX timestamps
     */
    public function getRecurrentDates(bool $bIncludeStart = true) : array
    {
        $aResult = [];
        if ($this->uxtsStart !== null) {
            $uxtsStart = $this->uxtsStart;
            if ($this->uxtsMinStart !== null && $this->uxtsMinStart > $this->uxtsStart) {
                $uxtsStart = $this->uxtsMinStart;
            }
            if (!empty($this->strRRule)) {
                $oCalcTimezone = $this->oICalendar->getCalcTimezone();
                $strTZID = $oCalcTimezone ? $oCalcTimezone->getTZID() : '';
                $oRRule = new iCalRecurrenceRule($this->oICalendar, $this->strRRule);
                $oRRule->setExcludeDates($this->aExcludeDateTimes, false);
                $oRRule->setExcludeDates($this->aExcludeDates, true);
                $aResult = $oRRule->getDateList($uxtsStart, 0, $strTZID);
            } else {
                // there's no RRULE specified...
                // we create at least the start date specified
                $aResult[] = $uxtsStart;
            }
            // ... and add possibly defined RDATE repetitions
            $aResult = array_merge($aResult, $this->aRDate);
            $aResult = array_unique($aResult);
            asort($aResult);

            if (!$bIncludeStart && count($aResult) > 0) {
                array_shift($aResult);
            }
        }
        return $aResult;
    }

    /**
     * Creates all items resulting from a RRule and/or RDates.
     * @return int  count of created items
     */
    public function createRecurrentItems() : int
    {
        // Build recurrent items
        $aRecurrentDates = $this->getRecurrentDates(false);
        $iDuration = $this->getDuration();
        $strUID = $this->getUID();
        $i = 0;
        foreach ($aRecurrentDates as $uxtsStart) {
            // we clone ourself
            // - set the new startdate
            // - set the duration (this is only done to avoid any shifts when switching between daylight saving time and standard time).
            // - create a new ID by appending a sequential number to the original ID
            // - revalidate to adjust end- or due times
            // - and add the item to the iCalendar
            $oSibbling = clone $this;
            $oSibbling->setStart($uxtsStart);
            $oSibbling->setDuration($iDuration);
            $oSibbling->setUID($strUID . '-' . ++$i);
            $this->oICalendar->addItem($oSibbling);
        }
        return $i;
    }

    /**
     * Creates an alarm with given properties and attach it to the item.
     * @param int|string $trigger
     * @param string $strAction
     * @param string $strRelated
     * @return iCalAlarm
     */
    public function setAlarm($trigger, string $strAction = iCalAlarm::DISPLAY, string $strRelated = 'START') : iCalAlarm
    {
        $oAlarm = $this->createAlarm();
        $oAlarm->setAction($strAction);
        if (is_numeric($trigger)) {
            $oAlarm->setTriggerFrom(intval($trigger), $strRelated);
        } else {
            $oAlarm->setTrigger($trigger, $strRelated);
        }
        return $oAlarm;
    }

    /**
     * Creates and embed an alarm item.
     * Although the RFC 5545 spec allows an component to include multiple VALARM's,
     * `iCalComponent` currently supports only one single VALARM per item. If an
     * item contains more than one VALARM at import the last one found is taken
     * and a WARNING ist generated in the log.
     * @see iCalAlarm
     * @return iCalAlarm
     */
    public function createAlarm(): iCalAlarm
    {
        if ($this->oAlarm !== null) {
            $this->oICalendar->log(LogLevel::WARNING, "The {$this->strComponentName} contains multiple VALARM. Only the last one ist taken!");
        }
        $this->oAlarm = new iCalAlarm($this);  // @phpstan-ignore-line
        return $this->oAlarm;
    }

    /**
     * Gets an embedded alarm component.
     * @return iCalAlarm
     */
    public function getAlarm() : ?iCalAlarm
    {
        return $this->oAlarm;
    }

    /**
     * Sets an extended property.
     * @see iCalendar::defineXProperty()
     * @param string $strName
     * @param string $strValue
     */
    public function setExtProperty(string $strName, string $strValue) : void
    {
        $this->aExtProp[$strName] = $strValue;
    }

    /**
     * Write the item data to the Writer instance.
     * @param Writer $oWriter
     * @param string $strTZID
     */
    abstract public function writeData(Writer $oWriter, string $strTZID = '') : void;
}
