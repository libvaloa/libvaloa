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
 * 2008 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2008
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
/**
 * Call command-line commands gracefully.
 *
 * Example:
 * $output = new Common_Process("command");
 *
 * @package    Kernel
 * @subpackage Common
 */

class Common_Process {

	/**
	 * Command output
	 * @var mixed
	 */
	private $output;

	/**
	 * Execute command through proc_open
	 * @param string $command Command to execute
	 */
	function __construct($command = false) {
		$this->output = new stdClass;
		if($command) {
			$this->output = $this->handleResource($command);
		}
	}

	/**
	 * Execute command through proc_open and return output
	 * @param string $commandCommand to execute
	 * @return string $output
	 */
	private function handleResource($command) {
		$pipes = array();
		$process = proc_open($command, array(
			0 => array("pipe", "r"), 
			1 => array("pipe", "w"), 
			2 => array("pipe", "w")), $pipes);

		if(is_resource($process)) {
			$output = new stdClass;
			fclose($pipes[0]);

			// Messages with return value 1 (success)
			while(!feof($pipes[1])) {
				$output->stdout.= fgets($pipes[1], 1024);
			}
			fclose($pipes[1]);

			// Messages with return value 2 (failure)
			while(!feof($pipes[2])) {
				$output->stderr.=fgets($pipes[2], 1024);
			}
			fclose($pipes[2]);

			// Close process
			proc_close($process);
			return $output;
		} else {
			throw new Exception("Executing command failed.");
		}
	}

	/**
	 * Returns stdout output as string
	 * @return mixed $stdout or false
	 */
	function stdout() {
		if(isset($this->output->stdout)) {
			return $this->output->stdout;
		}
		return false;
	}

	/**
	 * Returns stderr output as string
	 * @return mixed $stderr or false
	 */
	function stderr() {
		if(isset($this->output->stderr)) {
			return $this->output->stderr;
		}
		return false;
	}

	/**
	 * Returns stdout output as string
	 * @return string $output Output as string
	 */
	function __toString() {
		if(isset($this->output->stdout)) {
			return (string) $this->output->stdout;
		}
		return "";
	}

}
