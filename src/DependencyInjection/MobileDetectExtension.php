<?php

/*
 * This file is part of the MobileDetectBundle.
 *
 * (c) Nikolay Ivlev <nikolay.kotovsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SunCat\MobileDetectBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class MobileDetectExtension extends Extension
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @return void
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('mobile_detect.yaml');
        $loader->load('listener.yaml');
        $loader->load('twig.yaml');

        // valid mobile host
        if ($config['redirect']['mobile']['is_enabled'] && !$this->validHost($config['redirect']['mobile']['host'])) {
            $config['redirect']['mobile']['is_enabled'] = false;
        }

        // valid tablet host
        if ($config['redirect']['tablet']['is_enabled'] && !$this->validHost($config['redirect']['tablet']['host'])) {
            $config['redirect']['tablet']['is_enabled'] = false;
        }

        // valid full host
        if ($config['redirect']['full']['is_enabled'] && !$this->validHost($config['redirect']['full']['host'])) {
            $config['redirect']['full']['is_enabled'] = false;
        }

        $container->setParameter('mobile_detect.redirect', $config['redirect']);
        $container->setParameter('mobile_detect.switch_device_view.save_referer_path', $config['switch_device_view']['save_referer_path']);

        $container->setParameter('mobile_detect.cookie_key', $config['cookie_key']);
        $container->setParameter('mobile_detect.cookie_path', $config['cookie_path']);
        $container->setParameter('mobile_detect.cookie_domain', $config['cookie_domain']);
        $container->setParameter('mobile_detect.cookie_secure', $config['cookie_secure']);
        $container->setParameter('mobile_detect.cookie_httpOnly', $config['cookie_httpOnly']);
        $container->setParameter('mobile_detect.cookie_expire_datetime_modifier', $config['cookie_expire_datetime_modifier']);
        $container->setParameter('mobile_detect.switch_param', $config['switch_param']);

        $container->setParameter('mobile_detect.mobile_detector.class', $config['mobile_detector_class']);
        $container->setParameter('mobile_detect.device_view.class', $config['device_view_class']);
        $container->setParameter('mobile_detect.request_response_listener.class', $config['request_response_listener_class']);
        $container->setParameter('mobile_detect.twig.extension.class', $config['twig_extension_class']);

        $container->setAlias('mobile_detect.mobile_detector', $config['service']['mobile_detector']);
    }

    protected function validHost(string $url): bool
    {
        $pattern = "/^(?:(http|https):\/\/)([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i";

        return (bool) preg_match($pattern, $url);
    }
}
