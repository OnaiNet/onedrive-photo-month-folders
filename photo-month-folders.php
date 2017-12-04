<?php
/**
 * Quick daemon-style script to automatically move "Camera Roll" photos into year-month directories
 * @author Kevin Gwynn <kevin.gwynn@gmail.com>
 */

if (isset($_SERVER['OneDrive']))
	$photos_path = $_SERVER['OneDrive'] . DIRECTORY_SEPARATOR . 'Pictures' . DIRECTORY_SEPARATOR;
else
	// Assume Linux with ~/OneDrive SymLinked to OneDrive folder
	$photos_path = $_SERVER['HOME'] . '/OneDrive/Pictures/';

$scan_path = $photos_path . 'Camera Roll' . DIRECTORY_SEPARATOR;
//$scan_path = $photos_path;// . 'Camera Roll' . DIRECTORY_SEPARATOR;
$target_path = $photos_path;
$extensions = 'jpe?g|mkv|mp4|mpe?g|mov|png|avi';
date_default_timezone_set('America/Denver');

echo timestamp() . "-Scanning [$scan_path]...\n";

while (true) {
	$dp = opendir($scan_path);

	while ($file = readdir($dp)) {
		if (is_dir($file)) continue; // Ignore any directories including '.' and '..'
		$matches = [];
		$year = $month = $day = $hour = $minute = $second = null;

		// Mostly a catch-all expression...
		if (preg_match("/(20[012][0-9])[\. _\-]?(\d{2})[\. _\-]?(\d{2})[\. _\-](\d{2})[\. _\-]?(\d{2})[\. _\-]?(\d{2}).*?\.($extensions)$/", $file, $matches)) {
			$year = $matches[1];
			$month = $matches[2];
			$day = $matches[3];
			$hour = $matches[4];
			$minute = $matches[5];
			$second = $matches[6];
		}
		// For LG G6's stupid format, eg: 06261716.jpg (EWWW)
		elseif (preg_match("/([01][0-9])([0-3][0-9])([12][0-9])(\d{2})(\d{2}).*?\.($extensions)$/", $file, $matches)) {
			$month = $matches[1];
			$day = $matches[2];
			$year = '20' . $matches[3];
			$hour = $matches[4];
			$minute = $matches[5];
			$second = '00'; // not in file path
		}

		if ($year != null) {
			$move_from = $scan_path . $file;
			$move_to = $target_path . $year . DIRECTORY_SEPARATOR . $year . '-' . $month . DIRECTORY_SEPARATOR;

			if (move_file($move_from, $move_to . $file)) {
				echo timestamp() . "-$file => $move_to\n";
			}
		}
	}

	
	sleep(60 * 15);
}

function timestamp() {
	return date('Y-m-d H:i:s');
}

function assert_directory_exists($path) {
	if (!is_dir($path)) {
		echo timestamp() . "-Path does not exist: [$path]\n";

		// Create folder only if it is applicable to the current month
		if (basename($path) == date('Y-m')) {
			echo timestamp() . "-Creating folder [$path]...\n";
			mkdir($path, 0777, true);
		}

		return is_dir($path);
	}

	return true;
}

function move_file($from, $to) {
	$to_folder = dirname($to);

	if (!assert_directory_exists($to_folder)) {
		echo timestamp() . "-Cannot move [" . basename($from) . "]\n";
		return false;
	}

	if (file_exists($to)) {
		echo timestamp() . "-File exists! [$to]\n";

		$from_bytes = filesize($from);
		$to_bytes = filesize($to);
		
		if ($from_bytes != $to_bytes) {
			echo timestamp() . "-Files differ: ($from_bytes bytes) vs. ($to_bytes bytes)\n";
			return false;
		}

		$from_sha1 = sha1_file($from);
		$to_sha1 = sha1_file($to);
			
		if ($from_sha1 != $to_sha1) {
			echo timestamp() . "-Files differ: ($from_sha1 SHA1) vs. ($to_sha1 SHA1)\n";
			return false;
		}

		echo timestamp() . "-Files are identical.\n";
	}

	return rename($from, $to);
}

?>
