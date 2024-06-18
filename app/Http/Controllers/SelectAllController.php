<?php

namespace App\Http\Controllers;

use stdClass;
use Exception;
use Config\Consts;
use Illuminate\Http\Request;
use App\Models\AccessHistory;
use App\Models\Environment;
use App\Http\Controllers\Controller;
use App\Common\Service\CommonService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SelectAllController extends Controller
{   /**修正追加 */
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
     * 全件表示画面表示
     * 
     * @param String $type 表示タイプ(company：法人、person：個人、左記以外：すべて)
     * @return View ビューレスポンス
     */
    public function show(Request $request, String $type)
    {
        // 検索条件を初期化
        $condition = new stdClass();
        $condition->companyName = '';
        $condition->name = '';
        $condition->visitCnt = '';
        $condition->address = '';
        $condition->phoneNumber = '';
        $condition->visitPuropose = '';
        $condition->entryDateTime = '';
        $condition->exitDateTime = '';

        // ベースクエリ
        $query = AccessHistory::select(
            Consts::ACCESS_HISTORY_ID,
            Consts::ACCESS_HISTORY_CORP_NAME,
            Consts::ACCESS_HISTORY_ADDRESS,
            Consts::ACCESS_HISTORY_NAME,
            Consts::ACCESS_HISTORY_NUM_PEOPLE,
            Consts::ACCESS_HISTORY_TEL,
            Consts::ACCESS_HISTORY_VISITOR_SELECT,
            Consts::ACCESS_HISTORY_JOIN_TIME,
            Consts::ACCESS_HISTORY_LEFT_TIME,
            Consts::ACCESS_HISTORY_NOTE
        );

        $data = null;

        // セッションに表示タイプを格納
        $_SESSION[Consts::SELECT_ALL_DISP_TYPE] = $type;

        // クエリパラメータから表示件数を取得する
        // $dispCount = $request->input('dispCount') ? $request->input('dispCount') : 10;
        $dispCount = isset($_SESSION['dispCount']) ? $_SESSION['dispCount'] : 10;

        // 取得データタイプ判定
        if ($type == Consts::SELECT_ALL_DISP_TYPE_COMPANY) {
            // セッションに法人データを格納する
            $_SESSION[Consts::SELECT_ALL_DISP_DATA] = $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 0)->get();
            // 画面表示用データに取得結果を格納する
            $data = $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 0)->paginate($dispCount);
        } else if ($type == Consts::SELECT_ALL_DISP_TYPE_PERSON) {
            // セッションに個人データを格納する
            $_SESSION[Consts::SELECT_ALL_DISP_DATA] = $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 1)->get();
            // 画面表示用データに取得結果を格納する
            $data = $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 1)->paginate($dispCount);
        } else {
            // セッションにすべてのデータ格納する
            $_SESSION[Consts::SELECT_ALL_DISP_DATA] = $query->get();
            // 画面表示用データに取得結果を格納する
            $data = $query->paginate($dispCount);
        }

        // 全件表示画面へ遷移
        return view(
            Consts::DISP_SELECT_ALL,
            [
                'breadcrumb' => Consts::SELECT_ALL_BREADCRUMB,
                'user' => $this->commonService->getLoginUser(),
                'dispData' => $this->initDispData($data, $condition, '', '', 'hit-hidden', $this->commonService->getEnvironment(), $dispCount)
            ]
        );
    }

    /**
     * リンク表示件数取得
     *
     * @param String $type 表示タイプ(company：法人、person：個人、左記以外：すべて)
     * @return Int 取得件数
     */
    private function getLinkCount(String $type)
    {
        $count = 0;
        $query = AccessHistory::select(
            Consts::ACCESS_HISTORY_ID,
            Consts::ACCESS_HISTORY_CORP_NAME,
            Consts::ACCESS_HISTORY_ADDRESS,
            Consts::ACCESS_HISTORY_NAME,
            Consts::ACCESS_HISTORY_NUM_PEOPLE,
            Consts::ACCESS_HISTORY_TEL,
            Consts::ACCESS_HISTORY_VISITOR_SELECT,
            Consts::ACCESS_HISTORY_JOIN_TIME,
            Consts::ACCESS_HISTORY_LEFT_TIME,
            Consts::ACCESS_HISTORY_NOTE
        );

        if ($type == Consts::SELECT_ALL_DISP_TYPE_COMPANY) {
            $count = count($query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 0)->get());
        } else if ($type == Consts::SELECT_ALL_DISP_TYPE_PERSON) {
            $count = count($query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 1)->get());
        } else {
            $count = count($query->get());
        }
        return $count;
    }

    /**
     * ファイルダウンロード
     *
     * @param Request $request リクエスト
     * @return Response ダウンロードファイル
     */
    public function download(Request $request)
    {
        // セッションから出力データを取得
        $list = $_SESSION[Consts::SELECT_ALL_DISP_DATA]->toArray();

        // ヘッダーを用意
        $header = [
            Consts::ACCESS_HISTORY_ID => Consts::NO,
            Consts::ACCESS_HISTORY_CORP_NAME => Consts::COMPANY,
            Consts::ACCESS_HISTORY_ADDRESS => Consts::ADDRESS,
            Consts::ACCESS_HISTORY_NAME => Consts::NAME,
            Consts::ACCESS_HISTORY_NUM_PEOPLE => Consts::VISIT_CNT,
            Consts::ACCESS_HISTORY_TEL => Consts::PHONE_NUMBER,
            Consts::ACCESS_HISTORY_VISITOR_SELECT => Consts::VISIT_PURPOSE,
            Consts::ACCESS_HISTORY_JOIN_TIME => Consts::ENTRY_DATE_TIME,
            Consts::ACCESS_HISTORY_LEFT_TIME => Consts::EXIT_DATE_TIME,
            Consts::ACCESS_HISTORY_NOTE => Consts::NOTE
        ];

        // ヘッダーをリストの先頭に追加
        array_unshift($list, $header);

        // 出力tempファイルパス
        $filePath = Consts::OUTPUT_FILE_NAME;

        try {
            // 出力方式判定
            if ($request->input(Consts::FILE_TYPE_EXCEL) === Consts::FILE_TYPE_EXCEL) {
                // Excelボタンが押された場合の処理
                $filePath = $filePath . Consts::FILENAME_TYPE_EXCEL . date(Consts::DATA_FORMAT_YMD) . Consts::EXTENSION_TYPE_EXCEL;

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                $keys = array_keys($header);

                for ($i = 0; $i < count($list); $i++) {
                    for ($j = 0; $j < count($keys); $j++) {
                        // セルに値を設定
                        $sheet->setCellValue(chr(65 + $j) . ($i + 1), $list[$i][$keys[$j]]);
                    }
                }

                // Excelファイルの保存
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
            } else {
                // CSVボタンが押された場合の処理
                $filePath = $filePath . Consts::FILENAME_TYPE_CSV . date(Consts::DATA_FORMAT_YMD) . Consts::EXTENSION_TYPE_CSV;

                // ファイルを開く
                $fp = fopen($filePath, Consts::FOPEN_TYPE_WRITE);
                // 1行ずつ配列の内容をファイルに書き込む
                foreach ($list as $fields) {
                    fputcsv($fp, $fields);
                }
                // ファイルを閉じる
                fclose($fp);
            }

            // ファイルダウンロード開始
            return response()->download($filePath)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * 検索
     * 
     * @param Request $request リクエスト
     * @return View ビューレスポンス
     */
    public function search(Request $request)
    {
        // 検索条件を取得
        $condition = $this->getSearchCondition($request);

        // 企業名、住所、名前、電話番号、来客目的はそのまま条件指定
        $query = AccessHistory::select(
            Consts::ACCESS_HISTORY_ID,
            Consts::ACCESS_HISTORY_CORP_NAME,
            Consts::ACCESS_HISTORY_ADDRESS,
            Consts::ACCESS_HISTORY_NAME,
            Consts::ACCESS_HISTORY_NUM_PEOPLE,
            Consts::ACCESS_HISTORY_TEL,
            Consts::ACCESS_HISTORY_VISITOR_SELECT,
            Consts::ACCESS_HISTORY_JOIN_TIME,
            Consts::ACCESS_HISTORY_LEFT_TIME,
            Consts::ACCESS_HISTORY_NOTE
        );

        if ($condition->companyName != null) $query->where(Consts::ACCESS_HISTORY_CORP_NAME, 'LIKE', '%' . $condition->companyName . '%'); // 企業名nullチェック
        if ($condition->address != null) $query->where(Consts::ACCESS_HISTORY_ADDRESS, 'LIKE', '%' . $condition->address . '%'); // 住所nullチェック
        if ($condition->name != null) $query->where(Consts::ACCESS_HISTORY_NAME, 'LIKE', '%' . $condition->name . '%'); // 名前nullチェック
        if ($condition->phoneNumber != null) $query->where(Consts::ACCESS_HISTORY_TEL, 'LIKE', '%' . $condition->phoneNumber . '%'); // 電話番号nullチェック
        if ($condition->visitPuropose != null) $query->where(Consts::ACCESS_HISTORY_VISITOR_SELECT, 'LIKE', '%' . $condition->visitPuropose . '%'); // 来客目的nullチェック
        if ($condition->entryDateTime != null) $query->where(Consts::ACCESS_HISTORY_JOIN_TIME, '>=', $condition->entryDateTime); // 入室時刻nullチェック
        if ($condition->exitDateTime != null) $query->where(Consts::ACCESS_HISTORY_LEFT_TIME, '<=', $condition->exitDateTime); // 退室時刻nullチェック

        // 来客人数が5名か判定する
        if ($condition->visitCnt == 5) {
            $query->where(Consts::ACCESS_HISTORY_NUM_PEOPLE, '>=', $condition->visitCnt);
        } else if ($condition->visitCnt != 0) {
            $query->where(Consts::ACCESS_HISTORY_NUM_PEOPLE, $condition->visitCnt);
        }

        // セッションから表示タイプを取得する
        $type = isset($_SESSION[Consts::SELECT_ALL_DISP_TYPE]) ? $_SESSION[Consts::SELECT_ALL_DISP_TYPE] : null;

        // 表示データ判定
        if ($type == Consts::SELECT_ALL_DISP_TYPE_COMPANY) {
            $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 0)->get();
        } else if ($type == Consts::SELECT_ALL_DISP_TYPE_PERSON) {
            $query->where(Consts::ACCESS_HISTORY_VIST_TYPE, 1)->get();
        }

        // 取得結果をセッションに格納する
        $_SESSION[Consts::SELECT_ALL_DISP_DATA] = $query->get();

        // クエリパラメータから表示件数を取得する
        // $dispCount = $request->input('dispCount') ? $request->input('dispCount') : 10;
        $dispCount = isset($_SESSION['dispCount']) ? $_SESSION['dispCount'] : 10;

        // 取得のうちプルダウンの表示件数分表示する
        $data = $query->paginate($dispCount);

        // バインドされた値を含めたクエリ文を取得
        $sqlWithBindings = $this->getSqlWithBindings($query);

        // 検索結果をビューに渡す
        return view(
            Consts::DISP_SELECT_ALL,
            [
                'breadcrumb' => Consts::SELECT_ALL_BREADCRUMB,
                'user' => $this->commonService->getLoginUser(),
                'dispData' => $this->initDispData($data, $condition, 'open', $data->total(), 'hit-visible', $this->commonService->getEnvironment(), $dispCount)
            ]
        );
    }

    /**
     * 画面表示データ初期化
     * 
     * @param mixed $data 来訪者履歴データ
     * @param stdClass $condition 検索条件
     * @param String $isExpanded アコーディオン開閉状態
     * @param mixed $count 検索結果件数
     * @param String $hit 検索結果付加文言表示状態
     * @return stdClass 画面表示データ
     * @param Environment $env 環境変数
     * @param Int $dispCount 画面表示件数
     * @return stdClass 画面表示データ
     */
    private function initDispData(mixed $data, stdClass $condition, String $isExpanded, mixed $count, String $hit, Environment $env, Int $dispCount)
    {
        // リンク表示件数
        $linkCount = new stdClass();
        $linkCount->company = $this->getLinkCount(Consts::SELECT_ALL_DISP_TYPE_COMPANY);
        $linkCount->person = $this->getLinkCount(Consts::SELECT_ALL_DISP_TYPE_PERSON);
        $linkCount->all = $this->getLinkCount(Consts::SELECT_ALL_DISP_TYPE_ALL);

        // 画面表示データ
        $dispData = new stdClass();
        $dispData->data = $data;
        $dispData->condition = $condition;
        $dispData->isExpanded = $isExpanded;
        $dispData->count = $count;
        $dispData->hit = $hit;
        $dispData->linkCount = $linkCount;
        $dispData->env = $env;
        $dispData->dispCount = $dispCount;
        return $dispData;
    }

    /**
     * 検索条件取得
     * 
     * @param Request $request リクエスト
     * @return stdClass 検索条件
     */
    private function getSearchCondition(Request $request)
    {
        // 検索条件オブジェクトを初期化
        $condition = new stdClass();

        // action判定
        if ($_SERVER[Consts::REQUEST_METHOD] == Request::METHOD_POST) {
            // リクエストデータをセッションに格納
            $_SESSION[Consts::SESSION_KEY_COMPANY_NAME] = $request->input(Consts::SEARCH_CONDITION_CLASS_COMPAY_NAME);
            $_SESSION[Consts::SESSION_KEY_ADDRESS] = $request->input(Consts::SEARCH_CONDITION_CLASS_ADDRESS);
            $_SESSION[Consts::SESSION_KEY_NAME] = $request->input(Consts::SEARCH_CONDITION_CLASS_NAME);
            $_SESSION[Consts::SESSION_KEY_VISIT_CNT] = (int)$request->input(Consts::SEARCH_CONDITION_CLASS_VISIT_CNT);
            $_SESSION[Consts::SESSION_KEY_PHONE_NUMBER] = $request->input(Consts::SEARCH_CONDITION_CLASS_PHONE_NUMBER);
            $_SESSION[Consts::SESSION_KEY_VISIT_PROPOSE] = $request->input(Consts::SEARCH_CONDITION_CLASS_VISIT_PUROPOSE);
            $_SESSION[Consts::SESSION_KEY_ENTRY_DATE_TIME] = $request->input(Consts::SEARCH_CONDITION_CLASS_ENTRY_DATE_TIME);
            $_SESSION[Consts::SESSION_KEY_EXIT_DATE_TIME] = $request->input(Consts::SEARCH_CONDITION_CLASS_EXIT_DATE_TIME);

            // セッションから検索条件オブジェクトにを格納取得する
            $condition = $this->setSearchCondition();
        } else if ($_SERVER[Consts::REQUEST_METHOD] == Request::METHOD_GET) {
            // セッションから検索条件オブジェクトにを格納取得する
            $condition = $this->setSearchCondition();
        }

        return $condition;
    }

    /**
     * 検索条件格納
     * 
     * @return stdClass 検索条件
     */
    private function setSearchCondition()
    {
        // 検索条件をオブジェクトに格納
        $condition = new stdClass();
        $condition->companyName = $_SESSION[Consts::SESSION_KEY_COMPANY_NAME];
        $condition->address = $_SESSION[Consts::SESSION_KEY_ADDRESS];
        $condition->name = $_SESSION[Consts::SESSION_KEY_NAME];
        $condition->visitCnt = $_SESSION[Consts::SESSION_KEY_VISIT_CNT];
        $condition->phoneNumber = $_SESSION[Consts::SESSION_KEY_PHONE_NUMBER];
        $condition->visitPuropose = $_SESSION[Consts::SESSION_KEY_VISIT_PROPOSE];
        $condition->entryDateTime = $_SESSION[Consts::SESSION_KEY_ENTRY_DATE_TIME];
        $condition->exitDateTime = $_SESSION[Consts::SESSION_KEY_EXIT_DATE_TIME];
        return $condition;
    }

    /**
     * 表示件数選択
     * 
     * @param Request $request リクエスト
     * @return View ビューレスポンス
     */
    public function selectDispCount(Request $request)
    {
        $reqUri = $_POST['param'];
        $type = '';

        // データタイプ判定
        if (strpos($reqUri, Consts::SELECT_ALL_DISP_TYPE_COMPANY)) {
            $type = '/' . Consts::SELECT_ALL_DISP_TYPE_COMPANY;
        } else if (strpos($reqUri, Consts::SELECT_ALL_DISP_TYPE_PERSON)) {
            $type = '/' . Consts::SELECT_ALL_DISP_TYPE_PERSON;
        } else {
            $type = Consts::SELECT_ALL_DISP_TYPE_ALL;
        }

        // セッションに表示件数を格納する
        $_SESSION['dispCount'] = $request->input(Consts::SELECT_DISP_COUNT_CLASS);

        // リダイレクト
        return redirect(Consts::REQUEST_URI_SELECT_ALL . $type . '?dispCount=' . $request->input(Consts::SELECT_DISP_COUNT_CLASS));
    }

    /**
     * 削除
     * 
     * @param Int $id 識別子
     * @return View ビューレスポンス
     */
    public function delete(Int $id)
    {
        // $idに対応するレコードを削除
        AccessHistory::where(Consts::ACCESS_HISTORY_ID, $id)->delete();
        // リダイレクト
        return redirect(Consts::REQUEST_URI_SELECT_ALL . Consts::SELECT_ALL_DISP_TYPE_ALL);
    }

    // デバッグ用、クエリ取得
    function getSqlWithBindings($query)
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'$binding'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }
}
