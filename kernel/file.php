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
 * 2010 Joni Halme <jontsa@angelinecms.info>
 *
 * The Initial Developer of the Original Code is
 * Joni Halme <jontsa@angelinecms.info>
 *
 * Portions created by the Initial Developer are Copyright (C) 2010
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
 * Class for creating files, temporary files and file i/o.
 *
 * Examples.
 * 1. create temporary file which will be deleted at the end of pageload
 * $file=new File;
 * $file->write($mysomefile);
 * do_something_with_file((string)$file);
 *
 * 2. create temporary file
 * $tempfilename=(string)new File;
 *
 * @package    Kernel
 */

class File {

	private static $clean = array();
	private $path;
	public static $defaultperm;
	
	/**
	 * Constructor takes file path as parameter or false to create temporary file.
	 * @param mixed $path Optional filepath.
	 */
	public function __construct($path = false) {
		if(!is_string($path) || empty($path)) {
			$this->path = $this->tmpnam();
		} else {
			$this->path = $path;
		}
	}
	
	/**
	 * Casting class to string returns file path.
	 * @return string
	 */
	public function __toString() {
		return $this->path;
	}
	
	/**
	 * Return file contents as string.
	 * @return string
	 * @todo support for file_get_contents options.
	 */
	public function read() {
		if(!file_exists($this->path)) {
			return NULL;
		}
		$data = file_get_contents($this->path);
		if($data === false) {
			throw new Exception("Unable to read file '{$this->path}'");
		}
		return $data;
	}
	
	/**
	 * Write to file. If this is a new file, the default permissions are set to 0600
	 * unless you've set different default permissions using File::$defaultperm.
	 * Note that you can set file permissions later using File::chmod().
	 * @param string $content Content to write
	 * @param mixed $flags Additional flags to file_put_contents()
	 * @see http://www.php.net/file_put_contents
	 */
	public function write($content, $flags = NULL) {
		$new = !file_exists($this->path);
		if(file_put_contents($this->path, $content, $flags) === false) {
			throw new Exception("Unable to write to file '{$this->path}'.");
		}
		if($new && isset(self::$defaultperm)) {
			$this->chmod(self::$defaultperm);
		}
	}
	
	/**
	 * Removes target file. If target is a directory and the directory is empty,
	 * method deletes the directory.
	 */
	public function rm() {
		if(!file_exists($this->path)) {
			return;
		}
		if(is_dir($this->path) && rmdir($this->path) === false) {
			throw new Exception("Unable to delete directory '{$this->path}'.");
		} elseif(unlink($this->path) === false) {
			throw new Exception("Unable to delete file '{$this->path}'.");
		}
	}
	
	/**
	 * Copies file.
	 * @param string $to Path to new file
	 * @return File The target file as File object
	 */
	public function cp($to) {
		if(file_exists($this->path) && copy($this->path, $to) === false) {
			throw new Exception("Unable to copy file to '{$to}'.");
		}
		return new File($to);
	}
	
	/**
	 * Moves/renames file. If file was successfully renamed, the filename
	 * is also updated to current File instance.
	 * @param string $to New filename
	 */
	public function mv($to) {
		if(file_exists($this->path) && rename($this->path, $to) === false) {
			throw new Exception(i18n("Unable to move file to '{$to}'.");
		}
		$this->path = $to;
	}
	
	/**
	 * Change and/or return permissions of file.
	 * @param mixed $oct New file permissions as octal or empty to just return current permissions
	 * @return int New or current permissions of file
	 * @see http://www.php.net/chmod
	 */
	public function chmod($oct = NULL) {
		if(!file_exists($this->path)) {
			return false;
		}
		if($oct !== NULL && chmod($this->path, $oct) === false) {
			throw new Exception("Unable to change permissions for file '{$this->path}'.");
		}

		// This is copied from php.net examples, untested
		return substr(sprintf("%o",fileperms($this->path)),-4);
	}
	
	/**
	 * Returns information about the file/dir.
	 * @return array
	 * @see http://www.php.net/stat
	 */
	public function info() {
		if(!file_exists($this->path)) {
			return false;
		}
		$retval = stat($this->path);
		
		// strip duplicate info from array.
		return array_splice($retval, 13);
	}
	
	/**
	 * Returns type of target path. This has nothing to do with file mimetype.
	 * @return string Either fifo, char, dir, block, link, file, socket or unknown
	 * @see http://www.php.net/filetype
	 */
	public function type() {
		if(!file_exists($this->path)) {
			return false;
		}
		return filetype($this->path);
	}

	/**
	 * Internal function used to clear temporary files created with File.
	 */
	public static function cleanTmp() {
		foreach(self::$clean as &$file) {
			if(is_file($file)) {
				unlink($file);
			}
		}
	}
	
	/**
	 * Creates temporary file which will be automatically deleted after page is loaded.
	 * @return string Path to file
	 */
	private function tmpnam() {
		$file = tempnam(sys_get_temp_dir(), "");
		if(!$file) {
			throw new Exception("Failed to create temporary filename.");
		}
		if(!isset(self::$autoclean)) {
			register_shutdown_function(array("File","cleanTmp"));
		}
		self::$clean[] = $file;
		return $file;
	}
	
}
