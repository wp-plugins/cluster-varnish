<?php
/*
 * Plugin Name: Cluster Varnish
 * Description: A plugin to combine WordPress and Varnish on the Byte clustered platform.
 * Author: 42functions
 * Version: 1.2.1
 * Author URI: http://42functions.nl/
 */

require_once dirname(__FILE__) . '/build.methods.php';

require_once dirname(__FILE__)  . '/class/pattern.singleton.php';
require_once dirname(__FILE__)  . '/class/manager.module.php';
require_once dirname(__FILE__)  . '/class/facade.cache.php';

if(!defined('CACHE_DEBUG') && defined('ENV') && (stripos(ENV, 'acc') !== false || strpos(ENV, 'dev') !== false || strpos(ENV, 'test') !== false))
	define('CACHE_DEBUG', true);

XLII_Cache_Manager::init();
