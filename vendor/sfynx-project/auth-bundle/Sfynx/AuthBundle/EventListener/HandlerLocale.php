<?php
/**
 * This file is part of the <Auth> project.
 *
 * @category   Auth
 * @package    EventListener
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2013-04-18
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\AuthBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * Custom locale handler.
 *
 * @category   Auth
 * @package    EventListener
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class HandlerLocale
{
   private $defaultLocale;
   
   /**
    * @var \Symfony\Component\DependencyInjection\ContainerInterface
    */
   protected $container;   

   /**
    * Constructor.
    *
    * @param string $defaultLocale	Locale value
    */   
   public function __construct($defaultLocale = 'en', ContainerInterface $container)
   {
       $this->defaultLocale = $defaultLocale;   
       $this->container     = $container;  
   }

   /**
    * Invoked to modify the controller that should be executed.
    *
    * @param FilterControllerEvent $event The event
    *
    * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
    */   
   public function onKernelRequest(GetResponseEvent $event)
   {
       if (HttpKernel::MASTER_REQUEST != $event->getRequestType()) {
           // ne rien faire si ce n'est pas la requête principale
           return;
       }       
       $this->request = $event->getRequest($event);
       //if (!$this->request->hasPreviousSession()) {
       //    return;
       //}
   	   // print_r('priority 1');       
       // we set locale
       $locale = $this->request->cookies->has('_locale');
       $localevalue = $this->request->cookies->get('_locale');
       $is_switch_language_browser_authorized    = $this->container->getParameter('sfynx.auth.browser.switch_language_authorized');
       // Sets the user local value.
       if ($is_switch_language_browser_authorized && !$locale) {
           $lang_value  = $this->container->get('request')->getPreferredLanguage();
           $all_locales = $this->container->get('sfynx.auth.locale_manager')->getAllLocales();
           if (in_array($lang_value, $all_locales)) {
               $this->request->setLocale($lang_value);
               $_GET['_locale'] = $lang_value;

               return;
           }
       }
       if ($locale && !empty($localevalue)) {
           $this->request->attributes->set('_locale', $localevalue);
           $this->request->setLocale($localevalue);
           $_GET['_locale'] = $localevalue;
       } else {
           $this->request->setLocale($this->defaultLocale);
           $_GET['_locale'] = $this->defaultLocale;   
       }
   }

}