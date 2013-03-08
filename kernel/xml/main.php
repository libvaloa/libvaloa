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
 * 2004,2005,2006,2007,2008,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2006,2007,2008,2013
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2004 Mikko Ruohola <polarfox@polarfox.net>
 * 2005,2006,2007 Joni Halme <jontsa@angelinecms.info>
 * 2005 J-P Vieresjoki <jp@angelinecms.info>
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
 * XML parser.
 *
 * Creates XML data from PHP objects. Objects can contain more objects, arrays, strings etc.
 *
 * @package    Kernel
 * @subpackage Xml
 * @todo       Attribute support
 */

class Xml {

	/**
	 * Instance of DomDocument.
	 *
	 * @access public
	 * @var DomDocument
	 */
	protected $dom;

	/**
	 * Constructor.
	 *
	 * Fires up DomDocument.
	 *
	 * @todo   Add support for multiple encodings
	 * @access public
	 * @param  string $from XML root element name or DomDocument object with XML-data
	 */
	public function __construct($from = false) {
		if($from instanceof DomDocument) {
			$this->dom = $from;
		} else {
			if(!$from || !$this->validateNodeName($from)) {
				$from = "root";
			}
			$this->dom = new DomDocument("1.0", "utf-8");
			$this->dom->preserveWhiteSpace = false;
			$this->dom->resolveExternals = false;
			$this->dom->formatOutput = false;
			$this->dom->appendChild($this->dom->createElement($from));
		}
	}

	/**
	 * Checks if strings is valid as XML node name.
	 *
	 * @access public
	 * @param  string $node Node name
	 * @return bool True if string can be used as node name, otherwise false.
	 */
	public static function validateNodeName($node) {
		if(empty($node) || is_numeric(substr($node, 0, 1)) || substr(strtolower($node), 0, 3) === "xml" || strstr($node, " ")) {
			return false;
		}
		return true;
	}

	/**
	 * Returns XML data as string.
	 *
	 * @access public
	 * @return string
	 */
	public function toString() {	
		return (string) $this->dom->saveXML();
	}
	
	public function toObject($path = "/*") {
		return $this->nodeToObject($path);
	}
	
	public function toDom($clone = true) {
		return $clone?$this->dom->cloneNode(true):$this->dom;
	}

	/**
	 * Adds an object to XML tree.
	 *
	 * $object parameter can also be an array but array keys must be strings.
	 *
	 * @access  public
	 * @param   object $object XMLVars object or array
	 * @param   boolean $cdata Defines if we create CDATA section to XMLtree
	 */
	public function addObject($object, $cdata = false, $path = false) {
		if(is_object($object) || is_array($object)) {
			$root = $this->dom->firstChild;
			if($path) {
				$xp = new DomXPath($this->dom);
				$items = $xp->query($path);
				if($items && $items->length > 0) {
					$root = $items->item(0);
				}
			}
			if($object instanceof DomDocument) {
				$object = new XML($object);
			}
			if($object instanceof XML) {
				$object = $object->toObject();
			}
			$this->objectToNode($object, $root, $cdata);
		}
	}

	/**
	* Adds XML from file.
	*
	* @param string $file Target file
	* @param mixed $path False or xpath string which will be returned as object from file.
	* @return mixed
	*/
	public static function fromFile($file, $path = false) {
		if(!is_file($file)) {
			throw new Exception("Source file for XML data was not found.");
		}
		$xml = new DomDocument;
		$xml->load($file);
		$xml = new XML($xml);
		return $path?$xml->toObject($path):$xml;
		
	}

	/**
	* Adds XML from string.
	*
	* @param string $string XML as string
	* @param mixed $path False or xpath string which will be returned as object from XML string.
	* @return mixed
	*/
	public static function fromString($string, $path = false) {
		$xml = new DomDocument;
		$xml->loadXML($string);
		$xml = new XML($xml);
		return $path?$xml->toObject($path):$xml;
	}

	/**
	 * Parses object or array and converts it to XML.
	 *
	 * @todo   Validate $key (would it create too much overhead?)
	 * @access private
	 * @param  object $obj Object to parse. In theory arrays will work if keys are not integers.
	 * @param  object $top Toplevel DOM object
	 * @param  boolean $cdata Creates CDATASection if true
	 */
	protected function objectToNode($obj, $top, $cdata) {
		foreach($obj as $key=>$val) {
			if(is_array($val) || is_object($val) && $val instanceof ArrayAccess) {
				foreach($val as $v) {
					$item = $this->dom->createElement($key);
					if(is_object($v)) {
						$this->objectToNode($v, $item, $cdata);
					} elseif($cdata) {
						$item->appendChild($this->dom->createCDATASection($v));
					} elseif(is_string($v) || is_int($v)) {
						$item->appendChild($this->dom->createTextNode($v));
					}
					$top->appendChild($item);
				}
			} elseif(is_object($val)) {
				if(is_numeric($key)) {
					$item = $this->dom->createElement($top->nodeName);
				} else {
					$item = $this->dom->createElement($key);
				}
				$this->objectToNode($val, $item, $cdata);
				$top->appendChild($item);
			} else {
				$item = $this->dom->createElement($key);
				if($cdata) {
					$item->appendChild($this->dom->createCDATASection($val));
				} else {
					$item->appendChild($this->dom->createTextNode($val));
				}
				$top->appendChild($item);
			}
		}
	}

	/**
	 * Parses XML and converts it to object
	 *
	 * @access private
	 * @param  string $path XPath to XML element to return as object
	 * @param  DomXPath $xpath DomXPath object from current DomDocument
	 * @return mixed Either array, stdClass or false on error
	 */
	protected function nodeToObject($path, $xpath = false) {
		if(!$xpath instanceof DomXPath) {
			$xpath = new DomXPath($this->dom);
		}
		$items = $xpath->query("{$path}");
		if(!is_object($items)) {
			return false;
		}
		if($items->length > 1) {
			$retval = array();
			foreach($items as $k=>$item) {
				array_push($retval, $this->nodeToObject("{$path}[".($k+1)."]", $xpath));
			}
		} else {
			$retval = new stdClass;
			$nodelist = $xpath->query("{$path}/*");
			foreach($nodelist as $item) {
				if(isset($retval->{$item->nodeName}) && is_object($retval->{$item->nodeName})) {
					$retval->{$item->nodeName} = array(clone $retval->{$item->nodeName});
				}
				$tmp = $xpath->query("{$path}/{$item->nodeName}/*");
				if($tmp->length > 0) {
					if(isset($retval->{$item->nodeName})) {
						$count = count($retval->{$item->nodeName})+1;
						array_push($retval->{$item->nodeName}, $this->nodeToObject("{$path}/{$item->nodeName}[{$count}]", $xpath));
					} else {
						$retval->{$item->nodeName} = $this->nodeToObject("{$path}/{$item->nodeName}[1]", $xpath);
					}
				} else {
					if(isset($retval->{$item->nodeName})) {
						if(is_array($retval->{$item->nodeName})) {
							array_push($retval->{$item->nodeName}, $item->nodeValue);
						} else {
							if(isset($tmpval)) {
								$retval->{$item->nodeName} = array($tmpval);
								unset($tmpval);
							}
							array_push($retval->{$item->nodeName}, $item->nodeValue);
						}
					} else {
						$retval->{$item->nodeName} = $item->nodeValue;
						$tmpval = $item->nodeValue;
					}
				}
			}
		}
		return $retval;
	}

	/**
	 * self to string conversion.
	 *
	 * @access public
	 * @return string XML data as string
	 */
	public function __toString() {
		return (string) $this->toString();
	}

}
