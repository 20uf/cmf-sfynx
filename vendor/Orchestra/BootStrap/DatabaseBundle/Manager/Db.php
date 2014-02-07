<?php
/**
 * This file is part of the <Tools> project.
 * 
 * @category   Admin_Manager
 * @package    Manager
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2013-11-14
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BootStrap\DatabaseBundle\Manager;

use BootStrap\DatabaseBundle\Exception\DatabaseException;
use BootStrap\TranslationBundle\Route\AbstractFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Instance zend
 * 
 * @category   Admin_Manager
 * @package    Manager
 * 
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class Db extends AbstractFactory
{
	const BIND_TYPE_NUM = 'NUM';
	const BIND_TYPE_INT = 'INT';
	const BIND_TYPE_STR = 'CHAR';
	
	/**
	 * @var integer
	 */	
	private $InsertId;
		
    /**
     * Constructor.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * Return the connexion params of a database.
     *
     * @param string $db_name
     * @param string $varName
     * @param string $varValue
     * @param string $var
     * @param string $dataType
     * @access  public
     * @return array
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2013-11-14
     */
    public function addArrayParamsQuery(&$tabParamsquery, $varName, $varValue, $var = 0, $dataType = '')
    {
    	$tabParamsquery[$varName] = array(
    			"NAME" 		=> $varName,
                "VALUE" 	=> $varValue,
				"TYPE" 		=> $dataType
    	);
    }   

    /**
     * Execute the request qwith params
     *
     * @param string $query
     * @param array $tableauParams
     * @param int $nb
     * @access  public
     * @return array
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2013-11-14
     */
    public function executeQuery($query, $tabParams = array(), $log = false)
    {
    	if (is_array($tabParams)) {
    		foreach ($tabParams as $nom => $param) {
    			$nameParam  = $param["NAME"];
    			$valueParam = $param["VALUE"];
    			$typeParam  = $param["TYPE"];
    			if ($typeParam == self::BIND_TYPE_NUM || $typeParam == self::BIND_TYPE_INT) {
    				$query = preg_replace('/:' . $nameParam . '/i', $valueParam, $query);
    			} elseif ($typeParam == self::BIND_TYPE_STR) {
    				$query = preg_replace('/:' . $nameParam . '/i', $this->quoteSql($valueParam), $query);
    			} elseif (is_numeric($valueParam) || ($valueParam == "NULL")) {
    				$query = preg_replace('/:' . $nameParam . '/i', $valueParam, $query);
    			} else {
    				$query = preg_replace('/:' . $nameParam . '/i', $this->quoteSql($valueParam), $query);
    			}
    		}
    	}
    	
    	str_replace('select', 'select', $query, $cont_select);
    	str_replace('insert', 'insert', $query, $cont_insert);
    	
    	if ($log) {
    		print_r($query);
    		return true;
    	}
    	
    	if ($cont_select >= 1) {
    		return $this->getConnection()->executeQuery($query)->fetchAll();
    	} elseif ($cont_insert >= 1) {
    		$this->getConnection()->executeQuery($query);
    		$this->InsertId = $this->getConnection()->lastInsertId();
    		return true;
    	} else {
    		return $this->getConnection()->executeQuery($query)->execute();
    	}
    }    
    
    public function getInsertedId() 
    {
    	return $this->InsertId;	
    }
    
    /**
     * Quote SQL
     *
     * @param string $str
     * @access  private
     * @return string
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2013-11-14
     */    
    private function quoteSql($str) {
    	return "'".addslashes($str)."'";
    }    
}