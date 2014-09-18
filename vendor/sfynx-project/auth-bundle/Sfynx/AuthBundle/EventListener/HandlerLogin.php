<?php
/**
 * This file is part of the <Auth> project.
 *
 * @category   Handler
 * @package    Handler
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2011-01-25
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\AuthBundle\EventListener;

use BeSimple\I18nRoutingBundle\Routing\Router as Router;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpKernel\KernelEvents;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use Sfynx\AuthBundle\Event\RedirectionEvent;
use Sfynx\AuthBundle\Event\ResponseEvent;
use Sfynx\AuthBundle\SfynxAuthEvents;


/**
 * Custom login handler.
 * This allow you to execute code right after the user succefully logs in.
 * 
 * @category   Handler
 * @package    EventListerner
 *
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class HandlerLogin
{
    /**
     * @var \Sfynx\ToolBundle\Route\RouteTranslatorFactory $router
     */
    protected $router;
        
    /** 
     * @var \Symfony\Component\Security\Core\SecurityContext $security
     */
    protected $security;
    
    /**
     * @var \Symfony\Component\EventDispatcher\Event\EventDispatcher $dispatcher
     */
    protected $dispatcher;    

    /** 
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Symfony\Component\Security\Http\Event\InteractiveLoginEvent
     */
    protected $event;
    
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;    
    
    /**
     * @var $redirect        route name of the login redirection
     */    
    protected $redirect = "";
    
    /**
     * @var $template        layout file name
     */    
    protected $template = "";
    
    /**
     * @var $layout
     */
    protected $layout;
    
    /**
     * @var $locale
     */
    protected $locale;  
    
    /**
     * Constructs a new instance of SecurityListener.
     * 
     * @param SecurityContext $security The security context
     * @param EventDispatcher $dispatcher The event dispatcher
     * @param Doctrine        $doctrine
     * @param Container        $container
     */
    public function __construct(SecurityContext $security, EventDispatcher $dispatcher, Doctrine $doctrine, ContainerInterface $container)
    {
        $this->security     = $security;
        $this->dispatcher   = $dispatcher;
        $this->em           = $doctrine->getManager();
        $this->container    = $container;
        $this->router       = $this->container->get('sfynx.tool.route.factory');
    }

    /**
     * Invoked after a successful login.
     * 
     * @param InteractiveLoginEvent $event The event
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent  $event)
    {
        // Sets event.
        $this->event    = $event;
        // Sets the user local value.
        $this->setLocaleUser();
        // Sets the state of the redirection.
        $this->setParams();
        // Associate to the dispatcher the onKernelResponse event.
        $this->dispatcher->addListener(KernelEvents::RESPONSE, array($this, 'onKernelResponse'));
        // Return the success connecion flash message.        
        $this->getFlashBag()->clear();
    }    
    
    /**
     * Invoked after the response has been created.
     * Invoked to allow the system to modify or replace the Response object after its creation.
     *
     * @param FilterResponseEvent $event The event
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        // we delete the username info in session if it exists
        if ($this->container->get('request')->getSession()->has('login-username')) {
        	$this->container->get('request')->getSession()->remove('login-username');
        }
        // we apply all events allowed to change the url redirection
        $event_redirection = new RedirectionEvent($this->router, $this->redirect);
        $this->container->get('event_dispatcher')->dispatch(SfynxAuthEvents::HANDLER_LOGIN_CHANGEREDIRECTION, $event_redirection);
        $redirection = $event_redirection->getRedirection();
        // we deal with the general case with a non ajax connection.
        if (!empty($redirection)) {
            $response = new RedirectResponse($redirection);
        } elseif (!empty($this->redirect)) {
   		    $response = new RedirectResponse($this->router->getRoute($this->redirect));
    	}  	
        // we deal with the case where the connection is limited to a set of roles (ajax or not ajax connection).
        if (isset($_POST['roles']) && !empty($_POST['roles'])) {
        	$all_authorization_roles = json_decode($_POST['roles'], true);
        	$best_roles_name = $this->container->get('sfynx.auth.role.factory')->getBestRoleUser();
        	// If the permisssion is not given.
        	if (is_array($all_authorization_roles) && !in_array($best_roles_name, $all_authorization_roles)) {
        		if ($this->getRequest()->isXmlHttpRequest()) {
        			$response = new Response(json_encode("no-authorization"));
        			$response->headers->set('Content-Type', 'application/json');
        		} else {
        			$referer_url = $this->container->get('sfynx.tool.route.factory')->getRefererRoute();
        			$response = new RedirectResponse($referer_url);
        			$this->redirect = 'home_page';
        		}
        	} else {
        		if ($this->getRequest()->isXmlHttpRequest()) {
        			$response = new Response(json_encode("ok"));
        			$response->headers->set('Content-Type', 'application/json');
        		}
        	}
        // we deal with the case where the connection is done in ajax without limited connection.
        } elseif ($this->getRequest()->isXmlHttpRequest()) {
        	$response = new Response(json_encode("ok"));
        	$response->headers->set('Content-Type', 'application/json');
        }
        // Record the layout variable in cookies.
        if ($this->date_expire && !empty($this->date_interval)) {
            if (is_numeric($this->date_interval)) {
                $dateExpire = time() + intVal($this->date_interval);
            } else {
                $dateExpire = new \DateTime("NOW");
                $dateExpire->add(new \DateInterval($this->date_interval));
            }
        } else {
            $dateExpire = 0;
        }
        // Record all cookies in relation with ws.
        if ($this->application_id && !empty($this->application_id) && $this->container->hasParameter('ws.auth')) {
        	$config_ws     = $this->container->getParameter('ws.auth');
        	$key           = $config_ws['handlers']['getpermisssion']['key'];
        	$userId        = $this->container->get('sfynx.tool.twig.extension.tool')->encryptFilter($this->getUser()->getId(), $key);
            $applicationId = $this->container->get('sfynx.tool.twig.extension.tool')->encryptFilter($this->application_id, $key);
        	$response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-ws-user-id', $userId, $dateExpire));
        	$response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-ws-application-id', $applicationId, $dateExpire));
        	$response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-ws-key', $key, $dateExpire));
        	// $response->headers->getCookies();        	
        }   
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-layout', $this->layout, $dateExpire));
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-screen', $this->screen, $dateExpire));
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-redirection', $this->redirect, $dateExpire));
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('sfynx-framework', 'Symfony 2.2', $dateExpire));
        $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('_locale', $this->locale, $dateExpire));
        // we apply all events allowed to change the redirection response
        $event_response = new ResponseEvent($response, $dateExpire);
        $this->container->get('event_dispatcher')->dispatch(SfynxAuthEvents::HANDLER_LOGIN_CHANGERESPONSE, $event_response);
        $response = $event_response->getResponse();
        //
        $event->setResponse($response);        
    }    
    
    /**
     * Invoked to modify the controller that should be executed.
     *
     * @param FilterControllerEvent $event The event
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    public function onKernelController(FilterControllerEvent $event)
    {
/*         $request = $event->getRequest();
        //$controller = $event->getController();
        
        //...
        
        // the controller can be changed to any PHP callable
        $event->setController($controller); */    
    }
        
    /**
     * Invoked to allow some other return value to be converted into a Response.
     *
     * @param FilterControllerEvent $event The event
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        /*         $val = $event->getControllerResult();
         $response = new Response();
        // some how customize the Response from the return value
    
        $event->setResponse($response); */
    }
    
    /**
     * Invoked to allow to create and set a Response object, create and set a new Exception object, or do nothing.
     *
     * @param FilterControllerEvent $event The event
     * 
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        /*         $exception = $event->getException();
         $response = new Response();
        // setup the Response object based on the caught exception
        $event->setResponse($response); */
    
        // you can alternatively set a new Exception
        // $exception = new \Exception('Some special exception');
        // $event->setException($exception);
    }
    
    /**
     * Sets the state of the redirection.
     *
     * @return void
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function setParams()
    {
        // we get browser info
        $browser = $this->getRequest()->attributes->get('sfynx-browser');
        // we get params
        $this->date_expire    = $this->container->getParameter('sfynx.core.cookies.date_expire');
        $this->date_interval  = $this->container->getParameter('sfynx.core.cookies.date_interval');
        $this->application_id = $this->container->getParameter('sfynx.core.cookies.application_id');
        $this->is_browser_authorized  = $this->container->getParameter("sfynx.auth.browser.switch_layout_mobile_authorized");
        $this->redirect       = $this->container->getParameter('sfynx.auth.login.redirect');
        $this->template       = $this->container->getParameter('sfynx.auth.login.template');
        // we get vars
        $this->layout         = $this->getRequest()->attributes->get('sfynx-layout');
        $this->screen         = $this->getRequest()->attributes->get('sfynx-screen');        
        // we get the best role of the user.
        $BEST_ROLE_NAME = $this->container->get('sfynx.auth.role.factory')->getBestRoleUser();
        if (!empty($BEST_ROLE_NAME)) {
            $role         = $this->em->getRepository("SfynxAuthBundle:Role")->findOneBy(array('name' => $BEST_ROLE_NAME));
            if ($role instanceof \Sfynx\AuthBundle\Entity\Role) {
                $RouteLogin = $role->getRouteLogin();
                if (!empty($RouteLogin) && !is_null($RouteLogin)) {
                    $this->redirect = $RouteLogin;
                }
                if ($role->getLayout() instanceof \Sfynx\AuthBundle\Entity\Layout) {
                    $FilePc = $role->getLayout()->getFilePc();
                    if (!empty($FilePc)  && !is_null($FilePc)) {
                        $this->template = $FilePc;
                    }
                }
            }
        }
        // Sets layout
        if (
            $this->is_browser_authorized
            && $this->getRequest()->attributes->has('sfynx-browser') 
            && $this->getRequest()->attributes->get('sfynx-browser')->isMobileDevice
        ) {
        	$this->layout    = $this->container->getParameter('sfynx.auth.theme.layout.admin.mobile') . $this->screen . '.html.twig';
        } else {
       		$this->layout    = $this->container->getParameter('sfynx.auth.theme.layout.admin.pc').$this->template;
        }
        // we modify sfynx-layout and sfynx-screen info in the request
        $this->getRequest()->attributes->set('sfynx-layout', $this->layout);
    }        

    /**
     * Sets the user local value.
     *
     * @return void
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function setLocaleUser()
    {
    	if (method_exists($this->getUser()->getLangCode(), 'getId')) {
    		$this->locale = $this->getUser()->getLangCode()->getId();
    	} else {
    		$this->locale = $this->container->get('request')->getPreferredLanguage();
    	}
    	$this->getRequest()->setLocale($this->locale);
    }    
    
    /**
     * Return the request object.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    protected function getRequest()
    {
        return $this->event->getRequest();
    }
    
    /**
     * Return the connected user entity object.
     *
     * @return \Sfynx\AuthBundle\Entity\user
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    protected function getUser()
    {
        return $this->event->getAuthenticationToken()->getUser();
    }
    
    /**
     * Gets the flash bag.
     *
     * @return \Symfony\Component\HttpFoundation\Session\Flash\FlashBag
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function getFlashBag()
    {
        return $this->getRequest()->getSession()->getFlashBag();
    }    
    
    /**
     * Sets the welcome flash message.
     *
     * @return void
     * @access protected
     *
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */    
    protected function setFlash()
    {
        $this->getFlashBag()->add('notice', "pi.session.flash.welcom");
    }    
}