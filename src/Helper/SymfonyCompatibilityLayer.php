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
    #public const MASTER_REQUEST = self::MAIN_REQUEST;


    public static function getMasterRequestFunctionName(): string
    {
        if (version_compare(Kernel::VERSION, '5.3.0', '>='))
        {return "getMainRequest";}

        return "getMasterRequest";
    }

    public static function getMainRequest(RequestStack $requestStack = null): ?Request
    {
        if (version_compare(Kernel::VERSION, '5.3.0', '>='))
        {return $requestStack->getMainRequest();}

        return $requestStack->getMasterRequest();
    }
}
