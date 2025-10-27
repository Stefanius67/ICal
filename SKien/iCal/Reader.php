<?php

declare(strict_types=1);

namespace SKien\iCal;


use Psr\Log\LogLevel;
use Soundasleep\Html2Text;

/**
 * Abstract baseclass for several readers.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
abstract class Reader
{
    use iCalHelper;

    /** @var Reader sub-reader for nested components (VTIMEZONE, VEVENT, ...)     */
    protected ?Reader $oReader = null;
    /** @var iCalComponent  the component that currently is readed      */
    protected iCalComponent $oItem;

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar $oICalendar)
    {
        $this->oICalendar = $oICalendar;
    }

    /**
     * Checks if the property this reader is processing has reached the end.
     * If the end is reached, final operations to the instance should/can be
     * done. Last step should be to pass the generated property to the parent
     * iCalendar and return true.
     * @param string $strLine
     * @return bool
     */
    abstract public function hasEndReached(string $strLine) : bool;

    /**
     * Add property from import file.
     * Must be implemented in derived classes to parse the values ​​and parameters
     * in more detail or to assign them to internal properties.
     * @param string $strName
     * @param array<string,string> $aParams
     * @param string $strValue
     */
    abstract public function addProperty(string $strName, array $aParams, string $strValue) : void;

    /**
     * Read next line from the line buffer.
     * Takes care about iCal - folded lines.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.1
     * @param array<string> $aLines
     * @param int $iLine
     * @return string
     */
    public function nextLine(array $aLines, int &$iLine) : string
    {
        // remove linebreak
        $strLine = rtrim($aLines[$iLine++], "\r\n");

        // Check for folded lines:
        // A linebreak immediately followed by a single linear white-space character
        // (SPACE or HTAB)
        $aFoldChar = [" ", "\t"];
        while ($iLine < count($aLines) && in_array(substr($aLines[$iLine], 0, 1), $aFoldChar)) {
            $strLine .= rtrim(substr($aLines[$iLine++], 1), "\r\n");
        }
        return $strLine;
    }

    /**
     * Parses current unfolded line.
     * If previous lines started any nested component, the parsing is delegated to the
     * sub-reader that is able to handle this current component.
     * @param string $strLine
     */
    public function parseLine(string $strLine) : void
    {
        if ($this->oReader) {
            if ($this->oReader->hasEndReached($strLine)) {
                $this->oReader = null;
            } else {
                $this->oReader->parseLine($strLine);
            }
        } else {
            // split property name/params from value
            [$strProp, $strValue] = $this->explodeUnquoted($strLine, ':', '"', 2);
            if (!empty($strProp) && !empty($strValue)) {
                $aNameParams = $this->explodeUnquoted($strProp, ';', '"');
                $strName = array_shift($aNameParams);
                if ($strName !== null && $strName != '') {
                    $aParams = $this->parseParams($aNameParams);
                    $this->addProperty($strName, $aParams, $strValue);
                }
            }
        }
    }

    /**
     * Parse param string
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.2
     * @param array<string> $aParamsIn
     * @return array<string,string>
     */
    protected function parseParams(array $aParamsIn) : array
    {
        $aResult = [];
        foreach ($aParamsIn as $strParam) {
            $aParam = explode('=', $strParam, 2);
            if (count($aParam) == 2) {
                $strName = strtoupper($aParam[0]);
                $strValue = trim($aParam[1], ' "');
                $aResult[$strName] = $strValue;
            }
        }
        return $aResult;
    }

    /**
     * Split a string by given delimiter but ignore delimiters inside quoted string.
     *
     * Example: <br>
     * `DESCRIPTION;ALTREP="cid:part1.0001@example.org":The Fall'98 Wild Wizards Conference - - Las Vegas\, NV\, USA`   <br>
     * <br>
     * -> The first COLON after 'cid' must be ignored since it belongs to the param value!
     *
     * @param string $strLine
     * @param string $strDelimiter
     * @param string $strQuotes
     * @param int $iMax
     * @return array<string>
     */
    protected function explodeUnquoted(string $strLine, string $strDelimiter, string $strQuotes = '"', int $iMax = 0) : array
    {
        $aExplode = [];
        $bInQuotes = false;
        $iFrom = $iTo = 0;
        $iLen = strlen($strLine);
        $iCnt = 0;
        $iMax--;
        for ($i = 0; $i < $iLen; $i++, $iTo++) {
            if ($iMax >= 0 && $iCnt >= $iMax) {
                break;
            }
            $ch = $strLine[$i];
            if ($ch == $strDelimiter && !$bInQuotes) {
                $aExplode[] = substr($strLine, $iFrom, $iTo);
                $iFrom = $i + 1;
                $iTo = -1;
                $iCnt++;
            }
            if ($ch == $strQuotes) {
                $bInQuotes = !$bInQuotes;
            }
        }
        $aExplode[] = substr($strLine, $iFrom);

        return $aExplode;
    }

    /**
     * Unmask delimiter and newline.
     * @param string $strValue
     * @return string
     */
    protected function unmaskString(string $strValue) : string
    {
        $strValue = str_replace("\\n", "\n", $strValue);
        $strValue = str_replace("\\,", ",", $strValue);
        $strValue = str_replace("\\;", ";", $strValue);

        $strFrom = mb_detect_encoding($strValue);
        if ($strFrom !== false && $strFrom != $this->oICalendar->getEncoding()) {
            $strValue = iconv($strFrom, $this->oICalendar->getEncoding() . "//IGNORE", $strValue);
            if ($strValue === false) {      // I have no testcase for PHPUnit so far, but phpstan wants this code...
                $strValue = '';             // @codeCoverageIgnore
            }
        }

        return $strValue;
    }

    /**
     * Parses and converts an DATE / DATE-TIME property into a UNIX timestamp.
     * @param string $strValue
     * @param array<string> $aParams
     * @return int  UNIX timestamp
     */
    protected function parseDateTimeValue(string $strValue, array $aParams) : ?int
    {
        $strType = $aParams['VALUE'] ?? 'DATE-TIME';

        $strDateTime = $strValue;
        $dtResult = null;
        if ($strType == 'DATE') {
            // simply enhance to a full date-time
            if (strlen($strDateTime) == 8) {
                $strDateTime .= 'T000000';
            }
        }
        if (substr($strDateTime, -1) !== 'Z') {
            if (isset($aParams['TZID']) && $this->oICalendar !== null) {
                $oTimezone = $this->oICalendar->getTimezone($aParams['TZID']);
                if ($oTimezone !== null) {
                    $strDateTime .= $oTimezone->findTimeOffset($strDateTime);
                } else {
                    // unknown timezone... just extend to UTC time
                    $this->oICalendar->log(LogLevel::ERROR, 'Undefined TZID [' . $aParams['TZID'] . '] set for DATE-TIME value!');
                    $strDateTime .= 'Z';
                }
            } else {
                // no timezone set... just extend to UTC time
                $strDateTime .= 'Z';
            }
        }
        try {
            $dtResult = new \DateTime($strDateTime);
        } catch (\Exception $e) {
            $this->oICalendar->log(LogLevel::CRITICAL, 'Invalid Date/DateTime value: ' . $strValue . ' (' . $e->getMessage() . ')');
        }
        return $dtResult ? $dtResult->getTimestamp() : null;
    }

    /**
     * Parses and converts a list of DATE / DATE-TIME into an array of UNIX timestamps.
     * @param string $strValue
     * @param array<string> $aParams
     * @return array<int>  array of UNIX timestamps
     */
    protected function parseDateTimeList(string $strValue, array $aParams) : array
    {
        $aValues = explode(',', $strValue);
        $aResult = [];
        foreach ($aValues as $strDateTime) {
            $uxtsValue = $this->parseDateTimeValue(trim($strDateTime), $aParams);
            if ($uxtsValue !== null) {
                $aResult[] = $uxtsValue;
            }
        }
        return $aResult;
    }

    /**
     * Parsing of the organizer.
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.4.3
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseOrganizer(string $strName, string $strValue, array $aParams) : void
    {
        $strOrganizerName = $aParams['CN'] ?? '';
        $strOrganizerEMail = '';
        $iPos = strpos(strtolower($strValue), 'mailto:');
        if ($iPos !== false) {
            $strOrganizerEMail = substr($strValue, $iPos + 7);
        }
        $this->oItem->setOrganizer($strOrganizerName, $strOrganizerEMail);
    }

    /**
     * Parse the RDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseRDate(string $strName, string $strValue, array $aParams) : void
    {
        $this->oItem->addRDate($this->parseDateTimeList($strValue, $aParams));
    }

    /**
     * Parse the EXDATE value.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseExcludeDate(string $strName, string $strValue, array $aParams) : void
    {
        $strType = $aParams['VALUE'] ?? 'date-time';
        $bExcludeDay = strtolower($strType) == 'date';
        $this->oItem->addExcludeDate($this->parseDateTimeList($strValue, $aParams), $bExcludeDay);
    }

    /**
     * Parsing of the description.
     * Some agents use the ALTREP param for a HTML representation of the
     * description (Thunderbird, ...).
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseDescription(string $strName, string $strValue, array $aParams) : void
    {
        if (isset($aParams['ALTREP'])) {
            $strAltRep = $aParams['ALTREP'];
            if (strtolower(substr($strAltRep, 0, 15)) == 'data:text/html,') {
                $strHTML = rawurldecode(substr($strAltRep, 15));
                $this->oItem->setHtmlDescription($strHTML);
            }
        }
        $strDescription = $this->unmaskString($strValue);
        if ($strDescription != strip_tags($strDescription)) {
            // GoogleCalendar writes the HTML directly into the DESCRIPTION property
            $this->oItem->setHtmlDescription($strDescription);
            try {
                $strPlainText = Html2Text::convert($strDescription);
                $this->oItem->setDescription($strPlainText);
            } catch (\Exception $e) {
                $this->oICalendar->log(LogLevel::WARNING, 'The DESCRIPTION property contains malformed HTML!');
                $this->oItem->setDescription(strip_tags($strDescription));
            }
        } else {
            $this->oItem->setDescription($strDescription);
        }
    }

    /**
     * Parsing of the alternative description.
     * The X-ALT-DESC property does not belong to the RFC 5545 spec but is
     * commonly used by a lot of agents. (MS-Outlook, eM-Client, ...).
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function parseAltDescription(string $strName, string $strValue, array $aParams) : void
    {
        $strFmtTYpe = $aParams['FMTTYPE'] ?? '';
        if (strtolower($strFmtTYpe) == 'text/html') {
            $strHTML = $this->unmaskString($strValue);
            if ($this->oICalendar->getOption('removeHtmlBody', true) == true) {
                // some agents deploy full `<HTML><HEAD>...</HEAD><BODY> .... </BODY></HTML>` block...
                $iBody = strpos(strtolower($strHTML), '<body>');
                if ($iBody !== false) {
                    $strHTML = substr($strHTML, $iBody + 6);
                    $iBody = strrpos(strtolower($strHTML), '</body>');
                    if ($iBody !== false) {
                        $strHTML = substr($strHTML, 0, $iBody);
                    }
                }
            }
            $this->oItem->setHtmlDescription($strHTML);
        }
    }

    /**
     * Properties not supported so far.
     * @param string $strName
     * @param string $strValue
     * @param array<string,string> $aParams
     */
    protected function notSupported(string $strName, string $strValue, array $aParams) : void
    {
        // @codeCoverageIgnoreStart
        $this->oICalendar->log(LogLevel::WARNING, "Not supportet property {$strName} found", array_merge(['value' => $strValue], $aParams));
        // @codeCoverageIgnoreEnd
    }
}
