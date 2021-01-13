<?php

use Abraham\TwitterOAuth\TwitterOAuth;

require_once __DIR__ . '/hashtag.php';

Class Twitter {

	public $connection;
	public $searches;

	function __construct(){
		$this->connection = NULL;
	}

	/**
	 * Connect to Twitter API.
	 *
	 * @return True if it is connected and False if it is not
	 */
	function connect(){

		if (!$this->connection) {
			$this->connection = new TwitterOAuth(TW_CONSUMER_KEY, TW_CONSUMER_SECRET, TW_ACCESS_TOKEN, TW_ACCESS_TOKEN_SECRET);
		}

		$info = $this->connection->get("account/verify_credentials");

		if ($info){
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Connect to Twitter API and retrieve ID from the most recent tweet
	 *
	 * @return The most recent tweet's ID or false otherwise
	 */
	function getMostRecentStatusIDFromTimeLine($TWUserID)
	{
		if ($this->connect()) {
			$statuses = $this->connection->get("statuses/user_timeline", [
				"user_id" => $TWUserID,
				"count" => 1
			]);

			if (!is_object($statuses) && $statuses) {
				return $statuses[0]->id;
			} else {
				if (is_object($statuses) && isset($statuses->errors)){
					echo "Account $TWUserID has returned an error.\n";
					var_dump($statuses);
					echo "\n";
				}
			}
		}
		return false;
	}

	/**
	 * Connect to Twitter API and retrieve ID from the most recent tweet
	 *
	 * @return The most recent tweet's ID or false otherwise
	 */
	function getMostRecentStatusIDFromSearch($query)
	{
		if ($this->connect()) {
			$result = $this->connection->get("search/tweets", [
				"q" => $query,
				"lang" => "es",
				"count" => 1
			]);

			$this->searches += 1;

			if (isset($result->statuses) && !is_object($result->statuses) && $result->statuses) {
				return $result->statuses[0]->id;
			} else {
				if (isset($result->statuses) && is_object($result->statuses) && isset($result->statuses->errors)){
					echo "Query $query has returned an error.\n";
					var_dump($result->statuses);
					echo "\n";
				} else {
					if (!isset($result->statuses)) {
						echo "Query $query has returned an error.\n";
						var_dump($result);
						echo "\n";
					} else {
						return 0;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Retrieve array with the 3200 most recent tweets with hashtags
	 *
	 * @return Array with statuses
	 */
	function getTimeLine($TWUserID)
	{
		if ($this->connect()) {

			$statusesQuery = array();
			$mostRecentStatusID = $this->getMostRecentStatusIDFromTimeLine($TWUserID);
			$lastStatusID = $mostRecentStatusID;
			$preLastStatusID = 0;

			while ($lastStatusID && $lastStatusID != $preLastStatusID) {

				$statuses = $this->connection->get("statuses/user_timeline", [
					"user_id" => $TWUserID,
					"count" => 200,
					"max_id" => $lastStatusID,
					"exclude_replies" => "true",
					"include_rts" => "false"
				]);

				if ($statuses) {

					// If it's not the first iteration
					if ($lastStatusID != $mostRecentStatusID) {
						// Remove the fist element, already processed in the last iteration
						array_shift($statuses);
					}

					$i = 0;

					// Remove items that are not necessary
					foreach ($statuses as $status) {

						if (count($status->entities->hashtags)==0){
							array_splice($statuses, $i, 1);
							$i-=1;
						}

						$i+=1;
						
					}

					$statusesQuery = array_merge($statusesQuery, $statuses);

					$preLastStatusID = $lastStatusID;

					if (count($statuses)>0) {
						$lastStatusID = $statuses[count($statuses)-1]->id;
					} else {
						$lastStatusID = 0;
					}
					

				} else {
					break;
				}

			}

			return $statusesQuery;

		}

		return false;
	}

	/**
	 * Retrieve hashtags data and save it at the database
	 *
	 * @return true if it is done correctly and false otherwise
	 */
	function saveHashtagsDataFromAccount($TWUserID)
	{
		if ($this->connect()) {

			$statusesQuery = array();
			$mostRecentStatusID = $this->getMostRecentStatusIDFromTimeLine($TWUserID);
			$lastStatusID = $mostRecentStatusID;
			$preLastStatusID = 0;

			while ($lastStatusID && $lastStatusID != $preLastStatusID) {

				$statuses = $this->connection->get("statuses/user_timeline", [
					"user_id" => $TWUserID,
					"count" => 200,
					"max_id" => $lastStatusID,
					"exclude_replies" => "true",
					"include_rts" => "false"
				]);

				if (isset($statuses)) {

					// If it's not the first iteration
					if ($lastStatusID != $mostRecentStatusID) {
						// Remove the fist element, already processed in the last iteration
						array_shift($statuses);
					}

					foreach ($statuses as $status) {

						if (count($status->entities->hashtags)){
							foreach ($status->entities->hashtags as $hashtagData) {
								if (VERBOSE) echo "Adding data to the hashtag $hashtagData->text\n";
								$hashtag = new Hashtag(strtolower($hashtagData->text));
								$hashtag->addStatusData($status, 0);
							}
						}
						
					}

					$preLastStatusID = $lastStatusID;

					if (count($statuses)>0) {
						$lastStatusID = $statuses[count($statuses)-1]->id;
					} else {
						$lastStatusID = 0;
					}
					

				} else {
					break;
				}

			}

			return true;

		}

		return false;
	}

	/**
	 * Connect to Twitter API and retrieve data from a search query
	 *
	 * @param Query to search
	 * @return Array of statuses from the search query or false if it does not work
	 */
	function search($query)
	{
		if ($this->connect()) {

			$searchesBefore = $this->searches;

			$hashtag = new Hashtag($query);

			$query = '#' . $query;
			
			$mostRecentStatusID = $this->getMostRecentStatusIDFromSearch($query);

			if ($mostRecentStatusID) {

				$lastStatusID = $mostRecentStatusID;
				$preLastStatusID = 0;

				$hashtagDataBefore = $hashtag->getAnalysisData(1);

				while ($lastStatusID && $lastStatusID != $preLastStatusID) {

					$result = $this->connection->get("search/tweets", [
						"q" => $query,
						"lang" => "es",
						"count" => 100,
						"max_id" => $lastStatusID
					]);

					$this->searches += 1;

					if (isset($result->statuses)) {
						
						if (VERBOSE) echo "Adding general data to the hashtag $query.\n";

						// If it's not the first iteration
						if ($lastStatusID != $mostRecentStatusID) {
							// Remove the fist element, already processed in the last iteration
							array_shift($result->statuses);
						}

						foreach ($result->statuses as $status) {
							$hashtag->addStatusData($status, 1);
						}

						$preLastStatusID = $lastStatusID;
						
						if (count($result->statuses)>1) {
							$lastStatusID = $result->statuses[count($result->statuses)-1]->id;
						} else {
							$hashtag->markAsFinished(1);
							$lastStatusID = 0;
						}

					} else {
						if ($searchesBefore == 0) {
							if (VERBOSE) echo "Hashtag has got all the rate for itself. Finished.\n";
							$hashtag->markAsFinished(1);
						} else {
							if (VERBOSE) echo "Rate limit exceeded before finishing. Falling back to the last state.\n";
							$hashtag->updateAnalysisData($hashtagDataBefore, 1);
							return false;
						}
						break;
					}

				}

				
			} else {
				if ($mostRecentStatusID === 0) {
					$hashtag->markAsFinished(1);
				}
			}

			return true;

		}
		return false;
	}

	/**
	 * Connect to Twitter API and retrieve data from a user lookup query
	 *
	 * @param List of comma separated user nicenames
	 * @return Array of User objects from the user lookup query or false if it does not work
	 */
	function usersLookUp($accounts)
	{
		if ($this->connect()) {
			$users = $this->connection->get("users/lookup", [
				"screen_name" => $accounts
			]);

			if ($users) {
				return $users;
			}
		}

		return false;
	}

}