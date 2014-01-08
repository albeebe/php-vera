<?php

/*

Exception Codes
100		Unrecognized response from server
101 	Unable to connect to server

*/

class VERA {

	protected $_username, $_password;
	protected $_units;
	protected $_devices;
	protected $_categories;
	protected $_rooms;
	
/* ------------------------------------------------------------------- */
public function VERA($username, $password) {
	/*
	
	Initialize the class
	
	*/
	
	$this->_username = $username;
	$this->_password = $password;
	$this->_units = array();
	$this->_devices = array();
	$this->_categories = array();
	$this->_rooms = array();
	
	// Connect to the server
	$response = @file_get_contents("https://sta1.mios.com/locator_json.php?username=".urlencode($username)."&password=".urlencode($password));
	if ($response === false) {
		$this->throwException("Unable to connect to https://sta1.mios.com", 101);
		return;
	}
	
	// Get the available units
	$arrResponse = json_decode($response, true);
	if (!isset($arrResponse["units"])) {
		$this->throwException("No 'units' variable was returned", 100);
		return;
	}
	$this->_units = $arrResponse["units"];
	
	// Get all the unit details
	for ($x = 0; $x < sizeof($arrResponse["units"]); $x++) {
		$this->getUnitDetails($x);
	}
}




/* ------------------------------------------------------------------- */
public function units() {
	/*
	
	Returns an array containing the available units
	
	*/
	
	$arrUnits = array();
	foreach ($this->_units as $unit) {
		array_push($arrUnits, array("name"=>$unit["name"], "serial"=>$unit["serialNumber"], "firmware"=>$unit["FirmwareVersion"], "ip"=>$unit["ipAddress"]));
	}
	return $arrUnits;
}




/* ------------------------------------------------------------------- */
public function categories($unit) {
	/*
	
	Returns the category of devices currently in use
	
	*/
	
	$arrCategories = array();
	foreach ($this->devices($unit) as $device) {
		if ($device["invisible"] == false) {
			if ($device["category_id"] != "") {
				$arrCategories[$device["category_id"]] = $device["category_name"];
			}
		}
	}
	ksort($arrCategories);
	return $arrCategories;
}




/* ------------------------------------------------------------------- */
public function devices($unit) {
	/*
	
	Returns array containg the devices for the requested unit
	
	*/
	
	$arrDevices = array();
	foreach ($this->_devices[$unit] as $device) {
		$arrDevice = array();
		$arrDevice["id"] = $device["id"];
		$arrDevice["type"] = $device["device_type"];
		$arrDevice["name"] = $device["name"];
		$arrDevice["manufacturer"] = $device["manufacturer"];
		$arrDevice["created"] = $device["time_created"];
		$arrDevice["invisible"] = $device["invisible"];
		$arrDevice["disabled"] = $device["disabled"];
		$arrDevice["category_id"] = $device["category_num"];
		$arrDevice["category_name"] = $this->getCategoryName($unit, $device["category_num"]);
		$arrDevice["room_id"] = $device["room"];
		$arrDevice["room_name"] = $this->getRoomName($unit, $device["room"]);
		for ($x = 0; $x < sizeof($device["states"]); $x++) {
			$state = $device["states"][$x];
			switch (strtolower($state["variable"])) {
				case "armed":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:securitysensor1") {
						$arrDevice["is_armed"] = $state["value"];
					}
					break;
					
				case "batterylevel":
					$arrDevice["battery_level"] = $state["value"];
					break;
				
				case "currentlevel":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:lightsensor1") {
						$arrDevice["light_level"] = $state["value"];
					} else if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:humiditysensor1") {
						$arrDevice["humidity"] = $state["value"];
					}
					break;
					
				case "currenttemperature":
					if (strtolower($state["service"]) == "urn:upnp-org:serviceid:temperaturesensor1") {
						$arrDevice["temperature"] = $state["value"];
					}
					break;
					
				case "kwh":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:energymetering1") {
						$arrDevice["kwh"] = $state["value"];
					}
					break;
					
				case "lasttrip":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:securitysensor1") {
						$arrDevice["last_tripped"] = $state["value"];
					}
					break;
					
				case "status":
					if (strtolower($state["service"]) == "urn:upnp-org:serviceid:switchpower1") {
						$arrDevice["is_on"] = $state["value"];
					}
					break;
					
				case "tripped":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:securitysensor1") {
						$arrDevice["is_tripped"] = $state["value"];
					}
					break;
					
				case "watts":
					if (strtolower($state["service"]) == "urn:micasaverde-com:serviceid:energymetering1") {
						$arrDevice["watts"] = $state["value"];
					}
					break;
			}
		}
		$arrDevices[$device["id"]] = $arrDevice;
	}
	return $arrDevices;
}




/* ------------------------------------------------------------------- */
public function energyUsage($unit) {
	/*
	
	Returns array containg the energy usage for the requested unit
	
	*/
	
	$url = $this->getUnitAccessAddress($unit, true)."data_request?id=live_energy_usage";
	
	// Connect to the Vera
	$response = @file_get_contents($url);
	if ($response === false) {
		$this->throwException("Unable to connect to ".$this->getUnitAccessAddress($unit, false), 101);
		return;
	}
	
	// Parse the energy usage
	$arrEnergyUsage = array();
	$devices = explode("\n", $response);
	foreach ($devices as $device) {
	    $details = explode("\t", $device);
		if (sizeof($details) == 5) {
			$arrDevice = array();
			$arrDevice["device_id"] = $details[0];
			$arrDevice["device_name"] = $details[1];
			$arrDevice["room"] = $details[2];
			$arrDevice["category_id"] = $details[3];
			$arrDevice["category_name"] = $this->getCategoryName($unit, $details[3]);
			$arrDevice["watts"] = $details[4];
			array_push($arrEnergyUsage, $arrDevice);
		}
	}
	
	return $arrEnergyUsage;
}




/* ------------------------------------------------------------------- */
public function rooms($unit) {
	/*
	
	Returns array containg the rooms for the requested unit
	
	*/
	
	return $this->_rooms[$unit];
}




/* ------------------------------------------------------------------- */
private function getCategoryName($unit, $categoryID) {
	/*
	
	Returns a category name
	
	*/
	
	$name = $this->_categories[$unit][$categoryID];
	return (strlen($name) == 0) ? "Other" : $name;
}




/* ------------------------------------------------------------------- */
private function getRoomName($unit, $roomID) {
	/*
	
	Returns a category name
	
	*/
	
	$name = $this->_rooms[$unit][$roomID];
	return (strlen($name) == 0) ? "" : $name;
}




/* ------------------------------------------------------------------- */
private function getUnitDetails($unit) {
	/*
	
	Download and process the units details
	
	*/
	
	$url = $this->getUnitAccessAddress($unit, true)."data_request?id=user_data";
	
	// Connect to the Vera
	$response = @file_get_contents($url);
	if ($response === false) {
		$this->throwException("Unable to connect to ".$this->getUnitAccessAddress($unit, false), 101);
		return;
	}
	
	// Process the details
	$arrResponse = json_decode($response, true);
	$this->processDevices($unit, $arrResponse["devices"]);
	$this->processDetails($unit, $arrResponse["SetupDevices"]);
	$this->processRooms($unit, $arrResponse["rooms"]);
	
	//print_r($arrResponse);
}




/* ------------------------------------------------------------------- */
private function getUnitAccessAddress($unit, $addCredentials = false) {
	/*
	
	Returns the IP:Port or the hostname used to access the unit
	
	*/
	
	if (strlen($this->_units[$unit]["ip"]) > 0) {
		return "http://".$this->_units[$unit]["ip"].":3480/";
	} else {
		if ($addCredentials == true) {
			return "https://".$this->_units[$unit]["active_server"]."/".$this->_username."/".$this->_password."/".$this->getUnitSerialNumber($unit)."/";
		} else {
			return "https://".$this->_units[$unit]["active_server"]."/";
		}
	}
}




/* ------------------------------------------------------------------- */
public function getUnitSerialNumber($unit) {
	/*
	
	Returns a units serial number
	
	*/
	
	return $this->_units[$unit]["serialNumber"];
}




/* ------------------------------------------------------------------- */
private function processCategories($unit, $categories) {
	/*
	
	Process the categories
	
	*/
	
	$arrCategories = array();
	foreach ($categories["Tabs"] as $category) {
		if (strtolower($category["Function"]) == "sort_devices_by_category") {
			$label = $category["Label"]["text"];
			if (strlen($label) > 0) {
				for ($x = 0; $x < sizeof($category["Parameters"]); $x++) {
					$arrCategories[$category["Parameters"][$x]] = $label;
				}
			}
		}
	}
	$this->_categories[$unit] = $arrCategories;
}




/* ------------------------------------------------------------------- */
private function processDetails($unit, $details) {
	/*
	
	Process the categories
	
	*/
	
	foreach ($details as $detail) {
		switch(strtolower($detail["DeviceType"])) {
			case "scene_devices":
				$this->processCategories($unit, $detail);
				break;
		}
	}
}




/* ------------------------------------------------------------------- */
private function processDevices($unit, $devices) {
	/*
	
	Process the devices
	
	*/
	
	foreach ($devices as $device) {
		$this->_devices[$unit][$device["id"]] = $device;
	}
}




/* ------------------------------------------------------------------- */
private function processRooms($unit, $rooms) {
	/*
	
	Process the rooms
	
	*/
	
	$this->_rooms[$unit] = array();
	foreach ($rooms as $room) {
		$name = $room["name"];
		if (strlen($room) > 0) {
			$this->_rooms[$unit][$room["id"]] = $name;
		}
	}
	ksort($this->_rooms[$unit]);
}




/* ------------------------------------------------------------------- */
private function throwException($message, $code) {
	/*
	
	Throw an exception
	
	*/
	
	throw new Exception($message, $code);
}

}
?>