<?php

declare(strict_types=1);

/*
 * This file is part of Inline Flex.
 *
 * (c) Ryan <kwivix.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kwivix\FlexInline;

use Composer\Composer;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Flex\Configurator;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;
use Symfony\Flex\Lock;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    const RECIPE_MANIFEST = 'recipe-manifest';

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Configurator
     */
    private $configurator;

    /**
     * @var boolean
     */
    private $enabled = false;

    public function activate(Composer $composer, IOInterface $io)
    {
        foreach (array_merge($composer->getPackage()->getRequires() ?? [], $composer->getPackage()->getDevRequires() ?? []) as $link) {
            if ($link->getTarget() === 'kwivix/flexinline') {
                $this->enabled = true;
                break;
            }
        }

        $this->io = $io;

        $extra = $composer->getPackage()->getExtra();

        $options = array_merge([
            'bin-dir' => 'bin',
            'conf-dir' => 'conf',
            'config-dir' => 'config',
            'src-dir' => 'src',
            'var-dir' => 'var',
            'public-dir' => 'public',
            'root-dir' => $extra['symfony']['root-dir'] ?? '.',
        ], $extra);

        $options = new Options($options);

        $this->configurator = new Configurator($composer, $io, $options);
    }

    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'postPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'postPackageUpdate',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'postPackageUninstall',
        ];
    }

    public function postPackageInstall(PackageEvent $event)
    {
        $this->processPackageEvent($event);
    }

    public function postPackageUpdate(PackageEvent $event)
    {
        $this->processPackageEvent($event);
    }

    public function postPackageUninstall(PackageEvent $event)
    {
        $this->processPackageEvent($event);
    }

    private function recipeFromPackage(OperationInterface $operation, PackageInterface $package): ?Recipe
    {
        $name = $package->getName();
        $jobType = $operation->getJobType();
        $extra = $package->getExtra();

        $manifest = $extra[self::RECIPE_MANIFEST] ?? null;

        if ($manifest === null) {
            return null;
        }

        if ($manifest['copy-from-recipe'] ?? false) {
            $this->io->writeError(sprintf('<warning>Ignoring "copy-from-recipe" from package "%s"</warning>', $name));
            unset($manifest['copy-from-recipe']);
        }

        $data = [
            'manifest' => $manifest,
        ];

        return new Recipe($package, $name, $jobType, $data);
    }

    private function processPackageEvent(PackageEvent $event)
    {
        $operation = $event->getOperation();

        if ($operation instanceof UpdateOperation === true) {
            $package = $operation->getTargetPackage();
        } else {
            $package = $operation->getPackage();
        }

        $recipe = $this->recipeFromPackage($operation, $package);

        if ($recipe === null) {
            return;
        }

        if ($this->enabled === false) {
            $this->io->writeError('<warning>Inline recipes are disabled: "kwivix/flexinline" not found in the root composer.json</warning>');
            return;
        }

        $lock = new Lock(getenv('SYMFONY_LOCKFILE') ?: str_replace('composer.json', 'symfony.lock', Factory::getComposerFile()));

        if ($operation instanceof UninstallOperation === true) {
            $this->configurator->unconfigure($recipe, $lock);
        } else {
            $this->configurator->install($recipe, $lock);
        }
    }
}
