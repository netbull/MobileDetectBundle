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
use JetBrains\PhpStorm\Pure;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\InputBag;
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

    protected ?Request $request = null;
    protected string|int|bool|null|float|InputBag $requestedViewType = null;
    protected string|int|bool|null|float|InputBag $viewType = null;
    protected string $cookieKey = self::COOKIE_KEY_DEFAULT;
    protected string $cookiePath = self::COOKIE_PATH_DEFAULT;
    protected string $cookieDomain = self::COOKIE_DOMAIN_DEFAULT;
    protected bool $cookieSecure = self::COOKIE_SECURE_DEFAULT;
    protected bool $cookieHttpOnly = self::COOKIE_HTTP_ONLY_DEFAULT;
    protected string $cookieExpireDatetimeModifier = self::COOKIE_EXPIRE_DATETIME_MODIFIER_DEFAULT;
    protected string $switchParam = self::SWITCH_PARAM_DEFAULT;
    protected array $redirectConfig;

    public function __construct(RequestStack $requestStack = null)
    {
        if (!$requestStack || !$this->request = $requestStack->getMainRequest()) {
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
     */
    public function getRequestedViewType(): ?string
    {
        return $this->requestedViewType;
    }

    public function isFullView(): bool
    {
        return $this->viewType === self::VIEW_FULL;
    }

    public function isTabletView(): bool
    {
        return $this->viewType === self::VIEW_TABLET;
    }

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
    #[Pure]
    public function hasSwitchParam(): bool
    {
        return $this->request && $this->request->query->has($this->switchParam);
    }

    public function setView(string $view)
    {
        $this->viewType = $view;
    }

    public function setFullView()
    {
        $this->viewType = self::VIEW_FULL;
    }

    public function setTabletView()
    {
        $this->viewType = self::VIEW_TABLET;
    }

    public function setMobileView()
    {
        $this->viewType = self::VIEW_MOBILE;
    }

    public function setNotMobileView()
    {
        $this->viewType = self::VIEW_NOT_MOBILE;
    }

    /**
     * Gets the switch param value from the query string (GET header).
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
     */
    public function modifyResponse(string $view, Response $response): Response
    {
        $response->headers->setCookie($this->createCookie($view));

        return $response;
    }

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
