<?php

namespace Nealis\Identity\Entity;


use Nealis\EntityRepository\Entity\Field\Field;
use Nealis\Identity\Repository\KeyRepository;
use Nealis\Identity\Repository\RoleRepository;
use Nealis\EntityRepository\Entity\Entity;

/**
 * KeyRole Entity
 */
class KeyRole extends Entity
{
    public static $tableName = 'app_key_role';

    protected $uniqueKeys = [
        [
            [
                'name' => 'key_id'
            ],
            [
                'name' => 'role_id'
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
            'key_id' => [
                'required' => true,
                'columnName' => 'key_id',
                'label' => $this->translate('Key'),
                'type' => Field::TYPE_INTEGER,
                'nullable' => false,
                'resolve' => function($fieldName, $entity) {
                    /** @var Entity $entity */
                    $keyId = $entity->get('key_id');
                    /** @var KeyRepository $keyRepository */
                    $keyRepository = $this->entityManager['auth.key'];
                    $keyCode = $keyRepository->getCodeById($keyId);

                    if ($keyCode) $entity->set('key_code', $keyCode);
                }
            ],
            'key_code' => [
                'columnName' => 'key_code',
                'label' => $this->translate('Key'),
                'type' => Field::TYPE_STRING,
                'nullable' => false,
                'persist' => false,
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
            'authorized' => [
                'columnName' => 'authorized',
                'label' => $this->translate('Authorized'),
                'type' => Field::TYPE_INTEGER,
                'default' => 0,
                'nullable' => false,
            ],
            'locked' => [
                'columnName' => 'locked',
                'label' => $this->translate('Locked'),
                'type' => Field::TYPE_INTEGER,
                'default' => 0,
                'nullable' => false,
                'validators' => [
                    function(KeyRole $entity, $fieldName) {
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
                        'name' => 'key_id',
                        'fkName' => 'id',
                    ]
                ],
                $this->entityManager['auth.key']
            ),
            new ForeignKeyValidator(
                [
                    [
                        'name' => 'role_id',
                        'fkName' => 'id',
                    ]
                ],
                $this->entityManager['auth.role']
            )

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
