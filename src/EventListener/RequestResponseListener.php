<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\EventListener;

use Closure;
use SunCat\MobileDetectBundle\DeviceDetector\MobileDetector;
use SunCat\MobileDetectBundle\Helper\DeviceView;
use SunCat\MobileDetectBundle\Helper\RedirectResponseWithCookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Request and response listener
 *
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 * @author HenriVesala <henri.vesala@gmail.com>
 */
class RequestResponseListener
{
    public const REDIRECT = 'redirect';
    public const NO_REDIRECT = 'no_redirect';
    public const REDIRECT_WITHOUT_PATH = 'redirect_without_path';

    public const MOBILE = 'mobile';
    public const TABLET = 'tablet';
    public const FULL = 'full';

    protected bool $needModifyResponse = false;
    protected Closure $modifyResponseClosure;

    public function __construct(
        protected MobileDetector $mobileDetector,
        protected DeviceView $deviceView,
        private RouterInterface $router,
        protected array $redirectConf,
        protected bool $isFullPath = true
    ) {
        // isFullPath - Configs mobile & tablet
    }

    /**
     * Handles the Request
     */
    public function handleRequest(RequestEvent $event)
    {
        // only handle main request, do not handle sub request like esi includes
        // If the device view is "not the mobile view" (e.g. we're not in the request context)
        if (($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) || $this->deviceView->isNotMobileView()) {
            return;
        }

        $request = $event->getRequest();
        $this->mobileDetector->setUserAgent($request->headers->get('user-agent'));

        // Sets the flag for the response handled by the GET switch param and the type of the view.
        if ($this->deviceView->hasSwitchParam()) {
            $event->setResponse($this->getRedirectResponseBySwitchParam($request));

            return;
        }

        // If neither the SwitchParam nor the cookie are set, detect the view...
        $cookieIsSet = $this->deviceView->getRequestedViewType() !== null;
        if (!$cookieIsSet) {
            if (($this->redirectConf['detect_tablet_as_mobile'] === false) && $this->mobileDetector->isTablet()) {
                $this->deviceView->setTabletView();
            } elseif ($this->mobileDetector->isMobile()) {
                $this->deviceView->setMobileView();
            } else {
                $this->deviceView->setFullView();
            }
        }

        // Check if we must redirect to the target view and do so if needed
        if ($this->mustRedirect($request, $this->deviceView->getViewType())) {
            if (($response = $this->getRedirectResponse($request, $this->deviceView->getViewType()))) {
                $event->setResponse($response);
            }

            return;
        }

        // No need to redirect

        // We don't need to modify _every_ response: once the cookie is set,
        // save bandwith and CPU cycles by just letting it expire someday.
        if ($cookieIsSet) {
            return;
        }

        // Sets the flag for the response handler and prepares the modification closure
        $this->needModifyResponse = true;
        $this->prepareResponseModification($this->deviceView->getViewType());
    }

    /**
     * Will this request listener modify the response? This flag will be set during the "handleRequest" phase.
     * Made public for testability.
     */
    public function needsResponseModification(): bool
    {
        return $this->needModifyResponse;
    }

    /**
     * Handles the Response
     */
    public function handleResponse(ResponseEvent $event)
    {
        if ($this->needModifyResponse && $this->modifyResponseClosure instanceof Closure) {
            $modifyClosure = $this->modifyResponseClosure;
            $event->setResponse($modifyClosure($this->deviceView, $event));
        }
    }

    /**
     * Do we have to redirect?
     */
    protected function mustRedirect(Request $request, ?string $view): bool
    {
        if (!isset($this->redirectConf[$view]) ||
            !$this->redirectConf[$view]['is_enabled'] ||
            ($this->getRoutingOption($request->get('_route'), $view) === self::NO_REDIRECT)
        ) {
            return false;
        }

        $isHost = ($this->getCurrentHost($request) === $this->redirectConf[$view]['host']);

        if (!$isHost) {
            return true;
        }

        return false;
    }

    /**
     * Prepares the response modification which will take place after the controller logic has been executed.
     */
    protected function prepareResponseModification(?string $view)
    {
        $this->modifyResponseClosure = function (DeviceView $deviceView, ResponseEvent $event) use ($view) {
            return $deviceView->modifyResponse($view, $event->getResponse());
        };
    }

    protected function getRedirectResponseBySwitchParam(Request $request): RedirectResponseWithCookie
    {
        if ($this->mustRedirect($request, $this->deviceView->getViewType())) {
            // Avoid unnecessary redirects: if we need to redirect to another view,
            // do it in one response while setting the cookie.
            $redirectUrl = $this->getRedirectUrl($request, $this->deviceView->getViewType());
        } else {
            if (true === $this->isFullPath) {

                if($request->getPathInfo())
                {
                    $redirectUrl = $request->getUriForPath($request->getPathInfo());}
                else{
                    $redirectUrl = $this->getCurrentHost($request);
                }

                $queryParams = $request->query->all();
                if (array_key_exists($this->deviceView->getSwitchParam(), $queryParams)) {
                    unset($queryParams[$this->deviceView->getSwitchParam()]);
                }
                if (sizeof($queryParams) > 0) {
                    $redirectUrl .= '?'.Request::normalizeQueryString(http_build_query($queryParams));
                }
            } else {
                $redirectUrl = $this->getCurrentHost($request);
            }
        }

        return $this->deviceView->getRedirectResponseBySwitchParam($redirectUrl);
    }

    /**
     * Gets the RedirectResponse for the specified view.
     */
    protected function getRedirectResponse(Request $request, string $view): ?RedirectResponse
    {
        if (($host = $this->getRedirectUrl($request, $view))) {
            return $this->deviceView->getRedirectResponse(
                $view,
                $host,
                $this->redirectConf[$view]['status_code']
            );
        }

        return null;
    }

    /**
     * Gets the redirect url.
     */
    protected function getRedirectUrl(Request $request, string $platform): ?string
    {
        if (($routingOption = $this->getRoutingOption($request->get('_route'), $platform))) {
            if (self::REDIRECT === $routingOption) {
                // Make sure to hint at the device override, otherwise infinite loop
                // redirection may occur if different device views are hosted on
                // different domains (since the cookie can't be shared across domains)
                $queryParams = $request->query->all();
                $queryParams[$this->deviceView->getSwitchParam()] = $platform;

                return rtrim($this->redirectConf[$platform]['host'], '/').$request->getPathInfo(
                    ).'?'.Request::normalizeQueryString(http_build_query($queryParams));
            } elseif (self::REDIRECT_WITHOUT_PATH === $routingOption) {
                // Make sure to hint at the device override, otherwise infinite loop
                // redirections may occur if different device views are hosted on
                // different domains (since the cookie can't be shared across domains)
                return $this->redirectConf[$platform]['host'].'?'.$this->deviceView->getSwitchParam().'='.$platform;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Gets named option from current route.
     */
    protected function getRoutingOption(?string $routeName, string $optionName): ?string
    {
        if(!$routeName) {
            return null;
        }

        $option = null;
        $route = $this->router->getRouteCollection()->get($routeName);

        if ($route instanceof Route) {
            $option = $route->getOption($optionName);
        }

        if (!$option && isset($this->redirectConf[$optionName])) {
            $option = $this->redirectConf[$optionName]['action'];
        }

        if (in_array($option, array(self::REDIRECT, self::REDIRECT_WITHOUT_PATH, self::NO_REDIRECT))) {
            return $option;
        }

        return null;
    }

    protected function getCurrentHost(Request $request): string
    {
        return $request->getScheme().'://'.$request->getHost();
    }
}
