<?php

namespace Nealis\Identity\Entity;


use Nealis\EntityRepository\Entity\Field\Field;
use Nealis\EntityRepository\Entity\Entity;

/**
 * ResetPassword Entity
 */
class ResetPassword extends Entity
{
    public static $tableName = 'reset_password';

    public function initFields()
    {
        $this->fields = [
            'id' => [
                'id' => true,
                'columnName' => 'id',
                'type' => Field::TYPE_INTEGER,
                'generated' => true
            ],
            'user_id' => [
                'required' => true,
                'columnName' => 'user_id',
                'label' => $this->translate('User Id'),
                'type' => Field::TYPE_INTEGER,
                'nullable' => false,
            ],
            'reset_key' => [
                'required' => true,
                'columnName' => 'reset_key',
                'label' => $this->translate('Reset Key'),
                'type' => Field::TYPE_STRING,
                'nullable' => false,
            ],
            'active' => [
                'required' => true,
                'columnName' => 'active',
                'label' => $this->translate('Active'),
                'type' => Field::TYPE_STRING,
                'length' => 1,
                'nullable' => false,
            ],
            'reset_date' => [
                'required' => true,
                'columnName' => 'reset_date',
                'label' => $this->translate('Role'),
                'type' => Field::TYPE_DATE,
                'nullable' => false,
            ],
        ];
    }
}
