<?php

namespace FormaLibre\WorkspaceSubscriptionBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Security\PlatformRoles;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Symfony\Component\HttpFoundation\JsonResponse;

class WorkspaceSubscriptionController extends Controller
{
    /** @DI\Inject */
    private $request;

    /** @DI\Inject */
    private $logger;

    /** @DI\Inject("claroline.manager.user_manager") */
    private $userManager;

    /** @DI\Inject("claroline.manager.workspace_manager") */
    private $workspaceManager;

    /** @DI\Inject("%claroline.param.templates_directory%") */
    private $templateDir;
    
    /** @DI\Inject("%claroline.manager.mail_manager%") */
    private $mailManager;

    /** @DI\Inject("doctrine.orm.entity_manager") */
    private $em;

    /**
     * @EXT\Route("/create", name="formalibre_workspacesubscription_create")
     * @EXT\Template
     *
     * @return Response
     */
    public function createAction()
    {
        $postData = $this->request->request;
        $this->logger->debug('Creating workspace...');
        $payload = $this->decrypt($postData->get('payload'));
        $this->logger->debug($payload);
        $data = json_decode($payload);
        $userData = $data->user;
        $workspaceData = $data->workspace;
        //this is ok until here !
        $user = $this->userManager->getOneUserByUsername($userData->username);

        if (!$user) {
            $user = new User();
            $user->setFirstName($userData->first_name);
            $user->setLastName($userData->last_name);
            $user->setUsername($userData->username);
            $user->setPassword(uniqid());
            $user->setMail($userData->email);
            $user = $this->userManager->createUserWithRole($user, PlatformRoles::USER);
            $this->mailManager->sendForgotPassword($user);
        }

        $config = Configuration::fromTemplate(
            $this->templateDir . '/default.zip'
        );

        //maybe change the uniqid ?
        $config->setWorkspaceName(uniqid());
        $config->setWorkspaceCode(uniqid());
        $config->setDisplayable(true);
        $config->setSelfRegistration(false);
        $config->setRegistrationValidation(true);
        $config->setSelfUnregistration(false);
        $config->setWorkspaceDescription('');
        $workspace = $this->workspaceManager->create($config, $user);
        $workspace->setMaxUsers($workspaceData->max_user);
        $workspace->setMaxStorageSize($workspaceData->max_storage);
        $workspace->setMaxUploadResources($workspaceData->max_resource);
        $expDate = new \DateTime();
        $expDate->setTimeStamp($workspaceData->expiration_date);
        $workspace->setEndDate($expDate);
        $this->em->persist($workspace);
        $this->em->flush();
        $returnData = array();

        return new JsonResponse(array(
            'code' => '200',
            'workspace' => $this->workspaceManager->toArray($workspace)
        ));
    }

    /**
     * @EXT\Route("/workspace/{workspace}/exp_date/increase", name="formalibre_workspacesubscription_increase_exp_date")
     * @EXT\Template
     *
     * @return Response
     */
    public function increaseExpirationDateAction(Workspace $workspace)
    {
        $postData = $this->request->request;
        $payload = $this->decrypt($postData->get('payload'));
        $data = json_decode($payload);
        $timestamp = $data->expiration_date;
        $expDate = new \DateTime();
        $expDate->setTimeStamp($timestamp);
        $workspace->setEndDate($expDate);
        $this->em->persist($workspace);
        $this->em->flush();
        $returnData = array();

        return new JsonResponse(array(
            'code' => '200',
            'workspace' => $this->workspaceManager->toArray($workspace)
        ));
    }

    /**
     * @EXT\Route("/workspace/{workspace}", name="formalibre_workspacesubscription_get_workspace")
     * @EXT\Template
     *
     * @return Response
     */
    public function getWorkspaceAction(Workspace $workspace)
    {
        return new JsonResponse($this->workspaceManager->toArray($workspace));
    }

    private function decrypt($payload)
    {
        if (!$this->container->getParameter('formalibre_decrypt')) return $payload;
        $ciphertextDec = base64_decode($payload);
        $ivSize = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_192, MCRYPT_MODE_CBC);
        $ivDec = substr($ciphertextDec, 0, $ivSize);
        $ciphertextDec = substr($ciphertextDec, $ivSize);
        $secret = $this->container->getParameter('formalibre_encryption_secret_validation');
        $key = pack('H*', $secret);
        $plainTextDec = mcrypt_decrypt(
            MCRYPT_RIJNDAEL_192,
            $key,
            $ciphertextDec,
            MCRYPT_MODE_CBC,
            $ivDec
        );

        return $plainTextDec;
    }
}
