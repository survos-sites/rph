<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\Script;
use App\Repository\ScriptRepository;
use Survos\TablerBundle\Event\MenuEvent;
use Survos\TablerBundle\Menu\MenuBuilderTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class AppMenu
{
    use MenuBuilderTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ScriptRepository $scripts,
        protected readonly ?RouterInterface $router = null,
    ) {}

    #[AsEventListener(event: MenuEvent::NAVBAR_MENU)]
    public function navbar(MenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->add($menu, 'script_index', label: 'Scripts', icon: 'tabler:script');

        $playground = $this->addSubmenu($menu, 'Playground', icon: 'tabler:masks-theater');
        $this->add($playground, 'script_index', label: 'Choose a script', icon: 'tabler:list');
        $this->add($playground, 'survos_routes_sitemap', label: 'Route map', icon: 'tabler:route');
    }

    #[AsEventListener(event: MenuEvent::PAGE_NAV)]
    public function scriptNavigation(MenuEvent $event): void
    {
        if (null === $script = $this->currentScript()) {
            return;
        }

        $menu = $event->getMenu();
        $this->add($menu, 'script_overview', $script, 'Overview', icon: 'tabler:dashboard');
        $this->add($menu, 'script_show', $script, 'Full script', icon: 'tabler:book');
        $this->add($menu, 'script_scenes', $script, 'Scenes', icon: 'tabler:movie');
        $this->add($menu, 'script_characters', $script, 'Characters', icon: 'tabler:masks-theater');
        $this->add($menu, 'script_prompter', $script, 'Prompter', icon: 'tabler:presentation');
    }

    #[AsEventListener(event: MenuEvent::PAGE_ACTIONS)]
    public function scriptActions(MenuEvent $event): void
    {
        if (null !== $script = $this->currentScript()) {
            $this->add($event->getMenu(), 'script_source', $script, 'Download Fountain', icon: 'tabler:download');
        }
    }

    private function currentScript(): ?Script
    {
        $scriptId = $this->requestStack->getCurrentRequest()?->attributes->getString('scriptId');

        return '' === $scriptId ? null : $this->scripts->find($scriptId);
    }
}
