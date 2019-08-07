<?php

namespace Nealis\Identity\Entity;


use Nealis\EntityRepository\Data\Validator\ForeignKeyValidator;
use Nealis\EntityRepository\Entity\Entity;
use Nealis\Identity\Repository\RoleRepository;
use Nealis\EntityRepository\Entity\Field\Field;

/**
 * Security Entity
 */
class UserRole extends Entity
{
    public static $tableName = 'app_user_role';

    protected $uniqueKeys = [
        [
            [
                'name' => 'username',
            ],
            [
                'name' => 'role_id',
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
            'username' => [
                'columnName' => 'username',
                'label' => $this->translate('Username'),
                'type' => Field::TYPE_STRING,
                'nullable' => false,
            ],
            'role_id' => [
                'required' => true,
                'columnName' => 'role_id',
                'label' => $this->translate('Role'),
                'type' => Field::TYPE_INTEGER,
                'nullable' => false,
                'resolve' => function($fieldName, $entity) {
                    /** @var Entity $entity */
                    $roleId = $entity->get('role_id');
                    /** @var RoleRepository $roleRepository */
                    $roleRepository = $this->entityManager['auth.role'];
                    $roleCode = $roleRepository->getCodeById($roleId);

                    if ($roleCode) $entity->set('role_code', $roleCode);
                }
            ],
            'role_code' => [
                'columnName' => 'role_code',
                'label' => $this->translate('Role'),
                'type' => Field::TYPE_STRING,
                'nullable' => false,
                'persist' => false,
            ],
            'locked' => [
                'columnName' => 'locked',
                'label' => $this->translate('Locked'),
                'type' => Field::TYPE_INTEGER,
                'default' => 0,
                'nullable' => false,
                'validators' => [
                    function(UserRole $entity, $fieldName) {
                        $errors = [];

                        $fieldValue = $entity->get($fieldName);
                        if ($fieldValue) {
                            $errors = [$this->translate('Record is locked')];
                        }

                        return $errors;
                    }
                ]
            ],
        ];

        $this->setValidators([
            new ForeignKeyValidator(
                [
                    [
                        'name' => 'username',
                        'fkName' => 'username',
                    ]
                ],
                $this->entityManager['auth.user']
            ),
            new ForeignKeyValidator(
                [
                    [
                        'name' => 'role_id',
                        'fkName' => 'id',
                    ]
                ],
                $this->entityManager['auth.role']
            ),
        ]);
    }

    public function validateDelete()
    {
        $locked = $this->get('locked');

        if ($locked) {
            return [$this->translate('Record is locked')];
        } else {
            return [];
        }
    }
}
