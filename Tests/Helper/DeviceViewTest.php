<?php

namespace SunCat\MobileDetectBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

class DeviceViewTest extends TestCase
{
    private mixed $requestStack;
    private mixed $request;
    private string $cookieKey = DeviceView::COOKIE_KEY_DEFAULT;
    private string $switchParam = DeviceView::SWITCH_PARAM_DEFAULT;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->disableOriginalConstructor()->getMock();

        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $this->request->expects($this->any())->method('getScheme')->will($this->returnValue('http'));
        $this->request->expects($this->any())->method('getHost')->will($this->returnValue('testhost.com'));
        $this->request->expects($this->any())->method('getUriForPath')->will($this->returnValue('/'));
        $this->request->query = new InputBag();
        $this->request->cookies = new InputBag();

        $this->requestStack->expects($this->any())
            ->method('getMainRequest')
            ->will($this->returnValue($this->request))
        ;
    }

    /**
     * @test
     */
    public function getViewTypeMobile()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getRequestedViewType());
    }

    /**
     * @test
     */
    public function getViewTypeTablet()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_TABLET));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getViewType());
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getRequestedViewType());
    }

    /**
     * @test
     */
    public function getViewTypeFull()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_FULL));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_FULL, $deviceView->getViewType());
        $this->assertEquals(DeviceView::VIEW_FULL, $deviceView->getRequestedViewType());
    }

    /**
     * @test
     */
    public function getViewTypeNotMobile()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView();
        $this->assertEquals(DeviceView::VIEW_NOT_MOBILE, $deviceView->getViewType());
        $this->assertNull($deviceView->getRequestedViewType());
    }

    /**
     * @test
     */
    public function getViewTypeMobileFromCookie()
    {
        $this->request->cookies = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getViewType());
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getRequestedViewType());
    }

    /**
     * @test
     */
    public function isFullViewTrue()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_FULL));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertTrue($deviceView->isFullView());
    }

    /**
     * @test
     */
    public function isFullViewFalse()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertFalse($deviceView->isFullView());
    }

    /**
     * @test
     */
    public function isTabletViewTrue()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_TABLET));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertTrue($deviceView->isTabletView());
    }

    /**
     * @test
     */
    public function isTabletViewFalse()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertFalse($deviceView->isTabletView());
    }

    /**
     * @test
     */
    public function isMobileViewTrue()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertTrue($deviceView->isMobileView());
    }

    /**
     * @test
     */
    public function isMobileViewFalse()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_TABLET));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertFalse($deviceView->isMobileView());
    }

    /**
     * @test
     */
    public function isNotMobileViewTrue()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_NOT_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertTrue($deviceView->isNotMobileView());
    }

    /**
     * @test
     */
    public function isNotMobileViewFalse()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertFalse($deviceView->isNotMobileView());
    }

    /**
     * @test
     */
    public function hasSwitchParamTrue()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertTrue($deviceView->hasSwitchParam());
    }

    /**
     * @test
     */
    public function hasSwitchParamFalse1()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $this->assertFalse($deviceView->hasSwitchParam());
    }

    /**
     * @test
     */
    public function hasSwitchParamFalse2()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView();
        $this->assertFalse($deviceView->hasSwitchParam());
    }

    /**
     * @test
     */
    public function setViewMobile()
    {
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setView(DeviceView::VIEW_MOBILE);
        $this->assertTrue($deviceView->isMobileView());
    }

    /**
     * @test
     */
    public function setViewFull()
    {
        $deviceView = new DeviceView();
        $deviceView->setView(DeviceView::VIEW_FULL);
        $this->assertTrue($deviceView->isFullView());
    }

    /**
     * @test
     */
    public function setFullViewAndCheckIsFullView()
    {
        $deviceView = new DeviceView();
        $deviceView->setFullView();
        $this->assertTrue($deviceView->isFullView());
    }

    /**
     * @test
     */
    public function setTabletViewAndCheckIsTabletView()
    {
        $deviceView = new DeviceView();
        $deviceView->setTabletView();
        $this->assertTrue($deviceView->isTabletView());
    }

    /**
     * @test
     */
    public function setMobileViewAndCheckIsMobileView()
    {
        $deviceView = new DeviceView();
        $deviceView->setMobileView();
        $this->assertTrue($deviceView->isMobileView());
    }

    /**
     * @test
     */
    public function setNotMobileViewAndCheckIsNotMobileView()
    {
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setNotMobileView();
        $this->assertTrue($deviceView->isNotMobileView());
    }

    /**
     * @test
     */
    public function getSwitchParamValueNull()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView();
        $this->assertNull($deviceView->getSwitchParamValue());
    }

    /**
     * @test
     */
    public function getSwitchParamValueFullDefault()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_FULL, $deviceView->getSwitchParamValue());
    }

    /**
     * @test
     */
    public function getSwitchParamValueFull()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_FULL));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_FULL, $deviceView->getSwitchParamValue());
    }

    /**
     * @test
     */
    public function getSwitchParamValueMobile()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_MOBILE, $deviceView->getSwitchParamValue());
    }

    /**
     * @test
     */
    public function getSwitchParamValueTablet()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_TABLET));
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals(DeviceView::VIEW_TABLET, $deviceView->getSwitchParamValue());
    }

    /**
     * @test
     */
    public function getRedirectResponseBySwitchParamWithCookieViewMobile()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setRedirectConfig([DeviceView::VIEW_MOBILE => ['status_code' => 301]]);
        $response = $deviceView->getRedirectResponseBySwitchParam('/redirect-url');
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function getRedirectResponseBySwitchParamWithCookieViewTablet()
    {
        $this->request->query = new InputBag(array($this->switchParam=>DeviceView::VIEW_TABLET));
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setRedirectConfig([DeviceView::VIEW_TABLET => ['status_code' => 301]]);
        $response = $deviceView->getRedirectResponseBySwitchParam('/redirect-url');
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function getRedirectResponseBySwitchParamWithCookieViewFullDefault()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $response = $deviceView->getRedirectResponseBySwitchParam('/redirect-url');
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function modifyResponseToMobileAndCheckResponse()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $response = new Response();
        $this->assertCount(0, $response->headers->getCookies());
        $deviceView->modifyResponse(DeviceView::VIEW_MOBILE, $response);

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
    public function getRedirectResponseWithCookieViewMobile()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $response = $deviceView->getRedirectResponse(DeviceView::VIEW_MOBILE, 'https://mobilesite.com', 302);
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(302, $response->getStatusCode());
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
    public function getRedirectResponseAndCheckCookieSettings()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $deviceView->setCookiePath('/test');
        $deviceView->setCookieDomain('example.com');
        $deviceView->setCookieSecure(true);
        $deviceView->setCookieHttpOnly(false);

        $response = $deviceView->getRedirectResponse(DeviceView::VIEW_MOBILE, 'https://mobilesite.com', 302);
        $this->assertInstanceOf('SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie', $response);
        $this->assertEquals(302, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('/test', $cookies[0]->getPath());
        $this->assertEquals('example.com', $cookies[0]->getDomain());
        $this->assertTrue($cookies[0]->isSecure());
        $this->assertFalse($cookies[0]->isHttpOnly());
    }

    /**
     * @test
     */
    public function getCookieKeyDeviceView()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals($this->cookieKey, $deviceView->getCookieKey());
    }

    /**
     * @test
     */
    public function getSwitchParamDeviceView()
    {
        $this->request->query = new InputBag();
        $deviceView = new DeviceView($this->requestStack);
        $this->assertEquals($this->switchParam, $deviceView->getSwitchParam());
    }
}
