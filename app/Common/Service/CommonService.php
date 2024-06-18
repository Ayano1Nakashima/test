<?php

namespace App\Common\Service;

use Config\Consts;
use Illuminate\Support\Facades\Auth;
use App\Models\Environment;

class CommonService
{
    /**
     * ログインユーザー取得
     */
    public function getLoginUser()
    {
        // 現在の認証されたユーザーを取得
        $user = Auth::user();
        return $user->name;
    }

    /**
     * 環境変数取得
     * 
     * @return Environment 設定値
     */
    public function getEnvironment()
    {
        // 設定値を取得
        $data = Environment::all()->toArray();

        // 設定値を初期化
        $env = new Environment();
        $env->maxVisitorCount = (int)$data[0][Consts::ENVIRONMENT_VALUE];
        $env->vistPropose = explode(",", $data[1][Consts::ENVIRONMENT_VALUE]);
        $env->manager = explode(",", $data[2][Consts::ENVIRONMENT_VALUE]);

        return $env;
    }
}
