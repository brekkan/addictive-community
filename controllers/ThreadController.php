<?php

## -------------------------------------------------------
#  ADDICTIVE COMMUNITY
## -------------------------------------------------------
#  Created by Brunno Pleffken Hosti
#  http://github.com/brunnopleffken/addictive-community
#
#  File: Thread.php
#  License: GPLv2
#  Copyright: (c) 2016 - Addictive Community
## -------------------------------------------------------

namespace AC\Controllers;

use \AC\Kernel\Database;
use \AC\Kernel\Html;
use \AC\Kernel\Http;
use \AC\Kernel\i18n;
use \AC\Kernel\Session\SessionState;
use \AC\Kernel\Text;
use \AC\Kernel\Upload;

class Thread extends Application
{
	// Thread ID
	private $thread_id = 0;

	// Thread information
	private $thread_info = array();

	// Member ranks
	private $ranks = array();

	/**
	 * --------------------------------------------------------------------
	 * SHOW THREAD
	 * --------------------------------------------------------------------
	 */
	public function index()
	{
		$this->thread_id = Http::request("id", true);

		// Check if $this->thread_id exists and is a number
		if(!$this->thread_id) {
			$this->Core->redirect("500");
		}

		// Define messages
		$message_id = Http::request("m", true);
		$notification = ["",
			Html::notification(i18n::translate("T_MESSAGE_1"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_2"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_3"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_4"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_5"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_6"), "success"),
			Html::notification(i18n::translate("T_MESSAGE_7"), "success")
        ];

		// Get thread information
		$this->thread_info = $this->getThreadInfo();

		// Define notification if the thread has a locking date
		$has_date_notification = null;

		if(SessionState::isAdmin() && $this->thread_info['lock_date'] > time()) {
			$formatted_date = $this->Core->dateFormat($this->thread_info['lock_date']);
			$has_date_notification = Html::notification(
				"This thread will be locked in <b>" . $formatted_date . "</b>", "warning", true
			);
		}

		if(SessionState::isAdmin() && $this->thread_info['start_date'] > time()) {
			$formatted_date = $this->Core->dateFormat($this->thread_info['start_date']);
			$has_date_notification = Html::notification(
				"This thread is invisible and will be opened in <b>" . $formatted_date . "</b>", "info", true
			);
		}

		// Check and update cookie for read/unread threads
		$this->checkUnread();

		// Update session table with room ID
		$this->updateSessionTable();

		// Avoid page navigation from incrementing visit counter
		$_SERVER['HTTP_REFERER'] = (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : false;
		if(!strstr($_SERVER['HTTP_REFERER'], "thread")) {
			Database::update("c_threads", "views = views + 1", "t_id = '{$this->thread_id}'");
		}

		// Get first post
		$first_post_info = $this->getFirstPost();

		// Get replies
		$pages = $this->getPages();
		$replies = $this->getReplies($pages);

		// Get related threads
		$related_thread_list = $this->relatedThreads();

		// Page info
		$page_info['title'] = $this->thread_info['title'];
		$page_info['bc'] = [$this->thread_info['name'], $this->thread_info['title']];
		$this->Set("page_info", $page_info);

		$this->Set("thread_id", $this->thread_id);
		$this->Set("thread_info", $this->thread_info);
		$this->Set("url", urlencode($this->Core->canonicalTag($this->thread_id)));
		$this->Set("notification", $notification[$message_id]);
		$this->Set("has_date_notification", $has_date_notification);
		$this->Set("enable_signature", $this->Core->config['general_member_enable_signature']);
		$this->Set("first_post_info", $first_post_info);
		$this->Set("reply", $replies);
		$this->Set("pages", $pages);
		$this->Set("related_thread_list", $related_thread_list);
		$this->Set("is_member", SessionState::isMember());
		$this->Set("is_moderator", $this->isModerator($this->thread_info['moderators']));
	}

	/**
	 * --------------------------------------------------------------------
	 * REPLY THREAD
	 * --------------------------------------------------------------------
	 */
	public function reply($id)
	{
		// Do not allow guests
		SessionState::noGuest();

		// Get thread info
		Database::query("SELECT t.t_id, t.title, t.lock_date, t.locked, r.r_id, r.name, r.upload FROM c_threads t
				INNER JOIN c_rooms r ON (t.room_id = r.r_id) WHERE t_id = {$id};");
		$thread_info = Database::fetch();

		// Check if thread is locked
		if($thread_info['locked'] == 1 || ($thread_info['lock_date'] != 0 && $thread_info['lock_date'] < time())) {
			$this->Core->redirect("failure?t=thread_locked");
		}

		// If member is replying another post (quote)
		if(Http::request("quote", true)) {
			$quote_post_id = Http::request("quote", true);
			Database::query("SELECT p_id, post FROM c_posts WHERE p_id = {$quote_post_id};");
			$quote = Database::fetch();
		}
		else {
			$quote = [];
		}

		// Page info
		$page_info['title'] = i18n::translate("P_ADD_REPLY") . ": " . $thread_info['title'];
		$page_info['bc'] = [$thread_info['name'], i18n::translate("P_ADD_REPLY") . ": " . $thread_info['title']];
		$this->Set("page_info", $page_info);

		// Return variables
		$this->Set("thread_id", $id);
		$this->Set("thread_info", $thread_info);
		$this->Set("quote", $quote);
		$this->Set("allow_uploads", $thread_info['upload']);
		$this->Set("attachment_size_warning",
			i18n::translate("P_ATTACHMENTS_MAX_SIZE", [$this->Core->config['general_max_attachment_size'] . "MB"])
		);
	}

	/**
	 * --------------------------------------------------------------------
	 * ADD NEW THREAD
	 * --------------------------------------------------------------------
	 */
	public function add($room_id)
	{
		// Do not allow guests
		SessionState::noGuest();

		Database::query("SELECT r_id, name, upload, read_only, moderators FROM c_rooms WHERE r_id = {$room_id};");
		$room_info = Database::fetch();

		// If room is set as 'Read Only', redirect back to the list of threads
		if($room_info['read_only'] == 1) {
			$this->Core->redirect("room/" . $room_id);
		}

		// Page info
		$page_info['title'] = i18n::translate("P_NEW_THREAD") . ": " . $room_info['name'];
		$page_info['bc'] = array($room_info['name'], i18n::translate("P_NEW_THREAD"));
		$this->Set("page_info", $page_info);

		// Return variables
		$this->Set("room_info", $room_info);
		$this->Set("allow_uploads", $room_info['upload']);
		$this->Set("is_moderator", $this->isModerator($room_info['moderators']));
		$this->Set("is_poll", Http::request("poll"));
		$this->Set("thread_auto_details", i18n::translate("T_AUTO_DETAILS", $this->Core->config['date_default_offset']));
	}

	/**
	 * --------------------------------------------------------------------
	 * EDIT AN EXISTING POST
	 * --------------------------------------------------------------------
	 */
	public function edit($post_id)
	{
		// Don't allow guests
		SessionState::noGuest();

		// Get post info
		Database::query("SELECT * FROM c_posts WHERE p_id = {$post_id};");
		$post_info = Database::fetch();

		// If the author isn't the user currently logged in
		// check if is an administrator
		if(SessionState::$user_data['m_id'] != $post_info['author_id'] && !SessionState::isAdmin()) {
			Html::throwError("You cannot edit a post that you did not publish.");
		}

		// Get thread info
		Database::query("SELECT title FROM c_threads WHERE t_id = {$post_info['thread_id']};");
		$thread_info = Database::fetch();

		// Return variables
		$this->Set("thread_info", $thread_info);
		$this->Set("post_info", $post_info);
	}

	/**
	 * --------------------------------------------------------------------
	 * INSERT NEW REPLY INTO DATABASE
	 * --------------------------------------------------------------------
	 */
	public function saveReply($id)
	{
		$this->layout = false;

		// Get room ID
		$room_id = Http::request("room_id", true);

		// Format new post array
		$post = [
			"author_id"     => SessionState::$user_data['m_id'],
			"thread_id"     => Http::request("id", true),
			"post_date"     => time(),
			"ip_address"    => $_SERVER['REMOTE_ADDR'],
			"post"          => str_replace("'", "&apos;", $_POST['post']),
			"quote_post_id" => (Http::request("quote_post_id", true)) ? Http::request("quote_post_id", true) : 0,
			"best_answer"   => 0,
			"first_post"    => 0
		];

		// Send attachments
		if(Http::getFile("attachment")) {
			$Upload = new Upload();
			$post['attach_id'] = $Upload->sendAttachment(Http::getFile("attachment"), $post['author_id']);
		}

		// Insert new post into DB
		Database::insert("c_posts", $post);

		// Update: thread stats
		Database::update("c_threads", [
			"replies = replies + 1",
			"last_post_date = '{$post['post_date']}'",
			"last_post_member_id = '{$post['author_id']}'"
		], "t_id = '{$post['thread_id']}'");

		// Update: room stats
		Database::update("c_rooms", [
			"last_post_date = '{$post['post_date']}'",
			"last_post_thread = '{$post['thread_id']}'",
			"last_post_member = '{$post['author_id']}'"
		], "r_id = '{$room_id}'");

		// Update: member stats
		Database::update("c_members", [
			"posts = posts + 1",
			"last_post_date = '{$post['post_date']}'"
		], "m_id = '{$post['author_id']}'");

		// Update: community stats
		Database::update("c_stats", "post_count = post_count + 1");

		// Redirect back to post
		$this->Core->redirect("thread/" . $id);
	}

	/**
	 * --------------------------------------------------------------------
	 * CREATE A NEW THREAD
	 * --------------------------------------------------------------------
	 */
	public function saveThread()
	{
		$this->layout = false;

		// If we're adding a poll, build poll array
		if(Http::request("poll_question")) {
			// Transform list of choices in an array
			$replies = [];
			$questions = explode("\r\n", trim(Http::request("poll_choices")));
			$questions = array_filter($questions, "trim");

			 // For each question, add a corresponding number of votes...
			 // ...in this case: zero!
			for($i = 0; $i < count($questions); $i++) {
				$replies[] = 0;
			}

			// Put everything together
			$poll_data = [
				"questions" => $questions,
				"replies"   => $replies,
				"voters"    => []
			];

			// Serialize poll data array into JSON
			$poll_data = json_encode($poll_data, JSON_UNESCAPED_UNICODE);
		}
		else {
			$poll_data = "";
		}

		// Set the opening date
		if(Http::request("open_day") != "" || Http::request("open_month") != "" || Http::request("open_year") != "") {
			$open_time = mktime(
				Http::request("open_hours"),
				Http::request("open_minutes"), 0,
				Http::request("open_month"),
				Http::request("open_day"),
				Http::request("open_year")
			);

			// Convert custom date to UTC (a.k.a. remove timezone offset)
			// All dates in Addictive Community are treated in the back-end as UTC
			$open_time = $open_time - ($this->Core->config['date_default_offset'] * 3600);

			// Check if the opening date is equal or later than the current timestamp
			if($open_time < time()) {
				Html::throwError("Open a new thread can't be retroactive.");
			}
		}
		else {
			$open_time = time();
		}

		// Set the lock date
		if(Http::request("lock_day") != "" || Http::request("lock_month") != "" || Http::request("lock_year") != "") {
			$lock_time = mktime(
				Http::request("lock_hours"),
				Http::request("lock_minutes"), 0,
				Http::request("lock_month"),
				Http::request("lock_day"),
				Http::request("lock_year")
			);

			// Convert custom date to UTC (a.k.a. remove timezone offset)
			// All dates in Addictive Community are treated in the back-end as UTC
			$lock_time = $lock_time - ($this->Core->config['date_default_offset'] * 3600);
		}
		else {
			$lock_time = 0;
		}

		// Insert new thread item
		$thread = [
			"title"                => Http::request("title"),
			"slug"                 => Text::slug(htmlspecialchars_decode(Http::request("title"), ENT_QUOTES)),
			"author_member_id"     => SessionState::$user_data['m_id'],
			"replies"              => 1,
			"views"                => 0,
			"start_date"           => $open_time,
			"lock_date"            => $lock_time,
			"room_id"              => Http::request("room_id", true),
			"announcement"         => Http::request("announcement", true) ? Http::request("announcement") : 0,
			"last_post_date"       => time(),
			"last_post_member_id"  => SessionState::$user_data['m_id'],
			"locked"               => Http::request("locked", true) ? Http::request("announcement") : 0,
			"approved"             => 1,
			"with_best_answer"     => 0,
			"poll_question"        => Http::request("poll_question"),
			"poll_data"            => $poll_data,
			"poll_allow_multiple"  => (isset($_POST['poll_allow_multiple'])) ? 1 : 0
		];
		Database::insert("c_threads", $thread);

		// Insert first post
		$post = [
			"author_id"   => SessionState::$user_data['m_id'],
			"thread_id"   => Database::getLastId(),
			"post_date"   => $thread['last_post_date'],
			"ip_address"  => $_SERVER['REMOTE_ADDR'],
			"post"        => str_replace("'", "&apos;", $_POST['post']),
			"best_answer" => 0,
			"first_post"  => 1
		];

		// Send attachments
		if(Http::getFile("attachment")) {
			$Upload = new Upload();
			$post['attach_id'] = $Upload->sendAttachment(Http::getFile("attachment"), $post['author_id']);
		}

		Database::insert("c_posts", $post);

		// Update tables

		Database::update("c_rooms", [
			"last_post_date = '{$post['post_date']}'",
			"last_post_thread = '{$post['thread_id']}'",
			"last_post_member = '{$post['author_id']}'"
		], "r_id = '{$thread['room_id']}'");

		Database::update("c_stats", [
			"post_count = post_count + 1",
			"thread_count = thread_count + 1"
		]);

		Database::update("c_members", [
			"posts = posts + 1",
			"last_post_date = '{$post['post_date']}'"
		], "m_id = '{$post['author_id']}'");

		// Redirect
		$this->Core->redirect("thread/" . $post['thread_id'] . "-" . $thread['slug']);
	}

	/**
	 * --------------------------------------------------------------------
	 * SAVE EDITED POST
	 * --------------------------------------------------------------------
	 */
	public function saveEdit($post_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Get author ID from database for security reasons
		Database::query("SELECT author_id FROM c_posts WHERE p_id = {$post_id};");
		$post_info = Database::fetch();

		// If the author isn't the user currently logged in
		// check if is an administrator
		if(SessionState::$user_data['m_id'] != $post_info['author_id'] && !SessionState::isAdmin()) {
			Html::throwError("You cannot edit a post that you did not publish.");
		}

		$post = [
			"post"        => $_REQUEST['post'],
			"edit_time"   => time(),
			"edit_author" => SessionState::$user_data['m_id']
		];

		// Insert edited post on DB
		Database::update("c_posts", $post, "p_id = {$post_id}");

		// Redirect
		$this->Core->redirect("thread/" . Http::request("thread_id", true) . "#post-" . $post_id);
	}

	/**
	 * --------------------------------------------------------------------
	 * DELETE A POST
	 * --------------------------------------------------------------------
	 */
	public function deletePost()
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Get post information
		$author_id = Http::request("mid", true);
		$thread_id = Http::request("tid", true);
		$post_id = Http::request("pid", true);

		// If the author isn't the user currently logged in
		// check if is an administrator
		if(SessionState::$user_data['m_id'] != $author_id && !SessionState::isAdmin()) {
			Html::throwError("You cannot delete a post that you did not publish.");
		}

		// Remove post
		Database::delete("c_posts", "p_id = {$post_id}");

		// Update thread statistics
		Database::update("c_threads", "replies = replies - 1", "t_id = {$thread_id}");

		// Update member statistics
		Database::update("c_members", "posts = posts - 1", "m_id = {$author_id}");

		// Update community statistics
		Database::update("c_stats", "post_count = post_count - 1");

		// Redirect back to post
		$this->Core->redirect("thread/" . $thread_id . "?m=3");
	}

	/**
	 * --------------------------------------------------------------------
	 * MODERATION OPTIONS: LOCK THREAD
	 * --------------------------------------------------------------------
	 */
	public function lock($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Do not allow unauthorized members
		if(!$this->isModeratorFromThreadId($thread_id)) {
			Html::throwError("You're not allowed to perform this action.");
		}

		// Lock thread
		Database::update("c_threads", "locked = 1", "t_id = {$thread_id}");

		// Register Moderation log in DB
		$log = [
			"member_id"  => SessionState::$user_data['m_id'],
			"time"       => time(),
			"act"        => "Locked thread: ID #" . $thread_id,
			"ip_address" => $_SERVER['REMOTE_ADDR']
		];
		Database::insert("c_logs", $log);

		// Redirect
		header("Location: " . preg_replace("/(\?m=[0-9])/", "", $_SERVER['HTTP_REFERER']) . "?m=4");
	}

	/**
	 * --------------------------------------------------------------------
	 * MODERATION OPTIONS: UNLOCK THREAD
	 * --------------------------------------------------------------------
	 */
	public function unlock($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Do not allow unauthorized members
		if(!$this->isModeratorFromThreadId($thread_id)) {
			Html::throwError("You're not allowed to perform this action.");
		}

		// Lock thread
		Database::update("c_threads", "locked = 0", "t_id = {$thread_id}");

		// Register Moderation log in DB
		$log = [
			"member_id"  => SessionState::$user_data['m_id'],
			"time"       => time(),
			"act"        => "Unlocked thread: ID #" . $thread_id,
			"ip_address" => $_SERVER['REMOTE_ADDR']
		];

		Database::insert("c_logs", $log);

		// Redirect
		header("Location: " . preg_replace("/(\?m=[0-9])/", "", $_SERVER['HTTP_REFERER']) . "?m=5");
	}

	/**
	 * --------------------------------------------------------------------
	 * MODERATION OPTIONS: SET THREAD AS ANNOUNCEMENT
	 * --------------------------------------------------------------------
	 */
	public function announcementSet($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Do not allow unauthorized members
		if(!$this->isModeratorFromThreadId($thread_id)) {
			Html::throwError("You're not allowed to perform this action.");
		}

		// Lock thread
		Database::update("c_threads", "announcement = 1", "t_id = {$thread_id}");

		// Register Moderation log in DB
		$log = [
			"member_id"  => SessionState::$user_data['m_id'],
			"time"       => time(),
			"act"        => "Set thread ID #" . $thread_id . " as announcement",
			"ip_address" => $_SERVER['REMOTE_ADDR']
		];
		Database::insert("c_logs", $log);

		// Redirect
		header("Location: " . preg_replace("/(\?m=[0-9])/", "", $_SERVER['HTTP_REFERER']) . "?m=6");
	}

	/**
	 * --------------------------------------------------------------------
	 * MODERATION OPTIONS: REMOVE THREAD AS ANNOUNCEMENT
	 * --------------------------------------------------------------------
	 */
	public function announcementUnset($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Do not allow unauthorized members
		if(!$this->isModeratorFromThreadId($thread_id)) {
			Html::throwError("You're not allowed to perform this action.");
		}

		// Lock thread
		Database::update("c_threads", "announcement = 0", "t_id = {$thread_id}");

		// Register Moderation log in DB
		$log = [
			"member_id"  => SessionState::$user_data['m_id'],
			"time"       => time(),
			"act"        => "Removed thread ID #" . $thread_id . " as announcement",
			"ip_address" => $_SERVER['REMOTE_ADDR']
		];
		Database::insert("c_logs", $log);

		// Redirect
		header("Location: " . preg_replace("/(\?m=[0-9])/", "", $_SERVER['HTTP_REFERER']) . "?m=7");
	}

	/**
	 * --------------------------------------------------------------------
	 * MODERATION OPTIONS: COMPLETELY REMOVE THREAD AND ITS CONTENT
	 * --------------------------------------------------------------------
	 */
	public function delete($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Do not allow unauthorized members
		if(!$this->isModeratorFromThreadId($thread_id)) {
			Html::throwError("You're not allowed to perform this action.");
		}

		// Get room ID
		Database::query("SELECT room_id FROM c_threads WHERE t_id = {$thread_id};");
		$thread_info = Database::fetch(); // $thread_info['room_id']
		$room_id = $thread_info['room_id'];

		// Delete all posts in this thread
		Database::delete("c_posts", "thread_id = {$thread_id}");
		$deleted_posts = Database::affectedRows();

		// Delete thread itself
		Database::delete("c_threads", "t_id = {$thread_id}");

		// Update community/room statistics
		Database::update("c_stats", [
			"thread_count = thread_count - 1",
			"post_count = post_count - {$deleted_posts}"
		]);

		// Register Moderation log in DB
		$log = [
			"member_id"  => SessionState::$user_data['m_id'],
			"time"       => time(),
			"act"        => "Deleted thread: ID #" . $thread_id,
			"ip_address" => $_SERVER['REMOTE_ADDR']
		];
		Database::insert("c_logs", $log);

		// Redirect
		$this->Core->redirect("room/" . $room_id . "?m=7");
	}

	/**
	 * --------------------------------------------------------------------
	 * Set an answer as best answer
	 * --------------------------------------------------------------------
	 */
	public function setBestAnswer($reply_id)
	{
		$this->layout = false;

		// Check if the member logged in is the author of the thread
		// This will protect the script from ill-intentioned people
		Database::query("SELECT thread_id,
				(SELECT author_member_id
					FROM c_threads
					WHERE c_threads.t_id = c_posts.thread_id)
				AS thread_author
				FROM c_posts WHERE p_id = 9;");

		$thread = Database::fetch();

		if(SessionState::$user_data['m_id'] == $thread['thread_author']) {
			Database::update("c_posts", "best_answer = 1", "p_id = {$reply_id}");
			Database::update("c_threads", "with_best_answer = 1", "t_id = {$thread['thread_id']}");
			$this->Core->redirect("HTTP_REFERER");
		}
		else {
			$this->Core->redirect("HTTP_REFERER");
		}
	}

	/**
	 * --------------------------------------------------------------------
	 * Set an answer as best answer
	 * --------------------------------------------------------------------
	 */
	public function unsetBestAnswer($reply_id)
	{
		$this->layout = false;

		// Check if the member logged in is the author of the thread
		// This will protect the script from ill-intentioned people
		Database::query("SELECT thread_id,
				(SELECT author_member_id
					FROM c_threads
					WHERE c_threads.t_id = c_posts.thread_id)
				AS thread_author
				FROM c_posts WHERE p_id = 9;");

		$thread = Database::fetch();

		if(SessionState::$user_data['m_id'] == $thread['thread_author']) {
			Database::update("c_posts", "best_answer = 0", "p_id = {$reply_id}");
			Database::update("c_threads", "with_best_answer = 0", "t_id = {$thread['thread_id']}");
			$this->Core->redirect("HTTP_REFERER");
		}
		else {
			$this->Core->redirect("HTTP_REFERER");
		}
	}

	/**
	 * --------------------------------------------------------------------
	 * SAVE POLL VOTES
	 * --------------------------------------------------------------------
	 */
	public function savePoll($thread_id)
	{
		$this->layout = false;

		// Do not allow guests
		SessionState::noGuest();

		// Get updated poll info in DB
		Database::query("SELECT poll_data, poll_allow_multiple FROM c_threads WHERE t_id = {$thread_id};");
		$thread_info = Database::fetch();
		$poll_data = json_decode($thread_info['poll_data'], true);

		if($thread_info['poll_allow_multiple'] == 0) {
			// Increase vote count
			$poll_data['replies'][Http::request("chosen_option")] += 1;

			// Add member ID to voters list
			array_push($poll_data['voters'], SessionState::$user_data['m_id']);
		}
		else {
			// If poll allows multiple choice
			foreach(Http::request("chosen_option") as $chosen_option) {
				$poll_data['replies'][$chosen_option] += 1;
			}

			// Add member ID to voters list
			array_push($poll_data['voters'], SessionState::$user_data['m_id']);
		}

		// Update thread information with encoded data
		$encoded = json_encode($poll_data);
		Database::update("c_threads", "poll_data = '{$encoded}'", "t_id = {$thread_id}");

		// Redirect
		$this->Core->redirect("HTTP_REFERER");
	}

	/**
	 * --------------------------------------------------------------------
	 * CHECK IF THREAD IS UNREAD. IF TRUE, ADD TO COOKIE ARRAY
	 * --------------------------------------------------------------------
	 */
	private function checkUnread()
	{
		$read_threads_cookie = SessionState::getCookie("read_threads");
		if($read_threads_cookie) {
			$login_time_cookie = SessionState::getCookie("login_time");
			$read_threads = json_decode(html_entity_decode($read_threads_cookie), true);
			if(!in_array($this->thread_info['t_id'], $read_threads) && $login_time_cookie < $this->thread_info['last_post_date']) {
				array_push($read_threads, $this->thread_info['t_id']);
			}

			$read_threads_cookie = json_encode($read_threads);
			SessionState::createCookie("read_threads", $read_threads_cookie);
		}
	}

	/**
	 * --------------------------------------------------------------------
	 * GET GENERAL THREAD INFORMATION, LIKE NUMBER OF REPLIES, CHECK IF
	 * IT'S AN OBSOLETE THREAD AND PERMISSIONS
	 * --------------------------------------------------------------------
	 */
	private function getThreadInfo()
	{
		// Select thread info from database
		$thread = Database::query("SELECT t.t_id, t.title, t.slug, t.start_date, t.lock_date, t.room_id, t.author_member_id,
				t.locked, t.announcement, t.last_post_date, t.poll_question, t.poll_data, t.poll_allow_multiple,
				r.r_id, r.name, r.perm_view, r.perm_reply, r.moderators,
				(SELECT COUNT(*) FROM c_posts p WHERE p.thread_id = t.t_id) AS post_count FROM c_threads t
				INNER JOIN c_rooms r ON (r.r_id = t.room_id) WHERE t.t_id = '{$this->thread_id}';");
		$thread_info = Database::fetch($thread);

		// Redirect to Error 404 if the thread doesn't exist
		if($thread_info == null) {
			$this->Core->redirect("404");
		}

		// Calculate the number of actual replies (first post is not a reply)
		$thread_info['post_count_display'] = $thread_info['post_count'] - 1;

		// Check if thread has a poll
		$thread_info['poll'] = $this->getPoll($thread_info);

		// Check permission to read
		$thread_info['perm_view'] = unserialize($thread_info['perm_view']);
		$permission_value = "V_" . SessionState::$user_data['usergroup'];
		if(!in_array($permission_value, $thread_info['perm_view'])) {
			$this->Core->redirect("HTTP_REFERER");
		}

		// Check permission to reply
		$thread_info['perm_reply'] = unserialize($thread_info['perm_reply']);
		$permission_value = "V_" . SessionState::$user_data['usergroup'];
		if(in_array($permission_value, $thread_info['perm_reply'])) {
			$thread_info['allow_to_reply'] = true;
		}
		else {
			$thread_info['allow_to_reply'] = false;
		}

		// Check if it's an obsolete thread (if enabled)
		$thread_info['obsolete_notification'] = "";
		$obsolete_seconds = $this->Core->config['thread_obsolete_value'] * DAY;
		if(($thread_info['last_post_date'] + $obsolete_seconds) < time()) {
			$thread_info['obsolete'] = true;
			$thread_info['obsolete_notification'] = Html::notification(
				i18n::translate("T_OBSOLETE", [$this->Core->config['thread_obsolete_value']]), "warning", true
			);
		}
		else {
			$thread_info['obsolete'] = false;
		}

		// Lock thread if it has an scheduled date
		if($thread_info['lock_date'] != 0 && $thread_info['lock_date'] < time()) {
			$thread_info['locked'] = 1;
		}

		return $thread_info;
	}

	/**
	 * --------------------------------------------------------------------
	 * CHECK IF THREAD HAS POLL. IF TRUE, BUILD AND RETURN ELEMENTS.
	 * --------------------------------------------------------------------
	 */
	private function getPoll($thread_info)
	{
		// Get poll information if poll exists
		if($thread_info['poll_question'] != "") {
			$poll_data = json_decode($thread_info['poll_data'], true);

			// Get total of votes
			if(!empty($poll_data['replies'])) {
				$total = array_sum($poll_data['replies']);
				$ratio = ($total != 0) ? 100 / $total : 0; // Avoid "division by zero" exception

				// Get percentage of votes for each option
				foreach($poll_data['replies'] as $k => $v) {
					$poll_data['percentage'][$k] = $v * $ratio;
				}
			}

			$poll_info = [
				"has_poll"  => true,
				"question"  => $thread_info['poll_question'],
				"choices"   => $poll_data['questions'],
				"multiple"  => $thread_info['poll_allow_multiple'],
				"replies_n" => $poll_data['replies'],
				"replies_p" => $poll_data['percentage'],
				"voters"    => $poll_data['voters'],
				"total"     => $total
			];

			// Check if user has already voted in this poll
			$poll_info['has_voted'] = in_array(SessionState::$user_data['m_id'], $poll_info['voters']);
		}
		else {
			// If not, return false
			$poll_info = [
				"has_poll" => false
			];
		}

		return $poll_info;
	}

	/**
	 * --------------------------------------------------------------------
	 * UPDATE SESSION TABLE WITH THREAD ID IN 'location_room_id'
	 * --------------------------------------------------------------------
	 */
	private function updateSessionTable()
	{
		// Update session table with room ID
		$session = SessionState::$session_token;
		Database::update("c_sessions", "location_room_id = {$this->thread_id}", "session_token = '{$session}'");
	}

	/**
	 * --------------------------------------------------------------------
	 * RETURNS AN ARRAY OF BADWORDS
	 * --------------------------------------------------------------------
	 */
	private function filterBadWords($text)
	{
		if($this->Core->config['language_bad_words'] != "") {
			$bad_words = explode("\n", $this->Core->config['language_bad_words']);
			$bad_words_list = preg_replace("/(\r|\n)/i", "", "/\b(" . implode("|", $bad_words) . ")\b/i");

			return preg_replace($bad_words_list, $this->Core->config['language_bad_words_replacement'], $text);
		}
		else {
			return $text;
		}
	}

	/**
	 * --------------------------------------------------------------------
	 * GET FIRST POST CONTENT
	 * --------------------------------------------------------------------
	 */
	private function getFirstPost()
	{
		// Get first post info
		$first_post = Database::query("SELECT c_posts.*, c_threads.t_id, c_threads.tags, c_threads.room_id,
				c_attachments.*, c_threads.title, c_threads.locked, c_members.*, edit.username AS edit_username FROM c_posts
				INNER JOIN c_threads ON (c_posts.thread_id = c_threads.t_id)
				INNER JOIN c_members ON (c_posts.author_id = c_members.m_id)
				LEFT JOIN c_members AS edit ON (c_posts.edit_author = edit.m_id)
				LEFT JOIN c_attachments ON (c_posts.attach_id = c_attachments.a_id)
				WHERE thread_id = '{$this->thread_id}' AND first_post = '1' LIMIT 1;");

		$result = Database::fetch($first_post);

		// Format first thread
		$result['avatar'] = $this->Core->getAvatar($result, 96);
		$result['post_date'] = $this->Core->dateFormat($result['post_date']);
		$result['joined'] = $this->Core->dateFormat($result['joined'], "short");

		// Check if the currently logged in member is the thread author
		$result['is_author'] = ($result['author_id'] == SessionState::$user_data['m_id']);

		// Show label if post was edited
		if(isset($result['edit_time'])) {
			$result['edit_time'] = $this->Core->dateFormat($result['edit_time']);
			$result['edited']    = "(" . i18n::translate("T_EDITED", array($result['edit_time'], $result['edit_username'])) . ")";
		}
		else {
			$result['edited'] = "";
		}

		// Block bad words and parse HTML entities
		$result['post'] = $this->filterBadWords($result['post']);
		$result['post'] = $this->parseDbText($result['post']);

		// Get attachment link, if there is one
		if($result['attach_id'] != 0) {
			$result['attachment_link'] = $this->getAttachment($result);
		}

		return $result;
	}

	/**
	 * --------------------------------------------------------------------
	 * GET REPLIES
	 * --------------------------------------------------------------------
	 */
	private function getReplies($pages)
	{
		$reply_result = array();

		$replies = Database::query("SELECT c_posts.*, c_attachments.*, c_members.*, edit.username AS edit_username FROM c_posts
				INNER JOIN c_members ON (c_posts.author_id = c_members.m_id)
				LEFT JOIN c_members AS edit ON (c_posts.edit_author = edit.m_id)
				LEFT JOIN c_attachments ON (c_posts.attach_id = c_attachments.a_id)
				WHERE thread_id = '{$this->thread_id}' AND first_post = '0'
				ORDER BY best_answer DESC, post_date ASC
				LIMIT {$pages['offset']},{$pages['posts_per_page']};");

		while($result = Database::fetch($replies)) {
			// Is this a best answer or a regular reply?
			$result['bestanswer_class'] = ($result['best_answer'] == 1) ? "best-answer" : "";

			// Member information
			$result['avatar'] = $this->Core->getAvatar($result, 196);
			$result['joined'] = $this->Core->dateFormat($result['joined'], "short");
			$result['post_date'] = $this->Core->dateFormat($result['post_date']);

			// Member ranks
			if($this->Core->config['general_member_enable_ranks']) {
				$result['rank'] = $this->memberRank($result['posts']);
				if($result['rank']) {
					$result['rank_name'] = $result['rank']['title'];
					if($result['rank']['image'] == "") {
						$result['rank_pips'] = "";
						for($i = 1; $i <= $result['rank']['pips']; $i++) {
							$result['rank_pips'] .= "<i class='fa fa-star'></i>";
						}
					}
					else {
						$result['rank_pips'] = "<img src='" . $result['rank']['image'] . "'>";
					}
				}
			}
			else {
				$result['rank'] = [];
			}

			// Block bad words and parse HTML entities
			$result['post'] = $this->filterBadWords($result['post']);
			$result['post'] = $this->parseDbText($result['post']);

			// Get attachment link, if there is one
			if($result['attach_id'] != 0) {
				$result['attachment_link'] = $this->getAttachment($result);
			}

			// Show label if post was edited
			if(isset($result['edit_time'])) {
				$result['edit_time'] = $this->Core->dateFormat($result['edit_time']);
				$result['edited']    = "(" . i18n::translate("T_EDITED", array($result['edit_time'], $result['edit_username'])) . ")";
			}
			else {
				$result['edited'] = "";
			}

			// Get quoted post
			if($result['quote_post_id'] != 0) {
				$result['has_quote'] = true;
				Database::query("SELECT c_posts.post_date, c_posts.post, c_members.username AS author FROM c_posts
						INNER JOIN c_members ON (c_posts.author_id = c_members.m_id)
						WHERE p_id = {$result['quote_post_id']};");
				$quoted_post_result = Database::fetch();

				// Return results
				$result['quote_author'] = $quoted_post_result['author'];
				$result['quote_time'] = $this->Core->dateFormat($quoted_post_result['post_date']);
				$result['quote_post'] = $this->parseDbText($quoted_post_result['post']);
			}
			else {
				$result['has_quote'] = false;
			}

			// Build post/thread action controls
			$result['post_controls'] = [];

			// Member controls: edit or delete post
			if($result['author_id'] == SessionState::$user_data['m_id'] || SessionState::isAdmin()) {
				// Edit post
				array_push($result['post_controls'], [
					"url"   => "thread/edit/{$result['p_id']}",
					"class" => "",
					"data"  => "",
					"icon"  => "fa-pencil",
					"text"  => i18n::translate("T_EDIT")
				]);
				// Delete post
				array_push($result['post_controls'], [
					"url"   => "#deleteThreadConfirm",
					"class" => "fancybox delete-post-button",
					"data"  => "data-post='{$result['p_id']}' data-thread='{$this->thread_id}' data-member='{$result['author_id']}'",
					"icon"  => "fa-ban",
					"text"  => i18n::translate("T_DELETE")
				]);
			}

			// Set/uset best answer
			if($this->thread_info['author_member_id'] == SessionState::$user_data['m_id']
				&& $result['author_id'] != SessionState::$user_data['m_id']) {
				if($result['best_answer'] == 0) {
					// Set as best answer
					array_push($result['post_controls'], [
						"url"   => "thread/setbestanswer/{$result['p_id']}",
						"class" => "",
						"data"  => "",
						"icon"  => "fa-thumbs-o-up",
						"text"  => i18n::translate("T_BEST_SET")
					]);
				}
				else {
					// Remove best answer
					array_push($result['post_controls'], [
						"url"   => "thread/unsetbestanswer/{$result['p_id']}",
						"class" => "",
						"data"  => "",
						"icon"  => "fa-thumbs-o-down",
						"text"  => i18n::translate("T_BEST_UNSET")
					]);
				}
			}

			$reply_result[] = $result;
		}

		return $reply_result;
	}

	/**
	 * --------------------------------------------------------------------
	 * PARSE POST TEXT
	 * --------------------------------------------------------------------
	 */
	private function parseDbText($text)
	{
		$text = htmlentities($text);
		$text = html_entity_decode($text);
		$text = Text::removeHtmlElements($text);

		return $text;
	}

	/**
	 * --------------------------------------------------------------------
	 * GET ATTACHMENT LINK
	 * --------------------------------------------------------------------
	 */
	private function getAttachment($post_info) {
		return sprintf(
			"public/attachments/%s/%s/%s",
			$post_info['member_id'],
			$post_info['date'],
			$post_info['filename']
		);
	}

	/**
	 * --------------------------------------------------------------------
	 * GET NUMBER OF PAGES AND SQL OFFSET
	 * --------------------------------------------------------------------
	 */
	private function getPages()
	{
		$pages = [];

		$pages['posts_per_page'] = $this->Core->config['thread_posts_per_page'];
		$total_posts = $this->thread_info['post_count'] - 1;

		// Calculated SQL offset
		$pages['offset'] = Http::request("p", true)
			? Http::request("p", true) * $pages['posts_per_page'] - $pages['posts_per_page']
			: 0;

		// Page numbers for HTML pagination
		$pages['current'] = Http::request("p", true) ? Http::request("p", true) : 1;
		$pages['total'] = ceil($total_posts / $pages['posts_per_page']);

		return $pages;
	}

	/**
	 * --------------------------------------------------------------------
	 * GET LIST OF RELATED THREADS
	 * --------------------------------------------------------------------
	 */
	private function relatedThreads()
	{
		$thread_search = explode(" ", strtolower($this->thread_info['title']));
		$related_thread_list = [];

		foreach($thread_search as $key => $value) {
			if(strlen($value) < 4) {
				unset($thread_search[$key]);
			}
		}

		$thread_search = implode(" ", $thread_search);
		Database::query("SELECT *, MATCH(title) AGAINST ('{$thread_search}') AS relevance FROM c_threads
				WHERE t_id <> {$this->thread_id} AND MATCH(title) AGAINST ('{$thread_search}');");

		while($thread = Database::fetch()) {
			$thread['thread_date'] = $this->Core->dateFormat($thread['last_post_date'], "short");
			$related_thread_list[] = $thread;
		}

		return $related_thread_list;
	}

	/**
	 * --------------------------------------------------------------------
	 * WHEN POSTING, CHECK IF MEMBER IS A MODERATOR
	 * --------------------------------------------------------------------
	 */
	private function isModerator($moderators_serialized = "")
	{
		// Get array of moderators
		$moderators_array = unserialize($moderators_serialized);

		// Check if room has moderators and if
		// the current member is a moderator
		if(!empty($moderators_array) && in_array(SessionState::$user_data['m_id'], $moderators_array)) {
			return true;
		}
		else {
			// If member is not a moderator, check if is an Administrator
			return SessionState::isAdmin();
		}
	}

	/**
	 * --------------------------------------------------------------------
	 * WHEN POSTING, CHECK IF MEMBER IS A MODERATOR (GET THREAD ID VALUE)
	 * --------------------------------------------------------------------
	 */
	private function isModeratorFromThreadId($thread_id = 0)
	{
		// Get room moderators
		Database::query("SELECT r.moderators FROM c_threads t
				INNER JOIN c_rooms r ON (r.r_id = t.room_id)
				WHERE t_id = {$thread_id};");

		$moderators = Database::fetch();

		return $this->isModerator($moderators['moderators']);
	}

	/**
	 * --------------------------------------------------------------------
	 * GET MEMBER RANK
	 * --------------------------------------------------------------------
	 */
	private function memberRank($posts = 0)
	{
		// Get list of ranks just once and save results
		if(empty($this->ranks)) {
			Database::query("SELECT * FROM c_ranks ORDER BY min_posts DESC;");
			$this->ranks = Database::fetchToArray();
		}

		foreach($this->ranks as $rank) {
			if($posts >= $rank['min_posts']) {
				return $rank;
			}
		}

		return array();
	}
}
