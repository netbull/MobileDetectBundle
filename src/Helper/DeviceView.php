<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\Helper;

use Datetime;
use Exception;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeviceView
 *
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 */
class DeviceView
{
    const VIEW_MOBILE = 'mobile';
    const VIEW_TABLET = 'tablet';
    const VIEW_FULL = 'full';
    const VIEW_NOT_MOBILE = 'not_mobile';

    const COOKIE_KEY_DEFAULT = 'device_view';
    const COOKIE_PATH_DEFAULT = '/';
    const COOKIE_DOMAIN_DEFAULT = '';
    const COOKIE_SECURE_DEFAULT = false;
    const COOKIE_HTTP_ONLY_DEFAULT = true;
    const COOKIE_EXPIRE_DATETIME_MODIFIER_DEFAULT = '1 month';
    const SWITCH_PARAM_DEFAULT = 'device_view';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string|null
     */
    protected $requestedViewType;

    /**
     * @var string|null
     */
    protected $viewType;

    /**
     * @var string
     */
    protected $cookieKey = self::COOKIE_KEY_DEFAULT;

    /**
     * @var string
     */
    protected $cookiePath = self::COOKIE_PATH_DEFAULT;

    /**
     * @var string
     */
    protected $cookieDomain = self::COOKIE_DOMAIN_DEFAULT;

    /**
     * @var bool
     */
    protected $cookieSecure = self::COOKIE_SECURE_DEFAULT;

    /**
     * @var bool
     */
    protected $cookieHttpOnly = self::COOKIE_HTTP_ONLY_DEFAULT;

    /**
     * @var string
     */
    protected $cookieExpireDatetimeModifier = self::COOKIE_EXPIRE_DATETIME_MODIFIER_DEFAULT;

    /**
     * @var string
     */
    protected $switchParam = self::SWITCH_PARAM_DEFAULT;

    /**
     * @var array
     */
    protected $redirectConfig;

    /**
     * DeviceView constructor.
     * @param RequestStack|null $requestStack
     */
    public function __construct(RequestStack $requestStack = null)
    {
        if (!$requestStack || !$this->request = $requestStack->getMasterRequest()) {
            $this->viewType = self::VIEW_NOT_MOBILE;
            return;
        }

        if ($this->request->query->has($this->switchParam)) {
            $this->viewType = $this->request->query->get($this->switchParam);
        } elseif ($this->request->cookies->has($this->cookieKey)) {
            $this->viewType = $this->request->cookies->get($this->cookieKey);
        }

        $this->requestedViewType = $this->viewType;
    }

    /**
     * Gets the view type for a device.
     */
    public function getViewType(): ?string
    {
        return $this->viewType;
    }

    /**
     * Gets the view type that has explicitly been requested either by switch param, or by cookie.
     *
     * @return string|null The requested view type or null if no view type has been explicitly requested.
     */
    public function getRequestedViewType(): ?string
    {
        return $this->requestedViewType;
    }

    /**
     * Is the device in full view.
     */
    public function isFullView(): bool
    {
        return $this->viewType === self::VIEW_FULL;
    }

    /**
     * Is the device a tablet view type.
     */
    public function isTabletView(): bool
    {
        return $this->viewType === self::VIEW_TABLET;
    }

    /**
     * Is the device a mobile view type.
     */
    public function isMobileView(): bool
    {
        return $this->viewType === self::VIEW_MOBILE;
    }

    /**
     * Is not the device a mobile view type (PC, Mac, etc.).
     */
    public function isNotMobileView(): bool
    {
        return $this->viewType === self::VIEW_NOT_MOBILE;
    }

    /**
     * Has the Request the switch param in the query string (GET header).
     */
    public function hasSwitchParam(): bool
    {
        return $this->request && $this->request->query->has($this->switchParam);
    }

    /**
     * Sets the view type.
     */
    public function setView(string $view)
    {
        $this->viewType = $view;
    }

    /**
     * Sets the full (desktop) view type.
     */
    public function setFullView()
    {
        $this->viewType = self::VIEW_FULL;
    }

    /**
     * Sets the tablet view type.
     */
    public function setTabletView()
    {
        $this->viewType = self::VIEW_TABLET;
    }

    /**
     * Sets the mobile view type.
     */
    public function setMobileView()
    {
        $this->viewType = self::VIEW_MOBILE;
    }

    /**
     * Sets the not mobile view type.
     */
    public function setNotMobileView()
    {
        $this->viewType = self::VIEW_NOT_MOBILE;
    }

    /**
     * Gets the switch param value from the query string (GET header).
     *
     * @return string|null
     */
    public function getSwitchParamValue(): ?string
    {
        if (!$this->request) {
            return null;
        }

        return $this->request->query->get($this->switchParam, self::VIEW_FULL);
    }

    public function getRedirectConfig(): array
    {
        return $this->redirectConfig;
    }

    public function setRedirectConfig(array $redirectConfig)
    {
        $this->redirectConfig = $redirectConfig;
    }

    /**
     * Gets the RedirectResponse by switch param value.
     */
    public function getRedirectResponseBySwitchParam(string $redirectUrl): RedirectResponseWithCookie
    {
        switch ($this->getSwitchParamValue()) {
            case self::VIEW_MOBILE:
                $viewType = self::VIEW_MOBILE;
                break;
            case self::VIEW_TABLET:
                $viewType = self::VIEW_TABLET;

                if (isset($this->redirectConfig['detect_tablet_as_mobile']) && $this->redirectConfig['detect_tablet_as_mobile'] === true) {
                    $viewType = self::VIEW_MOBILE;
                }
                break;
            default:
                $viewType = self::VIEW_FULL;
        }

        return new RedirectResponseWithCookie(
            $redirectUrl,
            $this->createCookie($viewType),
            $this->getStatusCode($viewType)
        );
    }

    /**
     * Modifies the Response for the specified device view.
     *
     * @param string $view The device view for which the response should be modified.
     * @param Response $response
     *
     * @return Response
     */
    public function modifyResponse(string $view, Response $response): Response
    {
        $response->headers->setCookie($this->createCookie($view));

        return $response;
    }

    /**
     * Gets the RedirectResponse for the specified device view.
     *
     * @param string $view The device view for which we want the RedirectResponse.
     * @param string $host Uri host
     * @param int $statusCode Status code
     *
     * @return RedirectResponseWithCookie
     */
    public function getRedirectResponse(string $view, string $host, int $statusCode): RedirectResponseWithCookie
    {
        return new RedirectResponseWithCookie($host, $this->createCookie($view), $statusCode);
    }

    public function setCookieKey(string $cookieKey)
    {
        $this->cookieKey = $cookieKey;
    }

    public function getCookieKey(): string
    {
        return $this->cookieKey;
    }

    public function getCookiePath(): string
    {
        return $this->cookiePath;
    }

    public function setCookiePath(string $cookiePath)
    {
        $this->cookiePath = $cookiePath;
    }

    public function getCookieDomain(): string
    {
        return $this->cookieDomain;
    }

    public function setCookieDomain(string $cookieDomain)
    {
        $this->cookieDomain = $cookieDomain;
    }

    public function isCookieSecure(): bool
    {
        return $this->cookieSecure;
    }

    public function setCookieSecure(bool $cookieSecure)
    {
        $this->cookieSecure = $cookieSecure;
    }

    public function isCookieHttpOnly(): bool
    {
        return $this->cookieHttpOnly;
    }

    public function setCookieHttpOnly(bool $cookieHttpOnly)
    {
        $this->cookieHttpOnly = $cookieHttpOnly;
    }

    public function setSwitchParam(string $switchParam)
    {
        $this->switchParam = $switchParam;
    }

    public function getSwitchParam(): string
    {
        return $this->switchParam;
    }

    public function setCookieExpireDatetimeModifier(string $cookieExpireDatetimeModifier)
    {
        $this->cookieExpireDatetimeModifier = $cookieExpireDatetimeModifier;
    }

    public function getCookieExpireDatetimeModifier(): string
    {
        return $this->cookieExpireDatetimeModifier;
    }

    protected function createCookie(string $value): Cookie
    {
        try {
            $expire = new Datetime($this->getCookieExpireDatetimeModifier());
        } catch (Exception $e) {
            $expire = new Datetime(self::COOKIE_EXPIRE_DATETIME_MODIFIER_DEFAULT);
        }

        return Cookie::create(
            $this->getCookieKey(),
            $value,
            $expire,
            $this->getCookiePath(),
            $this->getCookieDomain(),
            $this->isCookieSecure(),
            $this->isCookieHttpOnly(),
            false,
            Cookie::SAMESITE_LAX
        );
    }


    protected function getStatusCode(string $view): int
    {
        if (isset($this->redirectConfig[$view]['status_code'])) {
            return $this->redirectConfig[$view]['status_code'];
        }

        return 302;
    }
}
