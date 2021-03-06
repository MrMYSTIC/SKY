<?php
/**
 * Plugin Core Class
 *
 * This class allows the registering of plugins
 *
 * LICENSE:
 *
 * This file may not be redistributed in whole or significant part, or
 * used on a web site without licensing of the enclosed code, and
 * software features.
 * 
 * @author      Alan Tirado <root@deeplogik.com>
 * @copyright   2013 DeepLogik, All Rights Reserved
 * @license     http://www.codethesky.com/license
 * @link        http://www.codethesky.com/docs/pluginclass
 * @package     Sky.Core
 */

/**
 * Plugin class
 * Handles the registering of user created plugins
 * @package Sky.Core.Plugin
 */
class Plugin
{
    /**
     * Array of all plugins
     * @access public
     * @static
     * @var array
     */
    public static $plugin = array();
    
    /**
     * Sets up {@link $plugin} under self::$plugin
     * Reads plugin's info.cnf file and runs it's init.php script
     * @access public
     * @static
     * @param string $name
     */
    public static function Register($name)
    {
        $name = strtolower($name);
        if(is_dir(SKYCORE_LIB.'/plugins/'.$name))
        {
            if(is_file(SKYCORE_LIB.'/plugins/'.$name.'/info.cnf'))
            {
                $info = file_get_contents(SKYCORE_LIB.'/plugins/'.$name.'/info.cnf');
                preg_match_all('/((?!#).+)=(.*)/', $info, $matches);
                for($i=0;$i<count($matches[1]);$i++)
                    self::$plugin[$name][$matches[1][$i]] = $matches[2][$i];
                if(!isset(self::$plugin[$name]['dir']))
                    self::$plugin[$name]['dir'] = SKYCORE_LIB.'/plugins/'.$name;
                require_once(self::$plugin[$name]['dir'].'/init.php');
            } else
                unset(self::$plugin[$name]);
        } else
            unset(self::$plugin[$name]);
    }
}
?>