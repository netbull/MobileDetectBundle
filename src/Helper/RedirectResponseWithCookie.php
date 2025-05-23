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

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RedirectResponseWithCookie
 *
 * @author suncat2000 <nikolay.kotovsky@gmail.com>
 */
class RedirectResponseWithCookie extends RedirectResponse
{
    /**
     * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
     */
    public function __construct(string $url, Cookie $cookie, $status = 302)
    {
        parent::__construct($url, $status);

        $this->headers->setCookie($cookie);
    }
}
