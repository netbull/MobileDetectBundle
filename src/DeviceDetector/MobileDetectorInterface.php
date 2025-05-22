<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\DeviceDetector;

/**
 * MobileDetectorInterface interface
 *
 * @author pierrickmartos <pierrick.martos@gmail.com>
 */
interface MobileDetectorInterface
{
    /**
     * Check if the device is mobile.
     * Returns true if any type of mobile device detected, including special ones
     * @param  null $userAgent   deprecated
     * @param  null $httpHeaders deprecated
     * @return bool
     */
    public function isMobile($userAgent = null, $httpHeaders = null): bool;

    /**
     * Check if the device is a tablet.
     * Return true if any type of tablet device is detected.
     *
     * @param string|null $userAgent   deprecated
     * @param array|null $httpHeaders deprecated
     * @return bool
     */
    public function isTablet(string $userAgent = null, array $httpHeaders = null): bool;

    /**
     * This method checks for a certain property in the userAgent.
     *@todo: The httpHeaders part is not yet used.
     *
     */
    public function is(string $key, string $userAgent = null, string $httpHeaders = null): bool|int|null;

    /**
     * Some detection rules are relative (not standard),
     * because of the diversity of devices, vendors and
     * their conventions in representing the User-Agent or
     * the HTTP headers.
     *
     * This method will be used to check custom regexes against
     * the User-Agent string.
     *
     * @todo: search in the HTTP headers too.
     */
    public function match(string $regex, string|null $userAgent = null): bool;

    /**
     * Retrieve the mobile grading, using self::MOBILE_GRADE_* constants.
     *
     * @return string One of the self::MOBILE_GRADE_* constants.
     */
    public function mobileGrade(): string;
}
