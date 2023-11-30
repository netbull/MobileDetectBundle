<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\Twig\Extension;

use Mobile_Detect;
use SunCat\MobileDetectBundle\DeviceDetector\MobileDetector;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * MobileDetectExtension
 *
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 */
class MobileDetectExtension extends AbstractExtension
{
    /**
     * @var MobileDetector
     */
    private $mobileDetector;

    /**
     * @var DeviceView
     */
    private $deviceView;

    /**
     * @var array
     */
    private $redirectConf;

    /**
     * @var Request
     */
    private $request;

    /**
     * MobileDetectExtension constructor.
     */
    public function __construct(MobileDetector $mobileDetector, DeviceView $deviceView, array $redirectConf)
    {
        $this->mobileDetector = $mobileDetector;
        $this->deviceView = $deviceView;
        $this->redirectConf = $redirectConf;
    }

    /**
     * Get extension twig function
     */
    public function getFunctions(): array
    {
        return array(
            new TwigFunction('is_mobile', array($this, 'isMobile')),
            new TwigFunction('is_tablet', array($this, 'isTablet')),
            new TwigFunction('is_device', array($this, 'isDevice')),
            new TwigFunction('is_full_view', array($this, 'isFullView')),
            new TwigFunction('is_mobile_view', array($this, 'isMobileView')),
            new TwigFunction('is_tablet_view', array($this, 'isTabletView')),
            new TwigFunction('is_not_mobile_view', array($this, 'isNotMobileView')),
            new TwigFunction('is_ios', array($this, 'isIOS')),
            new TwigFunction('is_android_os', array($this, 'isAndroidOS')),
            new TwigFunction('full_view_url', array($this, 'fullViewUrl'), array('is_safe' => array('html'))),
            new TwigFunction('device_version', array($this, 'deviceVersion')),
        );
    }

    /**
     * Check the version of the given property in the User-Agent.
     * Will return a float number. (eg. 2_0 will return 2.0, 4.3.1 will return 4.31)
     *
     * @param string $propertyName The name of the property. See self::getProperties() array
     *                             keys for all possible properties.
     * @param string $type         Either self::VERSION_TYPE_STRING to get a string value or
     *                             self::VERSION_TYPE_FLOAT indicating a float value. This parameter
     *                             is optional and defaults to self::VERSION_TYPE_STRING. Passing an
     *                             invalid parameter will default to the this type as well.
     *
     * @return string|float The version of the property we are trying to extract.
     */
    public function deviceVersion(string $propertyName, string $type = Mobile_Detect::VERSION_TYPE_STRING)
    {
        return $this->mobileDetector->version($propertyName, $type);
    }

    /**
     * Regardless of the current view, returns the URL that leads to the equivalent page
     * in the full/desktop view. This is useful for generating <link rel="canonical"> tags
     * on mobile pages for Search Engine Optimization.
     * See: http://searchengineland.com/the-definitive-guide-to-mobile-technical-seo-166066
     */
    public function fullViewUrl(bool $addCurrentPathAndQuery = true): ?string
    {
        if (!isset($this->redirectConf[DeviceView::VIEW_FULL]['host'])) {
            // The host property has not been configured for the full view
            return null;
        }

        $fullHost = $this->redirectConf[DeviceView::VIEW_FULL]['host'];

        if (empty($fullHost)) {
            return null;
        }

        // If not in request scope, we can only return the base URL to the full view
        if (!$this->request) {
            return $fullHost;
        }

        if (false === $addCurrentPathAndQuery) {
            return $fullHost;
        }

        // if fullHost ends with /, skip it since getPathInfo() also starts with /
        $result = rtrim($fullHost, '/').$this->request->getPathInfo();

        $query = Request::normalizeQueryString(http_build_query($this->request->query->all(), null, '&'));
        if ($query) {
            $result .= '?'.$query;
        }

        return $result;
    }

    /**
     * Is mobile
     */
    public function isMobile(): bool
    {
        return $this->mobileDetector->isMobile();
    }

    /**
     * Is tablet
     */
    public function isTablet(): bool
    {
        return $this->mobileDetector->isTablet();
    }

    /**
     * Is device
     *
     * @param string $deviceName is[iPhone|BlackBerry|HTC|Nexus|Dell|Motorola|Samsung|Sony|Asus|Palm|Vertu|...]
     */
    public function isDevice(string $deviceName): bool
    {
        $magicMethodName = 'is'.strtolower((string)$deviceName);

        return $this->mobileDetector->$magicMethodName();
    }

    /**
     * Is full view type
     */
    public function isFullView(): bool
    {
        return $this->deviceView->isFullView();
    }

    /**
     * Is mobile view type
     */
    public function isMobileView(): bool
    {
        return $this->deviceView->isMobileView();
    }

    /**
     * Is tablet view type
     */
    public function isTabletView(): bool
    {
        return $this->deviceView->isTabletView();
    }

    /**
     * Is not mobile view type
     */
    public function isNotMobileView(): bool
    {
        return $this->deviceView->isNotMobileView();
    }

    /**
     * Is iOS
     */
    public function isIOS(): bool
    {
        return $this->mobileDetector->isIOS();
    }

    /**
     * Is Android OS
     */
    public function isAndroidOS(): bool
    {
        return $this->mobileDetector->isAndroidOS();
    }

    /**
     * Sets the request from the current scope.
     *
     * @param RequestStack|null $requestStack
     */
    public function setRequestByRequestStack(?RequestStack $requestStack = null)
    {
        if (null !== $requestStack) {
            $this->request = $requestStack->getMasterRequest();
        }
    }
}
