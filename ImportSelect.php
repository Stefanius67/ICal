<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>iCalendar Importtest Selection</title>
</head>
<body>
	<form action="ImportTest.php" enctype="multipart/form-data" method="post">
	<label for="icsFile">import iCalendar - File:</label><br><br>
	<input type="file" id="icsFile" name="icsFile"><br><br>
	<input type="submit" value="import iCalendar">
	</form>
</body>
</html>