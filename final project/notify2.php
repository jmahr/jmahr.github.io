<?php
/*
 * Copyright (C) 2013 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
// Author: Jenny Murphy - http://google.com/+JennyMurphy


// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] != "POST") {
  header("HTTP/1.0 405 Method not supported");
  echo("Method not supported");
  exit();
}

// Always respond with a 200 right away and then terminate the connection to prevent notification
// retries. How this is done depends on your HTTP server configs. I'll try a few common techniques
// here, but if none of these work, start troubleshooting here.

// First try: the content length header
header("Content-length: 0");

// Next, assuming it didn't work, attempt to close the output buffer by setting the time limit.
ignore_user_abort(true);
set_time_limit(0);

// And one more thing to try: forking the heavy lifting into a new process. Yeah, crazy eh?
if (function_exists('pcntl_fork')) {
  $pid = pcntl_fork();
  if ($pid == -1) {
    error_log("could not fork!");
    exit();
  } else if ($pid) {
    // fork worked! but I'm the parent. time to exit.
    exit();
  }
}

// In the child process (hopefully). Do the processing.
require_once 'config.php';
require_once 'mirror-client.php';
require_once 'google-api-php-client/src/Google_Client.php';
require_once 'google-api-php-client/src/contrib/Google_MirrorService.php';
require_once 'util.php';

// Parse the request body
$request_bytes = @file_get_contents('php://input');
$request = json_decode($request_bytes, true);

// A notification has come in. If there's an attached photo, bounce it back
// to the user
$user_id = $request['userToken'];

$access_token = get_credentials($user_id);

$client = get_google_api_client();
// google's code that causes the 500 :( $client->setAccessToken($access_token);
// line 68 is needed for glassware to work (see next line)

// A glass service for interacting with the Mirror API
$mirror_service = new Google_MirrorService($client);

// Four functions for later, first one sets HTML
function maincardHTML(&$card,$row) {
	
	// map width and notes
	$mapWidth = ($row['notes'] == 0) ? 454 : 640;
	$note = ($row['notes'] == 0) ? "" : "NOTES";
	$notes = ($row['notes'] == 0) ? "" : $row['notes'];
	
	// picture of parking location
	$picture = ($row['picture'] == 0) ? "" : $row['picture'];
	$picHeight = ($row['picture'] == 0) ? 0 : 134;
	$picWidth = ($row['picture'] == 0) ? 0 : 224;
	
	// star icon in footer
	$star = ($row['favorite'] == 0) ? 
	"https://lh6.googleusercontent.com/-7Quj6ZVP2aI/UqLSmYpt2vI/AAAAAAAAA4w/zUuQW0hKb1E/s50-no/ic_unstar_50.png" 
	: "https://lh6.googleusercontent.com/-Oq6veNjZlHg/UqLSkDPyblI/AAAAAAAAA4M/dc2pWjhiFuM/s50-no/ic_star_50.png";
	
	// rest of footer, timer contains the return time, nested ternary operators
	$footer = ($row['timer'] == 0) ? (($row['favorite'] == 0) ? "Tap for more options." : $row['favorite']) : "Return by " . $row['timer'] . ".";
	
	// get HTML
	$htmlStream = file_get_contents("views/remember.php");
	
	// replace strings in HTML
	$htmlStream = str_replace("#LAT#",$row['latitude'],$htmlStream);
	$htmlStream = str_replace("#LONG#",$row['longitude'],$htmlStream);
	$htmlStream = str_replace("#ADDRESS#",$row['address'],$htmlStream);
	$htmlStream = str_replace("#MAPWIDTH#",$mapWidth,$htmlStream);
	$htmlStream = str_replace("#NOTE#",$note,$htmlStream);
	$htmlStream = str_replace("#NOTES#",$notes,$htmlStream);
	$htmlStream = str_replace("#PICTURE#",$picture,$htmlStream);
	$htmlStream = str_replace("#PICHEIGHT#",$picHeight,$htmlStream);
	$htmlStream = str_replace("#PICWIDTH#",$picWidth,$htmlStream);
	$htmlStream = str_replace("#STAR#",$star,$htmlStream);
	$htmlStream = str_replace("#FOOTER#",$footer,$htmlStream);
	
	// set HTML
	$card->setHTML($htmlStream);
}

// function for HTML, menu, notification, and inserting
function maincard($user_id) {	
	
	// current id and row
	$current_id = query("select id from current where userid = ?",$user_id)[0];
	$current_row = query("select * from location where id = ?", $id);
	
	// create new timeline item
	$remember_timeline_card = new Google_TimelineItem();
	
	// add HTML to new timeline card
	maincardHTML($remember_timeline_card,$current_row);

	// array for menu items
	$menu_items = array();
	
	// menu item for adding to or removing from favorites
	($row['favorite'] == 0) ?
	($star_menu_item = new Google_MenuItem()
	$star_menu_value = new Google_MenuValue()
	$star_menu_value->setDisplayName("Add Star")
	$star_menu_value->setIconUrl("https://lh5.googleusercontent.com/-6RGMPha8wJY/UqLSfwcGmZI/AAAAAAAAA24/vA_oYxQeWrk/s50-no/ic_plus_50.png")
	$star_menu_item->setValues(array($star_menu_value))
	$star_menu_item->setAction("REPLY")
	$star_menu_item->setId("star")
	array_push($menu_items, $star_menu_item))
	:
	($unstar_menu_item = new Google_MenuItem()
	$unstar_menu_value = new Google_MenuValue()
	$unstar_menu_value->setDisplayName("Remove Star")
	$unstar_menu_value->setIconUrl("https://lh4.googleusercontent.com/-bjrDWYMOMFo/UqLSZQgPFLI/AAAAAAAAA1w/gwT3DcjFYf0/s50-no/ic_no_50.png")
	$unstar_menu_item->setValues(array($unstar_menu_value))
	$unstar_menu_item->setAction("CUSTOM")
	$unstar_menu_item->setId("no_star")
	array_push($menu_items, $unstar_menu_item));
	
	// menu item to create or remove note
	if ($row['notes'] == 0) {
	$addnote_menu_item = new Google_MenuItem();
	$addnote_menu_value = new Google_MenuValue();
	$addnote_menu_value->setDisplayName("Add Note");
	$addnote_menu_value->setIconUrl("https://lh5.googleusercontent.com/-K09uW_9qMMM/UqLSP97SBqI/AAAAAAAAAyo/Ro59bGNbvCo/s50-no/ic_document_50.png");
	$addnote_menu_item->setValues(array($addnote_menu_value));
	$addnote_menu_item->setAction("REPLY");
	$addnote_menu_item->setId("note");
	array_push($menu_items, $addnote_menu_item);
	} else {
	$removenote_menu_item = new Google_MenuItem();
	$removenote_menu_value = new Google_MenuValue();
	$removenote_menu_value->setDisplayName("Remove Note");
	$removenote_menu_value->setIconUrl("https://lh4.googleusercontent.com/-bjrDWYMOMFo/UqLSZQgPFLI/AAAAAAAAA1w/gwT3DcjFYf0/s50-no/ic_no_50.png");
	$removenote_menu_item->setValues(array($removenote_menu_value));
	$removenote_menu_item->setAction("CUSTOM");
	$removenote_menu_item->setId("no_note");
	array_push($menu_items, $removenote_menu_item);
	}
	
	// menu item for pictures
	if ($row['picture'] == 0) {
	$addpic_menu_item = new Google_MenuItem();
	$addpic_menu_value = new Google_MenuValue();
	$addpic_menu_value->setDisplayName("Add Picture");
	$addpic_menu_value->setIconUrl("https://lh5.googleusercontent.com/-9E_yD4-gpXE/UqLSLMjtfvI/AAAAAAAAAx4/H8_zc68b2-Y/s50-no/ic_camera_50.png");
	$addpic_menu_item->setValues(array($addpic_menu_value));
	$addpic_menu_item->setAction("CUSTOM");
	$addpic_menu_item->setId("pic");
	array_push($menu_items, $addpic_menu_item);
	} else {
	$removepic_menu_item = new Google_MenuItem();
	$removepic_menu_value = new Google_MenuValue();
	$removepic_menu_value->setDisplayName("Remove Picture");
	$removepic_menu_value->setIconUrl("https://lh4.googleusercontent.com/-bjrDWYMOMFo/UqLSZQgPFLI/AAAAAAAAA1w/gwT3DcjFYf0/s50-no/ic_no_50.png");
	$removepic_menu_item->setValues(array($removepic_menu_value));
	$removepic_menu_item->setAction("CUSTOM");
	$removepic_menu_item->setId("no_pic");
	array_push($menu_items, $removepic_menu_item);
	}
	
	// menu item for a timer
	if ($row['timer'] == 0) {
	$timer_menu_item = new Google_MenuItem();
	$timer_menu_value = new Google_MenuValue();
	$timer_menu_value->setDisplayName("Add Timer");
	$timer_menu_value->setIconUrl("https://lh5.googleusercontent.com/-ZlHCoLH1OsY/UqLSmfwQm7I/AAAAAAAAA4o/LCt3MmTjTiI/s50-no/ic_timer_50.png");
	$timer_menu_item->setValues(array($timer_menu_value));
	$timer_menu_item->setAction("CUSTOM");
	$timer_menu_item->setId("timer");
	array_push($menu_items, $timer_menu_item);
	} else {
	$notimer_menu_item = new Google_MenuItem();
	$notimer_menu_value = new Google_MenuValue();
	$notimer_menu_value->setDisplayName("Remove Timer");
	$notimer_menu_value->setIconUrl("https://lh4.googleusercontent.com/-bjrDWYMOMFo/UqLSZQgPFLI/AAAAAAAAA1w/gwT3DcjFYf0/s50-no/ic_no_50.png");
	$notimer_menu_item->setValues(array($notimer_menu_value));
	$notimer_menu_item->setAction("CUSTOM");
	$notimer_menu_item->setId("no_timer");
	array_push($menu_items, $notimer_menu_item);
	}
	
	// menu item for reading aloud if there is a note
	if ($row['notes'] != 0) {
		
		// new menu item
		$read_aloud = new Google_MenuItem();
		$read_aloud_value = new Google_MenuValue();
		$read_aloud_value->setDisplayName("Read Note");
		$read_aloud->setValues(array($read_aloud_value));
		$read_aloud->setAction("READ_ALOUD");
		array_push($menu_items, $read_aloud);
		
		// set card's speakable text
		$remember_timeline_card->setSpeakableText("Notes: " . $row['notes']);
	}
	
	// menu item for pinning card
	$pin_menu_item = new Google_MenuItem();
	$pin_menu_item->setAction("TOGGLE_PINNED");
	array_push($menu_items, $pin_menu_item);

	// menu item for deleting card
	$delete_menu_item = new Google_MenuItem();
	$delete_menu_item->setAction("DELETE");
	array_push($menu_items, $delete_menu_item);
	
	// set menu items
	$remember_timeline_card->setMenuItems($menu_items);
	
	// default notification
	$notification = new Google_NotificationConfig();
	$notification->setLevel("DEFAULT");
	$remember_timeline_card->setNotification($notification);
	
	// insert card
	insert_timeline_item($mirror_service, $remember_timeline_card, null, null);
	$message = "Timeline Item inserted!";
}

// function for menu and notification on navigation card
function navcard(&$navigation_timeline_card,$user_id) {
	
	// current id and row
	$current_id = query("select id from current where userid = ?",$user_id)[0];
	$current_row = query("select * from location where id = ?", $id);
	
	// cannot create card in function because when loading favorites a bundle ID must be added
	// add HTML to new timeline card
	maincardHTML($navigation_timeline_card,$current_row);

	// array for menu items
	$menu_items = array();
	
	// menu item to get directions
	$nav_menu_item = new Google_MenuItem();
	$nav_menu_item->setAction("NAVIGATE");
	array_push($menu_items, $nav_menu_item);
	
	// set card's location
	$locationForNavigation = json_encode(array(
		"latitude" => $row['latitude'],
		"longitude" => $row['longitude']
	));
	$navigation_timeline_card->setLocation($locationForNavigation);
	
	// menu item for reading aloud if there is a note
	if ($row['notes'] != 0) {
		
		// new menu item
		$read_aloud = new Google_MenuItem();
		$read_aloud_value = new Google_MenuValue();
		$read_aloud_value->setDisplayName("Read Note");
		$read_aloud->setValues(array($read_aloud_value));
		$read_aloud->setAction("READ_ALOUD");
		array_push($menu_items, $read_aloud);
		
		// set card's speakable text
		$navigation_timeline_card->setSpeakableText("Notes: " . $row['notes']);
	}
	
	// menu item for pinning card
	$pin_menu_item = new Google_MenuItem();
	$pin_menu_item->setAction("TOGGLE_PINNED");
	array_push($menu_items, $pin_menu_item);

	// menu item for deleting card
	$delete_menu_item = new Google_MenuItem();
	$delete_menu_item->setAction("DELETE");
	array_push($menu_items, $delete_menu_item);
	
	// set menu items
	$navigation_timeline_card->setMenuItems($menu_items);
	
	// default notification
	$notification = new Google_NotificationConfig();
	$notification->setLevel("DEFAULT");
	$navigation_timeline_card->setNotification($notification);
	
	// insert card
	insert_timeline_item($mirror_service, $navigation_timeline_card, null, null);
	$message = "Timeline Item inserted!";
}

// Haversine formula for distance in miles between two locations, used when remembering location and sorting favorites
// obtained from http://www.nmcmahon.co.uk/getting-the-distance-between-two-locations-using-google-maps-api-and-php/
function haversine($startLat,$startLong,$endLat,$endLong) {
	$diffLong = $startLong - $endLong; 
  $distance = (sin(deg2rad($startLat)) * sin(deg2rad($endLat))) + (cos(deg2rad($startLat)) * cos(deg2rad($endLat)) * cos(deg2rad($diffLong))); 
  $distance = acos($distance); 
  $distance = rad2deg($distance);
	// return distance in miles
  return($distance * 60 * 1.1515);
}

// if user has shared a picture with this glassware
if ($request['userActions'][0]['type'] == "SHARE") {
	
	// Verify that the parameters we want are there
	if (!isset($_GET['timeline_item_id']) || !isset($_GET['attachment_id'])) {
	  http_response_code(400);
	  exit;
	}
	
	// Authenticate if we're not already
	if (!isset($_SESSION['userid']) || get_credentials($_SESSION['userid']) == null) {
	  header('Location: ' . $base_url . '/oauth2callback.php');
	  exit;
	} else {
	  $client->setAccessToken(get_credentials($_SESSION['userid']));
	}

	// fetch the metadata
	$attachment = $mirror_service->timeline_attachments->get(
	  $_GET['timeline_item_id'], $_GET['attachment_id']);
	
	// set the content type header
	header('Content-type: '. $attachment->getContentType());

	// update table with bytes from image
	$id = query("select id from current where userid = ?",$user_id);
	query("update location set picture = ? where id = ?",download_attachment($_GET['timeline_item_id'], $attachment),$id);
	
	// create and insert new timeline card then break
	maincard($user_id);
	break;
	
} else {
	
	// else not a shared picture. switch by payload, which contains ID of application-generated menu item
	switch ($request['userActions'][0]['payload']) {
	  
		// if user wants his location remembered
		case 'remember':
			
			// obtain location from timeline item reported to this file
			try {
				$location = $mirror_service->locations->get($request['itemId']);
				$latitude = $location->getLatitutde();
				$longitude = $location->getLongitude();
				$address = $location->getAddress();
			} catch (Exception $e) {
				print 'An error occurred: ' . $e->getMessage();
				exit;
			}
			
			// see whether user is likely at a previously remembered location
			get_location($user_id);
			$duplicate = 0;
			foreach ($rows as $row)
			{
				// same location likely when same address and within five meters (convert haversine to meters first)
				if ($address == $row['address'] && haversine($latitude,$longitude,$row['latitude'],$row['longitude'] * 1609.344 <= 5)) {
					
					// if so, update the existing entry with new information
					query("update location set userid = ?,latitude = ?,longitude = ?,timer = 0 where id = ?",$user_id,$latitude,$longitude,$row['id']);
					
					// store that location as user's current location
					store_current($row['id'],$user_id);
					
					// signals whether a match was found among existing locations
					$duplicate++;
					break;
				}
			}
			
			// if not a likely location, add a new entry and store it in current
			if (!duplicate) {
				store_location($user_id,$latitude,$longitude,$address,0,0,0,0,0);
				$current_id = query("select id from locations where userid = ? order by id desc",$user_id)[0];
				store_current($current_id,$user_id);
			}
			
			// create and insert new timeline card then break
			maincard($user_id);
			break;
		
		// if user chooses to add a favorite or add a note
		case 'star':
		case 'note':
			
			// parse for text
			$timelineitem = $mirror_service->timeline->get($itemId);
			
			// set column name and update table
			$id = query("select id from current where userid = ?",$user_id);
			$column = ($request['userActions'][0]['payload'] == 'star') ? "favorite" : "notes";
			query("update location set " . $column . " = ? where id = ?",$timelineitem['text'],$id);
		
			// create and insert new timeline card then break
			maincard($user_id);
			break;
		
		// occurs if user wishes to add a picture
		case 'pic':
		
			// create a new timeline card and set text
			$picInstructions = new Google_TimelineItem();
			$picInstructions->setText("Please swipe backwards and take a picture with Glass's camera. Then share with Parkifying Glass 
				and it will be added to your location card.\n\nTap twice to delete this card.");
			
			// array for menu item
			$menu_items = array();
			
			// menu item that deletes the temporary message card
			$delete_menu_item = new Google_MenuItem();
			$delete_menu_item->setAction("DELETE");
			array_push($menu_items, $delete_menu_item);

			// set menu item
			$picInstructions->setMenuItems($menu_items);

			// default notification
			$notification = new Google_NotificationConfig();
			$notification->setLevel("DEFAULT");
			$picInstructions->setNotification($notification);
			
			// insert card
			insert_timeline_item($mirror_service, $picInstructions, null, null);

			// message then break
			$message = "Timeline Item inserted!";
			break;
		
		// occurs if user wishes to add a timer
		case 'timer':
			
			// create a new timeline card. it will have menu items each with a specific amount of time.
			$setTimer = new Google_TimelineItem();
			
			// set text of new card
			$setTimer->setText("Tap to set the amount of time remaining or to delete this card.");
			
			// array for menu items (32 total)
			$menu_items = array();
			
			// function that creates a menu item displaying an amount of time and inserts it into an array of menu items
			function timerMenuItem($i,&$menu_items) {
				
				// new menu item
				$time_menu_item = new Google_MenuItem();
				$time_menu_value = new Google_MenuValue();
				
				// number of hours and minutes, total number of minutes is 5 * $i
				$hours = (5 * $i) % 60;
				$minutes = 5 * $i - $hours * 60;
				
				// display name, taking advantage of the facts that we cannot have only 1 minute and we cannot have 0 minutes when we have 0 hours
				// complex nested ternary operators are needed due to Google's limit for the length of menu display names
				$time_displayName = ($hours) ? (($hours == 1) ? (($minutes) ? $hours . " hr " . $minutes . " min" 
																																		: $hours . " hour")
																											: (($minutes) ? $hours . " hrs " . $minutes . " min" 
																																		: $hours . " hours")) 
																		 : $minutes . " minutes";
				
				// set properties of menu item
				$time_menu_value->setDisplayName($time_displayName);
				$time_menu_value->setIconUrl("https://lh5.googleusercontent.com/-ZlHCoLH1OsY/UqLSmfwQm7I/AAAAAAAAA4o/LCt3MmTjTiI/s50-no/ic_timer_50.png");
				$time_menu_item->setValues(array($time_menu_value));
				$time_menu_item->setAction("CUSTOM");
				
				// set ID of menu item to be a unique number for easy identification
				$time_menu_item->setId((string)$i);
				
				// push the new menu item into the array of menu items
				array_push($menu_items,$time_menu_item);
			}
			
			// iterate over times within the first 90 minutes in intervals of 5 minutes
			for ($i = 2; $i <= 18; $i++) {
				timerMenuItem($i,$menu_items);
			}
			
			// iterate over remaining times in intervals of 15 minutes
			for ($i = 21; $i <= 60; $i += 3) {
				timerMenuItem($i,$menu_items);
			}
			
			// menu item that deletes the card
			// deleting must be a separate menu item because when a menu item is deleted the payload is erased
			$delete_menu_item = new Google_MenuItem();
			$delete_menu_item->setAction("DELETE");
			array_push($menu_items, $delete_menu_item);

			// set menu items
			$setTimer->setMenuItems($menu_items);

			// default notification
			$notification = new Google_NotificationConfig();
			$notification->setLevel("DEFAULT");
			$setTimer->setNotification($notification);

			// insert card
			insert_timeline_item($mirror_service, $setTimer, null, null);

			// message then break
			$message = "Timeline Item inserted!";
			break;
			
		// occurs when user has just set an amount of time remaining
		case '2': case '3': case '4': case '5': case '6': case '7': case '8': case '9': case '10': 
		case '11': case '12': case '13': case '14': case '15': case '16': case '17': case '18':
		case '21': case '24': case '27': case '30': case '33': case '36': case '39':
		case '42': case '45': case '48': case '51': case '54': case '57': case '60':
			
			// amount of minutes to add to current time
			$minutesToAdd = (int)$request['userActions'][0]['payload'] * 5;
			
			// get current time
		
			
		/* not sure how servers work, but optimally we would manipulate it to send a $request-like object with userActions, payload, userToken, etc. to 
		notify.php when only 15 or 5 minutes remain. it would have 'userActions' = 'CUSTOM' and 'payload' = 'fifteen' or 'five' depending on the time */
		// occurs when user's time is running out
		case 'fifteen':
		case 'five':
		
			// create a new timeline card
			$almostTimeup = new Google_TimelineItem();
			
			// set text of new card depending on how much time is left
			$remainingTime = ($request['userActions'][0]['payload'] == 'fifteen') ? "15" : "5";
			$almostTimeup->setText("Only " . $remainingTime . " minutes left to get back to your car! Tap for directions or to delete this card.");
			
			// array for menu items
			$menu_items = array();
			
			// user wants a navigation card
			$where_menu_item = new Google_MenuItem();
			$where_menu_value = new Google_MenuValue();
			$where_menu_value->setDisplayName("Find Car");
			$where_menu_value->setIconUrl("https://lh5.googleusercontent.com/-oCZ6U_DGz6g/UqLSPMQBVKI/AAAAAAAAAyg/F_vvb8yOTS4/s50-no/ic_compass_50.png");
			$where_menu_item->setValues(array($where_menu_value));
			$where_menu_item->setAction("CUSTOM");
			$where_menu_item->setId("where");
			array_push($menu_items, $where_menu_item);
			
			// menu item that deletes the card
			$delete_menu_item = new Google_MenuItem();
			$delete_menu_item->setAction("DELETE");
			array_push($menu_items, $delete_menu_item);

			// set menu items
			$almostTimeup->setMenuItems($menu_items);

			// default notification
			$notification = new Google_NotificationConfig();
			$notification->setLevel("DEFAULT");
			$almostTimeup->setNotification($notification);
			
			// insert card
			insert_timeline_item($mirror_service, $almostTimeup, null, null);

			// message then break
			$message = "Timeline Item inserted!";
			break;
			
		// occurs if user is deleting information about current location
		case 'no_star':
		case 'no_note':
		case 'no_pic':
		case 'no_timer':
		
			// update database by setting deleted entry to 0
			$id = query("select id from current where userid = ?",$user_id);
			
			// set column name depending on payload
			switch ($request['userActions'][0]['payload']) {
				case 'no_star':  $column = "favorite"; break;
				case 'no_note':  $column = "notes";    break;
				case 'no_pic':   $column = "picture";  break;
				case 'no_timer': $column = "timer";    break;
				// style and lack of default from http://d2o9nyf4hwsci4.cloudfront.net/2013/fall/lectures/7/w/notes7w/notes7w.html
			}
			
			// make the update
			query("update location set " . $column . " = 0 where id = ?",$id);
		
			// create and insert new timeline card then break
			maincard($user_id);
			break;
		
		// menu option from welcome card, occurs when user wishes to find a saved location                                                                                                                                                                                                                                                                     
		case 'where':
		
			// create new navigation card
			$navigation_timeline_card = new Google_TimelineItem();
			
			// set properties then insert new navigation card and break
			navcard($navigation_timeline_card,$user_id);
			break;
			
		// menu option from welcome card, occurs when user wants to see favorites
		case 'favorites':
		
			// card with information about favorites
			$favorites = new Google_TimelineItem();
			
			// in order to sort favorites by proximity we need user's current location
			try {
				$location = $mirror_service->locations->get($request['itemId']);
				$latitude = $location->getLatitutde();
				$longitude = $location->getLongitude();
				$address = $location->getAddress();
			} catch (Exception $e) {
				print 'An error occurred: ' . $e->getMessage();
				exit;
			}
			
			// counter for number of stored locations
			$counter = 0;
			
			// select rows from table and update with current distance
			$rows = query("select from location where favorite <> 0 and userid = ?",$user_id);
			foreach ($rows as $row) {
				
				// calculate and update distance
				$distance = haversine($latitude,$longitude,$row['latitude'],$row['longitude']);
				query("update location set distance = ? where id = ?",$distance,$row['id']);
				
				// increment counter
				$counter++;
			}
			
			// carry out the following if there is at least one starred location
			if (counter) {
				
				// make card into bundle cover and generate unique bundle ID based on current time in milliseconds
				$bundle = uniqid();
				$favorites->setBundleId($bundle);
				$favorites->setIsBundleCover(true);

				// select rows again, this time sorted by distance from current location
				$rows_sorted = query("select from location where favorite <> 0 and userid = ? order by distance", $row['id']);

				// sub-timeline card for each location that user can navigate from
				foreach ($rows_sorted as $row) {

					// create new timeline card and set a bundle ID
					$favorite_item = new Google_TimelineItem();
					$favorite_item->setBundleId($bundle);
					
					// add HTML and navigation menu items
					navcard($favorite_item,$row);
				}
				
				// give user the ability to delete entire bundle
				// (then user will have ability to easily delete every card that this glassware sends)
				$deleteAll_card = new Google_TimelineItem();
				$deleteAll_card->setBundleId($bundle);
				$deleteAll_card->setText("Tap twice to remove this entire group of favorites from your timeline.");
				
				// array for custom menu item
				$menu_items = array();

				// custom menu item that indicates user wants to remove bundle
				$removeAll_item = new Google_MenuItem();
				$removeAll_value = new Google_MenuValue();
				$removeAll_value->setDisplayName("Remove All");
				$removeAll_value->setIconUrl("https://lh4.googleusercontent.com/-bjrDWYMOMFo/UqLSZQgPFLI/AAAAAAAAA1w/gwT3DcjFYf0/s50-no/ic_no_50.png");
				$removeAll_item->setValues(array($removeAll_value));
				$removeAll_item->setAction("CUSTOM");
				$removeAll_item->setId("remove_all");
				array_push($menu_items, $removeAll_item);
				
				// set menu items
				$deleteAll_card->setMenuItems($menu_items);

				// default notification
				$notification = new Google_NotificationConfig();
				$notification->setLevel("DEFAULT");
				$deleteAll_card->setNotification($notification);

				// insert card
				insert_timeline_item($mirror_service, $deleteAll_card, null, null);
				$message = "Timeline Item inserted!";
				
			} else {
				
				// else our menu item will be simply a message, so it needs a menu item that deletes the temporary message card
				$menu_items = array();
				$delete_menu_item = new Google_MenuItem();
				$delete_menu_item->setAction("DELETE");
				array_push($menu_items, $delete_menu_item);

				// set menu item
				$favorites->setMenuItems($menu_items);
			}
			
			// set text of cover card based on number of favorites found
			($counter) ? $favorites->setText($counter . " locations found. Tap to see your saved locations sorted by proximity to you. 
				\n\nThen tap twice on any location to navigate.") 
				: $favorites->setText("Sorry, no saved locations found.\n\nTap twice to delete this card.");
			
			// insert favorites card
			insert_timeline_item($mirror_service, $favorites, null, null);
			
			// now print message and exit
			$message = "Favorites bundle inserted!";
			break;
		
		// occurs when user wants to delete entire bundle of favorites
		case 'remove_all':
			
			// obtain card's bundle id using a get request
			try {
				$received_card = $mirror_service->$timeline->get($request['itemId']);
				$bundle_ID = $received_card->getBundleId();
			} catch (Exception $e) {
				print 'An error occurred: ' . $e->getMessage();
			}
			
			// list all timeline items with that bundle id
			try {
				
				// make an array for the entire bundle
				$entireBundle = array();
				$pageToken = null;
				
				// iterating via page tokens
				do {
					
					// array for bundle ID and pageToken
					$parameters = array();
					$parameters['bundleId'] = $bundle_ID;
					if ($pageToken) {
						$parameters['pageToken'] = $pageToken;
					}
					
					// list the timeline
					$timelineItems = $mirror_service->timeline->listTimeline($parameters);
					
					// if list is not empty continue, else break
					$gItems = $timelineItems->getItems();
					if (!empty($gItems)) {
						$entireBundle = array_merge($entireBundle,$gItems);
						$pageToken = $timelineItems->getNextPageToken();
					} else {
						break;
					}
				} while ($pageToken);
			} catch (Exception $e) {
				print 'An error occured: ' . $e->getMessage();
				return null;
			}
			
			// iterate over the list, deleting each timeline card
			foreach ($entireBundle['items'] as $cardToDelete) {
				
				// delete card with the corresponding item ID
				try {
					$mirror_service->timeline->delete($cardToDelete->getID());
				} catch (Exception $e) {
					print 'An error occurred: ' . $e->getMessage();
				}
			}
			
			// note - code for listing and deleting was mostly provided online by Google
			break;
			
		// default case, occurs when notification is not recognized as any of the above
		default:
	    error_log("Sorry, your request cannot be processed at this time.");
	}
}
?>