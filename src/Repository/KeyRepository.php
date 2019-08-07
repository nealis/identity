<?php

namespace Nealis\Identity\Repository;

use Nealis\EntityRepository\Data\Filter\Filter;
use Nealis\EntityRepository\Data\Filter\Rule;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\EntityRepository;
use Nealis\Params\Params;
use Nealis\Result\Result;

/**
 * Class KeyRepository
 */
class KeyRepository extends EntityRepository
{
    protected $entityClass = 'Nealis\Identity\Entity\Key';

    protected $defaultSorters = [
        'code' => 'ASC'
    ];

    protected $stmt = "
        SELECT
          id,
          code,
          description,
          locked
        FROM app_key
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
    public function getKeyById($id)
    {
        $key = $this->findOneBy([
            'id' => $id
        ]);

        return $key;
    }

    /**
     * @param $code
     * @return bool|Entity
     * @throws \Exception
     */
    public function getKeyByCode($code)
    {
        $key = $this->findOneBy([
            'code' => $code
        ]);

        return $key;
    }

    /**
     * @param $code
     * @return mixed|null
     * @throws \Exception
     */
    public function getIdByCode($code)
    {
        $key = $this->getKeyByCode($code);

        if ($key) {
            return $key['id'];
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
        $key = $this->getKeyById($id);

        if ($key) {
            return $key['code'];
        } else {
            return false;
        }
    }

    /**
     * @param $key
     * @return Result
     */
    public function createKey($key)
    {
        $keyData = [
            'code' => $key,
            'description' => ucfirst($key),
        ];

        return $this->create($keyData, true, true)->insert();
    }

    /**
     * @param $key
     * @return Result
     */
    public function createLockedKey($key)
    {
        $key = $this->createKey($key);

        if ($key->isSuccess()) {
            $keyData = $key->getData();
            $keyId = $keyData['id'];

            try {
                $queryBuilder = $this->getConnection()->createQueryBuilder();
                $queryBuilder->update($this->getTableName())
                    ->set('locked', 1)
                    ->where('id = ?')
                    ->setParameter(0, $keyId)
                    ->execute();
            } catch (\Exception $e) {
                $key->setErrors([sprintf($this->translate('Cannot make record locked: %s'), $e->getMessage())]);
            }
        }

        return $key;
    }
}
