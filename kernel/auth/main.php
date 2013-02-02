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
 * 2004,2005,2006,2007,2013 Tarmo Alexander Sundström <ts@greyscale.fi>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ts@greyscale.fi>
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

Interface Auth_IFace {
	public function authentication($user, $pass);
	public function authorize($userid, $module);
	public function getExternalUserID($user);
	public function getExternalSessionID($user);
	public function logout();
}

Interface Auth_PWResetIFace {
	public function updatePassword($user, $pass);
}

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
class Auth {

	private $backend;

	/**
	 * Constructor.
	 *
	 * @access      public
	 */
	public function __construct() {
		if(!defined(LIBVALOA_AUTH)) {
			define('LIBVALOA_AUTH','null');
		}
		$this->backend = "Auth_".LIBVALOA_AUTH;
	}

	public static function getClientIP() {
		if(isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && !empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
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
			throw new Exception("System error. Authentication driver '{$driver}' is not available.");
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
			$_SESSION["UserID"] = $auth->getExternalUserID();
			$_SESSION["ExternalSessionID"] = $auth->getExternalSessionID();
			$_SESSION["IP"] = self::getClientIP();
			$_SESSION["BASEHREF"] = $request->getBaseUri(true);
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
		if(!$module || isset($_SESSION["BASEHREF"]) && $_SESSION["BASEHREF"] != Controller_Request::getInstance()->getBaseUri(true)) {
			return false;
		}
		if(!defined('LIBVALOA_AUTH_CHECKIP') || LIBVALOA_AUTH_CHECKIP == 1) {
			if(self::getClientIP() != $_SESSION["IP"]) {
				return false;
			}
		}
		if(!isset($_SESSION["UserID"])) {
			return false;
		}
		$auth = new $this->backend;
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
