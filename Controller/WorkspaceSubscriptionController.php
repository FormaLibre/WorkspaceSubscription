<?php

namespace FormaLibre\WorkspaceSubscriptionBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;

class WorkspaceSubscriptionController extends Controller
{
    /**
     * @EXT\Route("/index", name="formalibre_workspacesubscription_index")
     * @EXT\Template
     *
     * @return Response
     */
    public function indexAction()
    {
        throw new \Exception('hello');
    }
}
