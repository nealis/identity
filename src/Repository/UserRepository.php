<?php


namespace Nealis\Identity\Repository;

use Nealis\Identity\Entity\User;
use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\EntityRepository;
use Nealis\Params\Params;
use Nealis\Result\Result;

/**
 * Class UserRepository
 * @package Main\Repository
 */
class UserRepository extends EntityRepository implements UserRepositoryInterface
{
    /** @var string */
    protected $defaultAppLocale = '';

    protected $entityClass = 'Nealis\Identity\Entity\User';

    /** @var int $userLimit */
    protected $userLimit = 0;

    protected $defaultSorters = [
        'username' => 'ASC'
    ];

    protected $stmt = "
        SELECT
          id,
          username,
          '' as password,
          '' as confirm_password,
          is_active,
          locale,
          signature,
          email
        FROM app_user
    ";

    /**
     * @param array|Params $params
     * @return Params
     * @throws \Exception
     */
    public function getQueryParams($params)
    {
        $params = parent::getQueryParams($params);
        $params['filters'] = $this->getQueryFilters($params->get('filters', []));

        return $params;
    }

    /**
     * @param $filters
     * @return Filter
     * @throws \Exception
     */
    protected function getQueryFilters($filters)
    {
        $filters = new Params($filters);

        $usernameOrSignature = $filters->get('username_or_signature', false);
        if ($usernameOrSignature) {
            $filters->remove('username_or_signature');
        }

        $newFilters = Filter::getInstance($filters, $this->defaultFilterRuleOperator)
            ->convertFilterRule('id', Rule::EQUALS)
            ->convertFilterRule('signature', Rule::CONTAINS)
            ->convertFilterRule('is_active', Rule::EQUALS);

        //Username or Signature
        if ($usernameOrSignature) {
            $userOrSignatureFilter = Filter::getInstance()
                ->setGlue(Filter::GLUE_OR)
                ->addRule('username', Rule::BEGINSWITH, $usernameOrSignature)
                ->addRule('signature', Rule::CONTAINS, $usernameOrSignature);

            $newFilters->addGroup($userOrSignatureFilter);
        }

        return $newFilters;
    }

    /**
     * @param $id
     * @return array|false
     * @throws \Exception
     */
    public function readUserById($id)
    {
        $user = $this->readOneBy([
            'id' => $id
        ]);

        return $user;
    }

    /**
     * @param $id
     * @return bool|Entity
     * @throws \Exception
     */
    public function getUserById($id)
    {
        $user = $this->findOneBy([
            'id' => $id
        ]);

        return $user;
    }

    /**
     * @param $username
     * @return array|false
     */
    public function readUserByUsername($username)
    {
        return $this->readOneBy([
            'username' => $username,
        ]);
    }

    /**
     * @param $id
     * @return bool|mixed|null
     * @throws \Exception
     */
    public function readUsernameById($id)
    {
        $user = $this->readUserById($id);

        if ($user) {
            return $user['username'];
        } else {
            return false;
        }
    }

    /**
     * @param $id
     * @return bool|mixed|null
     * @throws \Exception
     */
    public function readSignatureById($id)
    {
        $user = $this->readUserById($id);

        if ($user) {
            return $user['signature'];
        } else {
            return false;
        }
    }

    /**
     * @param $username
     * @return bool|Entity
     * @throws \Exception
     */
    public function getUserByUsername($username)
    {
        $user = $this->findOneBy([
            'username' => $username
        ]);

        return $user;
    }

    /**
     * @param $username
     * @return bool|mixed|null
     */
    public function getIdByUsername($username)
    {
        $user = $this->readUserByUsername($username);

        if ($user && array_key_exists('id', $user)) {
            return $user['id'];
        } else {
            return false;
        }
    }

    /**
     * @param $username
     * @return bool|string
     */
    public function getSignatureByUsername($username) {
        $user = $this->readUserByUsername($username);

        if ($user && array_key_exists('signature', $user)) {
            return $user['signature'];
        } else {
            return false;
        }
    }

    /**
     * @param $id
     * @return bool|mixed|null
     * @throws \Exception
     */
    public function getUsernameById($id)
    {
        $user = $this->getUserById($id);

        if ($user) {
            return $user->get('username');
        } else {
            return false;
        }
    }

    /**
     * @param $username
     * @param int $isActive
     * @return string
     */
    public function getPasswordByUsername($username, $isActive = 1)
    {
        $query = $this->getConnection()->createQueryBuilder()->select('password')
            ->from('app_user')
            ->andWhere('username = ?')
            ->andWhere('is_active = ?');
        $query = $this->prepareQuery($query, [$username, $isActive]);
        $row = $this->readRow($query);

        return $row['password'] ? $row['password'] : '';
    }

    /**
     * @param $username
     * @return bool|mixed|null
     */
    public function getLocaleByUsername($username)
    {
        $user = $this->readUserByUsername($username);

        if ($user) {
            return $user['locale'];
        } else {
            return false;
        }
    }

    /**
     * @param $username
     * @return mixed
     */
    public function readByUsername($username)
    {
        return $this->readOneBy(['username' => $username]);
    }

    /**
     * @param $user
     * @param $password
     * @return Result
     * @throws \Exception
     */
    public function createUser($user, $password)
    {
        $userData = [
            'username' => $user,
            'password' => $password,
            'confirm_password' => $password,
            'is_active' => 1,
            'locale' => $this->defaultAppLocale
        ];

        return $this->create($userData, true, true)->insert();
    }

    /**
     * @param $userId
     * @param $currentPassword
     * @param $newPassword
     * @param $confirmPassword
     * @return Result
     * @throws \Exception
     */
    public function changePassword($userId, $currentPassword, $newPassword, $confirmPassword)
    {
        $result = new Result();

        $username = $this->readUsernameById($userId);
        //Check old password
        $oldPassword = $this->getPasswordByUsername($username);

        if (password_verify($currentPassword, $oldPassword)) {
            /** @var User $user */
            $user = $this->getUserByUsername($username);

            if ($user) {
                $user->set('password', $newPassword);
                $user->set('confirm_password', $confirmPassword);
                $result = $user->save();
            } else {
                $result->addError($this->translator->trans('User does not exists'));
            }
        } else {
            $result->addError($this->translator->trans('Current password does not match'));
        }

        return $result;
    }

    /**
     * @param string $defaultAppLocale
     */
    public function setDefaultAppLocale($defaultAppLocale)
    {
        $this->defaultAppLocale = $defaultAppLocale;
    }

    /**
     * @return int
     */
    public function getUserLimit(){
        return $this->userLimit;
    }

    /**
     * @param $userLimit
     */
    public function setUserLimit($userLimit){
        $this->userLimit = intval($userLimit);
    }
}
