<?php

declare(strict_types=1);

namespace SKien\iCal;

use Psr\Log\LogLevel;

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

    /**
     * @param iCalendar $oICalendar
     */
    public function __construct(iCalendar &$oICalendar)
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
     * @param array<string> $aLines
     * @param int $iLine
     * @return string
     */
    static public function nextLine(array $aLines, int &$iLine) : string
    {
        // remove EOL and check for iCal-folded lines
        $strLine = rtrim($aLines[$iLine++], "\r\n");
        while ($iLine < count($aLines) && substr($aLines[$iLine], 0, 1) == ' ') {
            $strLine .= rtrim(substr($aLines[$iLine++], 1), "\r\n");
        }
        return $strLine;
    }

    /**
     * Parses the given line.
     * @param string $strLine
     */
    public function parseLine(string $strLine) : void
    {
        // split property name/params from value
        $aSplit = explode(':', $strLine, 2);
        if (count($aSplit) == 2) {
            $aNameParams = explode(';', $aSplit[0]);
            $strName = $aNameParams[0];
            $aParams = $this->parseParams($aNameParams);
            $this->addProperty($strName, $aParams, $aSplit[1]);
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
