<?php

namespace app\controller;

class Profile
{
    /**
     * 当前用户资料
     */
    public function read()
    {
        return $this->queryParams();
    }
}
