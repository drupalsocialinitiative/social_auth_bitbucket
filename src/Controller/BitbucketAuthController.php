<?php

namespace Drupal\social_auth_bitbucket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_bitbucket\BitbucketAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Simple Bitbucket Connect module routes.
 */
class BitbucketAuthController extends ControllerBase {

    /**
     * The network plugin manager.
     *
     * @var \Drupal\social_api\Plugin\NetworkManager
     */
    private $networkManager;

    /**
     * The user manager.
     *
     * @var \Drupal\social_auth\SocialAuthUserManager
     */
    private $userManager;

    /**
     * The bitbucket authentication manager.
     *
     * @var \Drupal\social_auth_bitbucket\BitbucketAuthManager
     */
    private $bitbucketManager;

    /**
     * Used to access GET parameters.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    private $request;

    /**
     * The Social Auth Data Handler.
     *
     * @var \Drupal\social_auth\SocialAuthDataHandler
     */
    private $dataHandler;

    /**
     * BitbucketAuthController constructor.
     *
     * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
     *   Used to get an instance of social_auth_bitbucket network plugin.
     * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
     *   Manages user login/registration.
     * @param \Drupal\social_auth_bitbucket\BitbucketAuthManager $bitbucket_manager
     *   Used to manage authentication methods.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request
     *   Used to access GET parameters.
     * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
     *   SocialAuthDataHandler object.
     */
    public function __construct(NetworkManager $network_manager,
      SocialAuthUserManager $user_manager,
      BitbucketAuthManager $bitbucket_manager,
      RequestStack $request,
      SocialAuthDataHandler $data_handler) {

        $this->networkManager = $network_manager;
        $this->userManager = $user_manager;
        $this->bitbucketManager = $bitbucket_manager;
        $this->request = $request;
        $this->dataHandler = $data_handler;

        // Sets the plugin id.
        $this->userManager->setPluginId('social_auth_bitbucket');

        // Sets the session keys to nullify if user could not logged in.
        $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
          $container->get('plugin.network.manager'),
          $container->get('social_auth.user_manager'),
          $container->get('social_auth_bitbucket.manager'),
          $container->get('request_stack'),
          $container->get('social_auth.data_handler')
        );
    }

    /**
     * Response for path 'user/login/bitbucket'.
     *
     * Redirects the user to Bitbucket for authentication.
     */
    public function redirectToBitbucket() {
        /* @var \Stevenmaguire\OAuth2\Client\Provider\Bitbucket|false $bitbucket */
        $bitbucket = $this->networkManager->createInstance('social_auth_bitbucket')->getSdk();

        // If bitbucket client could not be obtained.
        if (!$bitbucket) {
            drupal_set_message($this->t('Social Auth Bitbucket not configured properly. Contact site administrator.'), 'error');
            return $this->redirect('user.login');
        }

        // Bitbucket service was returned, inject it to $bitbucketManager.
        $this->bitbucketManager->setClient($bitbucket);

        // Generates the URL where the user will be redirected for Bitbucket login.
        // If the user did not have email permission granted on previous attempt,
        // we use the re-request URL requesting only the email address.
        $bitbucket_login_url = $this->bitbucketManager->getAuthorizationUrl();

        $state = $this->bitbucketManager->getState();

        $this->dataHandler->set('oauth2state', $state);

        return new TrustedRedirectResponse($bitbucket_login_url);
    }

    /**
     * Response for path 'user/login/bitbucket/callback'.
     *
     * Bitbucket returns the user here after user has authenticated in Bitbucket.
     */
    public function callback() {
        /* @var \League\OAuth2\Client\Provider\Bitbucket|false $bitbucket */
        $bitbucket = $this->networkManager->createInstance('social_auth_bitbucket')->getSdk();

        // If Bitbucket client could not be obtained.
        if (!$bitbucket) {
            drupal_set_message($this->t('Social Auth Bitbucket not configured properly. Contact site administrator.'), 'error');
            return $this->redirect('user.login');
        }

        $state = $this->dataHandler->get('oauth2state');

        // Retreives $_GET['state'].
        $retrievedState = $this->request->getCurrentRequest()->query->get('state');
        if (empty($retrievedState) || ($retrievedState !== $state)) {
            $this->userManager->nullifySessionKeys();
            drupal_set_message($this->t('Bitbucket login failed. Unvalid OAuth2 State.'), 'error');
            return $this->redirect('user.login');
        }

        // Saves access token to session.
        $this->dataHandler->set('access_token', $this->bitbucketManager->getAccessToken());

        $this->bitbucketManager->setClient($bitbucket)->authenticate();

        // Gets user's info from Bitbucket API.
        /* @var \Stevenmaguire\OAuth2\Client\Provider\BitbucketResourceOwner $profile */
        if (!$profile = $this->bitbucketManager->getUserInfo()) {
            drupal_set_message($this->t('Bitbucket login failed, could not load Bitbucket profile. Contact site administrator.'), 'error');
            return $this->redirect('user.login');
        }

        // Gets (or not) extra initial data.
        $data = $this->userManager->checkIfUserExists($profile->getId()) ? NULL : $this->bitbucketManager->getExtraDetails();

        // If user information could be retrieved.
        return $this->userManager->authenticateUser($profile->getName(), 'no-email@example.com', $profile->getId(), $this->bitbucketManager->getAccessToken(), $profile->toArray()['avatar_url'], $data);
    }

}
