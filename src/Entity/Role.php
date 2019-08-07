<?php

namespace Nealis\Identity\Entity;


use Nealis\Identity\Repository\KeyRoleRepository;
use Nealis\Identity\Repository\UserRoleRepository;
use Nealis\EntityRepository\Entity\Field\Field;
use Nealis\EntityRepository\Entity\Entity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Role Entity
 */
class Role extends Entity
{
    public static $tableName = 'app_role';

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
                    function(Role $entity, $fieldName) {
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
        $role = $this->get('id');

        $locked = $this->get('locked');

        if ($locked) {
            return [$this->translate('Record is locked')];
        }

        $errors = [];
        /** @var KeyRoleRepository $keyRoleRepository */
        $keyRoleRepository = $this->entityManager['auth.key_role'];
        $securityData = $keyRoleRepository->readByRole($role);
        if ($securityData) {
            $errors[] = $this->translate('Cannot delete, it is being used by Keys - Roles');
        }

        /** @var UserRoleRepository $userRoleRepository */
        $userRoleRepository = $this->entityManager['auth.user_role'];
        $userRoleData = $userRoleRepository->readByRole($role);
        if ($userRoleData) {
            $errors = [$this->translate('Cannot delete, it is being used by Users - Roles')];
        }

        return $errors;
    }
}
