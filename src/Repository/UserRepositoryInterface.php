<?php


namespace Nealis\Identity\Repository;


interface UserRepositoryInterface
{
    public function getPasswordByUsername($username);
    public function getIdByUsername($username);
    public function getSignatureByUsername($username);
    public function getLocaleByUsername($username);
}
