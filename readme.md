# ICal Import/Export

 ![Latest Stable Version](https://img.shields.io/badge/release-v1.0.0-brightgreen.svg)
 ![License](https://img.shields.io/packagist/l/gomoob/php-pushwoosh.svg) 
 [![Donate](https://img.shields.io/static/v1?label=donate&message=PayPal&color=orange)](https://www.paypal.me/SKientzler/5.00EUR)
 ![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)
 ![PhpUnit coverage](./PhpUnitCoverage.svg) 
 
----------
## Overview

This package can read and write data in the `iCalendar` format specified by 
[RFC-5545: Internet Calendaring and Scheduling Core Object Specification (iCalendar)](https://www.rfc-editor.org/rfc/rfc5545.html)

The iCalendar format is first choice when such data needs to be exchanged or synchronized 
between different platforms, systems, or applications. The clear time zone specifications 
ensure unambiguous, region-independent data exchange at all times.

From the four components `event`, `to-do`, `journal entrie` and `free/busy information`
that are defined within this specification, this package supports

- **Events** and
- **ToDo's**

> The code and class structure allows a fairly simple extension with the two additional 
> components - however, since I do not need them yet, the implementation is (still) 
> pending; feel free to contribute and create according pull requests ;-) 

 ## Timezone handling

 The timezone handling when reading is different from that when creating `iCalendar`
 files. This is because the time zones used by iCalendar files do not match the PHP 
 time zones (which are based on the time zone identifiers published in the IANA time
 zone database).
 
 1. When generating a calendar, the (PHP) timezone that is set when the iCalender 
    instance is created is generally used. An iCal TIMEZONE component with the same 
    name is automatically inserted.
 2. When reading a calendar, the timezone definitions contained in the file are taken
    into account, and all datetime values ​​are saved as UNIX timestamps. Since these
    values ​​are generally UTC-based, it is up to the processing code to decide which
    time zone to use to display and/or process the data.
    
## Recurring items

In addition to the actual definition of the components, the `iCalendar` specification 
provides an extremely flexible mechanism with the *'Recurrence Rule'* to describe 
recurring items (events, todo's).

When reading elements that use this mechanism, the calling agent can decide how to 
handle them:

1. The appointment series resulting from the 'Recurrence Rule' is resolved and the 
   resulting individual items will be generated, which can then be adopted.
2. The original item remains unchanged. A list of the resulting dates can be retrieved 
   to process/generate the recurring events in the own code.   
   
## Formattet Text in descriptions (HTML)

### Import
When reading a iCalendar file, formatted HTML text is recognized if it is either stored 
in the RFC 5545-compliant `ALTREP` parameter of the description, or if the widely used 
custom property `X-ALT-DESC` is used.
  
If HTML is passed directly in a description (which is actually intended for plain text), 
it is automatically moved to the HTML description and a plain text representation is 
created.

If only a HTML description is passed, a plain text representation is auto-created.

It can be queried whether formatted text is available and both versions can be retrieved 
separately.

### Export
When creating an iCalendar file, the description of an item can be set separately as 
plain text and as formatted text. If only an HTML description is provided, a plain text 
version is automatically generated before the item is written to the file.

Additionally, there's the option to automatically generate a rudimentary HTML 
representation of plain text when no formatted text is available. Besides generating HTML 
line breaks, this option currently only offers valuable benefits when the plain text 
contains long, difficult-to-read URLs (... this is often the case for calendar entries 
that refer to further information on the Internet):

Plain URL's such as 
```
https://www.anydomain.de/some-report/in20%a20%further20%folder/report.php?iew=3&source=external
```
are converted to real HTML links like:

```HTML
<a href="https://www.anydomain.de/some-report/in20%a20%further20%folder/report.php?iew=3&source=external">www.anydomain.de</a>
```

this can result in a much better read- and recognizable display by the reading agent in following way:

> [www.anydomain.de](https://www.anydomain.de/some-report/in20%a20%further20%folder/report.php?iew=3&source=external)

## Usage

The usage for reading and writing *iCalendar* files is demonstrated in the sample code files

- ImportSelect.php
- ImportTest.php
- ExportTest.php

that are part of the package and can be found in the root directory.

  



