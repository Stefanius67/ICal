<?php

declare(strict_types=1);

namespace SKien\Test\iCal;

use Psr\Log\AbstractLogger;

/**
 * Simple PSR logger to use within unit tests.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class UnitTestLogger extends AbstractLogger
{
    protected array $aLog = [];

    public function log($level, $message, array $context = [])
    {
        if (!isset($this->aLog[$level])) {
            $this->aLog[$level] = [];
        }
        $this->aLog[$level][] = $message;
    }

    public function getLog() : array
    {
        return $this->aLog;
    }
}