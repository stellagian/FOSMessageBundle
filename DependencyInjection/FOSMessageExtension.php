<?php

/*
 * This file is part of the fos/message-bundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\MessageBundle\DependencyInjection;

use FOS\MessageBundle\Bridge\BridgeManager;
use RuntimeException;
use InvalidArgumentException;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * FOSMessageBundle dependency injection extension
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class FOSMessageExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        if ($config['driver'] === 'odm') {
            throw new InvalidArgumentException(
                'Doctrine ODM is not supported by FOSMessageBundle for the moment. ' .
                'Don\'t hesitate to contribute to its implementation!'
            );
        }

        // Driver
        $loader->load(sprintf('drivers/%s.yml', $config['driver']));

        // Services
        $loader->load('config.yml');

        // Forms
        $loader->load('form.yml');

        // Validation
        $loader->load('validator.yml');

        // Enable bridges
        $bridgesManager = $container->get('fos_message.bridges_manager');
        $bridgesManager->registerAvailableBridge();

        foreach ($config['bridges'] as $bridge) {
            $bridgesManager->enable($bridge);
        }

        // Theme
        $container->setParameter('fos_message.theme', $config['theme']);

        // Load services
        $this->loadModels($config, $container);
        $this->loadServices($config, $container);
        $this->loadForms($config, $container);

        // Load bridges
        $this->loadBridges($loader, $bridgesManager, $container);
    }



    /**
     * Set models parameters in container for ModelBuilder
     *
     * @param array $config
     * @param ContainerBuilder $container
     */
    private function loadModels(array $config, ContainerBuilder $container)
    {
        $container->setParameter(
            'fos_message.model_class.message',
            $config['models']['message_class']
        );

        $container->setParameter(
            'fos_message.model_class.message_metadata',
            $config['models']['message_metadata_class']
        );

        $container->setParameter(
            'fos_message.model_class.thread',
            $config['models']['thread_class']
        );

        $container->setParameter(
            'fos_message.model_class.thread_metadata',
            $config['models']['thread_metadata_class']
        );
    }


    /**
     * Set services aliases from configuration
     *
     * @param array $config
     * @param ContainerBuilder $container
     *
     * @throws InvalidArgumentException
     */
    private function loadServices(array $config, ContainerBuilder $container)
    {
        $container->setAlias('fos_message.composer', $config['services']['composer']);
        $container->setAlias('fos_message.deleter', $config['services']['deleter']);
        $container->setAlias('fos_message.provider', $config['services']['provider']);
        $container->setAlias('fos_message.reader', $config['services']['reader']);
        $container->setAlias('fos_message.remover', $config['services']['remover']);
        $container->setAlias('fos_message.searcher', $config['services']['searcher']);
        $container->setAlias('fos_message.sender', $config['services']['sender']);
        $container->setAlias('fos_message.updater', $config['services']['updater']);
    }


    /**
     * Set forms parameters and aliases from configuration
     *
     * @param array $config
     * @param ContainerBuilder $container
     *
     * @throws InvalidArgumentException
     */
    private function loadForms(array $config, ContainerBuilder $container)
    {
        $container->setParameter('fos_message.field_type.recipient', $config['fields']['recipient']);
        $container->setParameter('fos_message.field_type.subject', $config['fields']['subject']);
        $container->setParameter('fos_message.field_type.content', $config['fields']['content']);

        $container->setAlias('fos_message.forms.new_thread.type', $config['forms']['new_thread']['type']);
        $container->setAlias('fos_message.forms.new_thread.factory', $config['forms']['new_thread']['factory']);
        $container->setAlias('fos_message.forms.new_thread.handler', $config['forms']['new_thread']['handler']);
        $container->setParameter('fos_message.forms.new_thread.name', $config['forms']['new_thread']['name']);
        $container->setParameter('fos_message.forms.new_thread.model', $config['forms']['new_thread']['model']);

        $container->setAlias('fos_message.forms.reply.type', $config['forms']['reply']['type']);
        $container->setAlias('fos_message.forms.reply.factory', $config['forms']['reply']['factory']);
        $container->setAlias('fos_message.forms.reply.handler', $config['forms']['reply']['handler']);
        $container->setParameter('fos_message.forms.reply.name', $config['forms']['reply']['name']);
        $container->setParameter('fos_message.forms.reply.model', $config['forms']['reply']['model']);
    }

    /**
     * @param Loader\YamlFileLoader $loader
     * @param BridgeManager $manager
     * @param ContainerBuilder $container
     *
     * @throws RuntimeException
     */
    private function loadBridges(Loader\YamlFileLoader $loader, BridgeManager $manager, ContainerBuilder $container)
    {
        /*
         * FOSUserBundle
         */
        if ($manager->isEnabled('fos_user')) {
            $loader->load('bridges/fos_user.yml');

            if ('_default_' === $container->getParameter('fos_message.field_type.recipient')) {
                $container->setParameter('fos_message.field_type.recipient', 'fos_user_recipient');
            }
        } else {
            throw new RuntimeException(
                'You have to implement your own recipient field type (or use the FOSUserBundle bridge)'
            );
        }

        /*
         * KnpPaginatorBundle
         */
        if ($manager->isEnabled('knp_paginator')) {
            $loader->load('bridges/knp_paginator.yml');
        }
    }
}
