<?php

## -------------------------------------------------------
#  ADDICTIVE COMMUNITY
## -------------------------------------------------------
#  Created by Brunno Pleffken Hosti
#  http://github.com/brunnopleffken/addictive-community
#
#  File: index.php
#  License: GPLv2
#  Copyright: (c) 2016 - Addictive Community
## -------------------------------------------------------

use \AC\Kernel\Html;
use \AC\kernel\Database;

/**
 * --------------------------------------------------------------------
 * INITIALIZATION INFORMATION
 * --------------------------------------------------------------------
 */

require("../init.php");
require("../kernel/Html.php");
require("../kernel/Database.php");

$template = "";

/**
 * --------------------------------------------------------------------
 * INSTALLER CLASS INHERITS THE MAIN DATABASE CLASS
 * --------------------------------------------------------------------
 */

class Installer extends Database
{
	public static $input = array();

	public function InstallerDB()
	{
		self::connect(self::$input);
	}
}

/**
 * --------------------------------------------------------------------
 * WHICH STEP IS THE USER IN
 * --------------------------------------------------------------------
 */

if(isset($_REQUEST['step'])) {
	$step = $_REQUEST['step'];
}
else {
	$step = 1;
}

/**
 * --------------------------------------------------------------------
 * BUILD INSTALLER
 * --------------------------------------------------------------------
 */

switch($step){

	/**
	 * --------------------------------------------------------------------
	 * STEP 1
	 * --------------------------------------------------------------------
	 */

	case 1:

		$eula = "";
		$disabled = "";
		$notification = "";
		$button = "<input type='button' class='btn btn-default' value='Proceed' onclick='eula()'>";

		// Check if installer is locked

		if(file_exists(".lock")) {
			$disabled = "disabled";
			$notification = Html::notification(
				"Installer is locked! Please, remove the file <b>install/.lock</b> to proceed.", "failure", true
			);
			$button  = "<input type='button' class='btn btn-default' value='Proceed' disabled>";
		}

		// Get EULA

		if(file_exists("../LICENSE") && $step == 1) {
			$eula = file_get_contents("../LICENSE");
		}

		$template = <<<HTML
			<div class="step-box">
				<div class="current"><h3>Step 1</h3><small>EULA</small></div>
				<div><h3>Step 2</h3><small>Requirements</small></div>
				<div><h3>Step 3</h3><small>Database Settings</small></div>
				<div><h3>Step 4</h3><small>Community Settings</small></div>
				<div><h3>Step 5</h3><small>Install</small></div>
			</div>

			{$notification}

			<form method="post" name="install">
				<div class="text-center">
					<textarea style="width: 575px; height: 300px; margin-bottom: 20px; font-size: 13px; font-family: Consolas, monospace" class="form-control" readonly>{$eula}</textarea>
				</div>
				<div class="form-group">
					<div class="text-center">
						<label class="checkbox-control">
							<input type="checkbox" id="agree" {$disabled}><span><i class="fa fa-check"></i></span>
							I agree with the End User Licence Agreement
						</label>
					</div>
				</div>
				<div class="form-group text-center">
					{$button}
				</div>
			</form>
HTML;

		break;

	/**
	 * --------------------------------------------------------------------
	 * STEP 2
	 * --------------------------------------------------------------------
	 */

	case 2:

		$mysql_information = Html::notification(
			"MySQL version will be checked in step 4.", "info", true
		);

		// Check system environment

		$info['php-version'] = PHP_VERSION;
		$info['memory-limit'] = @ini_get("memory_limit");

		$php_v = version_compare($info['php-version'], MIN_PHP_VERSION);
		$php_check = ($php_v >= 0) ? "<span style='color: #090'>Yes ({$info['php-version']})</span>" : "<span style='color: #900'>No ({$info['php-version']})</span>";

		$environment = "<table class='table'>";
		$environment .= "<tr><td style='width:190px'>Server Software</td><td>{$_SERVER['SERVER_SOFTWARE']} {$_SERVER['SERVER_PROTOCOL']}</td></tr>";
		$environment .= "<tr><td>PHP 5.3+</td><td>{$php_check}</td></tr>";
		$environment .= "</table>";

		// Check PHP extensions

		$extensions = get_loaded_extensions();
		$required = array("mysqlnd", "mysqli", "gd", "libxml", "json");

		$ext_name = array(
			"mysqlnd" => "MySQL Driver",
			"mysqli"  => "MySQLi Extension",
			"gd"      => "GD Library",
			"libxml"  => "DOM XML",
			"json"    => "JSON Support"
		);

		$extensions_ok = "<table class='table'>";

		foreach($required as $data) {
			$status = (in_array($data, $extensions)) ? "<span style='color: #090'>Yes</span>" : "<span style='color: #c00'>No</span>";
			$extensions_ok .= "<tr><td style='width:190px'>" . $ext_name[$data] . " ({$data})</td><td>{$status}</td></tr>";
		}

		$extensions_ok .= "</table>";

		// Check Apache extensions

		if(apache_get_modules()) {
			if(in_array("mod_rewrite", apache_get_modules())) {
				$mod_rewrite_ok = "<span style='color: #090'>Yes</span>";
			}
			else {
				$mod_rewrite_ok = "<span style='color: #c00'>No</span>";
				$disabled = "disabled='disabled'";
			}
		}
		else {
			$mod_rewrite_ok = "<span style='color: #ddd'>Not using Apache server</span>";
		}

		$apache_extensions = "<table class='table'>";
		$apache_extensions .= "<tr><td style='width:190px'>mod_rewrite</td><td>{$mod_rewrite_ok}</td></tr>";
		$apache_extensions .= "</table>";

		// Check folders
		$disabled = "";

		// root/config.ini
		if(is_writable("../config.ini")) {
			if(filesize("../config.ini") == 0) {
				$file_conf = "<span style='color: #090'>Writable</span>";
			}
			else {
				$file_conf = "<span style='color: #C00'>File not empty</span>";
				$disabled = "disabled='disabled'";
			}
		}
		else {
			$file_conf = "<span style='color: #C00'>Not writable</span>";
			$disabled = "disabled='disabled'";
		}

		// root/install
		if(is_writable("../install")) {
			$dir_install = "<span style='color: #090'>Writable</span>";
		}
		else {
			$dir_install = "<span style='color: #C00'>Not writable</span>";
			$disabled = "disabled='disabled'";
		}

		// root/public/attachments
		if(is_writable("../public/attachments/")) {
			$dir_attach = "<span style='color: #090'>Writable</span>";
		}
		else {
			$dir_attach = "<span style='color: #C00'>Not writable</span>";
			$disabled = "disabled='disabled'";
		}

		// root/public/avatar
		if(is_writable("../public/avatar/")) {
			$dir_avatar = "<span style='color: #090'>Writable</span>";
		}
		else {
			$dir_avatar = "<span style='color: #C00'>Not writable</span>";
			$disabled = "disabled='disabled'";
		}

		// root/public/cover
		if(is_writable("../public/cover/")) {
			$dir_cover = "<span style='color: #090'>Writable</span>";
		}
		else {
			$dir_cover = "<span style='color: #C00'>Not writable</span>";
			$disabled = "disabled='disabled'";
		}

		$folders = "<table class='table'>";
		$folders .= "<tr><td style='width:190px'>/config.ini</td><td>{$file_conf}</td></tr>";
		$folders .= "<tr><td>/install</td><td>{$dir_install}</td></tr>";
		$folders .= "<tr><td>/public/attachments</td><td>{$dir_attach}</td></tr>";
		$folders .= "<tr><td>/public/avatar</td><td>{$dir_avatar}</td></tr>";
		$folders .= "<tr><td>/public/cover</td><td>{$dir_cover}</td></tr>";
		$folders .= "</table>";

		if($disabled != "") {
			$notification = Html::notification(
				"There are still some things to do on your server environment.", "failure", true
			);
		}
		else {
			$notification = "";
		}

		// Do template!

		$template = <<<HTML
			<div class="step-box">
				<div class="prev"><h3>Step 1</h3><small>EULA</small></div>
				<div class="current"><h3>Step 2</h3><small>Requirements</small></div>
				<div><h3>Step 3</h3><small>Database Settings</small></div>
				<div><h3>Step 4</h3><small>Community Settings</small></div>
				<div><h3>Step 5</h3><small>Install</small></div>
			</div>

			{$mysql_information}
			{$notification}

			<form action="index.php?step=3" method="post">
				<div class="input-box">
					<h2>System Environment</h2>
					{$environment}
				</div>
				<div class="input-box">
					<h2>PHP Extensions</h2>
					{$extensions_ok}
				</div>
				<div class="input-box">
					<h2>Apache Modules</h2>
					{$apache_extensions}
				</div>
				<div class="input-box">
					<h2>Files and Folders</h2>
					{$folders}
				</div>
				<div class="form-group text-center">
					<input type="button" class="btn btn-default" value="Proceed" onclick="window.location.replace('index.php?step=3')" {$disabled}>
				</div>
			</form>
HTML;

		break;

	/**
	 * --------------------------------------------------------------------
	 * STEP 3
	 * --------------------------------------------------------------------
	 */

	case 3:

		// Second barrier to stop any unwanted reinstall

		if(file_exists(".lock")) {
			echo Html::notification(
				"Installer is locked! Please, remove the file <b>install/.lock</b> to proceed.", "failure", true
			);
			exit;
		}

		// Show notification message about tables prefixes
		$notification = Html::notification(
			"All tables are prefixed with <b>c_</b>.", "info", true
		);

		// Ok, proceed...

		$template = <<<HTML
			<div class="step-box">
				<div class="prev"><h3>Step 1</h3><small>EULA</small></div>
				<div class="prev"><h3>Step 2</h3><small>Requirements</small></div>
				<div class="current"><h3>Step 3</h3><small>Database Settings</small></div>
				<div><h3>Step 4</h3><small>Community Settings</small></div>
				<div><h3>Step 5</h3><small>Install</small></div>
			</div>

			{$notification}

			<form action="index.php?step=4" method="post" id="database-form">
				<div class="form-group grid">
					<label for="teste" class="col-3">MySQL Host</label>
					<div class="col-6">
						<input type="text" name="host" class="form-control" required>
					</div>
				</div>
				<div class="form-group grid">
					<label for="teste" class="col-3">MySQL Port</label>
					<div class="col-2">
						<input type="text" name="port" class="form-control" value="3306" required>
					</div>
				</div>
				<div class="form-group grid">
					<label for="teste" class="col-3">Username</label>
					<div class="col-4">
						<input type="text" name="username" class="form-control" required>
					</div>
				</div>
				<div class="form-group grid">
					<label for="teste" class="col-3">Password</label>
					<div class="col-4">
						<input type="password" name="password" class="form-control">
					</div>
				</div>
				<div class="form-group grid">
					<label for="teste" class="col-3">Database Name</label>
					<div class="col-4">
						<input type="text" name="database" class="form-control" required>
					</div>
				</div>
				<div class="form-group text-center">
					<input type="submit" class="btn btn-default" value="Proceed">
				</div>
			</form>
HTML;

		break;

	/**
	 * --------------------------------------------------------------------
	 * STEP 4
	 * --------------------------------------------------------------------
	 */

	case 4:

		session_start();

		// Get MySQL authentication info
		$installer = new Installer();
		$_SESSION['db_server']   = Installer::$input['db_server']   = $_REQUEST['host'];
		$_SESSION['db_database'] = Installer::$input['db_database'] = $_REQUEST['database'];
		$_SESSION['db_username'] = Installer::$input['db_username'] = $_REQUEST['username'];
		$_SESSION['db_password'] = Installer::$input['db_password'] = $_REQUEST['password'];
		$_SESSION['db_port']     = Installer::$input['db_port']     = $_REQUEST['port'];

		// Connect to database and get information
		$installer->InstallerDB();
		$installer->query("SELECT VERSION() AS mysql_version;");
		$result = $installer->fetch();

		preg_match("#[0-9]+\.[0-9]+\.[0-9]+#", $result['mysql_version'], $mysql_version);
		$info['mysql-version'] = $mysql_version[0];

		$sql_v = version_compare($info['mysql-version'], MIN_SQL_VERSION);

		if($sql_v >= 0) {
			Database::query("SHOW TABLES;");
			if(Database::rows() != 0) {
				// Show notification message about wrong MySQL version
				$instructions = "";
				$mysql_information = Html::notification(
					"The selected database is not empty. Remove all existing tables and try again.", "failure", true
				);
				$button_lock = "disabled";
				$supported = false;
			}
			else {
				// Don' show any notification if everything is OK
				$mysql_information = "";
				$button_lock = "";
				$supported = true;
			}
		}
		else {
			// Show notification message about wrong MySQL version
			$instructions = file_get_contents("partials/mysql_outdated.html");
			$mysql_information = Html::notification(
				"Addictive Community requires MySQL v" . MIN_SQL_VERSION . " or higher (installed: MySQL v{$info['mysql-version']}).", "failure", true
			);
			$button_lock = "disabled";
			$supported = false;
		}

		// Community URL and physical path
		$dir = str_replace("install", "", getcwd());
		$url = str_replace("install/index.php", "", $_SERVER['HTTP_REFERER']);
		$url = preg_replace("#\?(.+?)*#", "", $url);

		// Languages
		$dir_list = array();
		$lang_list = "";
		$lang_dir = scandir("../languages");

		foreach($lang_dir as $k => $v) {
			if(strpos($v, "_") && is_dir("../languages/" . $v)) {
				$dir_list[] = $v;
			}
		}

		foreach($dir_list as $language) {
			$language_info = json_decode(file_get_contents("../languages/" . $language . "/_language.json"), true);
			$selected = ($language_info['directory'] == "en_US") ? "selected" : "";
			$lang_list .= "<option value='{$language_info['directory']}' {$selected}>{$language_info['name']}</option>";
		}

		// Timezone list
		$tz_offset = array(
			"-12" => "(UTC-12:00) International Date Line West",
			"-11" => "(UTC-11:00) Midway Island, American Samoa",
			"-10" => "(UTC-10:00) Hawaii, Cook Islands",
			"-9"  => "(UTC-09:00) Alaska, French Polynesia",
			"-8"  => "(UTC-08:00) Pacific Time (US & Canada), Tijuana",
			"-7"  => "(UTC-07:00) Mountain Time (US & Canada), Chihuahua, Sonora",
			"-6"  => "(UTC-06:00) Central Time (US & Canada), Cental America, Ciudad de México",
			"-5"  => "(UTC-05:00) Eastern Time (US & Canada), Bogotá, Lima, Rio Branco",
			"-4"  => "(UTC-04:00) Atlantic Time (Canada), Caracas, Santiago, La Paz, Manaus",
			"-3"  => "(UTC-03:00) Brasília, São Paulo, Buenos Aires, Montevideo",
			"-2"  => "(UTC-02:00) Mid-Atlantic",
			"-1"  => "(UTC-01:00) Azores, Cabo Verde",
			"0"   => "(UTC&#177;00:00) London, Lisboa, Reykjavík, Dublin, Casablanca",
			"1"   => "(UTC+01:00) Paris, Amsterdam, Berlin, Roma, Stockholm, West Central Africa",
			"2"   => "(UTC+02:00) Helsinki, Kyiv, Riga, Jerusalem, Johannesburg, Cairo",
			"3"   => "(UTC+03:00) Moscow, Saint Petersburg, Istanbul, Nairobi, Baghdad",
			"4"   => "(UTC+04:00) Abu Dhabi, Baku, Dubai, Yerevan",
			"5"   => "(UTC+05:00) Islamabad, Karachi, Yekaterinburg, Tashkent",
			"5.5" => "(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi",
			"6"   => "(UTC+06:00) Astana, Dhaka, Almaty, Novosibirsk",
			"6.5" => "(UTC+06:30) Yangon (Rangoon)",
			"7"   => "(UTC+07:00) Bangkok, Hanoi, Jakarta, Krasnoyarsk",
			"8"   => "(UTC+08:00) Beijing, Hong Kong, Kuala Lumpur, Singapore, Taipei, Perth",
			"9"   => "(UTC+09:00) Tokyo, Osaka, Seoul, Yakutsk, Sapporo",
			"9.5" => "(UTC+09:30) Adelaide, Darwin",
			"10"  => "(UTC+10:00) Brisbane, Canberra, Melbourne, Sydney, Vladivostok",
			"11"  => "(UTC+11:00) Magadan, Solomon Is., New Caledonia",
			"12"  => "(UTC+12:00) Auckland, Wellington, Fiji, Marshall Is."
		);

		$tz_list = "";

		foreach($tz_offset as $tz_value => $tz_name) {
			$selected = ($tz_value == 0) ? "selected" : "";
			$tz_list .= "<option value='{$tz_value}' {$selected}>{$tz_name}</option>\n";
		}


		if($supported) {
			$template = <<<HTML
				<div class="step-box">
					<div class="prev"><h3>Step 1</h3><small>EULA</small></div>
					<div class="prev"><h3>Step 2</h3><small>Requirements</small></div>
					<div class="prev"><h3>Step 3</h3><small>Database Settings</small></div>
					<div class="current"><h3>Step 4</h3><small>Community Settings</small></div>
					<div><h3>Step 5</h3><small>Install</small></div>
				</div>

				{$mysql_information}

				<form action="index.php?step=5" method="post">

					<div class="form-group grid">
						<label for="teste" class="col-3">Community Name</label>
						<div class="col-5">
							<input type="text" name="community" class="form-control" required>
						</div>
					</div>

					<h2>Default Settings</h2>

					<div class="form-group grid">
						<label for="teste" class="col-3">Language</label>
						<div class="col-9">
							<select name="language" class="select2 span-5">
								{$lang_list}
							</select>
						</div>
					</div>
					<div class="form-group grid">
						<label for="teste" class="col-3">Time Zone</label>
						<div class="col-9">
							<select name="timezone" class="select2 span-12">
								{$tz_list}
							</select>
						</div>
					</div>

					<h2>Paths and URLs</h2>

					<div class="form-group grid">
						<label for="teste" class="col-3">Installation Path</label>
						<div class="col-9">
							<input type="text" name="install_path" class="form-control" value="{$dir}" required>
						</div>
					</div>
					<div class="form-group grid">
						<label for="teste" class="col-3">Installation URL</label>
						<div class="col-9">
							<input type="text" name="install_url" class="form-control" value="{$url}" required>
						</div>
					</div>

					<h2>Administrator Account</h2>

					<div class="form-group grid">
						<label for="teste" class="col-3">Username</label>
						<div class="col-4">
							<input type="text" name="adm_username" class="form-control" required>
						</div>
					</div>
					<div class="form-group grid">
						<label for="teste" class="col-3">Password</label>
						<div class="col-4">
							<input type="password" name="adm_password" class="form-control" id="adm_password" required>
						</div>
					</div>
					<div class="form-group grid">
						<label for="teste" class="col-3">Confirm Password</label>
						<div class="col-4">
							<input type="password" name="adm_password2" class="form-control" id="adm_password2" onblur="checkPasswordMatch()" required>
						</div>
					</div>
					<div class="form-group grid">
						<label for="teste" class="col-3">E-mail</label>
						<div class="col-6">
							<input type="email" name="adm_email" class="form-control" required>
						</div>
					</div>

					<div class="form-group text-center">
						<input type="hidden" name="db_server" value="{$_SESSION['db_server']}">
						<input type="hidden" name="db_database" value="{$_SESSION['db_database']}">
						<input type="hidden" name="db_username" value="{$_SESSION['db_username']}">
						<input type="hidden" name="db_password" value="{$_SESSION['db_password']}">
						<input type="hidden" name="db_port" value="{$_SESSION['db_port']}">
						<input type="submit" class="btn btn-default" value="Proceed" {$button_lock}>
					</div>

				</form>
HTML;
		}
		else {
			$template = <<<HTML
				<div class="step-box">
					<div class="prev"><h3>Step 1</h3><small>EULA</small></div>
					<div class="prev"><h3>Step 2</h3><small>Requirements</small></div>
					<div class="prev"><h3>Step 3</h3><small>Database Settings</small></div>
					<div class="current"><h3>Step 4</h3><small>Community Settings</small></div>
					<div><h3>Step 5</h3><small>Install</small></div>
				</div>

				{$mysql_information}
				{$instructions}

HTML;
		}

		session_destroy();

		break;

	/**
	 * --------------------------------------------------------------------
	 * STEP 5
	 * --------------------------------------------------------------------
	 */

	case 5:

		$template = <<<HTML
			<script type="text/javascript">
				$(document).ready(function() {
					installModule(1);
				});
			</script>

			<div class="step-box">
				<div class="prev"><h3>Step 1</h3><small>EULA</small></div>
				<div class="prev"><h3>Step 2</h3><small>Requirements</small></div>
				<div class="prev"><h3>Step 3</h3><small>Database Settings</small></div>
				<div class="prev"><h3>Step 4</h3><small>Community Settings</small></div>
				<div class="current"><h3>Step 5</h3><small>Install</small></div>
			</div>

			<input type="hidden" id="db_server" value="{$_REQUEST['db_server']}">
			<input type="hidden" id="db_database" value="{$_REQUEST['db_database']}">
			<input type="hidden" id="db_username" value="{$_REQUEST['db_username']}">
			<input type="hidden" id="db_password" value="{$_REQUEST['db_password']}">
			<input type="hidden" id="db_port" value="{$_REQUEST['db_port']}">
			<input type="hidden" id="community_name" value="{$_REQUEST['community']}">
			<input type="hidden" id="community_path" value="{$_REQUEST['install_path']}">
			<input type="hidden" id="community_url" value="{$_REQUEST['install_url']}">
			<input type="hidden" id="default_language" value="{$_REQUEST['language']}">
			<input type="hidden" id="default_timezone" value="{$_REQUEST['timezone']}">
			<input type="hidden" id="admin_username" value="{$_REQUEST['adm_username']}">
			<input type="hidden" id="admin_password" value="{$_REQUEST['adm_password']}">
			<input type="hidden" id="admin_email" value="{$_REQUEST['adm_email']}">

			<h2>Installation Progress</h2>

			<div id="log">
				<div class="step1">Saving configuration file... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<div class="step2">Checking saved information and connecting to database... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<div class="step3">Extracting table structure... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<div class="step4">Inserting initial data and settings... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<div class="step5">Saving administrator information... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<div class="step6">Locking installer... <span class="ok">OK</span><span class="failed">FAILED</span></div>
				<input type="submit" class="btn btn-default" value="Let's Go!" style="margin-top: 10px" onclick="window.location='../index.php'">
			</div>
HTML;

			break;

}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Addictive Community Installer</title>
	<!-- CSS Files -->
	<link rel="stylesheet" href="../thirdparty/font-awesome/css/font-awesome.min.css">
	<link rel="stylesheet" href="../thirdparty/select2/css/select2.min.css">
	<link rel="stylesheet" href="../static/css/framework.css">
	<link rel="stylesheet" href="../static/css/wireframe.css">

	<!-- JS Libraries -->
	<script type="text/javascript" src="../thirdparty/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../thirdparty/select2/js/select2.min.js"></script>

	<style type="text/css">
		/* Default styles override */
		h2 { margin-top: 20px; }
		#logo { text-align: center; margin-bottom: 20px; }
		.step-box { margin-bottom: 20px; }

		/* New installer styles */
		#log > div { display: none; line-height: 1.5em; }
		#log .ok { color: #090; display: none; }
		#log .failed { color: #d00; display: none; }
		#log input { display: none; margin-top: 20px; }
	</style>
</head>

<body>

	<header>
		<div class="top-half outer">
			<div class="row space-between">
				<div class="col-flexible">
					<a href="https://github.com/brunnopleffken/addictive-community" target="_blank" class="transition">View Addictive Community on GitHub</a>
				</div>
			</div>
		</div>
	</header>

	<div class="wrapper">
		<div id="logo">
			<img src="../static/images/logo.svg" alt="" height="40">
		</div>
		<div class="block" id="content" style="width: 700px; margin: auto">
			<?php echo $template ?>
		</div>
	</div>

	<footer class="text-center">
		Powered by
		<a href="https://github.com/brunnopleffken/addictive-community" target="_blank">Addictive Community</a>
		<?php echo VERSION . "-" . CHANNEL ?> &copy; <?php echo date("Y") ?> - All rights reserved.
	</footer>

	<!-- Community Installer -->
	<script type="text/javascript" src="../static/js/application.js"></script>
	<script type="text/javascript" src="installer.js"></script>
</body>
</html>
