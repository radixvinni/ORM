<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * логический объект системы
 *
 * @author		Александр Винников <al.vin@bk.ru>
 * @link 		 https://github.com/CJness/ORM
 * @package ORM
 * @category Библиотека
 */
class ORM {

	/**
	 * Конструктор объекта.
	 * 
	 * @access public
	 * @param mixed $params - ИД объекта либо сам объект (default: NULL)
	 * @return void
	 */
	function __construct($params = NULL) 
	{
		switch (TRUE) 
		{
      		case is_numeric($params):
        		$this->find_one($params);
        	break;
			
      		case is_array($params):
      		case is_object($params):
        		$this->fill_object($params);
        	break;
        	
        	default:
        		$this->fill_object();
        	break;
    	}
	}
	/**
	 * Название таблицы базы данных, в которой происходит поиск объектов данного класса. Предназначено для использования в SELECT FROM.
	 * 
	 * @access public
	 * @return string название таблицы
	 */
	function table() {
	  if(!isset($this->table)) return strtolower(get_class($this));
	  return $this->table;
	}
	/**
	 * Название таблицы(без названия схемы) базы данных, в которой происходит поиск объектов данного класса
	 * 
	 * @access public
	 * @return string название таблицы
	 */
	function table_no_schema() {
	  if(!isset($this->table)) return strtolower(get_class($this));
	  return $this->table;
	}
	/**
	 * Вызов метода. Может быть именем поля связи для отношений "принадлежит" или именем класса для связи "имеет" 
	 * 
	 * @access public
	 * @param string $method
	 * @param array $arguments
	 * @return object (связанный объект или массив объектов)
	 */
	function __call($method, $arguments) 
	{
		if ( ! $this->exists())
		{
			return;
		}
	
		$arguments = (isset($arguments[0])) ? $arguments[0] : NULL;
	  
    switch (TRUE)
		{
			case array_key_exists($method,$this->has_many()):
				return $this->return_has_many($method, $arguments);
			break;
			
			case in_array($method, $this->has_one()):
				return $this->return_has_one($method, $arguments);
			break;
			
			case in_array($method, $this->belongs_to()):
				return $this->return_belongs_to($method, $arguments);
			break;
		}
	}
	
	/* PHP is not a functional language. Functions can not be refered as arrays, 
	so it's not ambiguous to have a function with the same name as a variable. */
	var $title = '';
	/**
	 * Вернуть массив отношений "имеет много". 
	 * 
	 * @access public
	 * @return array Ключи массива - названия таблиц(=классов). Значения - названия полей связанного объекта по которым устанавливается связь.
	 */
   //Вообще говоря, связи транзитивны. Если предприятие имеет много контрактов, а контакт имеет много продукции, то предприятие имеет много продукции.
   //Но эта функция возвращает отнашения с конкретной таблицей, а транзитивность реализуется дальше в set_options.
   //Возможна переделка на рекурсивную реализацию, но тут много переделывать. и вообще это будет работать так, что любая таблица будет иметь много любую другую таблицу! Жесть!
	function has_many() 
	{ 
		if (isset($this->has_many))
		  return $this->has_many;
    $this->has_many = array();
    foreach($this->db()->query("
      SELECT
          tc.table_name, kcu.column_name
      FROM 
          information_schema.table_constraints AS tc 
          JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
          JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
      WHERE constraint_type = 'FOREIGN KEY' AND ccu.table_name='{$this->table()}';")->result_array() as $v) 
      { $this->has_many[$v['table_name']] = $v['column_name']; }
      return $this->has_many;
	}
	
	/**
	 * Вернуть массив отношений "имеет одного". 
	 * 
	 * @access public
	 * @return array Ключи массива - названия таблиц(=классов). Значения - названия полей связанного объекта по которым устанавливается связь.
	 */
	function has_one() 
	{ 
		if (isset($this->has_one))
		  return $this->has_one; 
		$this->has_one = array();
    return $this->has_one;
	}
	
	/**
	 * Вернуть массив отношений "принадлежит". TODO: удалять все отношения "имеет одного"
	 * 
	 * @access public
	 * @return array  Ключи - названия полей данного объекта по которым устанавливается связь. Значения массива - названия таблиц(=классов).
	 */
	function belongs_to() 
	{ 
		if (isset($this->belongs_to))
  		return $this->belongs_to; 
    $this->belongs_to = array();
    foreach($this->db()->query("
      SELECT
          ccu.table_name, kcu.column_name
      FROM 
          information_schema.table_constraints AS tc 
          JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
          JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
      WHERE constraint_type = 'FOREIGN KEY' AND tc.table_name='{$this->table()}';")->result_array() as $v) 
      { $this->belongs_to[$v['column_name']] = $v['table_name']; }
      return $this->belongs_to;
	}
	
	
	/**
	 * Массив полей, которые нуждаются в проверке.
	 * 
	 * @access public
	 * @return array
	 */
	function validation()
	{
		return array();
	}
	
	/**
	 * Вернуть экземпляр CI.
	 * 
	 * @access public
	 * @return object (CodeIgniter)
	 */
	function CI() 
	{ 
		return get_instance(); 
	}
		
	/**
	 * Вернуть экземпляр db.
	 * 
	 * @access public
	 * @return object (CodeIgniter)
	 */
	var $database='edb';
	function db() 
	{ 
		if(!isset($this->db)) 
		  $this->db = $this->CI()->load->database($this->database, TRUE);
		
		return $this->db; 
	}
	
	/**
	 * Вернуть объекты по отношениям "имеет много".
	 * 
	 * @access public
	 * @param string $method
	 * @param mixed $data (default: NULL)
	 * @return object (связанный объект)
	 */
	function return_has_many($method, $data = NULL) 
	{
		$relation 	 = ucfirst($method);
		$relation 	 = new $relation();
		
		$fk = $this->has_many();
		$fk = $fk[$method];
		
    $this_class     = strtolower(get_class($this));
		$relation_class = strtolower($method);
		
    $this_has_many = $this->has_many();
    $relation_has_many = $relation->has_many();
		if (isset($this_has_many[$relation_class]) AND 
        isset($relation_has_many[$this_class]) AND 
        $this_has_many[$relation_class]==$relation_has_many[$this_class] AND
        $relation_class !== $this_class) 
    {
		    $rel_table = $this_has_many[$relation_class];
        $data = array(
					$this_has_many[$rel_table] => $this->id,
				);
        $relation->find($data,array('with'=>$rel_table));
    }
		else
    
		if (is_numeric($data))
		{
			$where = array(
				$relation->get_primary_key() => (int) $data,
				$fk => (int) $this->id
			);
			
			$relation->find_one($where);
		}
		else
		{
			//$relation->fill_object($data);
			$relation = $relation->find(array($fk => (int) $this->id),$data);
		}
		
		return $relation;
	}
	function count_has_many($method, $data = NULL) 
	{
		$relation 	 = ucfirst($method);
		$relation 	 = new $relation();
		
		$fk = $this->has_many();
		$fk = $fk[$method];

		return	$relation = $relation->count(array($fk => (int) $this->id));

	}
	/**
	 * Вернуть объекты по отношениям "имеет одного".
	 * 
	 * @access public
	 * @param string $method
	 * @param mixed $data (default: NULL)
	 * @return object (связанный объект)
	 */
	function return_has_one($method, $data = NULL)
	{
		$relation 	 = ucfirst($method);
		$relation 	 = new $relation();
		$where = array(
			$this->get_foreign_key() => (int) $this->id
		);
		
		if (is_numeric($data))
		{
			$where[$relation->get_primary_key()] = (int) $data;
		}
		
		$relation->find_one($where);
		
		if (is_array($data) OR is_object($data))
		{
			$relation->fill_object($data);
		}
		
		return $relation;
	}
	
	/**
	 * Вернуть объекты по отношениям "принадлежит".
	 * 
	 * @access public
	 * @param string $method
	 * @param mixed $data (default: NULL)
	 * @return object (связанный объект)
	 */
	function return_belongs_to($fk, $data = NULL) 
  	{
  		$method = $this->belongs_to();
  		$method = $method[$fk];
  		
  		$relation 	 = ucfirst($method);
  		$relation 	 = new $relation();
  		
      
      if(isset($this->{$fk})) 
        $relation->find_one($this->{$fk});
  		
  		if (is_array($data) OR is_object($data))
  		  $relation->find_one($data);
		  
		  return $relation;
  	}
	
	/**
	 * Найти объекты
	 * 
	 * @access public
	 * @param mixed $where (default: NULL)
	 * @param array $options (default: NULL)
	 * @return ormArray (объекты)
	 */
	function find($where = NULL, $options = NULL)
	{
		if (is_numeric($where)) 
		{
			return $this->find_one($where, $options);
		}
		
		$this->set_where($where);
		$this->set_options($options);
		
		return $this->fill_objects($this->db()->get($this->table())->result());
	}
	
	/**
	 * Вернуть все объекты данного класса.
	 * 
	 * @access public
	 * @param array $options (default: NULL)
	 * @return object (a object with relation(s))
	 */
	function all($options = NULL)
	{
		return $this->find(NULL, $options);
	}
	
	
	/**
	 * Вернуть количество объектов данного класса.
	 * 
	 * @access public
	 * @param array $where (default: NULL)
	 * @return boolean
	 */
	function count($where = NULL, $options = NULL)
	{
		$this->set_where($where);
		$this->set_options($options);
		
		return $this->db()->count_all_results($this->table());
	}
	
	/**
	 * Найти объект.
	 * 
	 * @access public
	 * @param mixed $where (default: NULL)
	 * @param array $options (default: NULL)
	 * @return object (объект)
	 */
	function find_one($where = NULL, $options = NULL)
	{
		$options['limit'] = 1;
		
		if (is_numeric($where)) 
		{
			$where = array($this->get_primary_key() => (int) $where);
		}
		
		$this->set_where($where);
		$this->set_options($options);
		
		return $this->fill_object($this->db()->get($this->table())->row());
	}
	
	/**
	 * Вернуть первый объект.
	 * 
	 * @access public
	 * @param mixed $where (default: NULL)
	 * @param array $options (default: NULL)
	 * @return object (объект)
	 */
	function first($where = NULL, $options = NULL) 
	{	
		if ( ! isset($options['order_by'])) 
		{
      		$options['order_by'] = $this->table().".{$this->get_primary_key()} ASC";
    	}
    	
		return $this->find_one($where, $options);
	}
	
	/**
	 * Вернуть последный объект.
	 * 
	 * @access public
	 * @param mixed $where (default: NULL)
	 * @param array $options (default: NULL)
	 * @return object (связанный объект)
	 */
	function last($where = NULL, $options = NULL) 
	{
		if ( ! isset($options['order_by'])) 
		{
      		$options['order_by'] = $this->table().".{$this->get_primary_key()} DESC";
    	}
    	
		return $this->find_one($where, $options);
	}
	
	/**
	 * Узнать результат поиска(устарело).
	 * 
	 * @access public
	 * @return boolean
	 */
	function exists()
  	{
  		return (isset($this->id) AND ! is_null($this->id) AND ! empty($this->id));
  	}
  /**
	 * Узнать результат валидации.
	 * 
	 * @access public
	 * @return boolean
	 */
		
  	function validate($rules = NULL)
  	{
  		if ( ! is_array($rules))
  		{
  			$rules = $this->validation();
  		}
  		
  		if ( ! count($rules))
  		{
  			return TRUE;
  		}
  		
  		$this->CI()->load->library('form_validation');
    	$this->CI()->load->library('ORM_validation');
    		
 		$validation = new ORM_validation();
 		
   		return $validation->validate($this, $rules);
  	}
	
	/**
	 * Сохранить объект.
	 * 
	 * @access public
	 * @return boolean
	 */
	function save()
	{
		$arguments = func_get_args();
		
		foreach ($arguments as $arg)
		{
			switch (TRUE)
			{
				case ($arg instanceof ormArray):
					foreach ($arg as $object)
					{
						$this->save_relation($object);
					}
				break;
				
				case is_object($arg):
					$this->save_relation($arg);
				break;
				
				case is_array($arg):
					$this->fill_object($arg);
				break;
			}
		}
		
		if ( ! $this->validate())
		{
			return FALSE;
		}
		
		if ($this->exists())
		{
			return $this->update();
		}
		else
		{
			return $this->insert();
		}
	}
	
	/**
	 * Установить связь объекта с данным.
	 * 
	 * @access public
	 * @param object $relation
	 * @return void
	 */
	function save_relation($relation)
	{
		$this_class     = strtolower(get_class($this));
		$relation_class = strtolower(get_class($relation));
		
    $this_has_many = $this->has_many();
    $relation_has_many = $relation->has_many();
		switch (TRUE)
		{
			//случай связи многих ко многим, 1.уточнить название таблицы связей 2. выполняется insert, а если связь уже есть?? а быть не должно??
      case (isset($this_has_many[$relation_class]) AND 
            isset($relation_has_many[$this_class]) AND 
            $this_has_many[$relation_class]==$relation_has_many[$this_class]):
				$rel_table = $this_has_many[$relation_class];
        $data = array(
					$this_has_many[$rel_table] => $this->id,
					$relation_has_many[$rel_table] => $relation->id
				);
				
				$this->db()->insert($rel_table, $data);
				
				$relation->save();
			break;
			//остальное не тестировалось нужно испавлять:
			case in_array($relation_class, $this->has_many()):
				$relation->{$this->get_foreign_key()} = $this->id;
				$relation->save();
			break;
			
			case in_array($relation_class, $this->has_one()):
				$relation->{$this->get_foreign_key()} = $this->id;
				$relation->save();
			break;
			
			case in_array($relation_class, $this->belongs_to()):
				$relation->save();
				
				$this->{$relation->get_foreign_key()} = $relation->id;
			break;
		}
	}
	
	/**
	 * Сохранать новый объект.
	 * 
	 * @access protected
	 * @return boolean
	 */
	protected function insert()
	{
		if ($this->db()->insert($this->table(), $this->sanitize())) 
		{
			if ($id = $this->db()->insert_id())
			{
				$this->id = $id;
			}
			
			$this->log('insert');
			$this->db()->cache_delete_all();
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Обновить объект в БД.
	 * 
	 * @access protected
	 * @return boolean
	 */
	protected function update()
	{
		$this->set_where($this->id);
		
		if ($this->db()->update($this->table(), $this->sanitize())) 
		{
			$this->log('update');
			$this->db()->cache_delete_all();
	
	   		return TRUE;
	    }

    	return FALSE;
	}
	
	/**
	 * Удалить объект из БД.
	 * 
	 * @access public
	 * @return void
	 */
	function delete()
	{
		if ( ! $this->exists())
		{
			return FALSE;
		}
	
		$arguments = func_get_args();
		
		foreach ($arguments as $arg)
		{
			switch (TRUE)
			{
				case ($arg instanceof ormArray):
					foreach ($arg as $object)
					{
						$this->delete_relation($object);
					}
				break;
				
				case is_object($arg):
					$this->delete_relation($arg);
				break;
			}
		}
		
		if ( ! count($arguments))
		{
			foreach ($this->has_many() as $relation)
			{
				foreach ($this->return_has_many($relation) as $object)
				{	
					$this->delete_relation($object);
				}
			}
			
			foreach ($this->has_one() as $relation)
			{
				$this->delete_relation($this->return_has_one($relation));
			}
		
			$where = array(
				$this->get_primary_key() => $this->id
			);
		
			return $this->db()->delete($this->table(), $where);
		}
	}
	
	/**
	 * Удалить связь.
	 * 
	 * @access public
	 * @param object $relation
	 * @return void
	 */
	function delete_relation($relation)
	{
		if ( ! $relation->exists())
		{
			return;
		}
    //[не тестировалось]
		$this_class     = strtolower(get_class($this));
		$relation_class = strtolower(get_class($relation));
		
		switch (TRUE)
		{
			case (in_array($relation_class, $this->has_many()) AND in_array($this_class, $relation->has_many())):
				$where = array(
					$this->get_foreign_key() => $this->id,
					$relation->get_foreign_key() => $relation->id
				);
				
				$this->db()->delete($this->format_join_table($this->table(), $relation->table()), $where);
			break;
			
			case (in_array($relation_class, $this->has_many()) AND in_array($this_class, $relation->belongs_to())):
			case in_array($relation_class, $this->has_one()):
			case in_array($relation_class, $this->belongs_to()):
				$relation->{$this->get_foreign_key()} = NULL;
				$relation->save();
			break;
		}
	}
	/**
	 *  Удалить нулевые поля.
	 * 
	 * @access protected
	 * @return array
	 */
	public function filter_nulls()
	{
		foreach ($this as $key => $val)
		{
			if ($val===NULL)
			{
				unset($this->{$key});
			}
		}
		return $this;
	}
	/**
	 * Получить массив полей объекта для сохранения.
	 * 
	 * @access protected
	 * @return array
	 */
	public function sanitize()
	{
		$array = array();
	
		foreach ($this as $key => $val)
		{
			if (array_key_exists($key, $this->get_fields()))
			{
				$array[ $key ] = $val;
			}
		}
		
		return $array;
	}

	/**
	 * Получить массив названий полей.
	 * 
	 * @access public
	 * @return array
	 */
	function get_fields()
	{
		if(isset($this->fields))return $this->fields;
		//$this->fields = array();
		foreach($this->db()->query("select c.column_name,descr.description as comments
from INFORMATION_SCHEMA.COLUMNS c
     join pg_catalog.pg_class       klass on (table_name = klass.relname and klass.relkind = 'r')
left join pg_catalog.pg_description descr on (descr.objoid = klass.oid and descr.objsubid = c.ordinal_position)
 where table_name = '{$this->table_no_schema()}' ORDER BY c.ordinal_position;")->result_array() as $v) 
      { $this->fields[$v['column_name']] = $v['comments']?$v['comments']:$v['column_name']; }
      return $this->fields;
	}
	
	/**
	 * Получить внешний ключ(устарело).
	 * 
	 * @access public
	 * @return string
	 */
	function get_foreign_key()
	{
		return 'id_'.strtolower(get_class($this)).'';
	}
	function get_primary_key()
	{
		$this->get_fields();
		if(isset($this->fields)) return key($this->fields);
		//return 'id';
		list($prefix,$rest) = explode('_',get_class($this),2);
		return 'id_'.strtolower(trim($rest,'s')).'';
	}
	
	/**
	 * Получить ошибки варидации
	 * 
	 * @access public
	 * @return string
	 */
	function get_validation_errors()
	{
		$validation_errors = $this->CI()->config->item('orm_validation_errors');
		
		if ( ! $validation_errors)
		{
			return array();
		}
		
		return $validation_errors;
	}
	
	/**
	 * Установить параметры поиска.
	 * 
	 * @access public
	 * @param mixed $where (default: NULL)
	 * @return void
	 */
	function set_where($where = NULL) 
	{	
		if ( ! $where) 
		{
			return;
		}
		elseif (is_numeric($where))
		{
			$this->db()->where("{$this->table()}.{$this->get_primary_key()}", (int) $where);
			
			return;
		}
		
		foreach ($where as $field => $value) 
		{
      		if (strpos($field, '.') === FALSE) 
      		{
      			$field = $this->table().'.'.$field;
      		}

      		if (is_array($value)) 
      		{
				$this->db()->where_in($field, $value);
      		} 
      		else 
      		{
				$this->db()->where($field, $value);
			}
   }
    	
    	///$this->db()->select($this->table().'.*');
	}
	
	/**
	 * Установить опции поиска.
	 * 
	 * @access public
	 * @param array $options (default: NULL)
	 * @return void
	 */
	function set_options($options = NULL) 
	{
		if ( ! $options) 
		{
			return;
		}
		
    	foreach ($options as $option => $value) 
    	{
      		switch ($option) 
      		{
        		case 'limit':
          			if (is_numeric($value)) 
          			{
          				$value = array($value);
          			}
          			
          			if ( ! isset($value[1])) 
          			{
          				$value[1] = NULL;
          			}
          			
          			$this->db()->{$option}($value[0], $value[1]);
          		break;
          		
	        	case 'join':
					if(is_array($value[0])) 
						foreach($value as $value) {
							if ( ! isset($value[2])) 
								$value[2] = NULL;
							$this->db()->join($value[0], $value[1], $value[2]);
						}
	        		else {
	        		if ( ! isset($value[2])) 
	        			$value[2] = NULL;
	        		$this->db()->join($value[0], $value[1], $value[2]);
				}
	          	break;
				case 'with':
	        		if (is_array($value)) 
						foreach($value as $relation)
							$this->set_join($relation);
					else $this->set_join($value);
	          	break;
	        
	        	default:
	          		$this->db()->{$option}($value);
	        	break;
	      	}
	    }
	}
	
	/**
	 * Установить связи для поиска связанных объектов.
	 * 
	 * @access public
	 * @param object $relation идентификатор связи, т.е. название таблицы либо поля связи(для отношения "принадлежит")
	 * @return void
	 */
	function set_join($relation) 
	{
		switch (TRUE)
		{
			//замечание: тут сначала надо проверить связь много ко многим
      case array_key_exists($relation, $this->has_many()):
				$this->db()->join($relation, $relation.'.'.$this->has_many[$relation].' = '.$this->table().'.'.$this->get_primary_key(),'left');
			break;
			
			case array_key_exists($relation,$this->has_one()):
				$this->db()->join($relation, $relation.'.'.$this->has_one[$relation].' = '.$this->table().'.'.$this->get_primary_key(),'left');
			break;
			
			case array_key_exists($relation,$this->belongs_to()):
				$relclass = $this->belongs_to[$relation];
				$relclass = new $relclass();
				$this->db()->join($this->belongs_to[$relation], $this->belongs_to[$relation].'.'.$relclass->get_primary_key().' = '.$this->table().'.'.$relation);
			break;
			
			default: // many to many
/*				$relation = new $relation();
				$join_table = $this->format_join_table($this->table(), $relation->table());

				$this->db()->join($join_table, $join_table.'.'.$this->get_foreign_key().' = '.$this->table().'.'.$this->get_primary_key(), 'right');
				$this->db()->where($join_table.'.'.$relation->get_foreign_key(), $this->{$relation->get_foreign_key()});
*/			break;
			
		}
	}
	
	/**
	 * Установить название таблицы связи для поиска по отношению "многих ко многим".
	 * 
	 * @access public
	 * @return string
	 */
	function format_join_table() 
  	{
    	$tables = func_get_args();
    	sort($tables);
    	
    	return implode('_', $tables);
  	}
	
	/**
	 * Заполнить объект данными из массива.
	 * 
	 * @access public
	 * @param mixed $data (default: NULL)
	 * @return object
	 */
	function fill_object($data = NULL)
	{
		switch (TRUE)
		{
			case is_array($data):
			case is_object($data):
				foreach ($data as $field => $value)
				if(is_array($value))
        {
          $this->{$field} = $this->return_belongs_to($field,$value)->id;
        }
        else
        {
					$this->{$field} = $value;
				}
				if(isset($this->{$this->get_primary_key()})) $this->id = $this->{$this->get_primary_key()};
			break;
			
			default:
				foreach (array_keys($this->get_fields()) as $field)
				{
					$this->{$field} = NULL;
				}
			break;
		}
		
		return $this;
	}
	
	/**
	 * Заполнить массив объектами из массива.
	 * 
	 * @access public
	 * @param mixed $data
	 * @return object (a object with relation(s))
	 */
	function fill_objects($data) 
	{
		$object  = get_class($this);
		$objects = new ormArray();
		
    	foreach ($data as $row) 
    	{
      		$objects[] = new $object($row);
    	}
    	
    	return $objects;
	}
  /**
	 * Преобразовать объект в строку
	 * 
	 * @access public
	 * @return string
	 */
	
  public function __toString() {
    if(isset($this->short_name)) return $this->short_name;
    return '';
  }
}

/**
 * Класс представления массива логических объектов.
 * 
 * @extends ArrayObject
 */
class ormArray extends ArrayObject {

	/**
	 * Вызов метода.
	 * 
	 * @access public
	 * @param string $method
	 * @param array $arguments
	 * @return void
	 */
	function __call($method, $arguments) 
	{
		$arguments = (isset($arguments[0])) ? $arguments[0] : NULL;
	
		if (in_array($method, array('save', 'delete')))
		{
			foreach ($this as $object)
			{
				$object->$method($arguments);
			}
		}
		else
		{
			$objects = new ormArray();
			
			foreach ($this as $object)
			{
				$object = $object->$method($arguments);
			
				if ($object instanceOf ormArray)
				{
					foreach ($object as $item)
					{
						$objects[] = $item;
					}
				}
				else
				{
					$objects[] = $object;
				}
			}
			
			return $objects;
		}
	}

	/**
	 * Первый элемент.
	 * 
	 * @access public
	 * @return object
	 */
	function first() 
	{
		return reset($this);
	}
	
	/**
	 * Последний элемент.
	 * 
	 * @access public
	 * @return object
	 */
	function last() 
	{
		return end($this);
	}
	
  
	/**
	 * Преобразовать в массив $e->id => $e->__ToString().
	 * 
	 * @access public
	 * @return array
	 */
   function as_list() {
     $ret = array();
     foreach ($this as $item) {
       $ret[$item->id] = (string)($item);
     }
     return $ret;
	}
     
	/**
	 * Сгруппировать объекты в массив по набору полей.
	 * 
	 * @access public
	 * @param mixed $keys - поле группировки или массив полей группировки
	 * @return array
	 */
	function group($keys) {
	  $i=1;
		if(!is_array($keys)) $keys=array($keys);
	  
	  function set_elem(&$var, $val, $keys, $depth, &$i)
	  {
	    if(!isset($keys[$depth])) {
	      $var['items'][] = $val;
	    }
	    else {
	      if(!isset($var['items'][$val[$keys[$depth]]])) 
	       $var['items'][$val[$keys[$depth]]] = array(
	          'groupname'=>$val[$keys[$depth]], 
	          'gid' => $i++, 
	          'items' => array(),
	          'sum' => 0,
	          'count' => 0,
	        );
	      set_elem($var['items'][$val[$keys[$depth]]],$val,$keys,$depth+1, $i);
	    }
	  if(isset($val['count'])) $var['count'] += $val['count'];
	  if(isset($val['sum'])) $var['sum'] += $val['sum'];
	  }
	  
	  $groups = array('gid'=>'0', 'count'=>0, 'sum'=>0);
		foreach ($this as $item) {
		  //group['shortname'] = $items[$key], ['gid']=unique_id(), ['key'] = $key
		    $item->gid = $i++;
		    set_elem($groups, get_object_vars($item), $keys, 0, $i);
		}
		return $groups;
	}
	
}

spl_autoload_register('orm_autoload');

/**
 * Автоматическая загрузка моделей(???).
 * 
 * @access public
 * @param string $class
 * @return void
 */
function orm_autoload($class) 
{
	$class = APPPATH.'models/'.strtolower($class).EXT;
  
	if (file_exists($class)) 
	{
		include $class;
	}
}
