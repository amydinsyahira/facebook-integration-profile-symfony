<?php
// src/Controller/MainController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;

class MainController extends AbstractController
{
    private $fb;
    private $fbHelper;

    public function __construct(private ContainerBagInterface $params, private UrlGeneratorInterface $router)
    {
        $this->fb = new Facebook([
            'app_id' => $this->params->get('app.fb.app_id'),
            'app_secret' => $this->params->get('app.fb.app_secret'),
            'default_graph_version' => $this->params->get('app.fb.app_version'),
        ]);
        $this->fbHelper = $this->fb->getRedirectLoginHelper();
        if(isset($_GET['state'])) {
            $this->fbHelper->getPersistentDataHandler()->set('state', $_GET['state']);
        }
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cbUrl = $this->router->generate('callback_integration', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $permissions = ['email', 'ads_management', 'ads_read']; // Optional permissions
        $loginUrl = $this->fbHelper->getLoginUrl($cbUrl, $permissions);

        return $this->render('base.html.twig', [
            'fbLoginUrl' => $loginUrl,
            'fbAccessToken' => $request->query->get('access_token'),
            'fbUserId' => $request->query->get('user_id'),
            'fbUserEmail' => $request->query->get('user_email'),
            'fbUserPic' => $request->query->get('user_pic'),
            'error' => $request->query->get('error'),
        ]);
    }

    #[Route('/integration/callback', name: 'callback_integration', methods: ['GET'])]
    public function cb_integration(): RedirectResponse 
    {   
        try {
            $accessToken = $this->fbHelper->getAccessToken();
            if (! isset($accessToken)) {
                if ($this->fbHelper->getError()) {
                    return $this->redirectToRoute('index', [
                        'error' => 'Error: ' . $this->fbHelper->getError(),
                    ]);
                } else {
                    return $this->redirectToRoute('index', [
                        'error' => 'Bad request',
                    ]);
                }
            }
        } catch (FacebookResponseException $e) {
            // When Graph returns an error
            return $this->redirectToRoute('index', [
                'error' => 'Graph returned an error: ' . $e->getMessage(),
            ]);
        }
        
        // The OAuth 2.0 client handler helps us manage access tokens
        $oAuth2Client = $this->fb->getOAuth2Client();
        
        if (! $accessToken->isLongLived()) {
            // Exchanges a short-lived access token for a long-lived one
            try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            } catch (FacebookSDKException $e) {
                return $this->redirectToRoute('index', [
                    'error' => 'Error getting long-lived access token: ' . $e->getMessage(),
                ]);
            }
        }

        try {
            $profile = $this->fb->get('/me?fields=id,email,picture', $accessToken->getValue());
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            return $this->redirectToRoute('index', [
                'error' => 'Graph returned an error: ' . $e->getMessage(),
            ]);
        } catch(FacebookSDKException $e) {
            return $this->redirectToRoute('index', [
                'error' => 'Facebook SDK returned an error: ' . $e->getMessage(),
            ]);
        }
          
        $user = $profile->getGraphUser();
        return $this->redirectToRoute('index', [
            'access_token' => $accessToken->getValue(),
            'user_id' => $user['id'],
            'user_email' => $user['email'],
            'user_pic' => $user['picture']['url'],
        ]);
    }
}
