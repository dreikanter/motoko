<?php
/*
 * Project:	template_lite, a smarter template engine
 * File:	class.template.php
 * Author:	Paul Lockaby <paul@paullockaby.com>, Mark Dickenson <akapanamajack@sourceforge.net>
 * Copyright:	2003,2004,2005 by Paul Lockaby, 2005,2006 Mark Dickenson
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * The latest version of template_lite can be obtained from:
 * http://templatelite.sourceforge.net
 *
 */

if (!defined('TEMPLATE_LITE_DIR')) {
	define('TEMPLATE_LITE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}

class Template_Lite {
	// public configuration variables
	var $left_delimiter			= "{";		// the left delimiter for template tags
	var $right_delimiter			= "}";		// the right delimiter for template tags
	var $cache			= false;	// whether or not to allow caching of files
	var $force_compile		= false;	// force a compile regardless of saved state
	var $template_dir		= "templates";	// where the templates are to be found
	var $plugins_dir			= array("plugins");	// where the plugins are to be found
	var $compile_dir		= "compiled";	// the directory to store the compiled files in
	var $config_dir			= "templates";	// where the config files are
	var $cache_dir			= "cached";	// where cache files are stored
	var $config_overwrite		= false;
	var $config_booleanize		= true;
	var $config_fix_new_lines	= true;
	var $config_read_hidden		= true;
	var $cache_lifetime		= 0;		// how long the file in cache should be considered "fresh"
	var $encode_file_name		=	true;	// Set this to false if you do not want the name of the compiled/cached file to be md5 encoded.
	var $php_extract_vars		=	false;	// Set this to true if you want the $this->_tpl variables to be extracted for use by PHP code inside the template.
	var $reserved_template_varname = "templatelite";
	var $default_modifiers		= array();
	var $debugging	   =  false;

	// gzip output configuration
	var $send_now			=  1;
	var $force_compression	=  0;
	var $compression_level	=  9;
	var $enable_gzip		=  1;

	// private internal variables
	var $_vars		= array();	// stores all internal assigned variables
	var $_confs		= array();	// stores all internal config variables
	var $_plugins		= array(	   'modifier'	  => array(),
									   'function'	  => array(),
									   'block'		 => array(),
									   'compiler'	  => array(),
									   'resource'	  => array(),
									   'prefilter'	 => array(),
									   'postfilter'	=> array(),
									   'outputfilter'  => array());
	var $_linenum		= 0;		// the current line number in the file we are processing
	var $_file		= "";		// the current file we are processing
	var $_config_obj	= null;
	var $_compile_obj	= null;
	var $_cache_id		= null;
	var $_cache_dir		= "";		// stores where this specific file is going to be cached
	var $_cache_info	= array('config' => array(), 'template' => array());
	var $_sl_md5		= '39fc70570b8b60cbc1b85839bf242aff';
	var $_version		= 'V1.80 Template Lite 22 April 2006  (c) 2005,2006 Mark Dickenson. All rights reserved. Released LGPL.';
	var $_config_module_loaded = false;
	var $_templatelite_debug_info	= array();
	var $_templatelite_debug_loop	= false;
	var $_templatelite_debug_dir	= "";
	var $_inclusion_depth	  = 0;

	function Template_Lite()
	{
	}

	function load_filter($type, $name)
	{
		switch ($type) {
			case 'output':
				include_once( $this->_get_plugin_dir($type . "filter." . $name . ".php") . $type . "filter." . $name . ".php");
				$this->_plugins['outputfilter'][$name] = "template_" . $type . "filter_" . $name;
			   break;
			case 'pre':
			case 'post':
				if (!isset($this->_plugins[$type . 'filter'][$name]))
					$this->_plugins[$type . 'filter'][$name] = "template_" . $type . "filter_" . $name;
				break;
		}
	}

	function assign($key, $value = null) {
		if (is_array($key)) {
			foreach($key as $var => $val)
				if ($var != "")
					$this->_vars[$var] = $val;
		} else {
			if ($key != "")
				$this->_vars[$key] = $value;
		}
	}

	function assign_by_ref($key, $value = null) {
		if ($key != '')
			$this->_vars[$key] = &$value;
	}

	function assign_config($key, $value = null) {
		if (is_array($key)) {
			foreach($key as $var => $val)
				if ($var != "")
					$this->_confs[$var] = $val;
		} else {
			if ($key != "")
				$this->_confs[$key] = $value;
		}
	}

	function append($key, $value=null, $merge=false)
	{
		if (is_array($key)) {
			foreach ($key as $_key => $_value) {
				if ($_key != '') {
					if(!@is_array($this->_vars[$_key])) {
						settype($this->_vars[$_key],'array');
					}
					if($merge && is_array($_value)) {
						foreach($_value as $_mergekey => $_mergevalue) {
							$this->_vars[$_key][$_mergekey] = $_mergevalue;
						}
					} else {
						$this->_vars[$_key][] = $_value;
					}
				}
			}
		} else {
			if ($key != '' && isset($value)) {
				if(!@is_array($this->_vars[$key])) {
					settype($this->_vars[$key],'array');
				}
				if($merge && is_array($value)) {
					foreach($value as $_mergekey => $_mergevalue) {
						$this->_vars[$tpl_var][$_mergekey] = $_mergevalue;
					}
				} else {
					$this->_vars[$key][] = $value;
				}
			}
		}
	}

	function append_by_ref($key, &$value, $merge=false)
	{
		if ($key != '' && isset($value)) {
			if(!@is_array($this->_vars[$key])) {
			 settype($this->_vars[$key],'array');
			}
			if ($merge && is_array($value)) {
				foreach($value as $_key => $_val) {
					$this->_vars[$key][$_key] = &$value[$_key];
				}
			} else {
				$this->_vars[$key][] = &$value;
			}
		}
	}

	function clear_assign($key = null) {
		if ($key == null) {
			$this->_vars = array();
		} else {
			if (is_array($key)) {
				foreach($key as $index => $value)
					if (in_array($value, $this->_vars))
						unset($this->_vars[$index]);
			} else {
				if (in_array($key, $this->_vars))
					unset($this->_vars[$index]);
			}
		}
	}

	function clear_all_assign()
	{
		$this->_vars = array();
	}

	function clear_config($key = null) {
		if ($key == null) {
			$this->_conf = array();
		} else {
			if (is_array($key)) {
				foreach($key as $index => $value)
					if (in_array($value, $this->_conf))
						unset($this->_conf[$index]);
			} else {
				if (in_array($key, $this->_conf))
					unset($this->_conf[$key]);
			}
		}
	}

	function &get_template_vars($key = null) {
		if ($key == null) {
			return $this->_vars;
		} else {
			if (isset($this->_vars[$key]))
				return $this->_vars[$key];
			else
				return null;
		}
	}

	function &get_config_vars($key = null) {
		if ($key == null) {
			return $this->_confs;
		} else {
			if (isset($this->_confs[$key]))
				return $this->_confs[$key];
			else
				return null;
		}
	}

	function clear_compiled_tpl($file = null) {
		$this->_destroy_dir($file, null, $this->_get_dir($this->compile_dir));
	}

	function clear_cache($file = null, $cache_id = null, $compile_id = null, $exp_time = null) {
		if (!$this->cache)
			return;
		$this->_destroy_dir($file, $cache_id, $this->_get_dir($this->cache_dir));
	}

	function clear_all_cache($exp_time = null) {
		$this->clear_cache();
	}

	function is_cached($file, $cache_id = null) {
		if (!$this->force_compile && $this->cache && $this->_is_cached($file, $cache_id)) {
			return true;
		} else {
			return false;
		}
	}

	function register_modifier($modifier, $implementation) {
		$this->_plugins['modifier'][$modifier] = $implementation;
	}

	function unregister_modifier($modifier) {
		if (isset($this->_plugins['modifier'][$modifier]))
			unset($this->_plugins['modifier'][$modifier]);
	}

	function register_function($function, $implementation) {
		$this->_plugins['function'][$function] = $implementation;
	}

	function unregister_function($function) {
		if (isset($this->_plugins['function'][$function]))
			unset($this->_plugins['function'][$function]);
	}

	function register_block($function, $implementation) {
		$this->_plugins['block'][$function] = $implementation;
	}

	function unregister_block($function) {
		if (isset($this->_plugins['block'][$function]))
			unset($this->_plugins['block'][$function]);
	}

	function register_compiler($function, $implementation) {
		$this->_plugins['compiler'][$function] = $implementation;
	}

	function unregister_compiler($function) {
		if (isset($this->_plugins['compiler'][$function]))
			unset($this->_plugins['compiler'][$function]);
	}

	function register_prefilter($function)
	{
		$_name = (is_array($function)) ? $function[1] : $function;
		$this->_plugins['prefilter'][$_name] = $_name;
	}

	function unregister_prefilter($function)
	{
		if (isset($this->_plugins['prefilter'][$function]))
			unset($this->_plugins['prefilter'][$function]);
	}

	function register_postfilter($function)
	{
		$_name = (is_array($function)) ? $function[1] : $function;
		$this->_plugins['postfilter'][$_name] = $_name;
	}

	function unregister_postfilter($function)
	{
		if (isset($this->_plugins['postfilter'][$function]))
			unset($this->_plugins['postfilter'][$function]);
	}

	function register_outputfilter($function)
	{
		$_name = (is_array($function)) ? $function[1] : $function;
		$this->_plugins['outputfilter'][$_name] = $_name;
	}

	function unregister_outputfilter($function)
	{
		if (isset($this->_plugins['outputfilter'][$function]))
			unset($this->_plugins['outputfilter'][$function]);
	}
/*
	function register_resource($type, $functions)
	{
		$this->_plugins['resource'][$type] = $functions;
	}

	function unregister_resource($type)
	{
		if (isset($this->_plugins['resource'][$type]))
			unset($this->_plugins['resource'][$type]);
	}
*/
	function template_exists($file) {
		if (file_exists($this->_get_dir($this->template_dir).$file)) {
			return true;
		} else {
			return false;
		}
	}

	function display($file, $cache_id = null) {
		$this->fetch($file, $cache_id, true);
	}

	function fetch($file, $cache_id = null, $display = false)
	{
		if ($this->debugging) {
			$this->_templatelite_debug_info[] = array('type'	  => 'template',
												'filename'  => $file,
												'depth'	 => 0,
												'exec_time' => array_sum(explode(' ', microtime())) );
			$included_tpls_idx = count($this->_templatelite_debug_info) - 1;
		}

		$this->_cache_id = $cache_id;
		$this->template_dir = $this->_get_dir($this->template_dir);
		$this->compile_dir = $this->_get_dir($this->compile_dir);
		if ($this->cache)
			$this->_cache_dir = $this->_build_dir($this->cache_dir, $this->_cache_id);

		$name = ($this->encode_file_name) ? md5($this->template_dir.$file).'.php' : str_replace(".", "_", str_replace("/", "_", $file)).'.php';

		$this->_error_level = $this->debugging ? error_reporting() : error_reporting(error_reporting() & ~E_NOTICE);
//		$this->_error_level = error_reporting(E_ALL);

		if (!$this->force_compile && $this->cache && $this->_is_cached($file, $cache_id)) {
			ob_start();
			include($this->_cache_dir.$name);
			$output = ob_get_contents();
			ob_end_clean();
			$output = substr($output, strpos($output, "\n") + 1);
		} else {
			$output = $this->_fetch_compile($file, $cache_id);
			if ($this->cache) {
				$f = fopen($this->_cache_dir.$name, "w");
				fwrite($f, serialize($this->_cache_info) . "\n$output");
				fclose($f);
			}
		}

		if (strpos($output, $this->_sl_md5) !== false) {
			preg_match_all('!' . $this->_sl_md5 . '{_run_insert (.*)}' . $this->_sl_md5 . '!U',$output,$_match);
			foreach($_match[1] as $value) {
				$arguments = unserialize($value);
				$output = str_replace($this->_sl_md5 . '{_run_insert ' . $value . '}' . $this->_sl_md5, call_user_func_array('insert_' . $arguments['name'], array((array)$arguments, $this)), $output);
			}
		}

		foreach ($this->_plugins['outputfilter'] as $function) {
			$output = $function($output, $this);
		}

		error_reporting($this->_error_level);

	if ($this->debugging) {
		$this->_templatelite_debug_info[$included_tpls_idx]['exec_time'] = array_sum(explode(' ', microtime())) - $this->_templatelite_debug_info[$included_tpls_idx]['exec_time'];
	}

		if ($display) {
			echo $output;
			if($this->debugging && !$this->_templatelite_debug_loop)
			{
				$this->debugging = false;
				if(!function_exists("generate_debug_output"))
				{
					require_once(TEMPLATE_LITE_DIR . "internal/template.generate_debug_output.php");
				}
				$debug_output = generate_debug_output($this);
				$this->debugging = true;
				echo $debug_output;
			}
		} else {
			return $output;
		}
	}

	function config_load($file, $section_name = null, $var_name = null) {
		require_once(TEMPLATE_LITE_DIR . "internal/template.config_loader.php");
	}

	function _is_cached($file, $cache_id) {
		$this->_cache_dir = $this->_get_dir($this->cache_dir, $cache_id);
		$this->config_dir = $this->_get_dir($this->config_dir);
		$this->template_dir = $this->_get_dir($this->template_dir);

		$name = ($this->encode_file_name) ? md5($this->template_dir.$file).'.php' : str_replace(".", "_", str_replace("/", "_", $file)).'.php';

		if (file_exists($this->_cache_dir.$name) && (((time() - filemtime($this->_cache_dir.$name)) < $this->cache_lifetime) || $this->cache_lifetime == -1) && (filemtime($this->_cache_dir.$name) > filemtime($this->template_dir.$file))) {
			$fh = fopen($this->_cache_dir.$name, "r");
			if (!feof($fh) && ($line = fgets($fh, filesize($this->_cache_dir.$name)))) {
				$includes = unserialize($line);
				if (isset($includes['template']))
					foreach($includes['template'] as $value)
						if (!(file_exists($this->template_dir.$value) && (filemtime($this->_cache_dir.$name) > filemtime($this->template_dir.$value))))
							return false;
				if (isset($includes['config']))
					foreach($includes['config'] as $value)
						if (!(file_exists($this->config_dir.$value) && (filemtime($this->_cache_dir.$name) > filemtime($this->config_dir.$value))))
							return false;
			}
			fclose($fh);
		} else {
			return false;
		}
		return true;
	}

	function _fetch_compile_include($_templatelite_include_file, $_templatelite_include_vars)
	{
		if(!function_exists("fetch_compile_include"))
		{
			require_once(TEMPLATE_LITE_DIR . "internal/template.fetch_compile_include.php");
		}
		return fetch_compile_include($_templatelite_include_file, $_templatelite_include_vars, $this);
	}

	function _fetch_compile($file) {
		$this->template_dir = $this->_get_dir($this->template_dir);

		$name = ($this->encode_file_name) ? md5($this->template_dir.$file).'.php' : str_replace(".", "_", str_replace("/", "_", $file)).'.php';

		if ($this->cache) {
			array_push($this->_cache_info['template'], $file);
		}

		if (!$this->force_compile && file_exists($this->compile_dir.'c_'.$name) && (filemtime($this->compile_dir.'c_'.$name) > filemtime($this->template_dir.$file))) {
			ob_start();
			include($this->compile_dir.'c_'.$name);
			$output = ob_get_contents();
			ob_end_clean();
			error_reporting($this->_error_level);
			return $output;
		}

		if ($this->template_exists($file)) {
			$f = fopen($this->template_dir.$file, "r");
			$size = filesize($this->template_dir.$file);
			if ($size > 0) {
				$file_contents = fread($f, filesize($this->template_dir.$file));
			} else {
				$file_contents = "";
			}
			$this->_file = $file;
			fclose($f);
		} else {
			$this->trigger_error("file '$file' does not exist", E_USER_ERROR);
		}

		if (!is_object($this->_compile_obj)) {
			require_once(TEMPLATE_LITE_DIR . "class.compiler.php");
			$this->_compile_obj = new Template_Lite_Compiler;
		}
		$this->_compile_obj->left_delimiter = $this->left_delimiter;
		$this->_compile_obj->right_delimiter = $this->right_delimiter;
		$this->_compile_obj->plugins_dir = &$this->plugins_dir;
		$this->_compile_obj->template_dir = &$this->template_dir;
		$this->_compile_obj->_vars = &$this->_vars;
		$this->_compile_obj->_confs = &$this->_confs;
		$this->_compile_obj->_plugins = &$this->_plugins;
		$this->_compile_obj->_linenum = &$this->_linenum;
		$this->_compile_obj->_file = &$this->_file;
		$this->_compile_obj->php_extract_vars = &$this->php_extract_vars;
		$this->_compile_obj->reserved_template_varname = &$this->reserved_template_varname;
		$this->_compile_obj->default_modifiers = $this->default_modifiers;

		$output = $this->_compile_obj->_compile_file($file_contents);

		$f = fopen($this->compile_dir.'c_'.$name, "w");
		fwrite($f, $output);
		fclose($f);

		ob_start();
		eval(' ?>' . $output . '<?php ');
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	function _run_modifier() {
		$arguments = func_get_args();
		list($variable, $modifier, $_map_array) = array_splice($arguments, 0, 3);
		array_unshift($arguments, $variable);
		if ($_map_array && is_array($variable))
			foreach($variable as $key => $value)
				$variable[$key] = call_user_func_array($this->_plugins["modifier"][$modifier], $arguments);
		else
			$variable = call_user_func_array($this->_plugins["modifier"][$modifier], $arguments);
		return $variable;
	}

	function _run_insert($arguments) {
		if ($this->cache) {
			return $this->_sl_md5 . '{_run_insert ' . serialize((array)$arguments) . '}' . $this->_sl_md5;
		} else {
			if (!function_exists('insert_' . $arguments['name']))
				$this->trigger_error("function 'insert_" . $arguments['name'] . "' does not exist in 'insert'", E_USER_ERROR);
			if (isset($arguments['assign']))
				$this->assign($arguments['assign'], call_user_func_array('insert_' . $arguments['name'], array((array)$arguments, $this)));
			else
				return call_user_func_array('insert_' . $arguments['name'], array((array)$arguments, $this));
		}
	}

	function _get_dir($dir, $id = null) {
		if (empty($dir))
			$dir = '.';
		if (substr($dir, -1) != DIRECTORY_SEPARATOR)
			$dir .= DIRECTORY_SEPARATOR;
		if (!empty($id)) {
			$_args = explode('|', $id);
			if (count($_args) == 1 && empty($_args[0]))
				return $dir;
			foreach($_args as $value)
				$dir .= $value.DIRECTORY_SEPARATOR;
		}
		return $dir;
	}

	function _get_plugin_dir($plugin_name) {
		static $_path_array = null;

		$plugin_dir_path = "";
		$_plugin_dir_list = is_array($this->plugins_dir) ? $this->plugins_dir : (array)$this->plugins_dir;
		foreach ($_plugin_dir_list as $_plugin_dir) {
			if (!preg_match("/^([\/\\\\]|[a-zA-Z]:[\/\\\\])/", $_plugin_dir)) {
				// path is relative
				if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $_plugin_dir . DIRECTORY_SEPARATOR . $plugin_name)) {
					$plugin_dir_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $_plugin_dir . DIRECTORY_SEPARATOR;
					break;
				}
			}
			else
			{
				// path is absolute
				if(!isset($_path_array)) {
					$_ini_include_path = ini_get('include_path');

					if(strstr($_ini_include_path,';')) {
						// windows pathnames
						$_path_array = explode(';',$_ini_include_path);
					} else {
						$_path_array = explode(':',$_ini_include_path);
					}
				}

				if(!in_array($_plugin_dir,$_path_array)) 
					array_unshift($_path_array,$_plugin_dir); 

				foreach ($_path_array as $_include_path) {
					if (file_exists($_include_path . DIRECTORY_SEPARATOR . $plugin_name)) {
						   $plugin_dir_path = $_include_path . DIRECTORY_SEPARATOR;
						break 2;
					}
				}
			}
		}
		return $plugin_dir_path;
	}

//	function _parse_resource_link($resource_link)
//	{
//		$stuffing = "file:/this/is/the/time_5-23.tpl";
//		$stuffing_data = explode(":", $stuffing);
//		preg_match_all('/(?:([0-9a-z._-]+))/i', $stuffing, $stuff);
//		print_r($stuff);
//		echo "<br>Path: " . str_replace($stuff[0][count($stuff[0]) - 1], "", $stuffing);
//		echo "<br>Filename: " . $stuff[0][count($stuff[0]) - 1];
//	}

	function _build_dir($dir, $id) {
		$_args = explode('|', $id);
		if (count($_args) == 1 && empty($_args[0]))
			return $this->_get_dir($dir);
		$_result = $this->_get_dir($dir);
		foreach($_args as $value) {
			$_result .= $value.DIRECTORY_SEPARATOR;
			if (!is_dir($_result)) @mkdir($_result, 0777);
		}
		return $_result;
	}

	function _destroy_dir($file, $id, $dir) {
		if ($file == null && $id == null) {
			if (is_dir($dir))
				if($d = opendir($dir))
					while(($f = readdir($d)) !== false)
						if ($f != '.' && $f != '..')
							$this->_rm_dir($dir.$f.DIRECTORY_SEPARATOR);
		} else {
			if ($id == null) {
				$this->template_dir = $this->_get_dir($this->template_dir);

				$name = ($this->encode_file_name) ? md5($this->template_dir.$file).'.php' : str_replace(".", "_", str_replace("/", "_", $file)).'.php';
				@unlink($dir.$name);
			} else {
				$_args = "";
				foreach(explode('|', $id) as $value)
					$_args .= $value.DIRECTORY_SEPARATOR;
				$this->_rm_dir($dir.DIRECTORY_SEPARATOR.$_args);
			}
		}
	}

	function _rm_dir($dir) {
		if (is_file(substr($dir, 0, -1))) {
			@unlink(substr($dir, 0, -1));
			return;
		}
		if ($d = opendir($dir)) {
			while(($f = readdir($d)) !== false) {
				if ($f != '.' && $f != '..') {
					$this->_rm_dir($dir.$f.DIRECTORY_SEPARATOR);
				}
			}
			@rmdir($dir.$f);
		}
	}

	function trigger_error($error_msg, $error_type = E_USER_ERROR, $file = null, $line = null) {
		if(isset($file) && isset($line))
			$info = ' ('.basename($file).", line $line)";
		else
			$info = null;
		trigger_error('TPL: [in ' . $this->_file . ' line ' . $this->_linenum . "]: syntax error: $error_msg$info", $error_type);
	}
}
?>