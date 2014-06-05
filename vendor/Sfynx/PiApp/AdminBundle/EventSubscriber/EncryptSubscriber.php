<?php
/**
 * This file is part of the <Admin> project.
 *
 * @category   Admin_EventSubscriber
 * @package    Encryptor
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2011-01-27
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Gedmo\Mapping\MappedEventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Common\Annotations\Reader;
use \Doctrine\ORM\EntityManager;
use \ReflectionClass;
use PiApp\AdminBundle\Builder\PiEncryptorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\StringType;

/**
 * Doctrine event subscriber which encrypt/decrypt entities
 */
class EncryptSubscriber extends MappedEventSubscriber
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;
            
    /**
     * Locale which is set on this listener.
     * If Entity being translated has locale defined it
     * will override this one
     *
     * @var string
     */
    public $locale = 'en_GB';
    
    /**
     * is_defaultlocale_setting
     * @var BooleanType
     */    
    public $is_defaultlocale_setting;
        
    /**
     * Encryptor interface namespace
     * @var String
     */

    public $interfaceclass = 'PiApp\AdminBundle\Builder\PiEncryptorInterface';

    /**
     * Options
     * @var Array
     */
    private $options;
    
    /**
     * Encryptor
     * @var EncryptorInterface
     */
    private $encryptor;    

    /**
     * Annotation reader
     * @var Doctrine\Common\Annotations\Reader
     */
    private $annReader;
    
    /**
     * Registr to avoid multi decode operations for one entity
     * @var array
     */
    private $decodedRegistry = array();    

    /**
     * Initialization of subscriber
     * @param string $encryptorClass  The encryptor class.  This can be empty if 
     * a service is being provided.
     * @param string $secretKey The secret key. 
     * @param EncryptorInterface|NULL $service (Optional)  An EncryptorInterface.
     * This allows for the use of dependency injection for the encrypters.
     */
    public function __construct(Reader $annReader, $options, ContainerInterface $container) {
        $this->annReader = $annReader;
        $this->options   = $options;
        $this->container = $container;
    }

    /**
     * Listen a preUpdate lifecycle event. Checking and encrypt entities fields
     * which have @Encrypted annotation. Using changesets to avoid preUpdate event
     * restrictions
     * @param LifecycleEventArgs $args 
     * @return void
     */
    public function preUpdate(PreUpdateEventArgs $args) {
        $entity = $args->getEntity();
        $em     = $args->getEntityManager();
        $reflectionClass = new ReflectionClass($args->getEntity());
        $properties      = $reflectionClass->getProperties();
        foreach ($properties as $refProperty) {
            foreach ($this->options as $key => $encrypter) {
                if (
                    isset($encrypter['encryptor_annotation_name'])
                    &&
                    isset($encrypter['encryptor_class'])
                    &&
                    isset($encrypter['encryptor_options'])
                ) {
                    $this->encryptor = $this->getEncryptorService($key);
                    if ($this->annReader->getPropertyAnnotation($refProperty, $encrypter['encryptor_annotation_name'])) {
                    	// we have annotation and if it decrypt operation, we must avoid duble decryption
                    	$propName = $refProperty->getName();
                    	// we encrypt the field
                		if ($refProperty->isPublic()) {
                			$entity->$propName = $this->encryptor->encrypt($refProperty->getValue());
                		} else {
                    		$methodName = self::capitalize($propName);
                            if ($reflectionClass->hasMethod($getter = 'get' . $methodName) && $reflectionClass->hasMethod($setter = 'set' . $methodName)) {
                                // we get the locale value
                                $locale = false;                                
                                $om     = $args->getObjectManager();
                                $object = $args->getObject();
                                $meta   = $om->getClassMetadata(get_class($object));
                                $config = $this->getConfiguration($om, $meta->name);
                                if (isset($config['fields'])) {
                                	$locale = $this->getTranslatableLocale($object, $meta);
                                }
                                // we set the encrypt value
                                $currentPropValue        = $entity->$getter();
                                if (!empty($currentPropValue)) {
                                	$currentPropValue        = $this->encryptor->encrypt($currentPropValue);
                                }
                                // we set locale value
                                if (
                                    $locale
                                ) {
                                	if ($locale == $this->locale) {
                                		$entity->$setter($currentPropValue);
                                	}
                                	$entity->translate($locale)->$setter($currentPropValue);
                                }                                                               
                            } else {
                                throw new \RuntimeException(sprintf("Property %s isn't public and doesn't has getter/setter"));
                            }
                		}  
                    }
                } else {
                	throw new \RuntimeException(sprintf("encrypter is not correctly configured"));
                }
            }
        }
    }

    /**
     * Listen a prePersist lifecycle event. Checking and encrypt entities
     * which have @Encrypted annotation
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args) {
    	$this->processFields($args, true);
    }
        
    /**
     * Listen a postLoad lifecycle event. Checking and decrypt entities
     * which have @Encrypted annotations (This event is called after an entity is constructed by the EntityManager)
     * @param LifecycleEventArgs $args 
     */
    public function postLoad(LifecycleEventArgs $args) {
        $this->processFields($args, false);
    }

    /**
     * Realization of EventSubscriber interface method.
     * @return Array Return all events which this subscriber is listening
     */
    public function getSubscribedEvents() {
        return array(
            Events::prePersist,
            Events::preUpdate,
            Events::postLoad,
        );
    }

    /**
     * Capitalize string
     * @param string $word
     * @return string
     */
    public static function capitalize($word) {
        if (is_array($word)) {
            $word = $word[0];
        }

        return str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $word)));
    }

    /**
     * Process (encrypt/decrypt) entities fields
     * @param LifecycleEventArgs $args 
     * @param Boolean $isEncryptOperation If true - encrypt, false - decrypt entity 
     * @return void
     */
    private function processFields(LifecycleEventArgs $args, $isEncryptOperation = true) {
        $entity = $args->getEntity();
        $em     = $args->getEntityManager();
        $className = get_class($entity);
        $metadata = $em->getClassMetadata($className);        
        $encryptorMethod = $isEncryptOperation ? 'encrypt' : 'decrypt';
        $reflectionClass = new ReflectionClass($entity);
        $properties      = $reflectionClass->getProperties();
        foreach ($properties as $refProperty) {            
            foreach ($this->options as $key => $encrypter) {
            	if (
            	    isset($encrypter['encryptor_annotation_name'])
            	    &&
            	    isset($encrypter['encryptor_class'])
            	    &&
            	    isset($encrypter['encryptor_options'])
            	) {
            		$this->encryptor = $this->getEncryptorService($key);
                	if ($this->annReader->getPropertyAnnotation($refProperty, $encrypter['encryptor_annotation_name'])) {
                        // we have annotation and if it decrypt operation, we must avoid duble decryption
                        $propName = $refProperty->getName();
                        if ($refProperty->isPublic()) {
                            $entity->$propName = $this->encryptor->$encryptorMethod($refProperty->getValue());
                        } else {
                            $methodName = self::capitalize($propName);
                            if ($reflectionClass->hasMethod($getter = 'get' . $methodName) && $reflectionClass->hasMethod($setter = 'set' . $methodName)) {
                                if ($isEncryptOperation) {
                                    // we get the locale value
                                    if (isset($_GET['_locale'])) {
                                    	$locale = $_GET['_locale'];
                                    } else {
                                    	$locale = $this->locale;
                                    }
                                    // we set the encrypt value
                                    $currentPropValue        = $entity->$getter();
                                    if (!empty($currentPropValue)) {
                                    	$currentPropValue        = $this->encryptor->$encryptorMethod($currentPropValue);
                                    }
                                    // we set locale value
                                    $entity->$setter($currentPropValue);
                                    // we set translatable locale value
                                    $entity->translate($locale)->$setter($currentPropValue);
                                } else {
                                    // we get the locale value
                                    $locale = $entity->getTranslatableLocale(); 
                                    if (isset($_GET['_encrypt_subscriber_not_force_locale']) && ($_GET['_encrypt_subscriber_not_force_locale'] == true)) {
                                    } else {
                                        if (!empty($locale) && !is_null($locale)) {
                                        } elseif (isset($_GET['_locale'])) {
                                        	$locale = $_GET['_locale'];
                                        } else {
                                        	$locale = $this->locale;
                                        }
                                    }
                                    if (!empty($locale) && !is_null($locale)) {
                                        if (!$this->hasInDecodedRegistry($className, $entity->getId(), $locale, $methodName)) {
                                            // we set encrypt value
                                            $currentPropValue        = $entity->$getter();
                                            $currentPropValue_locale = $entity->translate($locale)->$getter();                                        
                                            if (!empty($currentPropValue)) {
                                                $currentPropValue        = $this->encryptor->$encryptorMethod($currentPropValue);
                                            }
                                            if (!empty($currentPropValue_locale)) {
                                                $currentPropValue_locale = $this->encryptor->$encryptorMethod($currentPropValue_locale);
                                            }
                                            $entity->$setter($currentPropValue);
                                            $entity->translate($locale)->$setter($currentPropValue_locale);
                                            // we add to registry        
                                            $this->addToDecodedRegistry($className, $entity->getId(), $locale, $methodName, $currentPropValue_locale);
                                            //print_r($this->decodedRegistry);
                                        }
                                    }
                                }
                            } else {
                                throw new \RuntimeException(sprintf("Property %s isn't public and doesn't has getter/setter"));
                            }
                        }
                    }
            	} else {
            		throw new \RuntimeException(sprintf("encrypter %s is not correctly configured", $key));
            	}
            } 
        }
    }

    /**
     * Encryptor factory. Checks and create needed encryptor
     * @param string $classFullName Encryptor namespace and name
     * @param string $secretKey Secret key for encryptor
     * @return EncryptorInterface
     * @throws \RuntimeException
     */
    private function encryptorFactory($classFullName, $encryptor_options) {
    	$refClass = new \ReflectionClass($classFullName);
    	if ($refClass->implementsInterface($this->interfaceclass)) {
    		return new $classFullName($encryptor_options);
    	} else {
    		throw new \RuntimeException('Encryptor must implements interface EncryptorInterface');
    	}
    }
    
    private function getEncryptorService($encrypter_name) {
    	$encryptorClass    = isset($this->options[$encrypter_name]['encryptor_class']) ? (string) $this->options[$encrypter_name]['encryptor_class'] : '';
    	$encryptor_options = isset($this->options[$encrypter_name]['encryptor_options']) ? (array) $this->options[$encrypter_name]['encryptor_options'] : null;
    	return $this->encryptorFactory($encryptorClass, $encryptor_options);
    }   
    
    /**
     * {@inheritDoc}
     */
    protected function getNamespace()
    {
    	return "Gedmo\Translatable";
    }
    
    /**
     * Validates the given locale
     *
     * @param string $locale - locale to validate
     * @throws \Gedmo\Exception\InvalidArgumentException if locale is not valid
     * @return void
     */
    protected function validateLocale($locale)
    {
    	if (!is_string($locale) || !strlen($locale)) {
    		throw new \Gedmo\Exception\InvalidArgumentException('Locale or language cannot be empty and must be set through Listener or Entity');
    	}
    }    
        
    /**
     * Gets the locale to use for translation. Loads object
     * defined locale first..
     *
     * @param object $object
     * @param object $meta
     * @throws \Gedmo\Exception\RuntimeException - if language or locale property is not
     *         found in entity
     * @return string
     */
    public function getTranslatableLocale($object, $meta)
    {
    	$locale = $this->locale;
    	if (isset(self::$configurations[$this->name][$meta->name]['locale'])) {
    		/** @var \ReflectionClass $class */
    		$class = $meta->getReflectionClass();
    		$reflectionProperty = $class->getProperty(self::$configurations[$this->name][$meta->name]['locale']);
    		if (!$reflectionProperty) {
    			$column = self::$configurations[$this->name][$meta->name]['locale'];
    			throw new \Gedmo\Exception\RuntimeException("There is no locale or language property ({$column}) found on object: {$meta->name}");
    		}
    		$reflectionProperty->setAccessible(true);
    		$value = $reflectionProperty->getValue($object);
    		try {
    			$this->validateLocale($value);
    			$locale = $value;
    		} catch(\Gedmo\Exception\InvalidArgumentException $e) {}
    	}
    	    
    	return $locale;
    }    
    
    /**
     * Check if we have entity in decoded registry
     * @param LifecycleEventArgs $args 
     * @return boolean
     */
    private function hasInDecodedRegistry($className, $id, $locale, $methodeName) {
    	return isset($this->decodedRegistry[$className][$id][$locale][$methodeName]);
    }
    
    /**
     * Adds entity to decoded registry
     * @param LifecycleEventArgs $args 
     * @return void
     */
    private function addToDecodedRegistry($className, $id, $locale, $methodeName, $currentPropValue) {
    	$this->decodedRegistry[$className][$id][$locale][$methodeName] = $currentPropValue;
    }    

}