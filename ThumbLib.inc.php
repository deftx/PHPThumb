<?php
/**
 * PhpThumb Library Definition File
 * 
 * This file contains the definitions for the PhpThumbFactory and the PhpThumb classes.
 * It also includes the other required base class files.
 * 
 * If you've got some auto-loading magic going on elsewhere in your code, feel free to
 * remove the include_once statements at the beginning of this file... just make sure that
 * these files get included one way or another in your code.
 * 
 * @author Ian Selby <ian@gen-x-design.com>
 * @copyright Copyright 2008 Gen X Design
 * @version 3.0
 * @package PhpThumb
 * @filesource
 */

include_once('ThumbBase.inc.php');

/**
 * PhpThumbFactory Object
 * 
 * This class is responsible for making sure everything is set up and initialized properly,
 * and returning the appropriate thumbnail class instance.  It is the only recommended way 
 * of using this library, and if you try and circumvent it, the sky will fall on your head :)
 * 
 * Basic use is easy enough.  First, make sure all the settings meet your needs and environment...
 * these are the static variables defined at the beginning of the class.
 * 
 * Once that's all set, usage is pretty easy.  You can simply do something like:
 * <code>$thumb = PhpThumbFactory::create('/path/to/file.png');</code>
 * 
 * Refer to the documentation for the create function for more information
 * 
 * @package PhpThumb
 * @subpackage Core
 */
class PhpThumbFactory
{
	/**
	 * Which implemenation of the class should be used by default
	 * 
	 * Currently, valid options are:
	 *  - imagick
	 *  - gd
	 *  
	 * These are defined in the implementation map variable, inside the create function
	 * 
	 * @var string
	 */
	public static $default_implemenation = 'gd';
	/**
	 * Where the plugins can be loaded from
	 * 
	 * Note, it's important that this path is properly defined.  It is very likely that you'll 
	 * have to change this, as the assumption here is based on a relative path.
	 * 
	 * @var string
	 */
	public static $plugin_path = 'thumb_plugins/';
	
	/**
	 * Factory Function
	 * 
	 * This function returns the correct thumbnail object, augmented with any appropriate plugins.  
	 * It does so by doing the following:
	 *  - Getting an instance of PhpThumb
	 *  - Loading plugins
	 *  - Validating the default implemenation
	 *  - Returning the desired default implementation if possible
	 *  - Returning the GD implemenation if the default isn't available
	 *  - Throwing an exception if no required libraries are present
	 * 
	 * @return GdThumb
	 * @uses PhpThumb
	 * @param string $filename The path and file to load [optional]
	 */
	public static function create($filename = '')
	{
		// map our implementation to their class names
		$implementation_map = array
		(
			'imagick' => 'ImagickThumb',
			'gd' => 'GdThumb'
		);
		
		// grab an instance of PhpThumb
		$pt = PhpThumb::getInstance();
		// load the plugins
		$pt->loadPlugins(self::$plugin_path);
		
		// attempt to load the default implementation
		if($pt->isValidImplementation(self::$default_implemenation))
		{
			$imp = $implementation_map[self::$default_implemenation];
			return new $imp($filename);
		}
		// load the gd implementation if default failed
		else if ($pt->isValidImplementation('gd'))
		{
			$imp = $implementation_map['gd'];
			return new $imp($filename);
		}
		// throw an exception if we can't load
		else
		{
			throw new Exception('You must have either the GD or iMagick extension loaded to use this library');
		}
	}
}

/**
 * PhpThumb Object
 * 
 * This singleton object is essentially a function library that helps with core validation 
 * and loading of the core classes and plugins.  There isn't really any need to access it directly, 
 * unless you're developing a plugin and need to take advantage of any of the functionality contained 
 * within.
 * 
 * If you're not familiar with singleton patterns, here's how you get an instance of this class (since you 
 * can't create one via the new keyword):
 * <code>$pt = PhpThumb::getInstance();</code>
 * 
 * It's that simple!  Outside of that, there's no need to modify anything within this class, unless you're doing 
 * some crazy customization... then knock yourself out! :)
 * 
 * @package PhpThumb
 * @subpackage Core
 */
class PhpThumb
{
	/**
	 * Instance of self
	 * 
	 * @var object PhpThumb
	 */
	static $_instance;
	/**
	 * The plugin registry
	 * 
	 * This is where all plugins to be loaded are stored.  Data about the plugin is 
	 * provided, and currently consists of:
	 *  - loaded: true/false
	 *  - implementation: gd/imagick/both
	 * 
	 * @var array
	 */
	protected $_registry;
	/**
	 * What implementations are available
	 * 
	 * This stores what implementations are available based on the loaded 
	 * extensions in PHP, NOT whether or not the class files are present.
	 * 
	 * @var array
	 */
	protected $_implementations;
	
	/**
	 * Returns an instance of self
	 * 
	 * This is the usual singleton function that returns / instantiates the object
	 * 
	 * @return PhpThumb
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	
	/**
	 * Class constructor
	 * 
	 * Initializes all the variables, and does some preliminary validation / checking of stuff
	 * 
	 */
	private function __construct()
	{
		$this->_registry		= array();
		$this->_implementations	= array('gd' => false, 'imagick' => false);
		
		$this->getImplementations();
	}
	
	/**
	 * Finds out what implementations are available
	 * 
	 * This function loops over $this->_implementations and validates that the required extensions are loaded.
	 * 
	 * I had planned on attempting to load them dynamically via dl(), but that would provide more overhead than I 
	 * was comfortable with (and would probably fail 99% of the time anyway)
	 * 
	 */
	private function getImplementations()
	{
		foreach($this->_implementations as $extension => $loaded)
		{
			if($loaded)
			{
				continue;
			}
			
			if(extension_loaded($extension))
			{
				$this->_implementations[$extension] = true;
			}
		}
	}
	
	/**
	 * Returns whether or not $implementation is valid (available)
	 * 
	 * If 'all' is passed, true is only returned if ALL implementations are available.
	 * 
	 * You can also pass 'n/a', which always returns true
	 * 
	 * @return bool 
	 * @param string $implementation
	 */
	public function isValidImplementation($implementation)
	{
		if($implementation == 'n/a')
		{
			return true;
		}
		
		if($implementation == 'all')
		{
			foreach($this->_implementations as $imp => $value)
			{
				if($value == false)
				{
					return false;
				}
			}
			
			return true;
		}
		
		if(array_key_exists($implementation, $this->_implementations))
		{
			return $this->_implementations[$implementation];
		}
		
		return false;
	}
	
	/**
	 * Registers a plugin in the registry
	 * 
	 * Adds a plugin to the registry if it isn't already loaded, and if the provided 
	 * implementation is valid.  Note that you can pass the following special keywords 
	 * for implementation:
	 *  - all - Requires that all implementations be available
	 *  - n/a - Doesn't require any implementation
	 *  
	 * When a plugin is added to the registry, it's added as a key on $this->_registry with the value 
	 * being an array containing the following keys:
	 *  - loaded - whether or not the plugin has been "loaded" into the core class
	 *  - implementation - what implementation this plugin is valid for
	 * 
	 * @return bool
	 * @param string $plugin_name
	 * @param string $implementation
	 */
	public function registerPlugin($plugin_name, $implementation)
	{
		if(!array_key_exists($plugin_name, $this->_registry) && $this->isValidImplementation($implementation))
		{
			$this->_registry[$plugin_name] = array('loaded' => false, 'implemenation' => $implementation);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Loads all the plugins in $plugin_path
	 * 
	 * All this function does is include all files inside the $plugin_path directory.  The plugins themselves 
	 * will not be added to the registry unless you've properly added the code to do so inside your plugin file.
	 * 
	 * @param string $plugin_path
	 */
	public function loadPlugins($plugin_path)
	{
		// strip the trailing slash if present
		if(substr($plugin_path, strlen($plugin_path) - 1, 1) == '/')
		{
			$plugin_path = substr($plugin_path, 0, strlen($plugin_path) - 1);
		}
		
		if($handle = opendir($plugin_path))
		{
			while (false !== ($file = readdir($handle)))
			{
				if ($file == '.' || $file == '..')
				{
					continue;
				}
				
				include_once($plugin_path . '/' . $file);
			}
		}
	}
}



?>