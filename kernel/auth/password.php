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
 * 2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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

// LIBVALOA_PASSWORD_USE_CRYPT 0 = Double-salted sha1- passwords
// LIBVALOA_PASSWORD_USE_CRYPT 1 = Crypt password using php's built-in crypt()
if(!defined('LIBVALOA_PASSWORD_USE_CRYPT')) DEFINE('LIBVALOA_PASSWORD_USE_CRYPT', 1);

class Auth_Password {

	public static function cryptPassword($username, $plaintextPassword) {
		$username = trim($username);
		$plaintextPassword = trim($plaintextPassword);
		if(LIBVALOA_PASSWORD_USE_CRYPT == 1) {
			return crypt($username.$plaintextPassword);
		}

		// Use double salting for password hash.
		// This way, even if someone can produce a rainbow table for this algorithm, 
		// and has the salt to do it with, they will never know where in the hash 
		// parameter the second salt was placed, because they don't know the length 
		// of the actual password. 
		$password = str_split($plaintextPassword, (strlen($plaintextPassword) / 2) + 1); 
		$hash = hash('sha1', $username.$password[0].'$$'.$password[1]); 
		return $hash; 
	}

	public static function verify($username, $plaintextPassword, $crypted) {
		if(LIBVALOA_PASSWORD_USE_CRYPT == 1) {
			if(crypt($username.$plaintextPassword, $crypted) == $crypted) {
				return true;
			}
			return false;
		} else {
			if(self::cryptPassword($username, $plaintextPassword) == $crypted) {
				return true;
			}			
		}
		return false;
	}	

}