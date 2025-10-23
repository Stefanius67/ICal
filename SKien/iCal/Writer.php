<?php

declare(strict_types=1);

namespace SKien\iCal;

/**
 * Class to generate the content for a new iCalendar file.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 * @internal
 */
class Writer
{
    use iCalHelper;

    /** @var string the output buffer     */
    protected string $strBuffer = '';

    /**
     * Creates aninstance of a writer.
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar $oICalendar)
    {
        $this->oICalendar = $oICalendar;
    }

    /**
     * Returns the internal output buffer.
     * @return string
     */
    public function getBuffer() : string
    {
        return $this->strBuffer;
    }

    /**
     * Adds a property to the output buffer.
     * Empty value will be ignored!
     * If line exceeds max length, data will be split into multiple lines
     * @link https://www.rfc-editor.org/rfc/rfc5545.html#section-3.2
     * @param string    $strName
     * @param string    $strValue
     * @param bool      $bMask      have value to be masked (default: true)
     * @param array<string,string> $aParams
     * @return string
     */
    public function addProperty(string $strName, string $strValue, bool $bMask = true, array $aParams = []) : string
    {
        $strProp = '';
        if (!empty($strValue)) {
            if ($bMask) {
                $strValue = $this->maskString($strValue);
            }
            foreach ($aParams as $strParamName => $strParamValue) {
                // the param value MUST NOT contain DQUOTES
                $strParamValue = str_replace('"', '', $strParamValue);
                /**
                 * If the param value contains one of COLON, SEMICOLON, or COMMA, it have to
                 * be enclosed in double quotes.
                 * Following code comes from PHP str_contains() manual
                 * https://www.php.net/manual/en/function.str-contains.php#128796
                 */
                $aToQuote = [':', ';', ','];
                if (array_reduce($aToQuote, fn($a, $n) => $a || str_contains($strParamValue, $n), false)) {
                    $strParamValue = '"' . $strParamValue .'"';
                }
                $strName .= ';' . $strParamName . '=' . $strParamValue;
            }
            $strLine = $strName . ':' . $strValue;
            $strProp = $this->foldLine($strLine);
            $this->strBuffer .= $strProp;
        }

        return $strProp;
    }

    /**
     * Adds a date-time property to the output buffer.
     * @param string $strName
     * @param int $uxtsValue
     * @param string $strTZID
     * @param bool $bAllDay
     * @return string
     */
    public function addDateTimeProperty(string $strName, ?int $uxtsValue, string $strTZID, bool $bAllDay = false) : string
    {
        $strProp = '';

        if ($uxtsValue !== null) {
            $aParams = [];
            if ($strTZID == 'GMT') {
                $strValue = gmdate('Ymd\THis\Z', $uxtsValue);
            } else {
                $strDateFormat = 'Ymd\THis';
                if ($bAllDay) {
                    $strDateFormat = 'Ymd';
                    $aParams['VALUE'] = 'DATE';
                }
                $oCalcTimezone = $this->oICalendar->getCalcTimezone();
                if ($oCalcTimezone !== null) {
                    $aParams['TZID'] = $oCalcTimezone->getTZID();
                    $strValue = $this->formatDate($strDateFormat, $uxtsValue);
                } else {
                    if (!empty($strTZID)) {
                        $aParams['TZID'] = $strTZID;
                    }
                    $strValue = date($strDateFormat, $uxtsValue);
                }
            }
            $strProp = $this->addProperty($strName, $strValue, false, $aParams);
        }
        return $strProp;
    }

    /**
     * Longer lines have to be broken down in iCal format.
     * @param string $strLine
     * @return string
     */
    public function foldLine(string $strLine) : string
    {
        $strFoldedLines = '';
        // Folding-technique:
        // 1. first replace al 'real' linebreaks ( CR, LF, CRLF ) with '\n'
        $strLine = str_replace( "\r\n", PHP_EOL, $strLine );
        $strLine = str_replace( "\r", PHP_EOL, $strLine );
        $strLine = str_replace( PHP_EOL, '\n', $strLine );

        // 2. split in multiple lines with max. 75 chars
        while (strlen($strLine) > 75) {
            // CRLF immediately followed by a single blank mark multiline content
            if ($strLine[74] == chr(92)) {
                // don't break inside control sequence!
                $strFoldedLines .=  substr($strLine, 0, 74) . PHP_EOL . " ";
                $strLine = substr($strLine, 74);
            } else {
                $strFoldedLines .=  substr($strLine, 0, 75) . PHP_EOL . " ";
                $strLine = substr($strLine, 75);
            }
        }
        // last line only with closing CRLF
        $strFoldedLines .=  $strLine . PHP_EOL;

        return $strFoldedLines;
    }

    /**
     * Mask delimiter and newline if inside of value.
     * @param string $strValue
     * @return string
     */
    public function maskString(string $strValue) : string
    {
        // decode entities before ';' is replaced !!
        $strValue = html_entity_decode($strValue, ENT_HTML5);
        $strValue = str_replace("\r\n", "\n", $strValue);
        $strValue = str_replace("\r", "\n", $strValue);
        $strValue = str_replace("\n", "\\n", $strValue);
        $strValue = str_replace(",", "\\,", $strValue);
        $strValue = str_replace(";", "\\;", $strValue);

        $strFrom = mb_detect_encoding($strValue);
        if ($strFrom !== false && $strFrom != $this->oICalendar->getEncoding()) {
            $strValue = iconv($strFrom, $this->oICalendar->getEncoding(), $strValue);
            if ($strValue === false) {      // I have no testcase for PHPUnit so far, but phpstan wants this code...
                $strValue = '';             // @codeCoverageIgnore
            }
        }

        return $strValue;
    }
}
