<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 *  Abstract baseclass for all iCal components.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
abstract class iCalComponent
{
    /**
     * Values for the state
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.2.12
     */
    public const STATE_ACCEPTED         = 'ACCEPTED';
    public const STATE_CANCELLED        = 'CANCELLED';
    public const STATE_COMPLETED        = 'COMPLETED';
    public const STATE_CONFIRMED        = 'CONFIRMED';
    public const STATE_DECLINED         = 'DECLINED';
    public const STATE_DELEGATED        = 'DELEGATED';
    public const STATE_IN_PROCESS       = 'IN-PROCESS';
    public const STATE_NEEDS_ACTION     = 'NEEDS-ACTION';
    public const STATE_TENTATIVE        = 'TENTATIVE';

    /**
     * Values for the time transparency
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.2.7
     */
    public const   TRANSP_OPAQUE        = 'OPAQUE';
    public const   TRANSP_TRANSPARENT   = 'TRANSPARENT';

    /**
     * Values for the classification
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.1.3
     */
    public const   CLASS_PUBLIC         = 'PUBLIC';
    public const   CLASS_PRIVATE        = 'PRIVATE';
    public const   CLASS_CONFIDENTIAL   = 'CONFIDENTIAL';

    use iCalHelper;

    /** @var string the bane of the component      */
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
    /** @var string a description of the component     */
    protected string $strComment = '';
    /** @var int    unix timestamp last modified        */
    protected ?int $uxtsLastModified = null;
    /** @var int    priority        */
    protected ?int $iPriority = 0;
    /** @var string categories           */
    protected string $strCategories = '';
    /** @var string location             */
    protected string $strLocation = '';
    /** @var string state of event (default: STATE_CONFIRMED)    */
    protected string $strState = self::STATE_CONFIRMED;
    /** @var string transparency (default: TRANSP_OPAQUE)        */
    protected string $strTrans = self::TRANSP_OPAQUE;
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
    /** @var array<int> EXDATE     */
    protected array $aExcludeDates = [];
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
     */
    public function validate() : void
    {

    }

    /**
     * @param string $strUID
     */
    public function setUID(string $strUID) : void
    {
        $this->strUID = $strUID;
    }

    /**
     * @return string
     */
    public function getUID() : string
    {
        return $this->strUID ?? '';
    }

    /**
     * @param int $uxtsStart  unix timestamp of the events start.
     */
    public function setStart(?int $uxtsStart) : void
    {
        $this->uxtsStart = $uxtsStart;
    }

    /**
     * @return int  unix timestamp
     */
    public function getStart() : ?int
    {
        return $this->uxtsStart;
    }

    /**
     * @param int $iDuration  Duration in seconds.
     */
    public function setDuration(?int $iDuration) : void
    {
        $this->iDuration = $iDuration;
    }

    /**
     * @return int  Duration in seconds
     */
    public function getDuration() : ?int
    {
        return $this->iDuration;
    }

    /**
     * Gets the end timestamp of the component.
     * Since the 'end' is not available or not the same in the different components,
     * the base implementation returns alway `null`!
     * @return int  unix timestamp
     * @codeCoverageIgnore
     */
    public function getEnd() : ?int
    {
        return null;
    }

    /**
     * @param string $strSubject
     */
    public function setSubject(string $strSubject) : void
    {
        $this->strSubject = $strSubject;
    }

    /**
     * @return string
     */
    public function getSubject() : string
    {
        return $this->strSubject;
    }

    /**
     * @param string $strDescription
     */
    public function setDescription(?string $strDescription) : void
    {
        $this->strDescription = $strDescription ?? '';
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return $this->strDescription;
    }

    /**
     * @param string $strDescription
     */
    public function setHtmlDescription(?string $strDescription) : void
    {
        $this->strHtmlDescription = $strDescription ?? '';
    }

    /**
     * @return string
     */
    public function getHtmlDescription() : string
    {
        return $this->strHtmlDescription;
    }

    /**
     * @return bool
     */
    public function hasHtmlDescription() : bool
    {
        return !empty($this->strHtmlDescription);
    }

    /**
     * Sets a comment to this timezone instance.
     * @param string $strComment
     */
    public function setComment(string $strComment) : void
    {
        $this->strComment = $strComment;
    }

    /**
     * Returns the comment.
     * @return string
     */
    public function getComment() : string
    {
        return $this->strComment;
    }

    /**
     * @param int $uxtsLastModified    unix timestamp the event been last modified.
     */
    public function setLastModified(?int $uxtsLastModified) : void
    {
        $this->uxtsLastModified = $uxtsLastModified;
    }

    /**
     * @return int unix timestamp the event been last modified.
     */
    public function getLastModified() : ?int
    {
        return $this->uxtsLastModified;
    }

    /**
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
     * @return int
     */
    public function getPriority() : int
    {
        return $this->iPriority ?? 0;
    }

    /**
     * Set/add categories to the event.
     * The CATEGORIES property can contain multiple categories separated by comma and
     * can also be specified multiple times within an component
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
     * @return string
     */
    public function getCategories() : ?string
    {
        return $this->strCategories;
    }

    /**
     * @param string $strLocation
     */
    public function setLocation(?string $strLocation) : void
    {
        $this->strLocation = $strLocation ?? '';
    }

    /**
     * @return string
     */
    public function getLocation() : ?string
    {
        return $this->strLocation;
    }

    /**
     * @param string $strState
     */
    public function setState(string $strState) : void
    {
        $this->strState = $strState;
    }

    /**
     * @return string
     */
    public function getState() : string
    {
        return $this->strState;
    }

    /**
     * @param string $strTrans
     */
    public function setTransparency(string $strTrans) : void
    {
        $this->strTrans = $strTrans;
    }

    /**
     * @return string
     */
    public function getTransparency() : string
    {
        return $this->strTrans;
    }

    /**
     * @param string $strClassification
     */
    public function setClassification(string $strClassification) : void
    {
        $this->strClassification = $strClassification;
    }

    /**
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
     * @return string
     */
    public function getRRule() : string
    {
        return $this->strRRule;
    }

    /**
     * Adds further RDate value(s) to the recurrent list.
     * @param array<int> $aRDate
     */
    public function addRDate(array $aRDate) : void
    {
        $this->aRDate = array_merge($this->aRDate, $aRDate);
    }

    /**
     * Adds further date(s) to exclude from the recurrent list.
     * @param array<int> $aExdate
     */
    public function addExcludeDate(array $aExdate) : void
    {
        $this->aExcludeDates = array_merge($this->aExcludeDates, $aExdate);
    }

    /**
     * Checks, if the event has further, recurrent siblings.
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
                $oRRule->setExcludeDates($this->aExcludeDates);
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
            $oSibbling->validate();
            $this->oICalendar->addItem($oSibbling);
        }
        return $i;
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
     * Write the component data to the Writer instance.
     * @param Writer $oWriter
     * @param string $strTZID
     */
    abstract public function writeData(Writer $oWriter, string $strTZID = '') : void;
}
