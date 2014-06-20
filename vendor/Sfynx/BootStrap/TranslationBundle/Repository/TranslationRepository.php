<?php
/**
 * This file is part of the <Translation> project.
 *
 * @category   BootStrap_Repositories
 * @package    Repository
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-03-09
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BootStrap\TranslationBundle\Repository;

use Gedmo\Translatable\TranslatableListener;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Tool\Wrapper\EntityWrapper;
use Gedmo\Translatable\Mapping\Event\Adapter\ORM as TranslatableAdapterORM;
use Doctrine\DBAL\Types\Type;

use BootStrap\TranslationBundle\Builder\RepositoryBuilderInterface;

/**
 * Translation Repository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @category   BootStrap_Repositories
 * @package    Repository
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class TranslationRepository extends EntityRepository implements RepositoryBuilderInterface
{
        
    /**
     * Current TranslatableListener instance used
     * in EntityManager
     *
     * @var \Gedmo\Translatable\TranslatableListener
     */
    private $listener;
    
    /**
     * Value of the  associated translation class.
     * 
     * @var string
     */
    private $_entityTranslationName = "";    
    
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $_container;    

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManager $em, ClassMetadata $class)
    {
           parent::__construct($em, $class);
           
           if (isset($this->getClassMetadata()->associationMappings['translations']) && !empty($this->getClassMetadata()->associationMappings['translations']))
               $this->_entityTranslationName = $this->getClassMetadata()->associationMappings['translations']['targetEntity'];
    }
    
    /**
     * Gets the container instance.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     * @access protected  
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function getContainer()
    {
        return $this->_container;
    }    
  
    /**
     * Gets the container instance.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function setContainer($container)
    {
        $this->_container = $container;
        return $this;
    }      

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->getEntityName();
    }    

    /**
     * Count all fields existed from the given entity 
     *
     * @param boolean    $enabled    [0, 1]    
     * @return string                the count of all fields.
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function count($enabled = null){
        if (!is_null($enabled))
            return $this->_em->createQuery("SELECT COUNT(c) FROM {$this->_entityName} c WHERE c.enabled = '{$enabled}'")->getSingleScalarResult();
        else
            return $this->_em->createQuery("SELECT COUNT(c) FROM {$this->_entityName} c")->getSingleScalarResult();
    }    
    
    /**
     * add where for user roles 
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @return \Doctrine\ORM\QueryBuilder
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @author Riad Hellal <hellal.riad@gmail.com>
     */    
    public function checkRoles(\Doctrine\ORM\QueryBuilder $query)
    {
        if ( ($this->_container instanceof \Symfony\Component\DependencyInjection\ContainerInterface)
            && (true === $this->_container->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY'))
            && !($this->_container->get('security.context')->isGranted('ROLE_ADMIN'))    
        ){
            $entity_name = $this->_entityName;
            if (isset($GLOBALS['ENTITIES']['RESTRICTION_BY_ROLES']) && isset($GLOBALS['ENTITIES']['RESTRICTION_BY_ROLES'][$entity_name]) ){
                if (is_array($GLOBALS['ENTITIES']['RESTRICTION_BY_ROLES'][$entity_name])){
                    $route = $this->_container->get('request')->get('_route');
                    if ((empty($route) || ($route == "_internal")))
                        $route = $this->container->get('bootstrap.RouteTranslator.factory')->getMatchParamOfRoute('_route', $this->container->get('request')->getLocale());
                    if (!in_array($route, $GLOBALS['ENTITIES']['RESTRICTION_BY_ROLES'][$entity_name])){
                        return $query;
                    }
                }
                $user_roles    = $this->_container->get('bootstrap.Role.factory')->getAllUserRoles();
                $orModule = $query->expr()->orx();
                foreach($user_roles as $key => $role){
                    $orModule->add($query->expr()->like('a.heritage', $query->expr()->literal('%"'.$role.'"%')));
                }
                $query->andWhere($orModule);                            
            }
        }    
        return $query;
    }
    
    /**
     * Loads all translations with all translatable fields from the given entity
     *
     * @param string $locale
     * @param \Doctrine\ORM\Query $query
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return array/object of result query
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findTranslationsByQuery($locale, Query $query, $result = "array", $INNER_JOIN = false, $FALLBACK = true, $lazy_loading = true)
    {
        if (!$query) {
            throw new \Gedmo\Exception\InvalidArgumentException(sprintf(
                    'Failed to find Tree by id:[%s]',
                    $id
            ));
        }
        $query = $this->setTranslatableHints($query, $locale, $INNER_JOIN, $FALLBACK, $lazy_loading);
        //$query = $this->cacheQuery($query);
        if ($result == 'array') {
            $entities = $query->getArrayResult();
        } elseif ($result == 'object') {
            $entities = $query->getResult();
        } else {
            throw new \InvalidArgumentException("We haven't set the good option value : array or object !");
        }
        // Frees the resources used by the query object.
        $query->free();
   
        return $entities;
    }    
    
    /**
     * return query in cache
     *
     * @param \Doctrine\ORM\Query 	$query
     * @param int					$time
     * @param string 				$MODE	[MODE_GET, MODE_PUT , MODE_NORMAL , MODE_REFRESH]	
     * @param boolean               $setCacheable
     * @param string                $namespace
     * @param string                $input_hash
     * @return \Doctrine\ORM\Query
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function cacheQuery(Query $query, $time = 3600, $MODE = \Doctrine\ORM\Cache::MODE_NORMAL, $setCacheable = true, $namespace = '', $input_hash = '')
    {
    	if (!$query) {
    		throw new \Gedmo\Exception\InvalidArgumentException('Invalide query instance');
    	}
        // create single file from all input
        if (empty($input_hash)) {
            $input_hash = $namespace . sha1(serialize($query->getParameters()) . $query->getSQL());
        }
        $query->useResultCache(true, $time, (string) $input_hash); 
        $query->useQueryCache(true); 
        $query->setCacheMode($MODE);
        $query->setCacheable($setCacheable);
        
    	return $query;
    } 

    /**
     * Loads all translations with all translatable
     * fields from the given entity
     * 
     * @link https://github.com/l3pp4rd/DoctrineExtensions/blob/master/doc/translatable.md#entity-domain-object
     *
     * @param object $entity Must implement Translatable
     * @return \Doctrine\ORM\Query
     * @param string $locale
     * @param bool    $INNER_JOIN         
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function setTranslatableHints(Query $query, $locale, $INNER_JOIN = false, $FALLBACK = true, $lazy_loading = true)
    {
        $query->setHint(\Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER, 'Gedmo\Translatable\Query\TreeWalker\TranslationWalker'); // if you use memcache or apc. You should set locale and other options like fallbacks to query through hints. Otherwise the query will be cached with a first used locale
        if ($INNER_JOIN) {
        	$query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_INNER_JOIN, $INNER_JOIN); // will use INNER joins for translations instead of LEFT joins, so that in case if you do not want untranslated records in your result set for instance.
        }
        if (!$lazy_loading) {
        	// to avoid lazy-loading.
        	$query->setHint(\Doctrine\ORM\Query::HINT_FORCE_PARTIAL_LOAD, true);
        }
        $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale); // take locale from session or request etc.
        $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_FALLBACK, $FALLBACK); // fallback to default values in case if record is not translated
        
        return $query;
    }    
        
    /**
     * Find all translations by an entity.
     *
     * @param string $locale
     * @param string $result = {'array', 'object'}    array by default
     * @param bool    $INNER_JOIN    
     * @param int     $MaxResults
     * @return array\object
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    public function findAllByEntity($locale, $result = "array", $INNER_JOIN = false, $MaxResults = null, $FALLBACK = true, $lazy_loading = true)
    {
        $qb = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->where('a.archived = 0');
      
        $query = $this->checkRoles($qb)->getQuery();
      
        if (!is_null($MaxResults))
            $query->setMaxResults($MaxResults);
        
        return $this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN, $FALLBACK, $lazy_loading);        
    }

    /**
     * Find a translation of an entity by its id
     *
     * @param string $locale
     * @param int    $id
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN    
     * @return object
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    public function findOneByEntity($locale, $id, $result = "array", $INNER_JOIN = false, $FALLBACK = true, $lazy_loading = true)
    {
        $qb = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->where('a.id = :id');
      
        $query = $this->checkRoles($qb)->getQuery();
      
        $query->setParameter('id', $id);
        $query->setMaxResults(1);
        
        return current($this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN, $FALLBACK, $lazy_loading));
    }

    /**
     * Find a translation of an entity by its id and return the query
     *
     * @param string $locale
     * @param int    $id
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findOneQueryByEntity($id)
    {
        $query = $this->createQueryBuilder('a')
        ->select('a')
        ->where('a.id = :ID')
           ->setParameters(array(
                'ID'    => $id,
        ));
        
        return $query;
    }    

    /**
     * Loads all translations with all translatable
     * fields from the given entity
     *
     * @param object $entity Must implement Translatable
     * @return array list of translations in locale groups
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findTranslations($entity)
    {
        $result = array();
        $wrapped = new EntityWrapper($entity, $this->_em);
        if ($wrapped->hasValidIdentifier()) {
            $entityId = $wrapped->getIdentifier();
            $entityClass = $wrapped->getMetadata()->name;
    
            $translationMeta = $this->getClassMetadata(); // table inheritance support
            $qb = $this->_em->createQueryBuilder();
            $qb->select('trans.content, trans.field, trans.locale')
            ->from($translationMeta->associationMappings['translations']['targetEntity'], 'trans')
            ->where('trans.object = :entityId')
            ->orderBy('trans.locale');
            $q = $qb->getQuery();
            $data = $q->execute(
                    compact('entityId', 'entityId'),
                    Query::HYDRATE_ARRAY
            );
    
            if ($data && is_array($data) && count($data)) {
                foreach ($data as $row) {
                    $result[$row['locale']][$row['field']][] = $row['content'];
                }
            }
        }
        return $result;
    }
    
    /**
     * Loads all translations with all translatable
     * fields by a given entity primary key
     *
     * @param mixed $id - primary key value of an entity
     * @return array
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findTranslationsByObjectId($id)
    {
        $result = array();
        if ($id) {
            $translationMeta = $this->getClassMetadata(); // table inheritance support
            // $this->_entityTranslationName
            $qb = $this->_em->createQueryBuilder();
            $qb->select('trans.content, trans.field, trans.locale')
            ->from($translationMeta->associationMappings['translations']['targetEntity'], 'trans')
            ->where('trans.object = :entityId')
            ->orderBy('trans.locale');
            $q = $qb->getQuery();
            $data = $q->execute(
                    array('entityId' => $id),
                    Query::HYDRATE_ARRAY
            );
    
            if ($data && is_array($data) && count($data)) {
                foreach ($data as $row) {
                    $result[$row['locale']][$row['field']] = $row['content'];
                }
            }
        }
        return $result;
    }    
        
    /**
     * Makes additional translation of $entity $field into $locale using $value
     *
     * @param object $entity
     * @param string $field
     * @param string $locale
     * @param mixed $value
     * @return TranslationRepository
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function translate($entity, $field, $locale, $value)
    {
        $meta = $this->_em->getClassMetadata(get_class($entity));
        $listener = new \Gedmo\Translatable\TranslatableListener; //$this->getTranslatableListener();
        $config = $listener->getConfiguration($this->_em, $meta->name);
        if (!isset($config['fields']) || !in_array($field, $config['fields'])) {
            throw new \Gedmo\Exception\InvalidArgumentException("Entity: {$meta->name} does not translate field - {$field}");
        }
        if (in_array($locale, array($listener->getDefaultLocale(), $listener->getTranslatableLocale($entity, $meta)))) {
            $meta->getReflectionProperty($field)->setValue($entity, $value);
            $this->_em->persist($entity);
        } else {
            $ea = new TranslatableAdapterORM();
            $foreignKey = $meta->getReflectionProperty($meta->getSingleIdentifierFieldName())->getValue($entity);
            $objectClass = $meta->name;
            $class = $listener->getTranslationClass($ea, $meta->name);
            $transMeta = $this->_em->getClassMetadata($class);
            $trans = $this->findOneBy(compact('locale', 'field', 'object'));
            if (!$trans) {
                $trans = new $class();
                $transMeta->getReflectionProperty('object')->setValue($trans, $entity->getId());
                $transMeta->getReflectionProperty('field')->setValue($trans, $field);
                $transMeta->getReflectionProperty('locale')->setValue($trans, $locale);
            }
            $type = Type::getType($meta->getTypeOfField($field));
            $transformed = $type->convertToDatabaseValue($value, $this->_em->getConnection()->getDatabasePlatform());
            $transMeta->getReflectionProperty('content')->setValue($trans, $transformed);
            if ($this->_em->getUnitOfWork()->isInIdentityMap($entity)) {
                $this->_em->persist($trans);
            } else {
                $oid = spl_object_hash($entity);
                $listener->addPendingTranslationInsert($oid, $trans);
            }
        }
        return $this;

//         $meta         = $this->_em->getClassMetadata(get_class($entity));
//         $listener     = $this->getTranslatableListener();
//         $config     = $listener->getConfiguration($this->_em, $meta->name);
        
//         if (!isset($config['fields']) || !in_array($field, $config['fields'])) {
//             throw new \Gedmo\Exception\InvalidArgumentException("Entity: {$meta->name} does not translate field - {$field}");
//         }

//         $ea         = new TranslatableAdapterORM();
//         $class         = $listener->getTranslationClass($ea, $meta->name);
        
//         $trans         = $this->findOneBy(compact('locale', 'field', 'object_id'));
//         if (!$trans) {
//             $entity->setTranslatableLocale('fr');
//             $entity->addTranslation(new $class($locale, $field, $value));
//         }
    
//         $this->_em->persist($entity);
//         $this->_em->flush();
    }

    /**
     * Find the entity $class by the translated field.
     * Result is the first occurence of translated field.
     * Query can be slow, since there are no indexes on such
     * columns
     *
     * @param string $field
     * @param string $value
     * @param string $class
     * @return object - instance of $class or null if not found
     * @access    public
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function findObjectByTranslatedField($field, $value, $class)
    {
//         $entity = null;
//         $meta = $this->_em->getClassMetadata($class);
//         $translationMeta = $this->getClassMetadata(); // table inheritance support
//         if ($meta->hasField($field)) {
//             $dql = "SELECT trans.foreignKey FROM {$translationMeta->rootEntityName} trans";
//             $dql .= ' WHERE trans.objectClass = :class';
//             $dql .= ' AND trans.field = :field';
//             $dql .= ' AND trans.content = :value';
//             $q = $this->_em->createQuery($dql);
//             $q->setParameters(compact('class', 'field', 'value'));
//             $q->setMaxResults(1);
//             $result = $q->getArrayResult();
//             $id = count($result) ? $result[0]['foreignKey'] : null;

//             if ($id) {
//                 $entity = $this->_em->find($class, $id);
//             }
//         }
//         return $entity;
    }
    
    /**
     * Gets all field values of an entity.
     *
     * @param    $field        value of the field table
     * @return array
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-03-15
     */
    public function getArrayAllByField($field)
    {
        $query = $this->createQueryBuilder('a')
        ->select("a.{$field}")
        ->where('a.enabled = :enabled')
        ->andWhere('a.archived = :archived')
        ->setParameters(array(
                'enabled'    => 1,
                'archived'    => 0,
        ));
    
        $result = array();
        $data    = $query->getQuery()->getArrayResult();
        if ($data && is_array($data) && count($data)) {
            foreach ($data as $row) {
                if (isset($row[$field]) && !empty($row[$field]))
                    $result[ $row[$field] ] = $row[$field];
            }
        }
        return $result;
    }
    
    /**
     * Gets all field values of an translation entity.
     *
     * @param    $id        value of the id
     * @return object
     * @access public
     *
     * @author Riad HELLAL <hellal.riad@gmail.com>
     * @since 2013-05-30
     */    
    public function getTranslationsByObjectId($id)
    {
        $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.object = :objectId ");
        $query->setParameter('objectId', $id);
        $entities = $query->getResult();
            
        if (!is_null($entities)){
            return $entities;
        }else
            return null;
   }    
    
    /**
     * Gets all entities by one category.
     *
     * @return array\entity
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-03-15
     */
    public function getAllByCategory($category = '', $MaxResults = null, $ORDER_PublishDate = '', $ORDER_Position = '', $enabled = true, $is_checkRoles = true, $with_archive = false)
    {
        $query = $this->createQueryBuilder('a')->select('a');        
        
        if (!empty($ORDER_PublishDate) && !empty($ORDER_Position)){
            $query
                ->orderBy('a.published_at', $ORDER_PublishDate)
                ->addOrderBy('a.position', $ORDER_Position);
        }elseif (!empty($ORDER_PublishDate) && empty($ORDER_Position)){
            $query
                ->orderBy('a.published_at', $ORDER_PublishDate);
        }elseif (empty($ORDER_PublishDate) && !empty($ORDER_Position)){
            $query
                ->orderBy('a.position', $ORDER_Position);
        }   
        if (!$with_archive){
            $query->where('a.archived = 0');   
        }  
    
        if ($enabled && !empty($category)){
            $query
            ->andWhere('a.enabled = :enabled')
            ->andWhere('a.category = :cat')
            ->setParameters(array(
                    'cat'        => $category,
                    'enabled'    => 1,
            ));
        }elseif ($enabled && empty($category)){
            $query
            ->andWhere('a.enabled = :enabled')
            ->setParameters(array(
                    'enabled'    => 1,
            ));
        }elseif (!$enabled && !empty($category)){
            $query
            ->andWhere('a.category = :cat')
            ->setParameters(array(
                    'cat'        => $category,
            ));       
        }
    
        if (!is_null($MaxResults))
            $query->setMaxResults($MaxResults);
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
        
        return $query;
    }
    
    /**
     * Gets all entities by multiple fields.
     *
     * @return array\entity
     * @access public
     *
     * @author Riad HELLAL <hellal.riad@gmail.com>
     * @since 2012-03-15
     */
    public function getAllByFields($fields = array(), $MaxResults = null, $ORDER_PublishDate = '', $ORDER_Position = '', $is_checkRoles = true)
    {
        $query = $this->createQueryBuilder('a')->select('a');
         
        if (!empty($ORDER_PublishDate) && !empty($ORDER_Position)) {
            $query
            ->orderBy('a.published_at', $ORDER_PublishDate)
            ->addOrderBy('a.position', $ORDER_Position);
        } elseif (!empty($ORDER_PublishDate) && empty($ORDER_Position)) {
            $query
            ->orderBy('a.published_at', $ORDER_PublishDate);
        } elseif (empty($ORDER_PublishDate) && !empty($ORDER_Position)) {
            $query
            ->orderBy('a.position', $ORDER_Position);
        }
        foreach ($fields as $key => $value) {
        	if (is_int($value)) {
        		$query->andWhere("a.{$key} = $value");
        	} else {
        		$query->andWhere("a.{$key} LIKE '{$value}'");
        	}
        }        
        if (!is_null($MaxResults)) {
            $query->setMaxResults($MaxResults);
        }
        if ($is_checkRoles) {
            $query = $this->checkRoles($query);
        }
    
        return $query;
    }

    /**
     * Gets all order by param.
     *
     * @return int
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-10-05
     */
    public function getAllOrderByField($field = 'createat', $ORDER = "DESC", $enabled = null, $is_checkRoles = true, $with_archive = false)
    {
        $query = $this->createQueryBuilder('a')
        ->select("a");
        
        if (!$with_archive){
        	$query->where('a.archived = 0');
        }
        
        if ( !is_null($enabled) ) {
            $query
            ->andWhere('a.enabled = :enabled')
            ->setParameters(array(
                    'enabled'    => $enabled,
            ));
           }
        $query->orderBy("a.{$field}", $ORDER);
        
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
                
        return $query;
    }
        
    /**
     * Gets all between first and last position.
     *
     * @return int
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-10-04
     */
    public function getAllBetweenPosition($FirstPosition = null, $LastPosition = null, $enabled = null, $is_checkRoles = true, $with_archive = false)
    {
        $query = $this->createQueryBuilder('a')
        ->select("a");
        
        if (!$with_archive){
        	$query->where('a.archived = 0');
        }
        
        if (!is_null($FirstPosition) && !is_null($LastPosition))
            $query
            ->andWhere("a.position BETWEEN '{$FirstPosition}' AND '{$LastPosition}'");
        elseif (!is_null($FirstPosition) && is_null($LastPosition))
            $query
            ->andWhere("a.position >= {$FirstPosition} ");
        elseif (is_null($FirstPosition) && !is_null($LastPosition))
            $query
            ->andWhere("a.position <= {$LastPosition} ");
        
        if ( !is_null($enabled) ) {
            $query
            ->andWhere('a.enabled = :enabled')
            ->setParameters(array(
                    'enabled'    => $enabled,
            ));
        }        

        $query->orderBy("a.position", 'ASC');
        
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
            
        return $query;
    }
    
    /**
     * Gets max/min value of a column.
     *
     * @return int
     * @access public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     * @since 2012-10-04
     */
    public function getMaxOrMinValueOfColumn($field, $type = 'MAX', $enabled = null, $is_checkRoles = true, $with_archive = false)
    {
        $query = $this->createQueryBuilder('a')->select("a.{$field}");
        
        if (!$with_archive){
        	$query->where('a.archived = 0');
        }
    
        if ($type == "MAX")
            $query->orderBy("a.{$field}", 'DESC');
        elseif ($type == "MIN")
            $query->orderBy("a.{$field}", 'ASC');
    
        if ( !is_null($enabled) ) {
            $query
            ->andWhere('a.enabled = :enabled')
            ->setParameters(array(
                    'enabled'    => $enabled,
            ));
        }    
    
        $query->setMaxResults(1);
        
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
            
        return $query;
    }  

    /**
     * Find all entities of the entity by category
     *
     * @param string $locale
     * @param string $category
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getAllEnabled($locale, $result = "object", $INNER_JOIN = false, $MaxResults = null, $is_checkRoles = true, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->where('a.archived = 0')
        ->andWhere('a.enabled = 1')
        ->setMaxResults($MaxResults);
    
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
        
        return $this->findTranslationsByQuery($locale, $query->getQuery(), $result, $INNER_JOIN, $FALLBACK, $lazy_loading);
    }    

    /**
     * Find all entities of the entity by category
     *
     * @param string $locale
     * @param string $category
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getAllEnableByCat($locale, $category, $result = "object", $INNER_JOIN = false, $is_checkRoles = true, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->where('a.archived = 0')
        ->andWhere("a.enabled = 1");
        
        if (!empty($category)){
            $query
            ->andWhere('a.category = :cat')
            ->setParameters(array(
                    'cat' => $category,
            ));
        }
    
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
        
        return $this->findTranslationsByQuery($locale, $query->getQuery(), $result, $INNER_JOIN, $FALLBACK, $lazy_loading);
    }    

    /**
     * Find all entities of the entity by category
     *
     * @param string $locale
     * @param string $category
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getAllEnableByCatAndByPosition($locale, $category, $result = "object", $INNER_JOIN = false, $is_checkRoles = true, $FALLBACK = true, $lazy_loading = true)
    {
        $query = $this->_em->createQueryBuilder()
        ->select('a')
        ->from($this->_entityName, 'a')
        ->orderBy('a.position', 'ASC')
        ->where('a.archived = 0')
        ->andWhere("a.enabled = 1");
        
        if (!empty($category)){
            $query
            ->andWhere('a.category = :cat')
            ->setParameters(array(
                    'cat' => $category,
            ));
        }
        
        if ($is_checkRoles)
            $query = $this->checkRoles($query);
    
        return $this->findTranslationsByQuery($locale, $query->getQuery(), $result, $INNER_JOIN, $FALLBACK, $lazy_loading);
    } 

    /**
     * Find a translation field of an entity by its id
     *
     * @param string $locale
     * @param int    $id
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getContentByField($locale, array $fields, $INNER_JOIN = false)
    {
         $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.content = :content ");
         $query->setParameter('locale', $locale);
         $query->setParameter('field', array_keys($fields['content_search']));
         $query->setParameter('content', array_values($fields['content_search']));
         $query->setMaxResults(1);
        $entities = $query->getResult();
                   
        if (!is_null($entities)){
             $entity = current($entities);             
             if (is_object($entity)){
                   $id        = $entity->getObject()->getId();
                   
                   $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.object = :objectId");
                   $query->setParameter('locale', $locale);
                   $query->setParameter('objectId', $id);
                   $query->setParameter('field', $fields['field_result']);
                   $query->setMaxResults(1);                
                   $entities = $query->getResult();
                   
                   if (!is_null($entities) && (count($entities)>=1) ){
                       return current($entities);
                  }else
                     return null;
             }else 
                 return null;
        }else
            return null;
                 
        //         $dql = <<<___SQL
        //   SELECT a
        //   FROM {$this->_entityName} a
        //   WHERE a.slug = '{$slug}'
        // ___SQL;
    
        //         $query  = $this->_em->createQuery($dql);
        //         $result = $this->findTranslationsByQuery($locale, $query, $result, $INNER_JOIN);
    
    
        //         print_r(count($result));exit;
            
        //         return current($result);
    }    
    
    /**
     * Find a translation of an entity by its id
     *
     * @param string $locale
     * @param int    $id
     * @param string $result = {'array', 'object'}
     * @param bool    $INNER_JOIN
     * @return object
     * @access    public
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function getEntityByField($locale, array $fields, $result = "object", $INNER_JOIN = false)
    {
        $query    = $this->_em->createQuery("SELECT p FROM {$this->_entityTranslationName} p  WHERE p.locale = :locale and p.field = :field and p.content = :content ");
        $query->setParameter('locale', $locale);
        $query->setParameter('field', array_keys($fields['content_search']));
        $query->setParameter('content', array_values($fields['content_search']));
        $query->setMaxResults(1);
        $entities = $query->getResult();
            
        if (!is_null($entities)){
            $entity = current($entities);
            
            if (is_object($entity)){
                $id        = $entity->getObject()->getId();
                return $this->findOneByEntity($locale, $id, $result, $INNER_JOIN);
            }else
                return null;
        }else
            return null;
    }   

}