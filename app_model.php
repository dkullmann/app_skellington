<?php
/**
 * Application Model class
 *
 * Add your application-wide methods to the class, your models will inherit them.
 *
 * @package       app
 */
App::import('Lib', 'LazyModel.LazyModel');
class AppModel extends LazyModel {
/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index. Eg:
 *
 * var $actsAs = array('Translate', 'MyBehavior' => array('setting1' => 'value1'))
 *
 * @var array
 * @access public
 * @link http://book.cakephp.org/view/1072/Using-Behaviors
 */
	var $actsAs = array(
		'CakeDjjob.CakeDjjob',
		'Containable',
        'Linkable.Linkable'
		'Log.Logable' => array('change' => 'full')
	);

/**
 * Number of associations to recurse through during find calls. Fetches only
 * the first level by default.
 *
 * @var integer
 * @access public
 * @link http://book.cakephp.org/view/1057/Model-Attributes#recursive-1063
 */
	var $recursive = -1;

/**
 * Query currently executing.
 *
 * @var array
 * @access public
 */
	var $query = null;

    public function __construct($id = false, $table = null, $ds = null) {
        parent::__construct($id, $table, $ds);
        $this->_findMethods['paginatecount'] = true;
    }

/**
 * Automatically set contain to false if not otherwise specified
 *
 * @param array $queryData Data used to execute this query, i.e. conditions, order, etc.
 * @return mixed true if the operation should continue, false if it should abort; or, modified
 *               $queryData to continue with new $queryData
 * @access public
 * @link http://book.cakephp.org/view/1048/Callback-Methods#beforeFind-1049
 */
    public function beforeFind($queryData = array()) {
        if (!isset($queryData['contain'])) {
            $queryData['contain'] = false;
        }
        return $queryData;
    }

/**
 * Removes 'fields' key from count query on custom finds when it is an array,
 * as it will completely break the Model::_findCount() call
 *
 * @param string $state Either "before" or "after"
 * @param array $query
 * @param array $data
 * @return int The number of records found, or false
 * @access protected
 * @see Model::find()
 */
    public function _findPaginatecount($state, $query, $results = array()) {
        if ($state == 'before' && isset($query['operation'])) {
            if (!empty($query['fields']) && is_array($query['fields'])) {
                if (!preg_match('/^count/i', $query['fields'][0])) {
                    unset($query['fields']);
                }
            }
        }
        return parent::_findCount($state, $query, $results);
    }

/**
 * Custom Model::paginateCount() method to support custom model find pagination
 *
 * @param array $conditions
 * @param int $recursive
 * @param array $extra
 * @return array
 */
    public function paginateCount($conditions = array(), $recursive = 0, $extra = array()) {
        $parameters = compact('conditions');

        if ($recursive != $this->recursive) {
            $parameters['recursive'] = $recursive;
        }

        if (isset($extra['type']) && isset($this->_findMethods[$extra['type']])) {
            $extra['operation'] = 'count';
            return $this->find($extra['type'], array_merge($parameters, $extra));
        } else {
            return $this->find('count', array_merge($parameters, $extra));
        }
    }

/**
 * Convenience method to update one record without invoking any callbacks
 *
 * @param array $fields Set of fields and values, indexed by fields.
 *    Fields are treated as SQL snippets, to insert literal values manually escape your data.
 * @param mixed $conditions Conditions to match, true for all records
 * @return boolean True on success, false on Model::id is not set or failure
 * @access public
 * @author Jose Diaz-Gonzalez
 * @link http://book.cakephp.org/view/1031/Saving-Your-Data
 **/
	function update($fields, $conditions = array()) {
		$conditions = (array) $conditions;
		if (!$this->id) return false;

		$conditions = array_merge(array("{$this->alias}.$this->primaryKey" => $this->id), $conditions);

		return $this->updateAll($fields, $conditions);
	}

/**
 * Disables/detaches all behaviors from model
 *
 * @param mixed $except string or array of behaviors to exclude from detachment
 * @param boolean $detach If true, detaches the behavior instead of disabling it
 * @return void
 * @access public
 * @author Jose Diaz-Gonzalez
 */
	function disableAllBehaviors($except = array(), $detach = false) {
		$behaviors = array_diff($this->Behaviors->attached(), (array) $except);
		foreach ($behaviors as &$behavior) {
			if ($detach) {
				$this->Behaviors->detach($behavior);
			} else {
				$this->Behaviors->disable($behavior);
			}
		}
	}

/**
 * Enables all previously disabled attachments
 *
 * @return void
 * @access public
 * @author Jose Diaz-Gonzalez
 */
	function enableAllBehaviors() {
		$behaviors = $this->Behaviors->attached();
		foreach($behaviors as &$behavior) {
			if (!$this->Behaviors->enabled($behavior)) {
				$this->Behaviors->enable($behavior);
			}
		}
	}

	function __findDistinct($fields = array()) {
		$fields = (array) $fields;

		foreach ($fields as &$field) {
			$field = "DISTINCT {$field}";
		}

		return $this->find('all', array(
			'contain' => false,
			'fields' => $fields));
	}

/**
 * __findRandom()
 *
 * Find a list of records ordered by rank.
 * Instead of executing a __findList() query to get the list of IDs,
 * you can pass an array of IDs via the $options['suppliedList']
 * argument.
 *
 * Two queries are executed, first a find('list') to generate a list of primary
 * keys, and then either a find('all') or find('first') depending on the return
 * amount specified (default 1).
 *
 * Pass find options to each query using the $options['list'] and $options['find']
 * arguments.
 *
 * Specify $options['amount'] as the maximum number of random items that should
 * be returned.
 *
 * If you already have an array of IDs(/primary keys), you can skip the find('list')
 * query by passing the array as $options['suppliedList'].
 *
 * @param $options array of standard and function-specific find options.
 * @return array
 * @access public
 * @author Jamie Nay
 * @link http://github.com/jamienay/find_random
 */
	function __findRandom($options = array()) {
		$amount = 1;
		if (isset($options['amount'])) $amount = $options['amount'];

		$findOptions = array();
		if (isset($options['find'])) {
			$findOptions = array_merge($findOptions, $options['find']);
		}

		if (!isset($options['suppliedList'])) {
			$listOptions = array();
			if (isset($options['list'])) {
				$listOptions = array_merge($listOptions, $options['list']);
			}

			$list = $this->find('list', $listOptions);
		} else {
			$list = $options['suppliedList'];
			$list = array_flip($list);
		}

		// Just a little failsafe.
		if (count($list) < 1) {
			return $list;
		}

		$originalAmount = null;
		if ($amount > count($list)) {
			$originalAmount = $amount;
			$amount = count($list);
		}

		$id = array_rand($list, $amount);

		if (is_array($id)) {
			shuffle($id);
		}

		if (!isset($findOptions['conditions'])) {
			$findOptions['conditions'] = array();
		}

		$findOptions['conditions'][$this->alias.'.'.$this->primaryKey] = $id;
		if ($amount == 1 && !$originalAmount) {
			return $this->find('first' , $findOptions);
		}
		return $this->find('all' , $findOptions);
	}

	function __findLookup($options = array()) {
		if (!is_array($options)) $options = array('conditions' => array($this->displayField => $options));
		$options = array_merge(array(
			'field' => $this->primaryKey,
			'create' => true,
			'conditions' => array()
		), $options);

		if (!empty($options['field'])) {
			$fieldValue = $this->field($options['field'], $options['condition']);
		} else {
			$fieldValue = $this->find('first', $options['conditions']);
		}

		if ($fieldValue !== false) return $fieldValue;
		if ($options['create'] === false) return false;

		$this->create($options['conditions']);
		if (!$this->save()) return false;

		$conditions[$this->primaryKey] = $this->id;
		if (empty($options['field'])) return $this->read();
		return $this->field($options['field'], $options['conditions']);
	}

/**
 * Checks that a given value is a valid postal code.
 *
 * Modified version of Validation::postal - allows for multiple
 * countries to be specified as an array.
 *
 * @param mixed $check Value to check
 * @param string $regex Regular expression to use
 * @param mixed $country Countries to use for formatting
 * @return boolean Success
 * @access public
 * @author Jamie Nay
 * @link http://github.com/jamienay/postal_validation
 */
	function postal_multiple($check, $regex = null, $country = null) {
		// List of regular expressions to use, if a custom one isn't specified.
		$countryRegs = array(
			'uk' => '/\\A\\b[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}\\b\\z/i',
			'ca' => '/\\A\\b[ABCEGHJKLMNPRSTVXY][0-9][A-Z][ ]?[0-9][A-Z][0-9]\\b\\z/i',
			'it' => '/^[0-9]{5}$/i',
			'de' => '/^[0-9]{5}$/i',
			'be' => '/^[1-9]{1}[0-9]{3}$/i',
			'us' => '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i',
			'default' => '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i' // Same as US.
		);

		$value = array_values($check);
		$value = $value[0];
		if ($regex) {
			return preg_match($regex, $value);
		} else if (!is_array($country)) {
			return preg_match($countryRegs[$country], $value);
		}

		foreach ($country as $check) {
			if (!isset($countryRegs[$check]) && preg_match($countryRegs['default'], $value)) {
				return true;
			} else if (preg_match($countryRegs[$check], $value)) {
				return true;
			}
		}

		return false;
	}

}
?>