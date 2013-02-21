ORM для CodeIgniter
===================

Библиотека предоставляет предоставлеят функционал ORM по типу Kohana в PHP фремвоке CodeIgniter

Примеры
-------

Включить ORM можно так: 

```php
  $this->load->config('orm');
  $this->load->model('institute');
```

Файл с моделью <code>institute.php</code> задаёт связь "много ко многим" между таблицами <code>my_exam</code> и <code>my_student</code> через таблицу <code>my_link_exam_student(id_exam, id_student)</code>:

```php
<?php
/** Фабрика таблиц студентов в БД */
class Institute extends CI_Model {
  /**
	 * Вернуть объект БД.
	 * 
	 * @access public
	 * @param string $method название таблицы
	 * @return object (объект)
	 */
	function __call($method, $arguments) 
	{
		return new $method();
	}
}

class DB extends ORM { var $database='default'; }

/** Класс экзамен */
class my_exam extends DB {
  var $title = 'Экзамены';
  var $has_many = array(
    'my_link_exam_student' => 'id_exam',
    'my_student' => 'my_link_exam_student'
    );
  }
/** Класс студент */
class my_student extends DB {
  var $title = 'Студенты';
  var $has_many = array(
    'my_link_exam_student' => 'id_student',
    'my_exam' => 'my_link_exam_student'
    );
  var $belongs_to = array(
    'id_group' => 'my_group',
    'id_degree' => 'my_degree'
    );
  public function __toString() {
    if(isset($this->fio)) return $this->fio;
    return '';
    }
  }
```

В массиве <code>$belongs_to</code> указываются элементы поле => таблица, а в <code>$has_many</code> наоборот. Также можно указать колонки таблицы <code>$fields</code> в виде массива поле => название

После загрузки модели доступны функции:

*  <code>$this->institute->my_student()->all()->as_list()</code> получить студентов в виде массива id => fio
*  <code>$this->institute->my_student()->all(array('with'=>'my_exam'))->group('fio')</code> получить студентов с их экзаменами
*  <code>$this->institute->my_student()->find(array('birth >'=>'1.1.1990','id_degree'=>array(4,5)),array('limit'=>10))</code> поиск студента

Пример сохранения студента из <code>$_REQUEST</code>:

```php
$st = $this->institute->my_student()->find_one(array('fio'=>$_REQUEST['fio']));
if(!$st->exists()){
  $st->filter_nulls()->save($_REQUEST);
}
```

Сохранение связей "принадлежит" осуществляется путем поиска соответствующей записи по указанному полю. Допустим студент принадлежит группе. Его можно сохранить, передав массив:

```php
$student->save(array(
  'fio'=>'Василий Пупкин',
  'birth'=>'10.01.1990',
  'id_group'=>array('code'=>"МП-11"), //но можно указать id
  'id_degree'=>array('sysname'=>'бакалавр')
));
```

Связи "имеет много" и "много ко многим" нужно сохранять отдельными вызывами:

```php
$student->save($this->institute->my_exam()->find(array('id_exam' => $_REQUEST['exams'])));
```
