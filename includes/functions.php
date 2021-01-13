<?php

function showArray ($array)
{
    echo "\n------\n"; print_r($array); echo "\n------\n";
}

/**
 * Save user IDs from Twitter accounts. Max: 100.
 * @return Array with User IDs
 */
function saveAccountIDs ($accounts)
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	$sql = "TRUNCATE " . SQL_TABLE_ACCOUNTS;
	$result = $conn->query($sql);

	$sql = "";

	$tw = new Twitter();

	$users = $tw->usersLookUp($accounts);

	foreach($users as $user){
		if( $conn->query("INSERT INTO " . SQL_TABLE_ACCOUNTS . " (user_id, name, screen_name, description, url) VALUES ('" . $user->id_str . "', '" . utf8_decode(str_replace("'", "´", $user->name)) . "', '" . utf8_decode(str_replace("'", "´", $user->screen_name)) . "', '" . utf8_decode(str_replace("'", "´", $user->description)) . "', '" . $user->url . "')") ){
			echo "$user->screen_name added successfully\n";
		} else {
			echo "Error with the user: $user->screen_name. Message: $conn->error\n";
		}
	}

	$conn->close();
}

/**
 * Get user IDs from Twitter accounts
 *
 * @return Array with User IDs
 */
function getAccountIDs ()
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	$sql = "SELECT * FROM " . SQL_TABLE_ACCOUNTS . " ORDER BY id ASC";
	$result = $conn->query($sql);

	$accountIDs = array();

	if ($result->num_rows > 0) {
	  while($row = $result->fetch_assoc()) {
	    $accountIDs[] = $row["user_id"];
	  }
	}

	$conn->close();

	return $accountIDs;
}

/**
 * Get hashtag names from database
 *
 * @return Array with hashtag names
 */
function getHashtags ($scope)
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	$sql = "SELECT * FROM " . SQL_TABLE_HASHTAGS . " WHERE id NOT IN (SELECT hashtag_id FROM " . SQL_TABLE_ANALYSIS . " WHERE scope = $scope AND time = " . TIME . " AND finished = 1) ORDER BY id ASC";
	$result = $conn->query($sql);

	$hashtags = array();

	if ($result->num_rows > 0) {
	  while($row = $result->fetch_assoc()) {
	    $hashtags[] = utf8_encode($row["name"]);
	  }
	}

	$conn->close();

	return $hashtags;
}

/**
 * Get number of hashtag left to finish
 *
 * @return Array with hashtag names
 */
function getNumHashtagsUnfinished ($scope)
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	$sql = "SELECT COUNT(*) as count FROM " . SQL_TABLE_ANALYSIS . " WHERE scope = $scope AND time = " . TIME . " AND finished = 0";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  $row = $result->fetch_assoc();
	  return $row["count"];
	}

	return false;
}

/**
 * Inicialize hashtag data
 */
function iniHashtagData ()
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	if (VERBOSE) echo "Truncating SQL tables.\n";

	$sql = "TRUNCATE " . SQL_TABLE_HASHTAGS;
	$result = $conn->query($sql);

	$sql = "TRUNCATE " . SQL_TABLE_ANALYSIS;
	$result = $conn->query($sql);

	$conn->close();
}

/**
 * Mark all hashtag analysis from accounts as finished
 */
function iniTWData ()
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	if (VERBOSE) echo "Initializing data from Twitter in general.\n";

	if( $conn->query("UPDATE " . SQL_TABLE_ANALYSIS . " SET finished = 0, num_tweets = 0,  retweet_count = 0, favorite_count = 0, user_num_followers = 0, user_num_tweets = 0, user_age = 0, url_inclusion = 0 WHERE scope = 1 AND time = " . TIME) ){
		return true;
	} else {
		echo "Error inserting $this->name. Message: $conn->error\n";
		return false;
	}
}

/**
 * Mark all hashtag analysis from accounts as finished
 */
function markHashtagAnalysisFromAccountsAsFinished ()
{
	// Create connection
	$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
	// Check connection
	if ($conn->connect_error) {
	  die("Connection failed: " . $conn->connect_error);
	}

	if (VERBOSE) echo "Marking all hashtag analysis from accounts as finished.\n";

	if( $conn->query("UPDATE " . SQL_TABLE_ANALYSIS . " SET finished = 1 WHERE scope = 0 AND time = " . TIME) ){
	} else {
		echo "Error inserting $this->name. Message: $conn->error\n";
	}

	$conn->close();
}

/**
 * Save hashtags data from acccounts
 */
function getHashtagDataFromAccounts ($ini = 0, $end = 10)
{
	$accounts = getAccountIDs();

	$tw = new Twitter();

	if (VERBOSE) echo "Getting hashtags data from accounts:\n";

	$i = 0;

	foreach ($accounts as $account) {

		if ($i >= $ini && $i < $end) {
			if (VERBOSE) echo "Account $account.\n";
			$tw->saveHashtagsDataFromAccount($account);
		}

		$i+=1;

	}

	markHashtagAnalysisFromAccountsAsFinished();

}

/**
 * Save hashtags data from Twitter in general
 */
function getHashtagDataFromTW ()
{
	$hashtags = getHashtags(1);

	$tw = new Twitter();

	if (VERBOSE) echo "Getting hashtags data from Twitter in general:\n-\n";

	$tw->searches = 0;

	foreach ($hashtags as $hashtag) {

		if ($tw->searches <= 178) {
			if (VERBOSE) echo "Hashtag $hashtag. ";
			if ($tw->search($hashtag)){
				if (VERBOSE) echo "Searches done: $tw->searches searches.\n";
			} else {
				if (VERBOSE) echo "Searches done: $tw->searches searches.\n";
				break;
			}
			
		}

	}

	$numHashtagsUnfinished = getNumHashtagsUnfinished(1);

	if ($numHashtagsUnfinished !== false) {
		if (VERBOSE) echo "\n" . $numHashtagsUnfinished . " hashtags left.\n";
	}

}