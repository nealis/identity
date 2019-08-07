<?php

namespace Nealis\Identity\Repository;

use Nealis\EntityRepository\Entity\EntityRepository;

/**
 * Class ResetPasswordRepository
 */
class ResetPasswordRepository extends EntityRepository
{
    protected $entityClass = 'Nealis\Identity\Entity\ResetPassword';

    protected $defaultSorters = [
        'id' => 'ASC'
    ];

    protected $stmt = "
        SELECT 
            reset_password.id as id,
            reset_key,
            active,
            user_id,
            reset_date,
            username,
            signature
        FROM reset_password
        LEFT JOIN app_user on reset_password.user_id = app_user.id
    ";

    public function readActivePasswordResetRequestByResetKeyAndUserId($resetKey, $userId)
    {
        return $this->readOneBy([
            'active' => 'Y',
            'reset_key' => $resetKey,
            'user_id' => $userId,
        ]);
    }

    public function readActivePasswordResetRequestByResetKey($resetKey)
    {
        return $this->readOneBy([
            'active' => 'Y',
            'reset_key' => $resetKey,
        ]);
    }

    public function deactivateResetPasswordRequest($id)
    {
        $entity = $this->findOneBy(['id' => $id]);
        if (!$entity) {
            throw new \Exception(sprintf($this->translate('Reset password request with id %s not found.')), $id);
        }
        $entity->set('active', 'N');
        return $entity->save();
    }

    public function createNewRequestByUserId($userId)
    {
        $resetPassword = $this->create([
            'user_id' => $userId,
            'reset_key' => md5(uniqid(rand(), true)),
            'active' => 'Y',
            'reset_date' => date('Y-m-d'),
        ]);
        return $resetPassword->save();
    }
}
