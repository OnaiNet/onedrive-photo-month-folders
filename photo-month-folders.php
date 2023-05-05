<?php
/**
 * Quick daemon-style script to automatically move "Camera Roll" photos into year-month directories
 * @author Kevin Gwynn <kevin.gwynn@gmail.com>
 */

 // Configuration
 // Now, to debug, set DEBUG=1 in env
 $_SERVER['DEBUG'] = $_SERVER['DEBUG'] ?? false;
const SLEEP_LENGTH = 60 * 15; // 15 minutes
const SLEEP_INTERVAL_COUNT = 60;
const SLEEP_INTERVAL = SLEEP_LENGTH / SLEEP_INTERVAL_COUNT; // divide into 60 individual sleeps
set_error_handler('error_handler');

// This works better on Windows
if (isset($_SERVER['OneDrive'])) {
	$photos_path = $_SERVER['OneDrive'] . DIRECTORY_SEPARATOR . 'Pictures' . DIRECTORY_SEPARATOR;
} elseif (isset($_SERVER['ONEDRIVE'])) {
	$photos_path = $_SERVER['ONEDRIVE'] . DIRECTORY_SEPARATOR . 'Pictures' . DIRECTORY_SEPARATOR;
} else {
	// Assume Linux with /OneDrive SymLinked to user's OneDrive folder
	// sudo ln -s "/mnt/c/Users/username/OneDrive" /OneDrive
	$photos_path = '/OneDrive/Pictures/';
}

if (!empty($_SERVER['argv'][1])) {
	$photos_path = $_SERVER['argv'][1];
}

$scan_path = $photos_path . 'Camera Roll' . DIRECTORY_SEPARATOR;
$target_path = $photos_path;
$extensions = 'jpe?g|mkv|mp4|mpe?g|mov|png|avi|gif';
date_default_timezone_set('America/Denver');

while (true) {
	output("Scanning [$scan_path]...");
	$dp = opendir($scan_path);

	while ($file = readdir($dp)) {
		if (is_dir($file)) continue; // Ignore any directories including '.' and '..'
		$matches = [];
		$year = $month = $day = $hour = $minute = $second = null;
		
		// Snapchat
		if (preg_match("/^Snapchat-/", $file)) {
			debug("Processing Snapchat file by created date: $file");

			$created_date = get_created_date($scan_path . DIRECTORY_SEPARATOR . $file);
			$year = $created_date['year'];
			$month = $created_date['month'];
		}
		// FIXED: Ex: 201801231058201000.jpg tries to create the path: 2023/2023-18
		// Mostly a catch-all expression...
		elseif (preg_match("/(20[012][0-9])[\. _\-]?(0[1-9]|1[0-2])[\. _\-]?([0-2][0-9]|3[01])[\. _\-]?([01][0-9]|2[0-3])[\. _\-]?([0-5][0-9])[\. _\-]?([0-5][0-9]).*?\.($extensions)$/", $file, $matches)) {
			debug("Matched main expression:", $matches);

			$year = $matches[1];
			$month = $matches[2];
			$day = $matches[3];
			$hour = $matches[4];
			$minute = $matches[5];
			$second = $matches[6];
		}
		// For LG G6's stupid format, eg: 0626171644.jpg (EWWW)
		elseif (preg_match("/(0[0-9]|1[0-2])([0-2][0-9]|3[01])([12][0-9])(\d{2})(\d{2}).*?\.($extensions)$/", $file, $matches)) {
			debug("Matched LG expression:", $matches);

			$month = $matches[1];
			$day = $matches[2];
			$year = '20' . $matches[3];
			$hour = $matches[4];
			$minute = $matches[5];
			$second = '00'; // not in file path
		}
		// Did not match by filename
		else {
			$created_date = get_created_date($scan_path . DIRECTORY_SEPARATOR . $file);

			if ($created_date['year'] && $created_date['month']) {
				$year = $created_date['year'];
				$month = $created_date['month'];
			} else {
				output("Could not calculate target date for: $file");
			}
		}

		if ($year != null) {
			$move_from = $scan_path . $file;
			$move_to = $target_path . $year . DIRECTORY_SEPARATOR . $year . '-' . $month . DIRECTORY_SEPARATOR;

			if (move_file($move_from, $move_to . $file)) {
				output("$file => $move_to");
			}
		}
	}

	// Go to sleep until next cycle
	output("Sleeping for " . SLEEP_LENGTH . " second(s)");

	for ($i = 0; $i < SLEEP_INTERVAL_COUNT; $i++) {
		echo ($i / SLEEP_INTERVAL_COUNT > 0.95) ? '!' : '.';
		sleep(SLEEP_INTERVAL);
	}
	
	echo "\n";
}

function error_handler($error_level, $error_message, $error_file, $error_line) {
	$error_level_name = get_error_level_name($error_level);

	if ($error_level & (E_ERROR | E_WARNING | E_CORE_ERROR | E_CORE_WARNING)) {
		
		throw new Exception("$error_level_name: $error_message at $error_file:$error_line");
	}

	debug("$error_level_name: $error_message at $error_file:$error_line");
}

function get_error_level_name($error_level) {
	static $LEVEL_NAMES_BY_LEVEL = array(
		E_ERROR => "E_ERROR",
		E_WARNING => "E_WARNING",
		E_PARSE => "E_PARSE",
		E_NOTICE => "E_NOTICE",
		E_CORE_ERROR => "E_CORE_ERROR",
		E_CORE_WARNING => "E_CORE_WARNING",
		E_COMPILE_ERROR => "E_COMPILE_ERROR",
		E_COMPILE_WARNING => "E_COMPILE_WARNING",
		E_USER_ERROR => "E_USER_ERROR",
		E_USER_WARNING => "E_USER_WARNING",
		E_USER_NOTICE => "E_USER_NOTICE",
		E_STRICT => "E_STRICT",
		E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
		E_DEPRECATED => "E_DEPRECATED",
		E_USER_DEPRECATED => "E_USER_DEPRECATED"
	);

	return $LEVEL_NAMES_BY_LEVEL[$error_level];
}

function debug($msg, $data = null) {
	if (!$_SERVER['DEBUG']) return;
	
	output("DEBUG: " . $msg);

	if ($data) {
		print_r($data);
	}
}

function output($msg) {
	echo timestamp() . ' ' . $msg . "\n";
}

function get_created_date($file) {
	$timestamp = false;
	// NOTE: exif only works on images; not videos.
	// NOTE: Here's an interesting library for getting metadata: https://code.google.com/archive/p/php-reader/source/default/source
	// NOTE: Another option is 'mediainfo' CLI script for Debian/apt: e.g. mediainfo MOVIE\(1\).m4v --output=JSON
	
	// EXIF / Images
	try {
		$exif = exif_read_data($file);
		// KAG: TODO: Handle EXIF data
		throw new Exception("EXIF Data available on file, but not yet supported (TODO)");

	} catch (Exception $ex) {
		debug("EXIF not available: " . $ex->getMessage());
		// EXIF not supported
	}

	if (!$timestamp) {
		// mediainfo command
		try {
			$file_argument = escapeshellarg($file);

			$json_output = shell_exec("mediainfo $file_argument --output=JSON");
			$media_info = json_decode($json_output);

			// May need to loop on all tracks; likely look for 'Encoded_Date' or 'Tagged_Date'
			$string_datetime = $media_info->media->track[0]->Encoded_Date ?? $media_info->media->track[0]->Tagged_Date;
			debug("Media Info; using date: $string_datetime");
			$timestamp = strtotime($string_datetime);
		} catch (Exception $ex) {
			debug("'mediainfo' command not available: " . $ex->getMessage());
		}
	}

	if (!$timestamp) {
		// filemtime vs. filectime
		$filectime = filectime($file);
		$filemtime = filemtime($file);

		if ($filectime < $filemtime) {
			debug("Using filemtime: $filectime (" . date("Y-m-d H:i:s", $filemtime));
			$timestamp = $filectime;
		} else {
			debug("Using filemtime: $filemtime (" . date("Y-m-d H:i:s", $filemtime));
			$timestamp = $filemtime;
		}
	}
	
	$data = array();
	$data['year'] = date('Y', $timestamp);
	$data['month'] = date('m', $timestamp);
	return $data;
}

function timestamp() {
	return date('Y-m-d H:i:s');
}

function assert_directory_exists($path) {
	if (!is_dir($path)) {
		output("Path does not exist: [$path]");

		// Create folder only if it is applicable to the current month
		// 2023-05-04 actually, let's just always create the path? Should be fine...
		//if (basename($path) == date('Y-m')) {
			output("Creating folder [$path]...");
			mkdir($path, 0777, true);
		//}

		return is_dir($path);
	}

	return true;
}

function move_file($from, $to) {
	$to_folder = dirname($to);

	if (!assert_directory_exists($to_folder)) {
		output("Cannot move [" . basename($from) . "]");
		return false;
	}

	if (file_exists($to)) {
		output("File exists! [$to])");

		$from_bytes = filesize($from);
		$to_bytes = filesize($to);
		
		if ($from_bytes != $to_bytes) {
			output("Files differ: ($from_bytes bytes) vs. ($to_bytes bytes)");
			return false;
		}

		$from_sha1 = sha1_file($from);
		$to_sha1 = sha1_file($to);
			
		if ($from_sha1 != $to_sha1) {
			output("Files differ: ($from_sha1 SHA1) vs. ($to_sha1 SHA1)");
			return false;
		}

		output("Files are identical.");
	}

	return $_SERVER['DEBUG'] ? true : rename($from, $to);
}

?>
