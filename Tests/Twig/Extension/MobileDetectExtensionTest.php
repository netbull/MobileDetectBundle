<?php

namespace SunCat\MobileDetectBundle\Tests\Twig\Extension;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use SunCat\MobileDetectBundle\Twig\Extension\MobileDetectExtension;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use \Twig\TwigFunction;

class MobileDetectExtensionTest extends TestCase
{
    private mixed $mobileDetector;
    private mixed $requestStack;
    private array $config;
    private MockObject $request;
    private string $cookieKey = DeviceView::COOKIE_KEY_DEFAULT;
    private string $switchParam = DeviceView::SWITCH_PARAM_DEFAULT;

    public function setUp(): void
    {
        parent::setUp();

        $this->mobileDetector = $this->getMockBuilder('SunCat\MobileDetectBundle\DeviceDetector\MobileDetector')->disableOriginalConstructor()->getMock();
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

        $this->config = array(
            'full' => array('is_enabled' => false, 'host' => null, 'status_code' => 302, 'action' => 'redirect'),
            'detect_tablet_as_mobile' => false
        );
    }

    /**
     * @test
     */
    public function getFunctionsArray()
    {
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);

        $functions = $extension->getFunctions();
        $this->assertCount(11, $functions);
        $names = [
            'is_mobile' => 'isMobile',
            'is_tablet' => 'isTablet',
            'is_device' => 'isDevice',
            'is_full_view' => 'isFullView',
            'is_mobile_view' => 'isMobileView',
            'is_tablet_view' => 'isTabletView',
            'is_not_mobile_view' => 'isNotMobileView',
            'is_ios' => 'isIOS',
            'is_android_os' => 'isAndroidOS',
            'full_view_url' => 'fullViewUrl',
            'device_version' => 'deviceVersion'
        ];
        foreach ($functions as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);
            $name = $function->getName();
            $callable = $function->getCallable();
            $this->assertArrayHasKey($name, $names);
            $this->assertIsArray($callable);
            $this->assertEquals($names[$name], $callable[1]);
        }
    }

    /**
     * @test
     */
    public function fullViewUrlHostNull()
    {
        $this->config['full'] = array('is_enabled' => true, 'host' => null);

        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertNull($extension->fullViewUrl());
    }

    /**
     * @test
     */
    public function fullViewUrlHostEmpty()
    {
        $this->config['full'] = array('is_enabled' => true, 'host' => '');

        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertNull($extension->fullViewUrl());
    }

    /**
     * @test
     */
    public function fullViewUrlNotSetRequest()
    {
        $this->config['full'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com');

        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertEquals('https://mobilehost.com', $extension->fullViewUrl());
    }

    /**
     * @test
     */
    public function fullViewUrlWithRequestQuery()
    {
        $this->config['full'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com');

        $this->request->query = new InputBag(array('myparam'=>'myvalue'));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $extension->setRequestByRequestStack($this->requestStack);
        $this->assertEquals('https://mobilehost.com?myparam=myvalue', $extension->fullViewUrl());
    }

    /**
     * @test
     */
    public function fullViewUrlWithRequestOnlyHost()
    {
        $this->config['full'] = array('is_enabled' => true, 'host' => 'https://mobilehost.com');

        $this->request->query = new InputBag(array('myparam'=>'myvalue'));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $extension->setRequestByRequestStack($this->requestStack);
        $this->assertEquals('https://mobilehost.com', $extension->fullViewUrl(false));
    }

    /**
     * @test
     */
    public function isMobileTrue()
    {
        $this->mobileDetector->expects($this->once())->method('isMobile')->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isMobile());
    }

    /**
     * @test
     */
    public function isMobileFalse()
    {
        $this->mobileDetector->expects($this->once())->method('isMobile')->will($this->returnValue(false));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isMobile());
    }

    /**
     * @test
     */
    public function isTabletTrue()
    {
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isTablet());
    }

    /**
     * @test
     */
    public function isTabletFalse()
    {
        $this->mobileDetector->expects($this->once())->method('isTablet')->will($this->returnValue(false));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isTablet());
    }

    /**
     * @test
     */
    public function isDeviceIPhone()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isiphone'))
            ->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isDevice('iphone'));
    }

    /**
     * @test
     */
    public function isDeviceAndroid()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isandroid'))
            ->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isDevice('android'));
    }

    /**
     * @test
     */
    public function isFullViewTrue()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_FULL]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isFullView());
    }

    /**
     * @test
     */
    public function isFullViewFalse()
    {
        $deviceView = new DeviceView();
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isFullView());
    }

    /**
     * @test
     */
    public function isMobileViewTrue()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_MOBILE]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isMobileView());
    }

    /**
     * @test
     */
    public function isMobileViewFalse()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_TABLET]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isMobileView());
    }

    /**
     * @test
     */
    public function isTabletViewTrue()
    {
        $this->request->cookies = new InputBag([$this->switchParam=>DeviceView::VIEW_TABLET]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isTabletView());
    }

    /**
     * @test
     */
    public function isTabletViewFalse()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_MOBILE]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isTabletView());
    }

    /**
     * @test
     */
    public function isNotMobileViewTrue()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_NOT_MOBILE]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isNotMobileView());
    }

    /**
     * @test
     */
    public function isNotMobileViewFalse()
    {
        $this->request->cookies = new InputBag([$this->switchParam => DeviceView::VIEW_FULL]);
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isNotMobileView());
    }

    /**
     * @test
     */
    public function isIOSTrue()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isIOS'))
            ->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isIOS());
    }

    /**
     * @test
     */
    public function isIOSFalse()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isIOS'))
            ->will($this->returnValue(false));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isIOS());
    }

    /**
     * @test
     */
    public function isAndroidOSTrue()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isAndroidOS'))
            ->will($this->returnValue(true));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertTrue($extension->isAndroidOS());
    }

    /**
     * @test
     */
    public function isAndroidOSFalse()
    {
        $this->mobileDetector->expects($this->once())
            ->method('__call')
            ->with($this->equalTo('isAndroidOS'))
            ->will($this->returnValue(false));
        $deviceView = new DeviceView($this->requestStack);
        $extension = new MobileDetectExtension($this->mobileDetector, $deviceView, $this->config);
        $this->assertFalse($extension->isAndroidOS());
    }
}
