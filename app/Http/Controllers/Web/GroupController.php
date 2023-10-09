<?php

namespace App\Http\Controllers\Web;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\WorkerGroup;
use DataTables;

class GroupController extends BaseController {

    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests;

    public function index(Request $request) {
        if ($request->ajax()) {
            $data = WorkerGroup::latest()->get();
            return Datatables::of($data)
                            ->make(true);
        }

        return view('group/index_log');
    }

    public function re_pull(Request $request) {
        $data_person = $this->get_data_worker_group_from_dss($request);
        if (($data_person['status']) > 0) {
            $return = $this->do_save_group($data_person['data']);
        } else {
            $return['status'] = 0;
            $return['data'] = [];
            $return['code'] = 500;
            $return['message'] = 'Connection error. Fail get data.';
        }
        echo json_encode($return, 1);
        exit();
    }

    protected function array_change_key_case_recursive($arr, $case = CASE_LOWER) {
        return array_map(function ($item) use ($case) {
            if (is_array($item))
                $item = $this->array_change_key_case_recursive($item, $case);
            return $item;
        }, array_change_key_case($arr, $case));
    }

    protected function set_created_at($arr, $now) {
        $arr['created_at'] = $now;
        return $arr;
    }

    protected function array_add_created_at($arr, $now) {
        return array_map(function ($item) use ($now) {
            if (is_array($item))
                $item = $this->array_add_created_at($item, $now);
            return $item;
        }, $this->set_created_at($arr, $now));
    }

    protected function do_save_group($data_save) {
        /**
         * CLEAN TABLE
         */
        DB::table('fa_group')->truncate();
        /**
         * LOWERED CASE INDEX/KEY
         */
        DB::table("fa_group")->insert($data_save);
        $return['status'] = 1;
        $return['data'] = [];
        $return['code'] = 200;
        $return['message'] = 'Success get data Persons.';
        return $return;
    }

    protected function get_data_worker_group_from_dss($request, $ip_server = null) {
//dd([$timestamp_start,$timestamp_end]);
        $server = config('face.API_FACEAPI_DOMAIN');

        if (empty($ip_server)) {
            
        } else {
            $server = $ip_server;
        }

        $isi_token = Storage::disk('local')->get('_token.txt');

        if ($isi_token) {
            $exploded_isi_token = explode("|", $isi_token);
            if (count($exploded_isi_token) >= 3) {
                $_token = trim($exploded_isi_token[2]);

                /**
                 * tutup dulu
                 */
                  $ch = curl_init("https://$server//obms/api/v1.1/acs/person-group/list");
                  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                  'Content-Type:application/json;charset=UTF-8',
                  'X-Subject-Token:' . $_token
                  )
                  );
                  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                  $result = curl_exec($ch);
                  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                  curl_close($ch);


//                $httpcode = 200;
//dd($result);
//        dd([$report_setting,$report]);
                if ($httpcode == 200) {
                    $return['status'] = 1;
                    /**
                     * SAMPLE DATA, MUST DELETED AFTER PUBLISH
                     */
//                    $result = '{"code":1000,"desc":"Success","data":{"results":[{"orgCode":"001","parentOrgCode":null,"orgName":"All Persons and Vehicles","remark":"","children":null,"childNum":"9","authority":"1"},{"orgCode":"001001","parentOrgCode":"001","orgName":"RAMP","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001002","parentOrgCode":"001","orgName":"ELECT","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001003","parentOrgCode":"001","orgName":"WORKSHOP","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001004","parentOrgCode":"001","orgName":"OFFICE","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001005","parentOrgCode":"001","orgName":"GENERAL CLEANING","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001006","parentOrgCode":"001","orgName":"LAB","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001007","parentOrgCode":"001","orgName":"PROCESS A","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001008","parentOrgCode":"001","orgName":"PROCESS B","remark":"","children":null,"childNum":"0","authority":"1"},{"orgCode":"001009","parentOrgCode":"001","orgName":"AP","remark":"","children":null,"childNum":"0","authority":"1"}]}}';

                    $parse_result = json_decode($result, 1);
//        dd([$parse_result]);
                    if ($parse_result['code'] == 1000) {
                        /**
                         * field name dilowercase
                         */
                        if (empty($parse_result['data']['results'])) {
                            $return['status'] = 0;
                            $return['data'] = [];
                            $return['code'] = $parse_result['code'];
                            $return['message'] = $parse_result['desc'];
                        } else {
                            $return['data'] = $parse_result['data']['results'];
                        }
                    } else {
                        $return['status'] = 0;
                        $return['data'] = [];
                        $return['code'] = $parse_result['code'];
                        $return['message'] = $parse_result['desc'];
                    }
                } else {
                    $return['status'] = 0;
                    $return['data'] = [];
                    $return['code'] = $httpcode;
                    $return['message'] = 'Connection error';
                }
            }
        } else {
            $return['status'] = 0;
            $return['data'] = [];
            $return['code'] = 500;
            $return['message'] = 'Connection error. No Token detected.';
        }
        return $return;
    }

    public function list_formatted(Request $request) {
        if ($request->ajax()) {
            $where = " 1 = ?";
            $val_where = [1];
            $searching = $request->get('searchbox');
            if(!empty($searching)){
                $where = "(1 = ?) AND (\"orgCode\" ilike '%$searching%' or \"parentOrgCode\" ilike '%$searching%' or \"orgName\" ilike '%$searching%')";
            }            
            $data = DB::table('fa_group')
                    ->select(
                            "orgCode",
                            "parentOrgCode",
                            "orgName",
                            "children",
                            "childNum",
                            "authority",
                            "remark",
                    )
                    ->orWhereRaw("$where", $val_where)
                    ->orderBy('fa_group_id', 'asc')
//                ->limit(10)
                    ->get();

            $no = 1;
            foreach ($data as $ky => $dtperson) {
                $dtperson->no_urut = $no;
                $data[$ky] = $dtperson;
                $no++;
            }
            return Datatables::of($data)
                            ->make(true);
        }
        return view('group/index_pretty_log');
    }

    public function getData_att(Request $request) {
        if ($request->ajax()) {
            $data = DB::table('fa_group')
                    ->select(
                            "orgCode",
                            "parentOrgCode",
                            "orgName",
                            "children",
                            "childNum",
                            "authority",
                            "remark",
                    )
//                    ->whereIn('sent_cpi', ['F', 'Y'])
//                    ->offset(0)
                    ->orderBy('fa_group_id', 'asc')
//                ->limit(10)
                    ->get();
            return Datatables::of($data)
                            ->make(true);
        }
//        var_dump($request);
    }

    public function getData(Request $request) {
        if ($request->ajax()) {
            $data = DB::table('fa_group')
                    ->select(
                            "orgCode",
                            "parentOrgCode",
                            "orgName",
                            "children",
                            "childNum",
                            "authority",
                            "remark",
                    )
                    ->orderBy('fa_group_id', 'asc')
//                ->limit(10)
                    ->get();
            return Datatables::of($data)
                            ->make(true);
        }
//        var_dump($request);
    }

    public function getDataFormatted(Request $request) {
        if ($request->ajax()) {
            $data = DB::table('fa_group')
                    ->select(
                            "orgCode",
                            "parentOrgCode",
                            "orgName",
                            "children",
                            "childNum",
                            "authority",
                            "remark",
                    )
                    ->orderBy('fa_group_id', 'asc')
//                ->limit(10)
                    ->get();
            return Datatables::of($data)
                            ->make(true);
        }
//        var_dump($request);
    }
}
