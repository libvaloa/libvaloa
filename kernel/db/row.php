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
 * 2010 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
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
 * Manage table row structures easily.
 * 
 * Examples:
 * Insert new row to table 'People' with fields 'Firstname', 'Lastname' and 'Phone':
 *
 * $data = new DB_Row("people");
 * [[ $data->columns(array("firstname"=>"","lastname"=>"","phone"=>0)); ]]
 * $data->firstname = "John";
 * $data->lastname = "Doe";
 * $data->phone = "0123456789";
 * $id = $data->save();
 * 
 * Now edit newly inserted row, as John got a new phone number!
 * 
 * $data = new DB_Row("people");
 * [[ $data->columns(array("phone"=>0)); ]]
 * $data->byID($id);
 * $data->phone = "0404020230";
 * $data->save();
 *
 * @package    Kernel
 * @subpackage DB
 * @uses       DB
 */

if(!defined('LIBVALOA_DB')) DEFINE('LIBVALOA_DB', 'mysql');
 
class DB_Row {

	private $struct;
	private $primaryKey = "id"; // name of the primary key field in table
	private $lateload = false;
	private $data;
	private $modified = false;

	/**
	 * Constructor - give the name of the target table 
	 * 
	 * @param string $structName Target table
	 */
	public function __construct($table = false) {
		if(!$table) {
			throw new Exception("Data structure name needed.");
		}
		$this->struct = $table;
		$this->data = new stdClass;
	}
	
	/**
	 * Set primary key field, defaults to id
	 *
	 * @param string $key Primary key field
	 */
	public function primarykey($key) {
		$this->primaryKey = $key;
	}
	
	/**
	 * Force validating of column names. Only names set in $columns array will 
	 * be included in the query.
	 *
	 * @param array $columns Allowed table columns as array
	 */
	public function columns($columns) {
		$this->data = (object) $columns;
	}	

	private function detectColumns() {
		// Columns already set
		if(isset($this->data->{$this->primaryKey})) {
			return;
		}

		// Detect columns
		switch(LIBVALOA_DB) {
			case 'mysql';
			case 'postgres';
			case 'sqlite';
			default;
				// Detect columns
				$query = "SHOW COLUMNS FROM ?";
				$stmt = db()->prepare($query);
				$stmt->set($this->struct);
				try {
					$stmt->execute();
					Debug::d($row);

					// TODO: Could add typecasting here based on $row->Type
					foreach($stmt as $row) {
						Debug::d($row);
						$columns[$row->Field] = "";
					}
					if(isset($columns)) {
						$this->columns($columns);
					}
				} catch(Exception $e) {
				}
			break;
		}
	}
	
	/**
	 * Get a column
	 *
	 * @param string $field
	 */ 
	public function __get($field) {
		$this->detectColumns();
		if(is_numeric($this->lateload)) {
			$this->_byID();
		}
		return isset($this->data->$field)?$this->data->$field:NULL;
	}

	/**
	 * Set a column
	 * 
	 * @param string $key
	 * @param string $value
	 */
	public function __set($key, $value) {
		$this->detectColumns();
		if(is_numeric($this->lateload)) {
			$this->_byID();
		}
		if($key == $this->primaryKey) {
			return;
		}
		foreach($this->data as $_tmpk => $_tmpv) {
			if($_tmpk == $key) {
				if($value !== $_tmpv) {
					$this->data->$key = $value;
					$this->modified = true;
				}
			}
		}
	}
	
	/**
	 * Load row by ID
	 * 
	 * @param int $id
	 */
	public function byID($id) {
		$this->lateload = $id;
	}
	
	private function _byID() {
		$this->detectColumns();
		$stmt = db()->prepare("SELECT * FROM {$this->struct} WHERE {$this->primaryKey}=?");
		$stmt->set((int) $this->lateload);
		$stmt->execute();
		$row = $stmt->fetch();
		if($row === false) {
			throw new Exception("Selected row does not exist.");
		}
		$this->data = $row;
		$this->lateload = false;
	}
	
	/**
	 * Insert/update row
	 */
	public function save() {
		if(is_numeric($this->lateload)) {
			$this->_byID();
		}
		if($this->modified === false) {
			return;
		}
		if(!isset($this->data->{$this->primaryKey})) {
			$this->data->{$this->primaryKey} = NULL;
		}
		$fields = $values = $updates="";
		foreach($this->data as $key=>&$val) {
			$fields[$key] = "?";
		}
		if(!is_numeric($this->data->{$this->primaryKey})) {
			$query = "INSERT INTO {$this->struct} (".implode(",",array_keys($fields)).") VALUES (".implode(",", $fields).")";
			if(config()->dbconn === "postgres") {
				$query.= " RETURNING {$this->primaryKey}";
			}
		} else {
			$query = "UPDATE {$this->struct} SET ".implode("=?,",array_keys($fields))."=? WHERE {$this->primaryKey}=?";
		}
		unset($fields);
		$stmt = db()->prepare($query);
		foreach($this->data as &$val) {
			$stmt->set($val);
		}
		if(is_numeric($this->data->{$this->primaryKey})) {
			$stmt->set((int) $this->data->{$this->primaryKey});
		}
		$stmt->execute();
		if(!is_numeric($this->data->{$this->primaryKey})) {
			$this->data->{$this->primaryKey} = (int) config()->dbconn === "postgres"?$stmt->fetchColumn():db()->lastInsertID();
		}
		return $this->data->{$this->primaryKey};
	}
	
	/**
	 * Delete row
	 */
	public function delete() {
		if(is_numeric($this->lateload)) {
			$this->_byID();
		}
		if(!isset($this->data->{$this->primaryKey}) || !is_numeric($this->data->{$this->primaryKey})) {
			return NULL;
		}
		$query = "DELETE FROM {$this->struct} WHERE {$this->primaryKey}=?";
		$stmt = db()->prepare($query);
		$stmt->set((int) $this->data->{$this->primaryKey});
		$stmt->execute();
	}

}
