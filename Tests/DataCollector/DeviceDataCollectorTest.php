<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SunCat\MobileDetectBundle\DataCollector\DeviceDataCollector;
use SunCat\MobileDetectBundle\EventListener\RequestResponseListener;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use SunCat\MobileDetectBundle\Helper\SymfonyCompatibilityLayer;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * DeviceDataCollectorTest
 *
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 */
class DeviceDataCollectorTest extends TestCase
{
    private MockObject $mobileDetector;
    private mixed $requestStack;
    private MockObject $request;
    private MockObject $response;

    public function setUp(): void
    {
        parent::setUp();

        $this->mobileDetector = $this->getMockBuilder('SunCat\MobileDetectBundle\DeviceDetector\MobileDetector')->disableOriginalConstructor()->getMock();
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $this->request->query = new InputBag();
        $this->request->cookies = new InputBag();
        $this->request->server = new ServerBag();
        $this->request->expects($this->any())->method('duplicate')->will($this->returnValue($this->request));

        $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->disableOriginalConstructor()->getMock();
        $this->requestStack->expects($this->any())
            #->method('getMainRequest')
            ->method(SymfonyCompatibilityLayer::getMainRequestFunctionName())
            ->will($this->returnValue($this->request))
        ;

        $this->response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')->getMock();
    }

    /**
     * @test
     */
    public function collectCurrentViewMobileIsCurrent()
    {
        $redirectConfig['tablet'] = array(
            'is_enabled' => true,
            'host' => 'https://testsite.com',
            'status_code' => Response::HTTP_FOUND,
            'action' => RequestResponseListener::REDIRECT
        );
        $this->request->cookies = new InputBag(array(DeviceView::COOKIE_KEY_DEFAULT => DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $deviceDataCollector = new DeviceDataCollector($deviceView);
        $deviceDataCollector->setRedirectConfig($redirectConfig);
        $deviceDataCollector->collect($this->request, $this->response);

        $currentView = $deviceDataCollector->getCurrentView();
        $views = $deviceDataCollector->getViews();

        $this->assertEquals($deviceView->getViewType(), $currentView);
        $this->assertEquals(DeviceView::VIEW_MOBILE, $currentView);
        $this->assertCount(3, $views);

        foreach ($views as $view) {
            $this->assertIsArray($view);
            $this->assertArrayHasKey('type', $view);
            $this->assertArrayHasKey('label', $view);
            $this->assertArrayHasKey('link', $view);
            $this->assertArrayHasKey('isCurrent', $view);
            $this->assertArrayHasKey('enabled', $view);
            if (DeviceView::VIEW_MOBILE === $view['type']) {
                $this->assertTrue($view['isCurrent']);
            }
        }
    }

    /**
     * @test
     */
    public function collectCurrentViewMobileCanUseTablet()
    {
        $redirectConfig['tablet'] = array(
            'is_enabled' => true,
            'host' => 'https://testsite.com',
            'status_code' => Response::HTTP_FOUND,
            'action' => RequestResponseListener::REDIRECT
        );
        $this->request->query = new InputBag(array('param1' => 'value1'));
        $this->request->expects($this->any())->method('getHost')->will($this->returnValue('testsite.com'));
        $this->request->expects($this->any())->method('getSchemeAndHttpHost')->will($this->returnValue('https://testsite.com'));
        $this->request->expects($this->any())->method('getBaseUrl')->will($this->returnValue('/base-url'));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/path-info'));
        $test = $this;
        $this->request->expects($this->any())->method('getQueryString')->will($this->returnCallback(function() use ($test) {
            $qs = Request::normalizeQueryString($test->request->server->get('QUERY_STRING'));

            return '' === $qs ? null : $qs;
        }));
        $this->request->expects($this->any())->method('getUri')->will($this->returnCallback(function() use ($test) {
            if (null !== $qs = $test->request->getQueryString()) {
                $qs = '?'.$qs;
            }

            return $test->request->getSchemeAndHttpHost().$test->request->getBaseUrl().$test->request->getPathInfo().$qs;
        }));
        $this->request->cookies = new InputBag(array(DeviceView::COOKIE_KEY_DEFAULT => DeviceView::VIEW_MOBILE));
        $deviceView = new DeviceView($this->requestStack);
        $deviceDataCollector = new DeviceDataCollector($deviceView);
        $deviceDataCollector->setRedirectConfig($redirectConfig);
        $deviceDataCollector->collect($this->request, $this->response);

        $currentView = $deviceDataCollector->getCurrentView();
        $views = $deviceDataCollector->getViews();

        $this->assertEquals($deviceView->getViewType(), $currentView);
        $this->assertEquals(DeviceView::VIEW_MOBILE, $currentView);
        $this->assertCount(3, $views);

        foreach ($views as $view) {
            $this->assertIsArray($view);
            $this->assertArrayHasKey('type', $view);
            $this->assertArrayHasKey('label', $view);
            $this->assertArrayHasKey('link', $view);
            $this->assertArrayHasKey('isCurrent', $view);
            $this->assertArrayHasKey('enabled', $view);
            if (DeviceView::VIEW_MOBILE === $view['type']) {
                $this->assertTrue($view['isCurrent']);
            }
            if (DeviceView::VIEW_TABLET === $view['type']) {
                $this->assertFalse($view['isCurrent']);
                $this->assertTrue($view['enabled']);
                $this->assertEquals(
                    sprintf(
                        'https://testsite.com/base-url/path-info?%s=%s&param1=value1',
                        $deviceView->getSwitchParam(),
                        DeviceView::VIEW_TABLET
                    ), $view['link']
                );
            }
        }
    }

    /**
     * @test
     */
    public function collectCurrentViewFullCanUseMobile()
    {
        $redirectConfig['tablet'] = array(
            'is_enabled' => true,
            'host' => 'https://testsite.com',
            'status_code' => Response::HTTP_FOUND,
            'action' => RequestResponseListener::REDIRECT
        );
        $this->request->query = new InputBag(array('param1' => 'value1'));
        $this->request->expects($this->any())->method('getHost')->will($this->returnValue('testsite.com'));
        $this->request->expects($this->any())->method('getSchemeAndHttpHost')->will($this->returnValue('https://testsite.com'));
        $this->request->expects($this->any())->method('getBaseUrl')->will($this->returnValue('/base-url'));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/path-info'));
        $test = $this;
        $this->request->expects($this->any())->method('getQueryString')->will($this->returnCallback(function() use ($test) {
            $qs = Request::normalizeQueryString($test->request->server->get('QUERY_STRING'));

            return '' === $qs ? null : $qs;
        }));
        $this->request->expects($this->any())->method('getUri')->will($this->returnCallback(function() use ($test) {
            if (null !== $qs = $test->request->getQueryString()) {
                $qs = '?'.$qs;
            }

            return $test->request->getSchemeAndHttpHost().$test->request->getBaseUrl().$test->request->getPathInfo().$qs;
        }));
        $this->request->cookies = new InputBag(array(DeviceView::COOKIE_KEY_DEFAULT => DeviceView::VIEW_FULL));
        $deviceView = new DeviceView($this->requestStack);
        $deviceDataCollector = new DeviceDataCollector($deviceView);
        $deviceDataCollector->setRedirectConfig($redirectConfig);
        $deviceDataCollector->collect($this->request, $this->response);

        $currentView = $deviceDataCollector->getCurrentView();
        $views = $deviceDataCollector->getViews();

        $this->assertEquals($deviceView->getViewType(), $currentView);
        $this->assertEquals(DeviceView::VIEW_FULL, $currentView);
        $this->assertCount(3, $views);

        foreach ($views as $view) {
            $this->assertIsArray($view);
            $this->assertArrayHasKey('type', $view);
            $this->assertArrayHasKey('label', $view);
            $this->assertArrayHasKey('link', $view);
            $this->assertArrayHasKey('isCurrent', $view);
            $this->assertArrayHasKey('enabled', $view);
            if (DeviceView::VIEW_FULL === $view['type']) {
                $this->assertTrue($view['isCurrent']);
            }
            if (DeviceView::VIEW_MOBILE === $view['type']) {
                $this->assertFalse($view['isCurrent']);
                $this->assertTrue($view['enabled']);
                $this->assertEquals(
                    sprintf(
                        'https://testsite.com/base-url/path-info?%s=%s&param1=value1',
                        $deviceView->getSwitchParam(),
                        DeviceView::VIEW_MOBILE
                    ), $view['link']
                );
            }
        }
    }

    /**
     * @test
     */
    public function collectCurrentViewFullCantUseMobile()
    {
        $redirectConfig['mobile'] = array(
            'is_enabled' => true,
            'host' => 'https://m.testsite.com',
            'status_code' => Response::HTTP_FOUND,
            'action' => RequestResponseListener::REDIRECT
        );
        $this->request->query = new InputBag(array('param1' => 'value1'));
        $this->request->expects($this->any())->method('getHost')->will($this->returnValue('testsite.com'));
        $this->request->expects($this->any())->method('getSchemeAndHttpHost')->will($this->returnValue('https://testsite.com'));
        $this->request->expects($this->any())->method('getBaseUrl')->will($this->returnValue('/base-url'));
        $this->request->expects($this->any())->method('getPathInfo')->will($this->returnValue('/path-info'));
        $test = $this;
        $this->request->expects($this->any())->method('getQueryString')->will($this->returnCallback(function() use ($test) {
            $qs = Request::normalizeQueryString($test->request->server->get('QUERY_STRING'));

            return '' === $qs ? null : $qs;
        }));
        $this->request->expects($this->any())->method('getUri')->will($this->returnCallback(function() use ($test) {
            if (null !== $qs = $test->request->getQueryString()) {
                $qs = '?'.$qs;
            }

            return $test->request->getSchemeAndHttpHost().$test->request->getBaseUrl().$test->request->getPathInfo().$qs;
        }));
        $this->request->cookies = new InputBag(array(DeviceView::COOKIE_KEY_DEFAULT => DeviceView::VIEW_FULL));
        $deviceView = new DeviceView($this->requestStack);
        $deviceDataCollector = new DeviceDataCollector($deviceView);
        $deviceDataCollector->setRedirectConfig($redirectConfig);
        $deviceDataCollector->collect($this->request, $this->response);

        $currentView = $deviceDataCollector->getCurrentView();
        $views = $deviceDataCollector->getViews();

        $this->assertEquals($deviceView->getViewType(), $currentView);
        $this->assertEquals(DeviceView::VIEW_FULL, $currentView);
        $this->assertCount(3, $views);

        foreach ($views as $view) {
            $this->assertIsArray($view);
            $this->assertArrayHasKey('type', $view);
            $this->assertArrayHasKey('label', $view);
            $this->assertArrayHasKey('link', $view);
            $this->assertArrayHasKey('isCurrent', $view);
            $this->assertArrayHasKey('enabled', $view);
            if (DeviceView::VIEW_FULL === $view['type']) {
                $this->assertTrue($view['isCurrent']);
            }
            if (DeviceView::VIEW_MOBILE === $view['type']) {
                $this->assertFalse($view['isCurrent']);
                $this->assertFalse($view['enabled']);
                $this->assertEquals(
                    sprintf(
                        'https://testsite.com/base-url/path-info?%s=%s&param1=value1',
                        $deviceView->getSwitchParam(),
                        DeviceView::VIEW_MOBILE
                    ), $view['link']
                );
            }
        }
    }

    public function getNameValue()
    {
        $deviceView = new DeviceView($this->requestStack);
        $deviceDataCollector = new DeviceDataCollector($deviceView);
        $this->assertEquals('device.collector', $deviceDataCollector->getName());
    }
}
