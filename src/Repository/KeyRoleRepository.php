<?php

namespace Nealis\Identity\Repository;

use Doctrine\DBAL\Connection;
use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\EntityRepository;
use Nealis\Params\Params;
use Nealis\Result\Result;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class SecurityRepository
 */
class KeyRoleRepository extends EntityRepository
{
    /** @var  Session */
    protected $session;

    protected $defaultAuthorization = false;
    protected $allowIfEqualGrantedDenied = true;

    protected $entityClass = 'Nealis\Identity\Entity\KeyRole';

    protected $defaultSorters = [
        'key_code' => 'ASC',
        'role_code' => 'ASC'
    ];

    protected $stmt = "
        SELECT
            app_key_role.id,
            app_key_role.key_id,
            app_key_role.role_id,
            app_key_role.authorized, 
            app_key_role.locked,
            app_key.code as key_code,
            app_key.description as key_description,
            app_role.code as role_code,
            app_role.description as role_description
        FROM
            app_key_role
            left join app_key on app_key_role.key_id = app_key.id
            left join app_role on app_key_role.role_id = app_role.id
    ";

    /**
     * KeyRoleRepository constructor.
     * @param Connection $connection
     * @param Session $session
     */
    public function __construct(Connection $connection, Session $session)
    {
        $this->session = $session;
        parent::__construct($connection);
    }

    /**
     * @param array|Params $params
     * @return Params
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
     */
    public function getQueryFilters($filters)
    {
        $filters = new Params($filters);

        $authorized = 0;
        $filterByAuthorized = $filters->has('authorized');
        if ($filterByAuthorized) {
            $authorized = $filters->get('authorized', false);
            $filters->remove('authorized');
        }

        if ($filters->get('role_id')) {
            if ($filters->get('role_code')) {
                $filters->remove('role_code');
            }
            if ($filters->get('role_description')) {
                $filters->remove('role_description');
            }
        }

        /** @var Filter $newFilters */
        $newFilters = Filter::getInstance($filters, $this->defaultFilterRuleOperator)
            ->convertFilterRule('key_id', Rule::EQUALS)
            ->convertFilterRule('role_id', Rule::EQUALS);

        if ($filterByAuthorized) {
            $newFilters->addRule('authorized', Rule::EQUALS, $authorized);
        }

        return $newFilters;
    }

    /**
     * @param $id
     * @return bool|Entity
     * @throws \Exception
     */
    public function getKeyRoleById($id)
    {
        $security = $this->findOneBy([
            'id' => $id
        ]);

        return $security;
    }

    /**
     * @param $key
     * @param $role
     * @return bool|Entity
     * @throws \Exception
     */
    public function getKeyRoleByKeyAndRole($key, $role)
    {
        return $this->findOneBy([
            'key_id' => $key,
            'role_id' => $role
        ]);
    }

    /**
     * @param $key
     * @param $role
     * @return bool|mixed|null
     */
    public function getIdByKeyAndRole($key, $role)
    {
        $security = $this->getKeyRoleByKeyAndRole($key, $role);

        if ($security) {
            return $security['id'];
        } else {
            return false;
        }
    }

    /**
     * @param string|array $keys
     * @return bool
     * @description check if action is granted by user defined keys
     */
    public function isGranted($keys)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $roles = $this->session->get('security/roles', []);
        $isGranted = $this->areRolesGranted($roles, $keys);

        return $isGranted;
    }

    /**
     * @param $roles
     * @param $keys
     * @return bool|mixed|null|string
     */
    public function areRolesGranted($roles, $keys)
    {
        $isGranted = $this->defaultAuthorization;

        foreach ($roles as $role) {
            $isGranted = $this->areKeysGranted($role, $keys);
            if ($isGranted == $this->allowIfEqualGrantedDenied) {
                return $isGranted;
            }
        }

        return $isGranted;
    }

    /**
     * @param $role
     * @param $keys
     * @return bool|mixed|null|string
     */
    public function areKeysGranted($role, $keys)
    {
        $keysGranted = true;

        foreach ($keys as $key) {
            $keysGranted = $this->isKeyGranted($role, $key);
            if (!$keysGranted) {
                return $keysGranted;
            }
        }

        return $keysGranted;
    }

    /**
     * @param $key
     * @param $role
     * @return bool
     */
    public function isKeyGranted($role, $key)
    {
        $keyGranted = $this->defaultAuthorization;
        $securityData = $this->session->get('security/keysroles');

        $securityKeys = array_filter($securityData, function($securityRow) use ($key, $role) {
            return $securityRow['key_code'] == $key && $securityRow['role_code'] == $role;
        });

        foreach ($securityKeys as $security) {
            return (bool) $security['authorized'];
        }

        return $keyGranted;
    }

    /**
     * @param $role
     * @return mixed
     */
    public function readByRole($role)
    {
        $query = $this->getReadQuery()
            ->andWhere('role_id = ?');

        $query = $this->prepareQuery($query, [$role]);

        return $this->read($query);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function readByKey($key)
    {
        $query = $this->getReadQuery()
            ->andWhere('key_id = ?');

        $query = $this->prepareQuery($query, [$key]);

        return $this->read($query);
    }

    /**
     * @param $roleIds
     * @return mixed
     */
    public function readByRoleIds($roleIds)
    {
        $query = $this->getReadQuery();

        if (empty($roleIds)) {
            return [];
        } else {
            $query->add('where', $query->expr()->in('role_id', $roleIds));

            return $this->read($query);
        }

    }

    public function initSecurity()
    {
        $username = $this->session->get('auth/username');
        $userRoleRepository = $this->entityManager['auth.user_role'];
        $userRoleIds = $userRoleRepository->getRoleIdsByUser($username);
        $roles = $userRoleRepository->getRoleCodesByUser($username);
        $securityData = $this->readByRoleIds($userRoleIds);
        $this->session->set('security/keysroles', $securityData);
        $this->session->set('security/roles', $roles);
    }

    /**
     * @param string $defaultAuthorization
     */
    public function setDefaultAuthorization($defaultAuthorization)
    {
        $this->defaultAuthorization = $defaultAuthorization;
    }

    /**
     * @return boolean
     */
    public function getDefaultAuthorization()
    {
        return $this->defaultAuthorization;
    }

    /**
     * @param string $allowIfEqualGrantedDenied
     */
    public function setAllowIfEqualGrantedDenied($allowIfEqualGrantedDenied)
    {
        $this->allowIfEqualGrantedDenied = $allowIfEqualGrantedDenied;
    }

    /**
     * @param $keyId
     * @param $roleId
     * @param bool|int $authorized
     * @return Result
     * @throws \Exception
     */
    public function createKeyRole($keyId, $roleId, $authorized = 0)
    {
        $keyRoleData = [
            'key_id' => $keyId,
            'role_id' => $roleId,
            'authorized' => $authorized,
        ];

        return $this->create($keyRoleData, true, true)->insert();
    }

    /**
     * @param $keyId
     * @param $roleId
     * @param int $authorized
     * @return Result
     * @internal param $key
     */
    public function createLockedKeyRole($keyId, $roleId, $authorized = 0)
    {
        $keyRole = $this->createKeyRole($keyId, $roleId, $authorized);

        if ($keyRole->isSuccess()) {
            $keyRoleData = $keyRole->getData();
            $keyRoleId = $keyRoleData['id'];

            try {
                $queryBuilder = $this->getConnection()->createQueryBuilder();
                $queryBuilder->update($this->getTableName())
                    ->set('locked', 1)
                    ->where('id = ?')
                    ->setParameter(0, $keyRoleId)
                    ->execute();
            } catch (\Exception $e) {
                $keyRole->setErrors([sprintf($this->translate('Cannot make record locked: %s'), $e->getMessage())]);
            }
        }

        return $keyRole;
    }
}
