<?php

Class Hashtag {

	public $id;
	public $name;

	function __construct($name)
	{
		$this->name = utf8_decode($name);
		if (!$this->save()) {
			$this->getID();
		}
	}

	function getID ()
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		$sql = "SELECT id FROM " . SQL_TABLE_HASHTAGS . " WHERE name = '$this->name'";
		$result = $conn->query($sql);

		$conn->close();

		if ($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$this->id = $row["id"];
			return true;
		}

		return false;
	}

	function isSaved ()
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		$sql = "SELECT * FROM " . SQL_TABLE_HASHTAGS . " WHERE name = '$this->name'";
		$result = $conn->query($sql);

		$conn->close();

		if ($result->num_rows > 0) {
			return true;
		}

		return false;
	}

	function save ()
	{
		if (!$this->isSaved()) {

			if (VERBOSE) echo "Creating hashtag $this->name.\n";

			// Create connection
			$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
			// Check connection
			if ($conn->connect_error) {
			  die("Connection failed: " . $conn->connect_error);
			}

			// Save hashtag

			if( $conn->query("INSERT INTO " . SQL_TABLE_HASHTAGS . " (name) VALUES ('" . $this->name . "')") ){
			} else {
				echo "Error inserting $this->name. Message: $conn->error\n";
			}

			$this->id = $conn->insert_id;

			$analysisDate = new DateTime('now');
			$analysisDate->modify('+1 Hour');

			// Initialize analysis

			if( $conn->query("INSERT INTO " . SQL_TABLE_ANALYSIS . " (hashtag_id, date, scope, time) VALUES ('" . $this->id . "', '" . $analysisDate->format('Y-m-d H:i:s') . "', '0', '0')") ){
			} else {
				echo "Error inserting $this->name. Message: $conn->error\n";
			}

			if( $conn->query("INSERT INTO " . SQL_TABLE_ANALYSIS . " (hashtag_id, date, scope, time) VALUES ('" . $this->id . "', '" . $analysisDate->format('Y-m-d H:i:s') . "', '0', '1')") ){
			} else {
				echo "Error inserting $this->name. Message: $conn->error\n";
			}

			if( $conn->query("INSERT INTO " . SQL_TABLE_ANALYSIS . " (hashtag_id, date, scope, time) VALUES ('" . $this->id . "', '" . $analysisDate->format('Y-m-d H:i:s') . "', '1', '0')") ){
			} else {
				echo "Error inserting $this->name. Message: $conn->error\n";
			}

			if( $conn->query("INSERT INTO " . SQL_TABLE_ANALYSIS . " (hashtag_id, date, scope, time) VALUES ('" . $this->id . "', '" . $analysisDate->format('Y-m-d H:i:s') . "', '1', '1')") ){
			} else {
				echo "Error inserting $this->name. Message: $conn->error\n";
			}

			$conn->close();

			return true;

		}

		return false;
	}

	function addStatusData ($status, $scope)
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		// Update Analysis data

		$user_age_total = 0;
		$user_created_at = new DateTime( $status->user->created_at );
		$user_created_at->modify('+1 Hour');
		$user_age = $user_created_at->diff(new DateTime());
		$user_age_total += $user_age->days;

		$url_inclusion = 0;
		if (count($status->entities->urls)>0) {
			$url_inclusion = 1;
		}


		if( $conn->query("UPDATE " . SQL_TABLE_ANALYSIS . " SET num_tweets = num_tweets+1,  retweet_count = retweet_count+$status->retweet_count, favorite_count = favorite_count+$status->favorite_count, user_num_followers = user_num_followers+".$status->user->followers_count.", user_num_tweets = user_num_tweets+".$status->user->statuses_count.", user_age = user_age+$user_age_total, url_inclusion = url_inclusion+$url_inclusion WHERE hashtag_id = $this->id AND scope = $scope AND time = " . TIME) ){
		} else {
			echo "Error inserting $this->name. Message: $conn->error\n";
		}

		$conn->close();
	}

	function markAsFinished ($scope)
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		if( $conn->query("UPDATE " . SQL_TABLE_ANALYSIS . " SET finished = 1 WHERE hashtag_id = $this->id AND scope = $scope AND time = " . TIME) ){
		} else {
			echo "Error inserting $this->name. Message: $conn->error\n";
		}

		$conn->close();
	}

	function getAnalysisData ($scope)
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		$sql = "SELECT * FROM " . SQL_TABLE_ANALYSIS . " WHERE hashtag_id = '$this->id' AND scope = $scope AND time = " . TIME;
		$result = $conn->query($sql);

		$conn->close();

		if ($result->num_rows > 0) {
			return $result->fetch_assoc();
		}

		return false;
	}

	function updateAnalysisData ($data, $scope)
	{
		// Create connection
		$conn = new mysqli(SQL_SERVERNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
		// Check connection
		if ($conn->connect_error) {
		  die("Connection failed: " . $conn->connect_error);
		}

		if (VERBOSE) echo "Updating data of hashtag $this->name.\n";

		if( $conn->query("UPDATE " . SQL_TABLE_ANALYSIS . " SET num_tweets = $data[num_tweets],  retweet_count = $data[retweet_count], favorite_count = $data[favorite_count], user_num_followers = $data[user_num_followers], user_num_tweets = $data[user_num_tweets], user_age = $data[user_age], url_inclusion = $data[url_inclusion] WHERE hashtag_id = $this->id AND scope = $scope AND time = " . TIME) ){
			return true;
		} else {
			echo "Error inserting $this->name. Message: $conn->error\n";
			return false;
		}

		$conn->close();
	}

}