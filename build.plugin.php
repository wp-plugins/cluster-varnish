<?php
/*
 * Plugin Name: Varnish Cache
 * Description: Varnish cache is a powerful extension which acts as a communication layer between Varnish and WordPress.
 * Author: 42functions
 * Version: 1.2.0
 * Author URI: http://42functions.nl/
 */

require_once dirname(__FILE__) . '/build.methods.php';

require_once dirname(__FILE__)  . '/class/pattern.singleton.php';
require_once dirname(__FILE__)  . '/class/manager.module.php';
require_once dirname(__FILE__)  . '/class/facade.cache.php';

if(!defined('CACHE_DEBUG') && defined('ENV') && (stripos(ENV, 'acc') !== false || strpos(ENV, 'dev') !== false || strpos(ENV, 'test') !== false))
	define('CACHE_DEBUG', true);

XLII_Cache_Manager::init();