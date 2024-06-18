<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Common\Service\CommonService;

class AdminTopController extends Controller
{
    /**
     * 管理TOP
     * 
     * @param CommonService $commonService 共通部品
     */
    public function show(CommonService $commonService)
    {
        return view('top', ['user' => $commonService->getLoginUser()]);
    }
}
