<?php
/**
 * Event Core Class
 *
 * Event system allows the ablity to hook plugins into the main core system classes
 *
 * LICENSE:
 *
 * This file may not be redistributed in whole or significant part, or
 * used on a web site without licensing of the enclosed code, and
 * software features.
 * 
 * @author Alan Tirado <root@deeplogik.com>
 * @copyright 2012 DeepLogiK, All Rights Reserved
 * @license http://www.deeplogik.com/sky/legal/license
 * @link http://www.deeplogik.com/sky/index
 * @version 1.0 Initial build
 * @package Sky.Core
 */

interface iEvent
{
    /**
     * Action CORE hooks
     */
    public static function SubscribeActionHook($hook, $callback);
    public static function UnSubscribeActionHook($hook);
    public static function PublishActionHook($hook, $args);
    
    /**
     * @todo Filter OUTPUT hooks
     */
    //public static function SubstribeFilterHook($hook, $callback);
    //public static function UnSubstribeFilterHook($hook);
    //public static function PublishFilterHook($hook, $args);
}

/**
 * Event class
 * Handles hooks added to core class systems
 * @package Sky.Core.Event
 */
class Event implements iEvent
{
    /**
     * Array of all subscribed to action hooks
     * @access public
     * @static
     * @var array
     */
    public static $hooks;
    
    /**
     * Sets up {@link $hooks} under self::$hooks['action']
     * @access public
     * @static
     * @param string $hook
     * @param mixed $callback. Array of OBJ::$method or string function name
     */
    public static function SubscribeActionHook($hook, $callback)
    {
        self::$hooks['action'][$hook] = $callback;
    }
    
    /**
     * Unsets {@link $hooks} under self::$hooks['action']
     * @access public
     * @static
     * @param string $hook
     */
    public static function UnSubscribeActionHook($hook)
    {
        if(isset(self::$hooks['action'][$hook]))
            unset(self::$hooks['action'][$hook]);
    }
    
    /**
     * Publishes an action. Checks to see if subscribed to and runs appropriate action
     * @access public
     * @static
     * @param string $hook
     * @param array $args. Arguments to pass to hook action
     * @todo Allow functions to be passed
     */
    public static function PublishActionHook($hook, $args = array())
    {
        if(isset(self::$hooks['action'][$hook]))
        {
            if(is_array(self::$hooks['action'][$hook])) //Object
            {
                foreach(Plugin::$plugin as $name => $info)
                {
                    if(isset($info['class']) && $info['class'] == self::$hooks['action'][$hook][0])
                    {
                        import(Plugin::$plugin[$name]['dir'].'/'.Plugin::$plugin[$name]['file']);
                        $class = Plugin::$plugin[$name]['class'];
                        $obj = new $class();
                        return call_user_func_array(array($obj, self::$hooks['action'][$hook][1]), $args);
                    }
                }
            } else { //Function
                //foreach(Plugin::$plugin as $name => $info)
                //{
                //    import(Plugin::$plugin[$name]['dir'].'/'.Plugin::$plugin[$name]['file']);
                //    return call_user_func_array(array($obj, self::$hooks['action'][$hook][1]), $args);
                //}
            }
        }
    }
}
?>