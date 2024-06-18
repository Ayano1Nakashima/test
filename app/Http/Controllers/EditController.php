<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Common\Service\CommonService;
use Config\Consts;
use App\Models\AccessHistory;
use stdClass;

class EditController extends Controller
{
    /**
     * 編集画面表示
     *
     * @param CommonService $commonService 共通部品
     * @param Int $id id
     * @return View ビューレスポンス
     */
    public function show(CommonService $commonService, Int $id)
    {
        $data = AccessHistory::findOrFail($id);
        $dispData = new stdClass();
        //$dispData->maxVisitorCount=$commonService;
        $dispData->env=$commonService->getEnvironment();
        //
        //
        //

        return view(
            'edit',
            [
                'user' => $commonService->getLoginUser(),
                'breadcrumb' => Consts::EDIT_BREADCRUMB,
                'data' => $data,
                'dispData' => $dispData
            ]
        );
    }

    public function update(Request $request,  $id)
    {
        $ah = AccessHistory::findOrFail($id);
        $ah->corp_name = $request->corp_name;
        $ah->addr01 = $request->addr01;
        $ah->name = $request->name;
        $ah->num_people = $request->num_people;
        $ah->tel = $request->tel;
        $ah->join_time = $request->join_time;
        $ah->left_time = $request->left_time;
        $ah->note = $request->note;

        $ah->save();

        return redirect(Consts::DISP_EDIT_AFTER);
    }
}
