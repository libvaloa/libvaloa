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
 * 2005 Joni Halme <jontsa@angelinecms.info>
 *
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>
 *
 * Portions created by the Initial Developer are Copyright (C) 2005
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2008 Tarmo Alexander Sundström <ta@sundstrom.im>
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
 * Net Socket class.
 *
 * @package       Kernel
 * @subpackage    Net
 */

class Net_Socket {

	private $error = "";
	private $errno = false;
	private $socket = false;
	private $response = "";

	/**
	 * Connect to a server.
	 *
	 * @todo   Validate $protocol parameter against get_stream_transports()
	 * @todo   Socket timeouts.
	 * @access public
	 * @param  string $server Server address fe ftp.mysite.com
	 * @param  int $port Port to connect to. Defaults to 80 (http)
	 * @param  string $protocol Protocol to use. Defaults to tcp://
	 * @param  int $timeout Timeout time in seconds. Defaults to 30
	 * @return bool True on success, false on error
	 */
	public function connect($server, $port = 80, $protocol = "", $timeout = 30) {
		$this->disconnect();
		$this->errno = "";
		$this->error = "";
		if(!empty($protocol) && !strstr($protocol, "://")) {
			$protocol.= "://";
		}
		if($server && $port) {
			$this->socket = fsockopen($protocol.$server, $port, $this->errno, $this->error, $timeout);
			if($this->socket) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Disconnects socket.
	 *
	 * @access public
	 * @return bool
	 */
	public function disconnect() {
		if(is_resource($this->socket)) {
			fclose($this->socket);
		}
		return true;
	}

	/**
	 * Returns error string from fsockopen().
	 *
	 * @access public
	 * @return string
	 */
	public function getError() {
		if($this->error) {
			return $this->errno." ".$this->error;
		} else {
			return "";
		}
	}

	/**
	 * Sends data to server and reads response.
	 *
	 * @access public
	 * @param  string $data Data to send. If false, defaults to linefeed '\n'
	 * @return bool
	 */
	public function sendData($data = false) {
		if(is_resource($this->socket)) {
			if($data === false) {
				$data = "\n";
			}
			fwrite($this->socket, $data);
			$this->response = "";
			while(!feof($this->socket)) {
				$this->response.= fgets($this->socket);
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns servers response to request sent with sendRequest().
	 *
	 * @access public
	 * @return string
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * Destructor.
	 *
	 * Disconnects from server.
	 *
	 * @access public
	 */
	public function __destruct() {
		$this->disconnect();
	}

}
