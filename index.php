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
 * 2004,2005,2007,2008,2009,2013 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are Copyright (C) 2004,2005,2007,2008,2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * 2005 Ville Heimonen <viltsu@angelinecms.info>
 * 2007 Mikko Ruohola <polarfox@polarfox.net>
 * 2008,2009 Joni Halme <jontsa@angelinecms.info>
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
 * libvaloa bootstrap-file.
 */

// Show errors/warnings during startup.
error_reporting(E_ALL | E_STRICT);

// Include separate config-file if one exists.
if(file_exists("config.php")) {
	require_once("config.php");
}

// Predefine some core vars
if(!defined("LIBVALOA_INSTALLPATH"))    DEFINE("LIBVALOA_INSTALLPATH", ".");
if(!defined("LIBVALOA_EXTENSIONSPATH")) DEFINE("LIBVALOA_EXTENSIONSPATH", LIBVALOA_INSTALLPATH."/extensions");
if(!defined("LIBVALOA_DEBUG"))          DEFINE("LIBVALOA_DEBUG", 0);
if(!defined("LIBVALOA_LAYOUT"))         DEFINE("LIBVALOA_LAYOUT", 'default');

// Default database settings
if(!defined("LIBVALOA_DB"))             DEFINE("LIBVALOA_DB", "sqlite");
if(!defined("LIBVALOA_DB_SERVER"))      DEFINE("LIBVALOA_SERVER", "localhost");
if(!defined("LIBVALOA_DB_USERNAME"))    DEFINE("LIBVALOA_USERNAME", "");
if(!defined("LIBVALOA_DB_PASSWORD"))    DEFINE("LIBVALOA_PASSWORD", "");
if(!defined("LIBVALOA_DB_DATABASE"))    DEFINE("LIBVALOA_DATABASE", LIBVALOA_INSTALLPATH."/db/default.sqlite");

if(!is_readable(LIBVALOA_INSTALLPATH."/kernel/main.php")) {
	die("libvaloa kernel is missing.");
}

// Set include path for autoloader
if(defined("ZEND_PATH")) {
	set_include_path(ZEND_PATH.PATH_SEPARATOR.get_include_path());
}

set_include_path(LIBVALOA_INSTALLPATH."/kernel/".PATH_SEPARATOR.get_include_path());
if(file_exists(LIBVALOA_EXTENSIONSPATH."/kernel")) {
	set_include_path(LIBVALOA_EXTENSIONSPATH."/kernel/".PATH_SEPARATOR.get_include_path());
}

// Include core classes and functions.
require_once(LIBVALOA_INSTALLPATH."/kernel/main.php");

// Load the kernel.
new libvaloa;

// Wake up frontcontroller and load module.
new Controller;
