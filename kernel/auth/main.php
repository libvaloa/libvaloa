<?php
/**
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is Copyright (C)
 * 2004,2005,2006,2007,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2006,2007,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2007 Mikko Ruohola <polarfox@polarfox.net>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 */
/**
 * Authentication library.
 *
 * Handles user authentication and validation.
 *
 * @package       Kernel
 * @subpackage    Auth
 * @uses          Controller_Request
 * @uses          Controller_Redirect
 */

if(!defined('LIBVALOA_AUTH'))                       define('LIBVALOA_AUTH','null');
if(!defined('LIBVALOA_AUTH_CHECKIP'))               define('LIBVALOA_AUTH_CHECKIP', 1);
if(!defined('LIBVALOA_CHECK_HTTP_X_FORWARDED_FOR')) define('LIBVALOA_CHECK_HTTP_X_FORWARDED_FOR', 0);
if(!defined('LIBVALOA_DEFAULT_ROUTE'))              define('LIBVALOA_DEFAULT_ROUTE', '/');

class Auth {

	private $backend;

	/**
	 * Constructor.
	 *
	 * @access      public
	 */
	public function __construct() {
		$this->backend = "Auth_".LIBVALOA_AUTH;
	}

	public function getBackend() {
		return $this->backend;
	}

	public static function getClientIP() {
		// Support for cache servers such as Varnish.
		if(LIBVALOA_CHECK_HTTP_X_FORWARDED_FOR == 1 && (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && !empty($_SERVER["HTTP_X_FORWARDED_FOR"]))) {
			return $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		return $_SERVER["REMOTE_ADDR"];		
	}
	
	/**
	 * Override default authentication driver.
	 *
	 * @access      public
	 * @param       string $driver Authentication driver
	 */
	public function setAuthenticationDriver($driver = "DB") {
		if(class_exists("Auth_{$driver}")) {
			$this->backend = "Auth_{$driver}";
		} else {
			throw new BadMethodCallException("System error. Authentication driver not available.");
		}
	}

	/**
	 * Authentication. Takes username+password as parameter.
	 *
	 * Loads authentication driver as defined in config and calls drivers
	 * authentication() method..
	 *
	 * @access      public
	 * @param       string $user Username
	 * @param       string $pass Password
	 * @return      bool Boolean wether or not authentication was valid
	 */
	public function authentication($user, $pass) {
		$auth = new $this->backend;
		$request = Controller_Request::getInstance();
		if($auth->authentication($user, $pass)) {
			$_SESSION["User"] = $user;
			$_SESSION["UserID"] = $auth->getExternalUserID($user);
			$_SESSION["ExternalSessionID"] = $auth->getExternalSessionID($user);
			$_SESSION["ClientIP"] = self::getClientIP();
			$_SESSION["BaseUri"] = $request->getBaseUri(true);
			return true;
		}
		return false;
	}

	/**
	 * Updates user password using available authentication driver.
	 *
	 * @access public
	 * @param  string $username Username
	 * @param  string $password Password
	 * @return bool Return value from auth drivers updatePassword method
	 */
	public function updatePassword($username,$password) {
		$auth = new $this->backend;
		if($auth instanceof Auth_PWResetIFace) {
			return $auth->updatePassword($username, $password);
		}
		return false;
	}

	/**
	 * Checks if user has permissions to access a certain module (groupfeature or userfeature).
	 *
	 * @access      public
	 * @param       string $module Module name
	 * @return      bool True (access granted) or false (access denied)
	 */
	public static function authorize($module) {
		// trying to get from other installation on the same server
		if(!$module || isset($_SESSION["BaseUri"]) && $_SESSION["BaseUri"] != Controller_Request::getInstance()->getBaseUri(true)) {
			return false;
		}
		if(LIBVALOA_AUTH_CHECKIP == 1) {
			if(self::getClientIP() != $_SESSION["ClientIP"]) {
				return false;
			}
		}
		if(!isset($_SESSION["UserID"])) {
			return false;
		}
		$tmp = new Auth;
		$backend = $tmp->getBackend();
		$auth = new $backend;
		if($auth instanceof Auth_IFace) {
			return $auth->authorize($_SESSION["UserID"], $module);
		}
		return false;		
	}

	/**
	 * Destroys session & redirects to default module.
	 *
	 * @access      public
	 * @uses        Controller_Request
	 * @uses        Common_Profile
	 */
	public function logout() {
		$pageuri = Controller_Request::getInstance();
		$auth = new $this->backend;
		$auth->logout();
		if(isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-43200, '/');
		}
		session_destroy();
		Controller_Redirect::to(LIBVALOA_DEFAULT_ROUTE);
	}

}
