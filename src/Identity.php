<?php

namespace Nealis\Identity;

use Nealis\Identity\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Identity
{
    const DEFAULT_USERNAME = 'debug';

    /** @var  SessionInterface */
    protected $session;

    /** @var bool */
    protected $authCheck = true;

    /** @var bool  */
    protected $cli = false;

    /** @var bool  */
    protected $phpOs = PHP_OS;

    /** @var  string */
    protected $defaultUser = self::DEFAULT_USERNAME;

    /** @var  string */
    protected $appLocale;

    /** @var  bool */
    protected $useAppLocale = false;

    /** @var  UserRepositoryInterface */
    protected $userRepository;

    /**
     * Identity constructor.
     * @param SessionInterface $session
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(SessionInterface $session, UserRepositoryInterface $userRepository)
    {
        $this->session = $session;
        $this->userRepository = $userRepository;
    }

    /**
     * @param $username
     * @param $password
     * @return bool
     */
    public function login($username, $password)
    {
        $storedPassword = $this->userRepository->getPasswordByUsername($username);

        if ($storedPassword && password_verify($password, $storedPassword)) {
            $this->session->set('app/username', $username);
            $this->saveSession();
            return true;
        } else {
            return false;
        }
    }

    public function invalidateSession()
    {
        $this->session->clear();
        $this->saveSession();
    }

    /**
     * @param string $user
     * @return bool
     */
    public function initialize($user = '')
    {
        $user = !empty($user) ? $user : $this->defaultUser;

        $hasIdentity = ($this->isLoggedIn() || !$this->authCheck) && $this->hasIdentity();

        if (!$hasIdentity) {
            if ($this->cli) {
                $hasIdentity = $this->initCli($user);
            } else {
                $hasIdentity = $this->authCheck ? $this->init() : $this->initCli($user);
            }
        }
        if ($hasIdentity) {
            if ($this->phpOs === 'AIX') $this->setLibraryList();
        }

        return $hasIdentity;
    }

    /**
     * @return bool
     */
    protected function init()
    {
        $hasIdentity = false;

        if ($this->isLoggedIn()) {
            $hasIdentity = $this->initCli($this->getUsername());
        } else {
            $this->invalidateSession();
        }

        $this->saveSession();

        return $hasIdentity;
    }

    /**
     * @param string $username
     * @return bool
     */
    protected function initCli($username = self::DEFAULT_USERNAME)
    {
        $this->initSession($username);
        $this->session->set('auth/identity', true);
        return $this->hasIdentity();
    }

    /**
     * @param string $username
     * @return array
     */
    protected function getSessionData($username = self::DEFAULT_USERNAME)
    {
        return [
            'userid' => $this->userRepository->getIdByUsername($username) ? : 0,
            'username' => $username,
            'signature' => $this->userRepository->getSignatureByUsername($username) ? : '',
            'locale' => $this->getUserLocale($username),
        ];
    }

    /**
     * @param $username
     * @return string
     */
    protected function getUserLocale($username)
    {
        return $this->userRepository->getLocaleByUsername($username) ? : $this->appLocale;
    }

    /**
     * @param string $username
     */
    protected function initSession($username = self::DEFAULT_USERNAME)
    {
        $data = $this->getSessionData($username);
        $this->initSessionData($data);
        $this->initAppSessionData($username);
    }

    /**
     * @param $data
     */
    protected function initSessionData($data)
    {
        $this->session->set('auth', $data);
        $this->saveSession();
    }

    /**
     * @param $username
     */
    protected function initAppSessionData($username)
    {
        $appLocale = $this->useAppLocale ? $this->appLocale : $this->getUserLocale($username);
        $this->session->set('app/locale', $appLocale);
        $this->saveSession();
    }

    protected function saveSession()
    {
        $this->session->save();
    }

    /**
     * @return bool
     */
    public function hasIdentity()
    {
        return $this->session->get('auth/identity') ? true : false;
    }

    /**
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->getUsername() ? true : false;
    }

    /**
     * @return string|null
     */
    public function getUsername()
    {
        return $this->session->get('app/username');
    }

    /**
     * @return string|null
     */
    public function getSysinf()
    {
        return $this->session->get('auth/sysinf');
    }

    /**
     * @param boolean $authCheck
     */
    public function setAuthCheck($authCheck)
    {
        $this->authCheck = $authCheck;
    }

    /**
     * @param boolean $cli
     */
    public function setCli($cli)
    {
        $this->cli = $cli;
    }

    /**
     * @param boolean $phpOs
     */
    public function setPhpOs($phpOs)
    {
        $this->phpOs = $phpOs;
    }

    /**
     * @return bool
     */
    public function getPhpOs()
    {
        return $this->phpOs;
    }

    /**
     * @param string $defaultUser
     */
    public function setDefaultUser($defaultUser)
    {
        $this->defaultUser = $defaultUser;
    }

    /**
     * @param string $appLocale
     */
    public function setAppLocale($appLocale)
    {
        $this->appLocale = $appLocale;
    }

    /**
     * @param boolean $useAppLocale
     */
    public function setUseAppLocale($useAppLocale)
    {
        $this->useAppLocale = $useAppLocale;
    }
}
