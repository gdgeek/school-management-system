<?php

declare(strict_types=1);

namespace App\Model;

/**
 * User Model
 * 
 * 用户模型，对应user表（只读）
 */
class User
{
    public int $id;
    public string $username;
    public ?string $nickname = null;
    public ?string $avatar = null;

    public static function tableName(): string
    {
        return 'user';
    }

    public static function fromArray(array $data): self
    {
        $model = new self();
        
        if (isset($data['id'])) $model->id = (int)$data['id'];
        if (isset($data['username'])) $model->username = $data['username'];
        if (isset($data['nickname'])) $model->nickname = $data['nickname'];
        if (isset($data['avatar'])) $model->avatar = $data['avatar'];
        
        return $model;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
        ];
    }
}
