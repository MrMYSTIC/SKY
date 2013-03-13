<?php
/**
 * MySQL Driver
 *
 * This driver interfaces the Model core class
 * to a MySQL server.
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
 * @link        http://www.codethesky.com/docs/mysqldriver
 * @package     Sky.Core
 */

import(SKYCORE_CORE_MODEL."/Driver.interface.php");

/**
 * MySQLDriver Driver Class Implements iDriver interface
 * This class talks MySQL
 * @package Sky.Driver
 * @subpackage MySQL
 */
class MySQLDriver implements iDriver
{
    /**
     * MySQLi's database instance
     * @access static private
     * @var object
     */
    private static $db = array();
    /**
     * Schema of current table
     * @access static private
     * @var array
     */
    private static $table_schema;
    /**
     * Model's table name
     * @access private
     * @var string
     */
    private $table_name;
    
    private $db_flag = false;
    private $db_array = array();
    private $server;

    /**
     * Sets up self::$db[$this->server] if not instantiated with mysqli object
     */
    public function __construct($db_array = NULL)
    {
        if(is_null($db_array))
        {
            $this->server = DB_SERVER;
            if(!isset(self::$db[$this->server]))
                self::$db[$this->server] = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        }
        else
        {
            $this->server = $db_array['DB_SERVER'];
            $this->db_flag = true;
            $this->db_array = $db_array;
            if(!isset(self::$db[$this->server]))
                self::$db[$this->server] = new mysqli($db_array['DB_SERVER'], $db_array['DB_USERNAME'], $db_array['DB_PASSWORD'], $db_array['DB_DATABASE']);
        }
    }

    /**
     * Sets current table for object {@link $table_name}
     * @param string $name
     */
    public function setTableName($name)
    {
        $this->table_name = $name;
    }

    /**
     * Returns table's schema, if not set it will figure out the schema then return
     * @return array self::$table_schema[$this->table_name]
     */ 
    public function getSchema()
    {
        if(!isset(self::$table_schema[$this->table_name]))
            $this->setSchema();
        return self::$table_schema[$this->table_name];
    }
    
    /**
     * Figures out table's schema and sets it {@link self::$table_schema}
     * @return bool
     */
    public function setSchema()
    {
        if(!isset(self::$table_schema[$this->table_name]))
        {
            $r = self::$db[$this->server]->query("DESCRIBE `".$this->table_name."`");
            while($row = $r->fetch_assoc())
            {
                self::$table_schema[$this->table_name][$row['Field']] = array(
                    "Type" => $row['Type'],
                    "Null" => $row['Null'],
                    "Key" => $row['Key'],
                    "Default" => $row['Default'],
                    "Extra" => $row['Extra']
                );
            }
        }
        return true;
    }

    /**
     * Checks to see if table exists in database
     * @param string $class_name
     * @return bool
     */
    public function doesTableExist($class_name)
    {
        //preg_match_all('/[A-Z][^A-Z]*/', $class_name, $strings);
        //$table_name = false;
        //if(isset($strings[0]))
        //    $table_name = strtolower(implode('_', $strings[0]));
        //else
        //    return false;
        $table_name = strtolower($class_name);
        Log::corewrite('Checking if table exists [%s]', 1, __CLASS__, __FUNCTION__, array($table_name));
        if($table_name)
        {
            $r = self::$db[$this->server]->query("SHOW TABLES");
            while($row = $r->fetch_assoc())
            {
                if($row['Tables_in_'.(($this->db_flag) ? $this->db_array['DB_DATABASE'] : DB_DATABASE)] == $table_name)
                {
                    $this->table_name = $table_name;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Escapes string value using mysqli's escape method
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return self::$db[$this->server]->real_escape_string($value);
    }

    /**
     * Builds MySQL query from Model's material
     * @param array $material
     * @return string
     */
    public function buildQuery($material)
    {
        $query = "SELECT ";
        if(empty($material['select']))
            $material['select'][] = $this->table_name.".*";
        $query .= implode(',', $material['select']);
        if(empty($material['from']))
            $material['from'] = $this->table_name;
        $query .= " FROM ".$material['from']." ";
        if(!empty($material['joins']))
        {
            foreach($material['joins'] as $value)
            {
                $query .= $value;
            }
        }
        if(!empty($material['where']))
        {
            $query .= " WHERE ";
            foreach($material['where'] as $where)
            {
                if(is_array($where))
                {
                    $query .= "`".$where['field']."` ".$where['operator'];
                    if(is_array($where['value']))
                    {
                        $query .= " ('".implode("','", $where['value'])."') ";
                    } else {
                        $query .= " '".$this->escape($where['value'])."'";
                    }
                    $query .= ' AND ';
                } else {
                    $query .= $where.' AND ';
                }
            }
            $query = substr($query, 0, -4);
        }
        if(!empty($material['groupby']))
        {
            $query .= " GROUP BY ";
            foreach($material['groupby'] as $value)
            {
                $query .= '`'.$value."`,";
            }
            $query = substr($query, 0, -1);
        }
        if(!empty($material['orderby']))
        {
            $query .= " ORDER BY ";
            foreach($material['orderby'] as $value)
            {
                $query .= $value.",";
            }
            $query = substr($query, 0, -1);
        }
        if(!empty($material['limit']))
        {
            $query .= " LIMIT ";
            if(!is_array($material['limit']))
            {
                $query .= $material['limit'];
            }
            else
            {
                $query .= $material['limit']["offset"].",".$material['limit']["limit"];
            }
        }
        return $query;
    }

    /**
     * Executes query on mysqli's query method
     * @param string $query
     * @return array
     */
    public function runQuery($query)
    {
        $r = self::$db[$this->server]->query($query);
        $return = array();
        $i = 0;
        if(!$r)
        {
            if(isset(self::$db[$this->server]->error)) trigger_error("[MySQL ERROR] => ".self::$db[$this->server]->error, E_USER_WARNING);
            return $return;
        }
        if($r === true)
            return true;
        while($row = $r->fetch_assoc())
        {
            foreach($row as $key => $value)
            {
                if(is_null($value))
                    $return[$i][$key] = "NULL";
                else
                    $return[$i][$key] = $value;
            }
            $i++;
        }
        return $return;
    }

    /**
     * Deletes current model from database
     * @access public
     * @return bool
     */
    public function delete($field, $value)
    {
        $sql = "DELETE FROM `".$this->table_name."` ";
        if(!is_array($value))
            $value = array($value);
        $where = "WHERE `".self::$db[$this->server]->real_escape_string($field)."` IN (";
        foreach($value as $v)
        {
            $where .= "'".self::$db[$this->server]->real_escape_string($v)."',";
        }
        $where = substr($where, 0, -1);
        $where .= ")";
        if($GLOBALS['ENV'] == 'DEV')
        {
            $f = fopen(DIR_LOG."/development.log", 'a');
            fwrite($f, "START: ".date('H:i:s')."\t".trim($sql.$where)."\n");
            fclose($f);
        }
        return self::$db[$this->server]->query($sql.$where);
    }

    /**
     * Saves current model's data to database
     * @param array $data
     * @return mixed
     */
    public function save($data)
    {
        $where = "";
        foreach(self::$table_schema[$this->table_name] as $field => $detail)
        {
            if($detail['Key'] == 'PRI')
            {
                $pri = $field;
                continue;
            }
        }
        if($data[$pri] === NULL)
        {
            $query = 'INSERT INTO `'.$this->table_name.'` SET ';
        } else {
            $query = 'UPDATE `'.$this->table_name.'` SET ';
            $where = ' WHERE `'.$pri.'` = "'.$data[$pri].'"';
        }

        foreach($data as $field => $value)
        {
            if($field != $pri && $field != 'updated_at' && $field != 'created_at' && isset(self::$table_schema[$this->table_name][$field]))
            {
                $query .= "`".$field."` = '".self::$db[$this->server]->real_escape_string($value)."',";
            }
            elseif($field == 'created_at' && $data[$pri] === NULL)
            {
                $query .= "`created_at` = NOW(),";
            }
        }
        $query = substr($query,0,-1);
        if($GLOBALS['ENV'] == 'DEV')
        {
            $f = fopen(DIR_LOG."/development.log", 'a');
            fwrite($f, "START: ".date('H:i:s')."\t".trim($query.$where)."\n");
            fclose($f);
        }
        if(self::$db[$this->server]->query($query.$where))
        {
            if(self::$db[$this->server]->insert_id !== 0)
                return self::$db[$this->server]->insert_id;
            else
                return true;
        } else {
            return false;
        }
    }
}
?>
