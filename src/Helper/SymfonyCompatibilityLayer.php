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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The main reason is the change from symfony 5.3 from MainRequest to MasterRequest
 */
class SymfonyCompatibilityLayer
{
    public const MAIN_REQUEST = 1;
    public const MASTER_REQUEST = 1;

    public static function getMainRequestFunctionName(): string
    {
        if(method_exists(RequestStack::class, 'getMainRequest'))
        {return "getMainRequest";}

        return "getMasterRequest";
    }

    public static function getMainRequest(RequestStack $requestStack = null): ?Request
    {
        if(method_exists(RequestStack::class, 'getMainRequest'))
        {return $requestStack->getMainRequest();}

        return $requestStack->getMasterRequest();
    }
}
