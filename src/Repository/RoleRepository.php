<?php

namespace Nealis\Identity\Repository;

use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\EntityRepository;
use Nealis\Params\Params;
use Nealis\Result\Result;

/**
 * Class RoleRepository
 */
class RoleRepository extends EntityRepository
{
    protected $entityClass = 'Nealis\Identity\Entity\Role';

    protected $defaultSorters = [
        'code' => 'ASC'
    ];

    protected $stmt = "
       SELECT
          id,
          code,
          description,
          locked
        FROM app_role
    ";

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

        $codeOrDescription = $filters->get('code_or_description');
        if ($codeOrDescription) {
            $filters->remove('code_or_description');
        }

        /** @var Filter $newFilters */
        $newFilters = Filter::getInstance($filters, $this->defaultFilterRuleOperator)
            ->convertFilterRule('id', Rule::EQUALS);

        if ($codeOrDescription) {
            $codeOrDescriptionFilter = Filter::getInstance()
                ->setGlue(Filter::GLUE_OR)
                ->addRule('code', Rule::BEGINSWITH, $codeOrDescription)
                ->addRule('description', Rule::BEGINSWITH, $codeOrDescription);

            $newFilters->addGroup($codeOrDescriptionFilter);
        }

        return $newFilters;
    }

    /**
     * @param $id
     * @return bool|Entity
     * @throws \Exception
     */
    public function getRoleById($id)
    {
        $role = $this->findOneBy([
            'id' => $id
        ]);

        return $role;
    }

    /**
     * @param $code
     * @return bool|Entity
     * @throws \Exception
     */
    public function getRoleByCode($code)
    {
        $role = $this->findOneBy([
            'code' => $code
        ]);

        return $role;
    }

    /**
     * @param $code
     * @return mixed|null
     * @throws \Exception
     */
    public function getIdByCode($code)
    {
        $role = $this->getRoleByCode($code);

        if ($role) {
            return $role['id'];
        } else {
            return false;
        }
    }

    /**
     * @param $id
     * @return mixed|null
     * @throws \Exception
     */
    public function getCodeById($id)
    {
        $role = $this->getRoleById($id);

        if ($role) {
            return $role['code'];
        } else {
            return false;
        }
    }

    /**
     * @param $role
     * @return Result
     * @throws \Exception
     */
    public function createRole($role)
    {
        $adminRole = [
            'code' => $role,
            'description' => ucfirst($role),
        ];

        return $this->create($adminRole, true, true)->insert();
    }

    /**
     * @param $role
     * @return Result
     */
    public function createLockedRole($role)
    {
        $role = $this->createRole($role);

        if ($role->isSuccess()) {
            $roleData = $role->getData();
            $roleId = $roleData['id'];

            try {
                $queryBuilder = $this->getConnection()->createQueryBuilder();
                $queryBuilder->update($this->getTableName())
                    ->set('locked', 1)
                    ->where('id = ?')
                    ->setParameter(0, $roleId)
                    ->execute();
            } catch (\Exception $e) {
                $role->setErrors([sprintf($this->translate('Cannot make record locked: %s'), $e->getMessage())]);
            }
        }

        return $role;
    }
}
