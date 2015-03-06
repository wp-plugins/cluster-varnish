<?php
/**
 * @author Ferdy Perdaan
 * @version 1.0
 *
 * Setup the default cache extenstion 
 */

class XLII_Cache_Manager extends XLII_Cache_Singleton
{
	const REQUIRED_CAP = 'administrator';
	const OPTION_NAME = 'xlii_cache';
	
	const DEFAULT_EXPIRATION = 2592000; // 30 days
	
	private $error;
	private $notice;
	
	private $_intances;
	
	/**
	 * Setup the default manager object
	 */
	protected function __construct()
	{
		spl_autoload_register(array(&$this, '__autoload'));
		
		// -- Regsiter admin page
		add_action('admin_bar_menu', array($this, '_adminbar'), 110);
		add_action('admin_menu', array($this, '_adminmenu'));
		
		add_action('wp_ajax_cache-flush-blog', array($this, '_apiCacheFlushBlog'));
		add_action('wp_ajax_cache-flush-page', array($this, '_apiCacheFlushPage'));
		add_action('wp_ajax_cache-flush-object', array($this, '_apiCacheFlushObject'));
		
		register_shutdown_function(array($this, '__shutdown'));
		
		XLII_Cache::init();
		
		add_action('wp', array($this, '_headers'));
		
		// -- Register observers
		XLII_Cache_Post_Observer::init();
		XLII_Cache_Term_Observer::init();
		XLII_Cache_Option_Observer::init();
		XLII_Cache_Comment_Observer::init();
		XLII_Cache_User_Observer::init();
		
		// -- Register helper
		// add_action('plugins_loaded', array('XLII_Cache_WPML_Helper', 'init'));
	}
	
	/**
	 * Keep track of the flushed pages
	 * 
	 * @access	private
	 */ 
	public function __shutdown()
	{
		if(($queue = XLII_Cache::getQueue()) && ($user = wp_get_current_user()) && $user->ID)
		{
			if($data = get_option(self::OPTION_NAME . '_' . $user->ID))
			{
				if(!is_array($data))
				{	
					$queue = $data;
				}
				else if(is_array($queue))
				{
					$queue = array_unique(array_merge($data, $queue));
			
					asort($queue);
				}
			}
			
			update_option(self::OPTION_NAME . '_' . $user->ID, $queue);
		}
	}
	
	/**
	 * Autoloader used to load cache classes dynamicly.
	 * 
	 * @access	private
	 * @param	string $class The name of the class being loaded.
	 * @return	bool
	 */
	public function __autoload($class)
	{
		if(stripos($class, 'xlii_cache') === false)
			return false;
			
		if($file = str_replace('xlii_cache', '', strtolower($class)))
		{
			$file = explode('_', trim($file, '_'));
			$file = count($file) == 2 ? $file[1] . '/class.' . $file[0] . '.php' : 'cache/class.' . $file[0] . '.php';
		}
		else
		{
			$file = 'cache/class.abstract.php';
		}
		
		if(file_exists($file = dirname(__FILE__)  . '/' . $file) && require_once $file)
			return class_exists($class);
		else
			return false;
	}
	
	/**
	 * Register the admin bar.
	 * 
	 * @access	private
	 * @param	WP_Admin_Bar $admin_bar The generated admin bar.
	 */
	public function _adminbar(WP_Admin_Bar $admin_bar)
	{
		if(!current_user_can(self::REQUIRED_CAP))
			return;
		
		$valid = XLII_Cache::isValid() !== false || defined('CACHE_DEBUG') && CACHE_DEBUG;
		
		// -- Add primary node
		if($valid)
		{
			$title = __('Cache', 'xlii-cache');
			
			// -- Track auto flushing
			$queue = XLII_Cache::getQueue();
			$queue = !$queue ? get_option(self::OPTION_NAME . '_' . wp_get_current_user()->ID) : $queue;
	
			if($queue)
			{
				if(is_array($queue) && count($queue))
				{
					$admin_bar->add_menu( array( 
						'id' => 'varnish-cache-flushed',
						'parent' => 'varnish-cache',
						'title' => __('Flushed pages', 'xlii-cache')
					));
				
					asort($queue);
				
					foreach($queue as $i => $key)
					{
						$label = apply_filters('cache_label_flushed', str_replace(home_url(''), '', $key));
						$label = !$label || $label == '/' ? __('Home', 'theme') : $label;
						$label = substr($label, 0, 37) . (strlen($label) > 40 ? '...' : '');
						
						$admin_bar->add_menu( array( 
							'id' => 'varnish-cache-flushed-' . $i,
							'parent' => 'varnish-cache-flushed',
							'title' => $label,
							'href' => $key
						));
					}	
				
					$title = __('Flushed', 'xlii-cache') . ' <span style = "font-size:0.8em;">(' . count($queue) . ')</span>';
				}
				else if($queue)
				{
					$title = __('Flushed', 'xlii-cache') . ' <span style = "font-size:0.8em;">(' . __('all', 'xlii-cache') . ')</span>';
				}
				
				delete_option(self::OPTION_NAME . '_' . wp_get_current_user()->ID);
			}
		
			$admin_bar->add_menu( array( 
				'id' => 'varnish-cache',
				'title' => $title
			));
		}
		else
		{
			$admin_bar->add_menu( array( 
				'id' => 'varnish-cache',
				'title' => __('Unable to decect cache', 'xlii-cache')
			));	
		}
	
		
		$url = set_url_scheme( (is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$url = add_query_arg('redirect', urlencode($url), admin_url('admin-ajax.php'));
		
		// -- Blog cache
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-flush-blog',
			'parent' => 'varnish-cache',
			'href' => add_query_arg('action', 'cache-flush-blog', $url),
			'title' => __('Flush blog cache', 'xlii-cache') 
		));
		
		// -- Singular cache
		{
			if(is_admin()) 
			{
				global $tag, $user_id;
			
				$current_screen = get_current_screen();
				$post = get_post();

				if ($current_screen->base == 'post')
					$object = $post;
				
				else if($current_screen->base == 'edit-tags' && !empty($tag))
					$object = $tag;
					
				else if($current_screen->base == 'user-edit' && !empty($user_id))
					$object = get_user_by('id', $user_id);
					
			} 
			else 
			{
				$object = $GLOBALS['wp_the_query']->get_queried_object();
				
				if(!is_404())
				{
					$admin_bar->add_menu( array( 
						'id' => 'varnish-cache-flush-page',
						'parent' => 'varnish-cache',
						'href' => add_query_arg('action', 'cache-flush-page', $url),
						'title' => __('Flush page cache', 'xlii-cache') 
					));
				}
			}
	
			if(!empty($object))
			{
				if(!empty($object->post_type) && ($pt = get_post_type_object($object->post_type)) && $pt->public)
				{
					if(XLII_Cache_Manager::option('post.enabled'))
						$action = add_query_arg(array('object' => 'post', 'object_id' => $object->ID), $url);
				} 
				else if (!empty($object->taxonomy) && ($tax = get_taxonomy($object->taxonomy)) && $tax->public)
				{
					if(XLII_Cache_Manager::option('term.enabled'))
						$action = add_query_arg(array('object' => 'term', 'object_id' => $object->term_taxonomy_id), $url);
				}
				else if(is_a($object, 'WP_User'))
				{
					if(XLII_Cache_Manager::option('user.enabled'))
						$action = add_query_arg(array('object' => 'user', 'object_id' => $object->ID), $url);
				}
				
				if(!empty($action))
				{
					$admin_bar->add_menu( array( 
						'id' => 'varnish-cache-flush-object',
						'parent' => 'varnish-cache',
						'href' => add_query_arg('action', 'cache-flush-object', $action),
						'title' => __('Flush object cache', 'xlii-cache')
					));
				}
			}
		}
		
		
		// -- Add configuration node
		$url = admin_url('options-general.php');
		$url = add_query_arg('page', 'varnish-config', $url);
		
		$admin_bar->add_menu( array( 
			'id' => 'varnish-cache-config',
			'parent' => 'varnish-cache',
			'title' => __('Configuration', 'xlii-cache'),
			'href' => $url
		));
	}

	/**
	 * Register our custom admin menu
	 * 
	 * @access	private
	 */
	public function _adminmenu()
	{
		$hook = add_submenu_page('options-general.php', __('Cache', 'xlii-cache'), __('Cache', 'xlii-cache'), self::REQUIRED_CAP, 'varnish-config', array($this, '_adminPage'));
	
		add_action('load-' . $hook, array($this, '_adminProcess'));
	}
	
	/**
	 * Render our custom admin page
	 * 
	 * @access	private
	 */
	public function _adminPage()
	{		
		// Render notice
		if($error = $this->error)
		{	
			$this->notice = is_array($error) ? implode('<br />', array_unique($error)) : $error;
			$this->notice = '<div class = "error"><p>' . $this->notice . '</p></div>';
		}
		
		if($note = $this->notice)
			echo $note[0] != '<' ? '<div class = "updated"><p>' . $note . '</p></div>' : $note;
		
		require_once dirname(dirname(__FILE__)) . '/resource/view.admin.phtml';
	}
	
	/**
	 * Process our admin page.
	 * 
	 * @access	private
	 */
	public function _adminProcess()
	{
		if(empty($_POST['submit']))
			return;
	
		$data = array();
	
		// Process general data
		if(isset($_POST['general']) && is_array($_POST['general']))
		{
			$data['general'] = array();
			
			if(!empty($_POST['general']['flushing']))
			{	
				$data['general']['flushing'] = intval($_POST['general']['flushing']);
				$data['general']['flushing'] = $data['general']['flushing'] > 0 ? $data['general']['flushing'] : 0;
			}
				
			if(!empty($_POST['general']['pagination']))
				$data['general']['pagination'] = intval($_POST['general']['pagination']);
				
			if(!empty($_POST['general']['expire']) && intval($_POST['general']['expire']) >= 0)
				$data['general']['expire'] = intval($_POST['general']['expire']);
		}
		
		// Process option data
		if(isset($_POST['options']) && is_array($_POST['options']))
		{
			$data['options'] = array();
			
			foreach(array('additional', 'cookies', 'exclude') as $field)
			{
				if(!empty($_POST['options'][$field]))
				{
					$data['options'][$field] = preg_split('/(,|\n|\r)/', $_POST['options'][$field]);
					$data['options'][$field] = array_map('trim', $data['options'][$field]);
					$data['options'][$field] = array_map('sanitize_text_field', $data['options'][$field]);
					$data['options'][$field] = array_filter($data['options'][$field]);
				}
			}
		}
		
		// Process generic purge policy
		foreach(array('term', 'post', 'user') as $key)
		{
			$data[$key] = array(
				'purge' => array(),
				'feed' => array()
			);
			
			if(!isset($_POST[$key]) || !is_array($_POST[$key]))
				continue;
			
			$data[$key]['enabled'] = !empty($_POST[$key]['enabled']);
			
			if(isset($_POST[$key]['feed']))
			{
				$data[$key]['feed'] = (array)$_POST[$key]['feed'];
				$data[$key]['feed'] = array_map('sanitize_text_field', $data[$key]['feed']);
			}
			
			if(!empty($_POST[$key]['additional']))
			{
				$data[$key]['additional'] = preg_split('/(\n|\r)/', $_POST[$key]['additional']);
				$data[$key]['additional'] = array_map('trim', $data[$key]['additional']);
				$data[$key]['additional'] = array_filter($data[$key]['additional']);
			}
			
			if(isset($_POST[$key]['purge']))
			{
				$data[$key]['purge'] = (array)$_POST[$key]['purge'];
				
				$this->_processPurge($data[$key]['purge']);
			}
		}
		
		// Process comment purge policy
		$data['comment'] = array(
			'enabled' => false,
			'type' => array()
		);
	
		if(isset($_POST['comment']) && is_array($_POST['comment']))
		{
			$data['comment']['enabled'] = !empty($_POST['comment']['enabled']);
			
			if(isset($_POST['comment']['type']))
			{
				$data['comment']['type'] = (array)$_POST['comment']['type'];
				$data['comment']['type'] = array_map('sanitize_title', $data['comment']['type']);
			}
		}
	
		update_option(self::OPTION_NAME, apply_filters('cache_form_process', $data, $this));
		
		$this->notice = __('Settings saved', 'xlii-cache');
	}
	
	/**
	 * Flush the entire cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushBlog()
	{
		// Preform action
		if(current_user_can(self::REQUIRED_CAP))
			XLII_Cache::flush();
		
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		wp_redirect($location);
	}
	
	/**
	 * Flush the page cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushPage()
	{
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		// Preform action
		if(current_user_can(self::REQUIRED_CAP))
			XLII_Cache::delete($location);
		
		wp_redirect($location);
	}
	
	
	/**
	 * Flush the object cache
	 * 
	 * @access	private
	 */
	public function _apiCacheFlushObject()
	{
		// Preform action
		if(current_user_can(self::REQUIRED_CAP) && !empty($_REQUEST['object_id']))
		{
			$id = intval($_REQUEST['object_id']);
			
			switch(isset($_REQUEST['object']) ? $_REQUEST['object'] : '')
			{
				case 'post':
					XLII_Cache_Post_Observer::getInstance()->flush($id);
				
					break;
				
				case 'term':
					global $wpdb;
					
					$term = $wpdb->get_row($wpdb->prepare('SELECT term_id, taxonomy FROM ' . $wpdb->term_taxonomy . ' WHERE term_taxonomy_id = %d', $id));
					
					if($term)
						XLII_Cache_Term_Observer::getInstance()->flush($term->term_id, $id, $term->taxonomy);
				
					break;
					
				
				case 'user':
					XLII_Cache_User_Observer::getInstance()->flush($id);
				
					break;
				
			}
		}
		
		// Redirect user
		$location = empty($_REQUEST['redirect']) ? wp_get_referer() : $_REQUEST['redirect'];
		$location = $location ? $location : site_url('/');
		
		wp_redirect($location);
	}
	
	/**
	 * Returns wether the user contains a cookie which is excluded from caching
	 * 
	 * @return	bool
	 */
	public function hasCookieMatch()
	{
		if(!$list = XLII_Cache_Manager::option('options.cookies'))
			return false;
			
		if(array_intersect($list, $match = array_keys($_COOKIE)))
			return true;
		
		foreach($list as $regex)
		{
			foreach($match as $key)
			{
				if(preg_match('#' . $regex . '#', $key))
					return true;
			}
		}
		
		return false;
	}
	
	
	/**
	 * Returns wether the user accessed a page which is excluded from caching
	 * 
	 * @return	bool
	 */
	public function hasPageMatch()
	{
		if(!$list = XLII_Cache_Manager::option('options.exclude'))
			return false;
		
		if(in_array($match = add_query_arg(null, null), $list))
			return true;
		
		foreach($list as $regex)
		{
			if(preg_match('#' . $regex . '#', $match))
				return true;
		}
		
		return false;
	}
	
	/**
	 * Print the caching headers in some cases
	 * 
	 * @access	private
	 */
	public function _headers()
	{
		$headers = array();
		
		if(is_search())
			$headers = wp_get_nocache_headers();
		
		else if(is_user_logged_in())
			$headers = wp_get_nocache_headers();
		
		else if(!empty($_POST))
			$headers = wp_get_nocache_headers();
			
		else if($this->hasCookieMatch())
			$headers = wp_get_nocache_headers();
		
		else if($this->hasPageMatch())
			$headers = wp_get_nocache_headers();
	
		else
		{
			$expire = XLII_Cache_Manager::option('general.expire');
			$expire = $expire ? $expire : self::DEFAULT_EXPIRATION;
			
			$headers['Cache-Control'] = 'public, max-age=' . $expire;
			$headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT';
		}	
			
		// Print all generated headers
		foreach( $headers as $name => $field_value )
			@header("{$name}: {$field_value}");
	}
	
	/**
	 * Process the purging data
	 * 
	 * @param	array &$data The data object to cleanup.
	 */
	protected function _processPurge(array &$data)
	{
		foreach($data as &$val)
		{
			if(is_array($val))
				$this->_processPurge($val);
			else
				$val = (bool)$val;
		}
	}
	
	/**
	 * Return the header of the metabox
	 * 
	 * @param	string $title The title to display in the metabox.
	 * @return	string
	 */
	public function metaboxHeader($title)
	{
		return '<div class="postbox">' .	
					'<div class="handlediv" title="' . __('Click to toggle', 'xlii-cache') . '"><br /></div>' .
					'<h3 class="hndle"><span>' . $title . '</span></h3>' .
					'<div class="inside">';
	}
	
	/**
	 * Return the footer of the metabox
	 * 
	 * @return	string
	 */
	public function metaboxFooter()
	{
		
        return 			// '<p>' .
        //             				'<input type="submit" name="submit" class="button-primary" value="' . __('Save', 'xlii-cache') . '" />' .
        // 						'</p>' .
					'</div>' .
			  	'</div>';
	}

	/**
	 * Return the cache configuration options
	 * 
	 * @param	string $key = null A particular key from the options to retrieve.
	 * @param	void $default = null The default value to return.
	 * @return	void
	 */
	static public function option($key = null, $default = null)
	{
		$opt = get_option(self::OPTION_NAME, false);
		
		if($opt === false)
		{
			update_option(self::OPTION_NAME, $opt = array(
				'general' => array(
					'pagination' => 10,
					'flushing' => 50
				),
				
				'post' => array(
					'enabled' => true,
					'feed' => array(get_default_feed()),
					'purge' => array(
						'post' => array('term' => true, 'archive' => true),
						'global' => array('front' => true, 'posts' => true)
					)
				),
				
				'term' => array(
					'enabled' => true,
					'feed' => array(get_default_feed()),
					'purge' => array(
						'post' => array('archive' => true),
						'global' => array('front' => true, 'posts' => true),
						'term' => array('ancestors' => true)
					)
				),
				
				'comment' => array(
					'enabled' => false,
					'type' => array(
						'comment'
					)
				)
			));
		}
		
		if(!$key)
			return $opt;
		
		$key = explode('.', $key);
		
		foreach($key as $k)
		{
			if(!isset($opt[$k]))
				return $default;
			
			if(!is_array($opt[$k]))
				return $opt[$k];
				
			$opt = $opt[$k];
		}
		
		
		return $opt;
	}
}