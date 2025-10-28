<?php

declare(strict_types=1);

namespace SKien\iCal;


use Soundasleep\Html2Text;

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
     * Adds a description and HTML decription (X-ALT-DESC) property to the output buffer.
     * I've decided to use the widly used `X-ALT-DESC` property instead of the `ALTREP`
     * param so there is no need to take care about any DQUOTES probably contained in the
     * HTML description. <br><br>
     * If no HTML description is passed, a rudimental HTML ist generated from the
     * given plain text, if it is not deactivated through the options. <br>
     * If only a html description is passed, the plain text is generated from the
     * given HTML. <br>
     * If a HTML formatet text is passed as plain text, it is moved to the HTML
     * property and a converted plain text is used as raw description.
     * @param string $strDescription
     * @param string $strHtmlDescription
     */
    public function addDescription(string $strDescription, string $strHtmlDescription) : void
    {
        if (!empty($strDescription)) {
            if ($strDescription != strip_tags($strDescription)) {
                // if no HTML description is set, just move the HTML there
                if (empty($strHtmlDescription)) {
                    $strHtmlDescription = $strDescription;
                }
                $strDescription = Html2Text::convert($strDescription);
            } else if (empty($strHtmlDescription)) {
                // no HTML set - create rudimental HTML
                if ($this->oICalendar->getOption('autoCreateHTML', true)) {
                    $strHtmlDescription = $this->convTextToHTML($strDescription);
                    $strHtmlDescription = $this->replaceURLsWithLinks($strHtmlDescription);
                }
            }
        } elseif (!empty($strHtmlDescription)) {
            // only HTML set - convert and set to the plain property
            $strDescription = Html2Text::convert($strHtmlDescription);
        }
        $this->addProperty('DESCRIPTION', $strDescription);
        $this->addProperty('X-ALT-DESC', $strHtmlDescription);
    }

    /**
     * Adds the organizer to the output buffer.
     * @param string $strOrganizerName
     * @param string $strOrganizerEMail
     */
    public function addOrganizer(string $strOrganizerName, string $strOrganizerEMail) : void
    {
        if (!empty($strOrganizerEMail)) {
            $aParams = [];
            if (!empty($strOrganizerName)) {
                $aParams['CN'] = $strOrganizerName;
            }
            $strValue = 'mailto:' . $strOrganizerEMail;
            $this->addProperty('ORGANIZER', $strValue, true, $aParams);
        }
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

    /**
     * Replaces all contained URL's with HTML link.
     * @param string $strText text containing URL(s)
     * @return string text containing the generated links
     */
    public function replaceURLsWithLinks(string $strText) : string
    {
        $match = [];
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $strText, $match);
        if (is_array($match[0])) {
            foreach ($match[0] as $strURL) {
                $strHost = parse_url($strURL, PHP_URL_HOST);
                $strLink = '<a href="' . $strURL . '">' . $strHost . '</a>';
                $strText = str_replace($strURL, $strLink, $strText);
            }
        }
        return $strText;
    }

    /**
     * Converts plain text for use as HTML with monotype font.
     * - Enclose the text in a paragraph. (&lt;p&gt; .. &lt;/p&gt;) <br>
     * - Multiple CR/LF   => new paragraph (&lt;/p&gt;&lt;p&gt;)    <br>
     * - CR/LF            => &lt;br/&gt;                            <br>
     * - Tab              => 3 save blanks (&amp;nbsp;)             <br>
     * - multiple Spaces  => save blanks (&amp;nbsp;)               <br>
     * @param string $strText
     * @return string
     */
    public function convTextToHTML(?string $strText) : string
    {
        $strHTML = '';
        if ($strText !== null) {
            $strHTML = htmlspecialchars($strText);
            $strHTML = str_replace("\r\n", "\n", $strHTML);
            $strHTML = str_replace("\r", "\n", $strHTML);
            $strHTML = str_replace("\n", "<br>", $strHTML);
            // reduce multiple linebreakt to max. double linebreak ...
            while (strpos($strHTML, '<br><br><br>') !== false) {
                $strHTML = str_replace('<br><br><br>', '<br><br>', $strHTML);
            }
            // ... and then replace double linebreak with new paragraph,
            $strHTML = str_replace('<br><br>', '</p><p>', $strHTML);
            $strHTML = str_replace("\t", "&nbsp;&nbsp;&nbsp;", $strHTML);
            $strHTML = str_replace("  ", "&nbsp;&nbsp;", $strHTML);
            $strHTML = '<p>' . $strHTML . '</p>';
        }
        return $strHTML;
    }
}
