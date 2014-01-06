<PRE>
<?php

include("vera.inc.php");

// Connect to the server
try {
	$_VERA = new VERA("USERNAME", "PASSWORD");
} catch (Exception $e) {
	print "Error (".$e->getCode().") ".$e->getMessage();	
	exit;
}

// Print the units
print "<h1>Units</h1>";
$units = $_VERA->units();
print_r($units);

for ($x = 0; $x < sizeof($units); $x++) {
	print "<HR>";

	// Print the devices
	print "<h1>Devices for unit ".$x."</h1>";
	print_r($_VERA->devices($x));
	
	// Print the categories
	print "<h1>Categories for unit ".$x."</h1>";
	print_r($_VERA->categories($x));
	
	// Print the rooms
	print "<h1>Rooms for unit ".$x."</h1>";
	print_r($_VERA->rooms($x));
	
	// Print the energy usage
	print "<h1>Energy Usage for unit ".$x."</h1>";
	print_r($_VERA->energyUsage($x));

}

?>