<?php
abstract class Model implements Iterator, ArrayAccess, Countable
{
	private $_unaltered_data		= array();
	private $_iterator_data 		= array();
	private $_iterator_position 	= 0;

	private $_child;
	private $_object_id;
	private $_driver_info 			= array();
	private static $_static_info 	= array();

	protected $DatabaseOverwrite 	= array(
		'DB_SERVER' 	=> null,
		'DB_USERNAME' 	=> null,
		'DB_PASSWORD' 	=> null,
		'DB_DATABASE' 	=> null,
		'MODEL_DRIVER' 	=> null
	);
	protected $TableName 			= null;
	protected $PrimaryKey 			= null;
	protected $OutputFormat			= array();
	protected $InputFormat			= array();
	protected $EncryptField			= array();
	//# Association Properties
	protected $BelongsTo			= array();
	protected $HasOne				= array();
	protected $HasMany				= array();
	protected $HasAndBelongsToMany	= array();

	//############################################################
	//# Magic Methods
	//############################################################
	
	public function __construct()
	{
		$this->_child = get_called_class();
		if(!isset(self::$_static_info[$this->_child])) self::$_static_info[$this->_child] = array();

		// # If driver for this Child Model is not set, instantiate!
		if(!isset(self::$_static_info[$this->_child]['driver']))
		{
			$_DB = array(
				'DB_SERVER'		=> DB_SERVER,
				'DB_USERNAME' 	=> DB_USERNAME,
				'DB_PASSWORD' 	=> DB_PASSWORD,
				'DB_DATABASE' 	=> DB_DATABASE,
				'MODEL_DRIVER' 	=> MODEL_DRIVER
			);
			if(isset($this->DatabaseOverwrite['MODEL_DRIVER'])) 
				$_DB = $this->DatabaseOverwrite;
			if(is_file(SKYCORE_CORE_MODEL."/drivers/".$_DB['MODEL_DRIVER'].".driver.php"))
			{
				import(SKYCORE_CORE_MODEL."/drivers/".$_DB['MODEL_DRIVER'].".driver.php");
				$_DRIVER_CLASS = $_DB['MODEL_DRIVER'].'Driver';
				self::$_static_info[$this->_child]['driver'] = new $_DRIVER_CLASS($_DB);
				if(is_null($this->TableName))
					$this->TableName = strtolower(get_class($this));
				self::$_static_info[$this->_child]['driver']->setTableName($this->TableName);
				self::$_static_info[$this->_child]['driver']->setPrimaryKey($this->PrimaryKey);
			}
		}

		$this->PrimaryKey = self::$_static_info[$this->_child]['driver']->getPrimaryKey();
		$this->_object_id = md5($this->_child.rand(0, 9999));
		self::$_static_info[$this->_child]['driver']->buildModelInfo($this);
	}

	public function __call($method, $args)
	{
		if(method_exists(self::$_static_info[$this->_child]['driver'], $method))
		{
			call_user_func_array(array(self::$_static_info[$this->_child]['driver'], $method), $args);
			return $this;
		}
	}

	public function &__GetDriverInfo($hash_key, $default = array())
	{
		if(!isset($this->_driver_info[$hash_key])) $this->_driver_info[$hash_key] = $default;
		return $this->_driver_info[$hash_key];
	}

	public function get_raw($key)
	{
		if(!array_key_exists($key, $this->_iterator_data[$this->_iterator_position]))
		{
		  trigger_error(__CLASS__."::".__FUNCTION__." No field by the name [".$name."]", E_USER_NOTICE);
		  return null;
		}
		return $this->_iterator_data[$this->_iterator_position][$key];
	}

	public function __get($key)
	{
		if(!array_key_exists($this->_iterator_position, $this->_iterator_data))
			return null;
		if(!array_key_exists($key, $this->_iterator_data[$this->_iterator_position]))
		{
			if(SKY::singularize($key) === false) // Key is Singular
			{
				if(array_key_exists($key, $this->BelongsTo))
				{
					if($this->_BelongsTo($key))
						return $this->_iterator_data[$this->_iterator_position][$key];
				}
				if(array_key_exists($key, $this->HasOne))
				{
					if($this->_HasOne($key))
						return $this->_iterator_data[$this->_iterator_position][$key];
				}
			} else { // Key is plural
				if(array_key_exists($key, $this->HasMany))
				{

				}
				if(array_key_exists($key, $this->HasAndBelongsToMany))
				{
					
				}
			}
			return null;
		}
		if(array_key_exists($key, $this->OutputFormat))
		{
			if(method_exists($this, $this->OutputFormat[$key]))
			{
				return call_user_func(array($this, $this->OutputFormat[$key]), $this->_iterator_data[$this->_iterator_position][$key]);
			} else {
				return sprintf($this->OutputFormat[$key], $this->_iterator_data[$this->_iterator_position][$key]);
			}
		}
		return $this->_iterator_data[$this->_iterator_position][$key];
	}

	public function __set($key, $value)
	{
		if(isset($this->_iterator_data[$this->_iterator_position]) && array_key_exists($key, $this->_iterator_data[$this->_iterator_position]))
		{
			$this->_unaltered_data[$this->_iterator_position] = $this->_iterator_data[$this->_iterator_position];
		}

		if(array_key_exists($key, $this->InputFormat))
		{
			if(method_exists($this, $this->InputFormat[$key]))
			{
				$this->_iterator_data[$this->_iterator_position][$key] = call_user_func(array($this, $this->InputFormat[$key]), $value);
			} else {
				$this->_iterator_data[$this->_iterator_position][$key] = sprintf($this->InputFormat[$key], $value);
			}
		}
		elseif(in_array($key, $this->EncryptField))
		{
			$this->_iterator_data[$this->_iterator_position][$key] = $this->Encrypt($value);
		}
		else
		{
			$this->_iterator_data[$this->_iterator_position][$key] = $value;
		}
	}

	public function __isset($key)
	{
		return isset($this->_iterator_data[$this->_iterator_position][$key]);
	}

	public function __unset($key)
	{
		unset($this->_iterator_data[$this->_iterator_position][$key]);
	}

	//############################################################
	//# Association Methods
	//############################################################

	public function getPrimaryKey()
	{
		return $this->PrimaryKey;
	}

	private function _GetModel($model_name)
	{
		if(SKY::singularize($model_name) === false) $model_name = SKY::pluralize($model_name);
		return new $model_name();
	}
	
	private function _BelongsTo($model_name)
	{
		$obj = $this->_GetModel($model_name);
		if($obj instanceof Model)
		{
			$r = $obj->findOne(array(
				$obj->getPrimaryKey() => $this->_iterator_data[$this->_iterator_position][$model_name.'_id']
			))->run();
			$this->_iterator_data[$this->_iterator_position][$model_name] = $r;
			return true;
		}
		return false;
	}

	private function _HasOne($model_name)
	{
		$obj = $this->_GetModel($model_name);
		if($obj instanceof Model)
		{
			$r = $obj->findOne(array(
				strtolower(SKY::singularize($this->_child).'_id') => $this->_iterator_data[$this->_iterator_position][$this->getPrimaryKey()]
			))->run();
			$this->_iterator_data[$this->_iterator_position][$model_name] = $r;
			return true;
		}
	}

	private function _HasMany($model_name)
	{
		$obj = $this->_GetModel($model_name);
		if($obj instanceof Model)
		{
			$r = $obj->search(array(
				strtolower(SKY::singularize($this->_child).'_id') => $this->_iterator_data[$this->_iterator_position][$this->getPrimaryKey()]
			))->run();
			$this->_iterator_data[$this->_iterator_position][$model_name] = $r;
		}
	}

	private function _HasAndBelongsToMany()
	{

	}

	//############################################################
	//# Run Methods
	//############################################################
	
	public function run()
	{
		$this->_iterator_data = self::$_static_info[$this->_child]['driver']->run();
		return $this;
	}

	//############################################################
	//# Save Methods
	//############################################################

	public function create($hash = array())
	{
		// @ToDo: Create object with hash
	}
	
	public function save()
	{
		//# Update Record
		if(isset($this->_iterator_data[$this->_iterator_position][$this->PrimaryKey]))
		{
			$UPDATED = self::$_static_info[$this->_child]['driver']->update(
				$this->_unaltered_data, 
				$this->_iterator_data[$this->_iterator_position],
				$this->_iterator_position
			);
			$this->_iterator_data[$this->_iterator_position] = $UPDATED['updated'];
			return $UPDATED['status'];
		//# Save New Record
		} else {
			$DOCUMENT = self::$_static_info[$this->_child]['driver']->savenew(
				$this->_iterator_data[$this->_iterator_position]
			);
			$this->_iterator_data[$this->_iterator_position][$this->PrimaryKey] = $DOCUMENT['data'];
			return $DOCUMENT['pri'];
		}
	}

	public function save_all()
	{
		$RETURN = true;
		for($i = 0; $i < count($this->_iterator_data); $i++)
		{
			$this->_iterator_position = $i;
			$STATUS = $this->save();
			if((bool)$STATUS === false) $RETURN = false;
		}
		return $RETURN;
	}

	//############################################################
	//# Delete Methods
	//############################################################
	
	public function delete()
	{
		if(isset($this->_iterator_data[$this->_iterator_position][$this->PrimaryKey]))
		{
			return self::$_static_info[$this->_child]['driver']->delete($this->_iterator_data[$this->_iterator_position][$this->PrimaryKey]);
		}
		return false;
	}

	public function delete_all()
	{
		$RETURN = true;
		for($i = 0; $i < count($this->_iterator_data); $i++)
		{
			$this->_iterator_position = $i;
			$STATUS = $this->delete();
			if((bool)$STATUS === false) $RETURN = false;
		}
		return $RETURN;
	}

	//############################################################
	//# Output Format Methods
	//############################################################
	
	public function Encrypt($value)
	{
		return md5(AUTH_SALT.$value);
	}

	//############################################################
	//# To_ Methods
	//############################################################
	
	public function to_array()
	{
		return $this->_iterator_data[$this->_iterator_position];
	}

	public function to_set()
	{
		return $this->_iterator_data;
	}

	//############################################################
	//# Countable Methods
	//############################################################
	
	public function count()
	{
		return count($this->_iterator_data);
	}

	//############################################################
	//# Iterator Methods
	//############################################################

	public function current()
	{
		return $this;
	}

	public function key()
	{
		return $this->_iterator_position;
	}

	public function next()
	{
		++$this->_iterator_position;
	}

	public function rewind()
	{
		$this->_iterator_position = 0;
	}

	public function valid()
	{
		return isset($this->_iterator_data[$this->_iterator_position]);
	}

	//############################################################
	//# ArrayAccess Methods
	//############################################################
	
	public function offsetExists($offset)
	{
		return isset($this->_iterator_data[$offset]);
	}

	public function offsetGet($offset)
	{
		if(is_null($offset))
		{
			++$this->_iterator_position;
			return $this->current();
		}
		$this->_iterator_position = $offset;
		return $this->current();
	}

	public function offsetSet($offset, $value)
	{
		if(is_null($offset))
			$this->_iterator_data[] = $value;
		else
			$this->_iterator_data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->_iterator_data[$offset]);
	}
}
?>
