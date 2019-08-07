<?php

namespace Nealis\Identity\Entity;


use Nealis\EntityRepository\Entity\Entity;
use Nealis\EntityRepository\Entity\Field\Field;

/**
 * User Entity
 */
class User extends Entity
{
    public static $tableName = 'app_user';

    protected $uniqueKeys = [
        [
            [
                'name' => 'username'
            ],
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
                'required' => true,
                'columnName' => 'username',
                'label' => $this->translate('Username'),
                'type' => Field::TYPE_STRING,
                'length' => 50,
                'nullable' => false,
            ],
            'password' => [
                'columnName' => 'password',
                'type' => Field::TYPE_STRING,
                'length' => 100,
            ],
            'confirm_password' => [
                'type' => Field::TYPE_STRING,
                'length' => 100,
                'persist' => false,
            ],
            'signature' => [
                'columnName' => 'signature',
                'label' => $this->translate('Signature'),
                'type' => Field::TYPE_STRING,
                'length' => 50,
                'nullable' => false,
            ],
            'is_active' => [
                'columnName' => 'is_active',
                'label' => $this->translate('Is Active'),
                'type' => Field::TYPE_INTEGER,
                'default' => 0,
                'nullable' => false,
            ],
            'locale' => [
                'required' => true,
                'columnName' => 'locale',
                'label' => $this->translate('Locale'),
                'type' => Field::TYPE_STRING,
                'default' => '',
                'nullable' => false,
            ],
            'email' => [
                'columnName' => 'email',
                'label' => $this->translate('Email'),
                'type' => Field::TYPE_STRING,
                'length' => 100,
                'nullable' => false,
            ],
        ];

        $this->setValidators([
            // Password
            function($entity, $fieldName = null) {
                /** @var Entity $entity */
                $errors = [];
                if ($fieldName === null || in_array($fieldName, ['password','confirm_password'])) {
                    $password = $entity->get('password');
                    $confirm_password = $entity->get('confirm_password');
                    if ($password === $confirm_password) {
                        if (empty($password) && empty($confirm_password) && $this->isEmptyStoredIdentityData()) {
                            $errors[] = $this->translate('Password is mandatory');
                        }
                    } else {
                        $errors[] = $this->translate('Passwords don\'t match');
                    }
                }

                return $errors;
            },
            // Email
            function($entity, $fieldName = null) {
                $errors = [];
                if ($fieldName === 'email' || $fieldName === null) {
                    $email = $this->get('email');
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = $this->translate('Invalid Email Address');
                    }
                }
                return $errors;
            }
        ]);
    }

    public function beforeInsert()
    {
        parent::beforeInsert();

        $userCount = $this->getEntityRepository()->readCount();
        $userLimit = $this->getEntityRepository()->getUserLimit();
        if ($userLimit > 0 && $userCount >= $userLimit) {
            throw new \Exception($this->translate('Cannot create User, maximum number of users exceeded'));
        }

        $password = $this->get('password');
        $this->set('password', password_hash($password, PASSWORD_BCRYPT), true, false);
    }


    public function beforeUpdate()
    {
        parent::beforeUpdate();
        $password = $this->get('password');

        if (empty($password)) {
            $fields = $this->getFields();
            unset($fields['password']);
            $this->setFields($fields);
        } else {
            $password = password_hash($password, PASSWORD_BCRYPT);
            $this->set('password', $password , true, false);
        }

        $this->updateRelatedRoles();
    }

    public function updateRelatedRoles()
    {
        $newUsername = $this->get('username');
        $oldUsername = $this->getPersistedUsername();

        if ($oldUsername && $newUsername !== $oldUsername) {
            $this->execUpdateRoles($newUsername, $oldUsername);
        }
    }

    public function execUpdateRoles($newUsername, $oldUsername)
    {
        /** @var UserRoleRepository $userRoleRepository */
        $userRoleRepository = $this->entityManager['auth.user_role'];
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $tableName = $userRoleRepository->getTableName();
        $queryBuilder->update($tableName)
            ->set('username', '?')
            ->where('username = ?')
            ->setParameters([$newUsername, $oldUsername])
            ->execute();
    }

    public function getPersistedUsername()
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->getEntityRepository();
        $userRow = $userRepository->readUserById($this->get('id'));
        if ($userRow) {
            return $userRow['username'];
        }
        return $userRow;
    }

    public function validateDelete()
    {
        $username = $this->get('username');

        $userRoleData = false;

        /** @var  $userRoleRepository */
        $userRoleRepository = $this->entityManager['auth.user_role'];
        if ($userRoleRepository !== null) {
            $userRoleData = $userRoleRepository->readByUser($username);
        }

        if ($userRoleData) {
            return [$this->translate('Cannot delete, it is being used by Users - Roles')];
        } else {
            return [];
        }
    }

    protected function afterSave()
    {
        parent::afterSave();
        if ($this->session) {
            $username = $this->session->get('auth/username');
            if ($this->get('username') == $username) {
                $this->session->set('auth/locale', $this->get('locale'));
                $this->session->set('app/locale', $this->get('locale'));
                $this->session->save();
            }
        }
    }

}
