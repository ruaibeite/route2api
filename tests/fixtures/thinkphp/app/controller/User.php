<?php

namespace app\controller;

class User
{
    /**
     * 用户登录
     * 使用账号密码登录。
     *
     * @param string username 用户名
     * @param string password 密码
     */
    public function login()
    {
        $data = $this->input();
        if ($error = $this->requireFields($data, ['username', 'password'])) {
            return $error;
        }
        $remember = $data['remember'] ?? false;
    }

    /**
     * 用户详情
     *
     * @param int id 用户ID
     */
    public function read()
    {
        $refresh = $this->request->get('refresh', false);
    }
}
