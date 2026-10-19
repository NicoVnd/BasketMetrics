<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LoginNormalizationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST => ['normalize', 16]]; }
    public function normalize(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && $request->attributes->get('_route') === 'app_login' && $request->isMethod('POST')) {
            $request->request->set('_username', strtolower(trim($request->request->getString('_username'))));
        }
    }
}
