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
 * Class UserRoleRepository
 */
class UserRoleRepository extends EntityRepository
{
    protected $entityClass = 'Nealis\Identity\Entity\UserRole';

    /** @var Session */
    protected $session;

    protected $defaultSorters = [
        'username' => 'ASC',
        'role_code' => 'ASC'
    ];

    protected $stmt = "
        SELECT
            app_user_role.id,
            app_user_role.username,
            app_user_role.role_id,
            app_user_role.locked,
            app_role.code as role_code,
            app_role.description as role_description
        FROM
            app_user_role
            left join app_role on app_user_role.role_id = app_role.id
    ";

    public function __construct(Connection $connection, Session $session)
    {
        $this->session = $session;
        parent::__construct($connection);
    }

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
    public function getQueryFilters($filters)
    {
        $filters = new Params($filters);

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
            ->convertFilterRule('role_id', Rule::EQUALS);

        return $newFilters;
    }

    /**
     * @param $roles
     * @return bool
     * @description Check whether at least one Role exists in session (security/roles)
     */
    public function inRoles($roles)
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $userRoles = $this->session->get('security', ['roles' => []])['roles'];

        return empty(array_intersect($roles, $userRoles)) ? false : true;
    }

    /**
     * @param $id
     * @return bool|Entity
     * @throws \Exception
     */
    public function getUserRoleById($id)
    {
        $userRole = $this->findOneBy([
            'id' => $id
        ]);

        return $userRole;
    }

    /**
     * @param $user
     * @param $role
     * @return bool|Entity
     * @throws \Exception
     */
    public function getUserRoleByUserAndRole($user, $role)
    {
        return $this->findOneBy([
            'username' => $user,
            'role_id' => $role
        ]);
    }

    /**
     * @param $user
     * @return array
     * @throws \Exception
     */
    public function getRoleIdsByUser($user)
    {
        $userRoles = $this->readByUser($user);
        return array_column($userRoles, 'role_id');
    }

    /**
     * @param $user
     * @return array
     * @throws \Exception
     */
    public function getRoleCodesByUser($user)
    {
        $userRoles = $this->readByUser($user);
        return array_column($userRoles, 'role_code');
    }

    /**
     * @param $roleIds
     * @return mixed
     * @throws \Exception
     */
    public function readByRoleIds($roleIds)
    {
        if (empty($roleIds)) $roleIds = '""';
        $query = $this->getReadQuery();
        $query->add('where', $query->expr()->in('role_id', $roleIds));

        return $this->read($query);
    }

    /**
     * @param $roleIds
     * @return array
     * @throws \Exception
     */
    public function getRoleCodesByRoleIds($roleIds)
    {
        $userRoles = $this->readByRoleIds($roleIds);
        return array_column($userRoles, 'role_code');
    }

    /**
     * @param $role
     * @return mixed
     * @throws \Exception
     */
    public function readByRole($role)
    {
        $query = $this->getReadQuery()
            ->andWhere('role_id = ?');

        $query = $this->prepareQuery($query, [$role]);

        return $this->read($query);
    }

    /**
     * @param $user
     * @return mixed
     * @throws \Exception
     */
    public function readByUser($user)
    {
        $query = $this->getReadQuery()
            ->andWhere('username = ?');

        $query = $this->prepareQuery($query, [$user]);

        return $this->read($query);
    }

    /**
     * @param $user
     * @param $roleId
     * @return Result
     * @throws \Exception
     */
    public function createUserRole($user, $roleId)
    {
        $userRoleData = [
            'username' => $user,
            'role_id' => $roleId,
        ];

        return $this->create($userRoleData, true, true)->insert();
    }

    /**
     * @param $user
     * @param $roleId
     * @return Result
     * @throws \Exception
     * @internal param $key
     */
    public function createLockedUserRole($user, $roleId)
    {
        $userRole = $this->createUserRole($user, $roleId);

        if ($userRole->isSuccess()) {
            $userRoleData = $userRole->getData();
            $userRoleId = $userRoleData['id'];

            try {
                $queryBuilder = $this->getConnection()->createQueryBuilder();
                $queryBuilder->update($this->getTableName())
                    ->set('locked', 1)
                    ->where('id = ?')
                    ->setParameter(0, $userRoleId)
                    ->execute();
            } catch (\Exception $e) {
                $userRole->setErrors([sprintf($this->translate('Cannot make record locked: %s'), $e->getMessage())]);
            }
        }

        return $userRole;
    }
}
