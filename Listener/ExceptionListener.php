<?php

namespace FormaLibre\WorkspaceSubscriptionBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerAware;
use Doctrine\ORM\Event\OnFlushEventArgs;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Claroline\CoreBundle\Controller\Exception\WorkspaceAccessDeniedException;

/**
 * @DI\Service
 */
class ExceptionListener
{

    /**
     * @DI\InjectParams({
     *     "templating" = @DI\Inject("templating"),
     *     "sc"         = @DI\Inject("security.context"),
     *     "ch"         = @DI\Inject("claroline.config.platform_config_handler")
     * })
     */
    public function __construct($templating, $sc, $ch)
    {
        $this->sc = $sc;
        $this->templating = $templating;
        $this->ch = $ch;
    }

    /**
     * @DI\Observe("kernel.exception")
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // We get the exception object from the received event
        $exception = $event->getException();
        $prev = $exception->getPrevious();(get_class($exception->getPrevious()));

        if ($prev instanceof WorkspaceAccessDeniedException) {
            $workspace = $prev->getWorkspace();
            $now = new \DateTime();
            $now = $now->getTimeStamp();

            if ($workspace->getEndDate()->getTimeStamp() < $now) {
                $user = $this->sc->getToken()->getUser();
                $url = $this->ch->getParameter('formalibre_commercial_url') . "/invoice/workspace/renew/test/workspace/{$workspace->getId()}";
                $content = $this->templating->render(
                    'FormaLibreWorkspaceSubscriptionBundle:exceptions:workspace_expired.html.twig',
                    array('user' => $user, 'url' => $url)
                );
                $response = new Response($content);
                $event->setResponse($response);
            }

        }
    }
}
