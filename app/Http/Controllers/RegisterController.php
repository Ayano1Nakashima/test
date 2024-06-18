<?php

namespace App\Http\Controllers;

use stdClass;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Config\Consts;
use App\Common\Service\CommonService;
use App\Models\AccessHistory;

class RegisterController extends Controller
{
    /** 共通部品 */
    public CommonService $commonService;

    /**
     * コンストラクタ
     * 
     * @param CommonService $commonService 共通部品
     */
    public function __construct(CommonService $commonService)
    {
        session_start();
        $this->commonService = $commonService;
    }

    /**
     * 登録画面表示
     * 
     * @param Request $request リクエスト
     * @return View ビューレスポンス
     */
    public function show(Request $request)
    {
        $dispData = new stdClass();
        $dispData->type = (int)$request->input('type') ? (int)$request->input('type') : 0;

        // セッションに登録タイプを格納
        $_SESSION['register_type'] = $dispData->type;

        return view(
            Consts::DISP_REGISTER,
            [
                'breadcrumb' => Consts::REGISTER_BREADCRUMB,
                'user' => $this->commonService->getLoginUser(),
                'dispData' => $dispData
            ]
        );
    }

    /**
     * プルダウン選択
     * 
     * @param Request $request リクエスト
     * @return View ビューレスポンス
     */
    public function selected(Request $request)
    {
        $_SESSION['register_type'] = $request->input('type');
        return redirect('vistData/register?type=' . $request->input('type'));
    }

    /**
     * 登録
     * 
     * @param Request $request リクエスト
     * @return View ビューレスポンス
     */
    public function insert(Request $request)
    {
        // セッションから登録データタイプを取得
        $type = $_SESSION['register_type'];

        // 登録データタイプ判定
        if ($type === 0) {
            // 法人データ登録
            AccessHistory::insert(
                [
                    Consts::ACCESS_HISTORY_CORP_NAME => $request->input('corp_name'),
                    Consts::ACCESS_HISTORY_VIST_TYPE => $type,
                    Consts::ACCESS_HISTORY_ADDRESS => $request->input('address'),
                    Consts::ACCESS_HISTORY_NAME => $request->input('name'),
                    Consts::ACCESS_HISTORY_NUM_PEOPLE => $request->input('num_people'),
                    Consts::ACCESS_HISTORY_TEL => $request->input('tel'),
                    Consts::ACCESS_HISTORY_VISITOR_SELECT => $request->input('visitor_select'),
                    Consts::ACCESS_HISTORY_JOIN_TIME => $request->input('join_time'),
                    Consts::ACCESS_HISTORY_LEFT_TIME => $request->input('left_time'),
                    Consts::ACCESS_HISTORY_NOTE => $request->input('note')
                ]
            );
        } else {
            // 個人データ登録
            AccessHistory::insert(
                [
                    Consts::ACCESS_HISTORY_VIST_TYPE => $type,
                    Consts::ACCESS_HISTORY_NAME => $request->input('name'),
                    Consts::ACCESS_HISTORY_NUM_PEOPLE => $request->input('num_people'),
                    Consts::ACCESS_HISTORY_TEL => $request->input('tel'),
                    Consts::ACCESS_HISTORY_VISITOR_SELECT => $request->input('visitor_select'),
                    Consts::ACCESS_HISTORY_JOIN_TIME => $request->input('join_time'),
                    Consts::ACCESS_HISTORY_LEFT_TIME => $request->input('left_time'),
                    Consts::ACCESS_HISTORY_NOTE => $request->input('note')
                ]
            );
        }

        // 全件表示画面にリダイレクト
        return redirect(Consts::DISP_EDIT_AFTER);
    }
}
