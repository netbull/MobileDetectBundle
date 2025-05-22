<?php

namespace SunCat\MobileDetectBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SunCat\MobileDetectBundle\EventListener\RequestResponseListener;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use SunCat\MobileDetectBundle\Helper\SymfonyCompatibilityLayer;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Request and Response Listener Test
 */
class RequestResponseListenerTest extends TestCase
{
    private mixed $mobileDetector;
    private mixed $deviceView;
    private mixed $requestStack;

    private MockObject $request;
    private MockObject $router;

    private array $config;
    private string $cookieKey = DeviceView::COOKIE_KEY_DEFAULT;
    private string $switchParam = DeviceView::SWITCH_PARAM_DEFAULT;

    public function setUp(): void
    {
        parent::setUp();

        $this->mobileDetector = $this->getMockBuilder('SunCat\MobileDetectBundle\DeviceDetector\MobileDetector')->disableOriginalConstructor()->getMock();
        $this->deviceView = $this->getMockBuilder('SunCat\MobileDetectBundle\Helper\DeviceView')->disableOriginalConstructor()->getMock();
        $this->router = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Routing\Router')
            ->disableOriginalConstructor()
            ->onlyMethods(array('getRouteCollection'))
            ->getMock();

        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $this->request->expects($this->any())->method('getScheme')->will($this->returnValue('http'));
        $this->request->expects($this->any())->method('getHost')->will($this->returnValue('testhost.com'));
        $this->request->expects($this->any())->method('getUriForPath')->will($this->returnValue('/'));
        $this->request->query = new InputBag();
        $this->request->cookies = new InputBag();

        $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->disableOriginalConstructor()->getMock();
        $this->requestStack->expects($this->any())
            ->method('getMainRequest')
            ->will($this->returnValue($this->request))
        ;

        $this->config = array(
            'mobile' => array('is_enabled' => false, 'host' => null, 'status_code' => Response::HTTP_FOUND, 'action' => 'redirect'),
            'tablet' => array('is_enabled' => false, 'host' => null, 'status_code' => Response::HTTP_FOUND, 'action' => 'redirect'),
            'detect_tablet_as_mobile' => false
        );
    }

    /**
     * @test
     */
    public function handleRequestHasSwitchParam()
    {
        $this->request->query = new InputBag(array('myparam' => 'myvalue', $this->switchParam => DeviceView::VIEW_MOBILE));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/'));
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setRedirectConfig([DeviceView::VIEW_MOBILE => ['status_code' => Response::HTTP_FOUND]]);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, array());
        $event = $this->createGetResponseEvent('some content');

        $listener->handleRequest($event);
        $this->assertFalse($listener->needsResponseModification());

        $response = $event->getResponse();
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }


    /**
     * @test
     */
    public function handleRequestHasSwitchParamAndQuery()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com');

        $this->request->query = new InputBag(array('myparam' => 'myvalue', $this->switchParam => DeviceView::VIEW_MOBILE));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->exactly(2))->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT, 2)
            )
        );

        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setRedirectConfig([DeviceView::VIEW_MOBILE => ['status_code' => Response::HTTP_FOUND]]);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);
        $event = $this->createGetResponseEvent('some content');

        $listener->handleRequest($event);
        $this->assertFalse($listener->needsResponseModification());

        $response = $event->getResponse();
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
            'https://mobilehost.com/?%s=%s&myparam=myvalue',
            $this->switchParam,
            DeviceView::VIEW_MOBILE
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestIsFullView()
    {
        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);
        $this->assertFalse($deviceView->hasSwitchParam());
        $this->assertNull($deviceView->getRequestedViewType());
        $getResponseEvent = $this->createGetResponseEvent('some content');

        $listener->handleRequest($getResponseEvent);
        $this->assertTrue($listener->needsResponseModification());
        $this->assertEquals(DeviceView::VIEW_FULL, $deviceView->getViewType());

        $requestEventResponse = $getResponseEvent->getResponse();
        $this->assertNull($requestEventResponse);

        $responseEventResponse = new Response('Full view', Response::HTTP_OK);
        $filterResponseEvent = $this->createResponseEvent($responseEventResponse);
        $listener->handleResponse($filterResponseEvent);
        $modifiedResponse = $filterResponseEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $modifiedResponse);
        $this->assertEquals(Response::HTTP_OK, $modifiedResponse->getStatusCode());
        $this->assertEquals('Full view', $modifiedResponse->getContent());

        $cookies = $modifiedResponse->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_FULL, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestIsNotMobileView()
    {
        $deviceView = new DeviceView();
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);
        $this->assertFalse($deviceView->hasSwitchParam());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_NOT_MOBILE, $deviceView->getViewType());
        $getResponseEvent = $this->createGetResponseEvent('some content');

        $listener->handleRequest($getResponseEvent);
        $this->assertFalse($listener->needsResponseModification());

        $requestEventResponse = $getResponseEvent->getResponse();
        $this->assertNull($requestEventResponse);

        $responseEventResponse = new Response('Not mobile view', Response::HTTP_OK);
        $filterResponseEvent = $this->createResponseEvent($responseEventResponse);
        $listener->handleResponse($filterResponseEvent);
        $modifiedResponse = $filterResponseEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $modifiedResponse);
        $this->assertEquals(Response::HTTP_OK, $modifiedResponse->getStatusCode());
        $this->assertEquals('Not mobile view', $modifiedResponse->getContent());

        $cookies = $modifiedResponse->headers->getCookies();
        $this->assertCount(0, $cookies);
    }

    /**
     * @test
     */
    public function handleRequestHasTabletRedirect()
    {
        $this->config['tablet'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $this->request->query = new InputBag(array('some'=>'param'));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->exactly(2))->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT, 2)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');

        $listener->handleRequest($getResponseEvent);
        $this->assertFalse($deviceView->hasSwitchParam());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
                'https://testsite.com/some/parameters?%s=%s&some=param',
                $this->switchParam,
                DeviceView::VIEW_TABLET
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_TABLET, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestWithDifferentSwitchParamRedirect()
    {
        $this->config['tablet'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $switchParam = 'custom_param';


        $this->request->query = new InputBag(array('some'=>'param'));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->exactly(2))->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT, 2)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setSwitchParam($switchParam);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');

        $listener->handleRequest($getResponseEvent);
        $this->assertFalse($deviceView->hasSwitchParam());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
                'https://testsite.com/some/parameters?%s=%s&some=param',
                $switchParam,
                DeviceView::VIEW_TABLET
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_TABLET, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleDeviceIsTabletAndTabletRedirectIsDisabledAndDetectTabletAsMobileIsFalse()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com');

        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertTrue($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());

        $requestEventResponse = $getResponseEvent->getResponse();
        $this->assertNull($requestEventResponse);

        $responseEventResponse = new Response('Tablet view', Response::HTTP_OK);
        $filterResponseEvent = $this->createResponseEvent($responseEventResponse);
        $listener->handleResponse($filterResponseEvent);
        $modifiedResponse = $filterResponseEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $modifiedResponse);
        $this->assertEquals(Response::HTTP_OK, $modifiedResponse->getStatusCode());
        $this->assertEquals('Tablet view', $modifiedResponse->getContent());

        $cookies = $modifiedResponse->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_TABLET, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleDeviceIsTabletAsMobileAndTabletRedirectIsDisabledAndDetectTabletAsMobileIsTrue()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com', 'status_code' => Response::HTTP_FOUND);
        $this->config['detect_tablet_as_mobile'] = true;

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT, 2)
            )
        );
        $this->mobileDetector->expects($this->atLeastOnce())->method('isMobile')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertFalse($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();

        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
                'https://mobilehost.com/some/parameters?%s=%s',
                $this->switchParam,
                DeviceView::VIEW_MOBILE
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestHasTabletRedirectWithoutPath()
    {
        $this->config['tablet'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT_WITHOUT_PATH, 2)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertFalse($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();

        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://testsite.com?device_view=tablet', $response->getTargetUrl());
        $this->assertEquals(sprintf(
                'https://testsite.com?%s=%s',
                $this->switchParam,
                DeviceView::VIEW_TABLET
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_TABLET, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestHasTabletNoRedirect()
    {
        $this->config['tablet'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::NO_REDIRECT, 1)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertTrue($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());

        $requestEventResponse = $getResponseEvent->getResponse();
        $this->assertNull($requestEventResponse);

        $responseEventResponse = new Response('Tablet view no redirect', Response::HTTP_OK);
        $filterResponseEvent = $this->createResponseEvent($responseEventResponse);
        $listener->handleResponse($filterResponseEvent);
        $modifiedResponse = $filterResponseEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $modifiedResponse);
        $this->assertEquals(Response::HTTP_OK, $modifiedResponse->getStatusCode());
        $this->assertEquals('Tablet view no redirect', $modifiedResponse->getContent());

        $cookies = $modifiedResponse->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_TABLET, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestHasMobileRedirect()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT, 2)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(false));
        $this->mobileDetector->expects($this->once())->method('isMobile')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertFalse($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();

        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
                'https://testsite.com/some/parameters?%s=%s',
                $this->switchParam,
                DeviceView::VIEW_MOBILE
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestHasMobileRedirectWithoutPath()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => Response::HTTP_FOUND);

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::REDIRECT_WITHOUT_PATH, 2)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(false));
        $this->mobileDetector->expects($this->once())->method('isMobile')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertFalse($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());

        $response = $getResponseEvent->getResponse();

        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals(sprintf(
                'https://testsite.com?%s=%s',
                $this->switchParam,
                DeviceView::VIEW_MOBILE
            ), $response->getTargetUrl()
        );

        $cookies = $response->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestHasMobileNoRedirect()
    {
        $this->config['mobile'] = array('is_enabled' => true, 'host' => 'https://testsite.com', 'status_code' => 123);

        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/some/parameters'));
        $this->request->expects($this->any())->method('get')->will($this->returnValue('routeName'));

        $this->router->expects($this->atLeastOnce())->method('getRouteCollection')->will(
            $this->returnValue(
                $this->createRouteCollectionWithRouteAndRoutingOption(RequestResponseListener::NO_REDIRECT, 1)
            )
        );
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(false));
        $this->mobileDetector->expects($this->once())->method('isMobile')->will($this->returnValue(true));

        $deviceView = new DeviceView($this->requestStack);
        $listener = new RequestResponseListener($this->mobileDetector, $deviceView, $this->router, $this->config);

        $getResponseEvent = $this->createGetResponseEvent('some content');
        $listener->handleRequest($getResponseEvent);

        $this->assertTrue($listener->needsResponseModification());
        $this->assertNull($deviceView->getRequestedViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());

        $requestEventResponse = $getResponseEvent->getResponse();
        $this->assertNull($requestEventResponse);

        $responseEventResponse = new Response('Mobile view no redirect', Response::HTTP_OK);
        $filterResponseEvent = $this->createResponseEvent($responseEventResponse);
        $listener->handleResponse($filterResponseEvent);
        $modifiedResponse = $filterResponseEvent->getResponse();

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $modifiedResponse);
        $this->assertEquals(Response::HTTP_OK, $modifiedResponse->getStatusCode());
        $this->assertEquals('Mobile view no redirect', $modifiedResponse->getContent());

        $cookies = $modifiedResponse->headers->getCookies();
        $this->assertGreaterThan(0, count($cookies));
        foreach ($cookies as $cookie) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Cookie', $cookie);

            if ($cookie->getName() == $deviceView->getCookieKey()) {
                $this->assertEquals(DeviceView::VIEW_MOBILE, $cookie->getValue());
            }
        }
    }

    /**
     * @test
     */
    public function handleRequestUpdatedMobileDetectorUserAgent()
    {
        $this->mobileDetector->expects($this->once())->method('setUserAgent')->with($this->equalTo('agent'));

        $event = $this->createGetResponseEvent('some content');
        $event->getRequest()->headers->set('user-agent', 'agent');

        $listener = new RequestResponseListener($this->mobileDetector, $this->deviceView, $this->router, $this->config);
        $listener->handleRequest($event);
    }


    private function createRouteCollectionWithRouteAndRoutingOption(string $returnValue, int $times): MockObject
    {
        $route = $this->getMockBuilder('Symfony\Component\Routing\Route')->disableOriginalConstructor()->getMock();
        $route->expects($this->exactly($times))->method('getOption')->will($this->returnValue($returnValue));
        $routeCollection = $this->createMock('Symfony\Component\Routing\RouteCollection');
        $routeCollection->expects($this->exactly($times))->method('get')->will($this->returnValue($route));

        return $routeCollection;
    }

    private function createGetResponseEvent(string $content, string $method = 'GET', array $headers = array()): ViewEvent
    {
        $event = new ViewEvent(
            $this->createMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
            $this->request,
            #HttpKernelInterface::MAIN_REQUEST,
            SymfonyCompatibilityLayer::MAIN_REQUEST,
            $content
        );
        $event->getRequest()->headers = new HeaderBag($headers);

        return $event;
    }


    private function createResponseEvent(Response $response, string $method = 'GET', array $headers = array()): ResponseEvent
    {
        $event = new ResponseEvent(
            $this->createMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
            $this->request,
            #HttpKernelInterface::MAIN_REQUEST,
            SymfonyCompatibilityLayer::MAIN_REQUEST,
            $response
        );
        $event->getRequest()->headers = new HeaderBag($headers);

        return $event;
    }
}
