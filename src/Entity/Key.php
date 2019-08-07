<?php

namespace Nealis\Identity\Entity;


use Nealis\Identity\Repository\KeyRoleRepository;
use Nealis\EntityRepository\Entity\Entity;

/**
 * Key Entity
 */
class Key extends Entity
{
    public static $tableName = 'app_key';

    protected $uniqueKeys = [
        [
            [
                'name' => 'code'
            ]
        ]
    ];

    public function initFields()
    {
        $this->fields = [
            'id' => [
                'id' => true,
                'columnName' => 'id',
                'type' => Field::TYPE_INTEGER,
                'generated' => true
            ],
            'code' => [
                'required' => true,
                'columnName' => 'code',
                'label' => $this->translate('Code'),
                'type' => Field::TYPE_STRING,
                'length' => 50,
                'nullable' => false,
            ],
            'description' => [
                'columnName' => 'description',
                'label' => $this->translate('Description'),
                'type' => Field::TYPE_STRING,
                'length' => 200,
            ],
            'locked' => [
                'columnName' => 'locked',
                'label' => $this->translate('Locked'),
                'type' => Field::TYPE_INTEGER,
                'length' => 1,
                'default' => 0,
                'nullable' => false,
                'validators' => [
                    function(Key $entity, $fieldName) {
                        $errors = [];

                        $fieldValue = $entity->get($fieldName);
                        if ($fieldValue) {
                            $errors = [$this->translate('Record is locked')];
                        }

                        return $errors;
                    }
                ]
            ]
        ];
    }

    public function validateDelete()
    {
        $key = $this->get('id');

        $locked = $this->get('locked');

        if ($locked) {
            return [$this->translate('Record is locked')];
        }

        /** @var KeyRoleRepository $keyRoleRepository */
        $keyRoleRepository = $this->entityManager['auth.key_role'];
        $securityData = $keyRoleRepository->readByKey($key);

        if ($securityData)
            return [$this->translate('Cannot delete, it is being used by Keys - Roles')];
        else
            return [];
    }
}
