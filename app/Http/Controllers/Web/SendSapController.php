<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\RequestLog;
use App\Models\AccessControl;

class SendSapController extends BaseController {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $date_start = date('Y-m-d');
        $date_end = date('Y-m-d');
        return view('sendsap.view', compact(['date_start', 'date_end']));
    }

    public function transfer_sap(Request $request) {
        $zone = config('face.API_ZONE');
        if ($zone == 'MY') {
            date_default_timezone_set('Asia/Kuala_Lumpur');
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        $setting = Setting::orderBy('fa_setting_id', 'desc')->first();

        //var_dump([$request->type]);die();
        if (!empty($request->type) && $request->type == 'resend') {
            // $this->keep_alive($setting);
            $this->resend_to_cpi($request, $setting);
        } elseif (!empty($request->type) && $request->type == 'pull') {
            $this->keep_alive($setting);
            //	var_dump(["ok"]);die();
            $this->crawling_passing_attendance($request, $setting);
        } elseif (!empty($request->type) && $request->type == 'push') {
            // $this->keep_alive($setting);
            $this->passing_to_cpi($request, $setting);
        } else {
            //  $this->keep_alive($setting);
            $this->crawling_passing_attendance($request, $setting);
            $this->passing_to_cpi($request, $setting);
        }
        echo json_encode(
                array(
                    'status' => 'success',
                    'data' => $setting,
                    'message' => 'Process completed.'
                )
        );
        die();
    }

    public function crawling_passing_attendance($request, $report_setting) {
        /**
         * 
         * To-do:
         * 1. hit api attandance
         * 2. if success, pass data to CPI, then log
         * 3. if fail,log the error, back to no.1
         */
        $ip_server = $report_setting->ip_server_fr;
        $ops_unit = $report_setting->unit_name;
        $now = date('Y-m-d H:i:s');
        $isi_token = Storage::disk('local')->get('_token.txt');
        if ($isi_token) {
            $exploded_isi_token = explode("|", $isi_token);
            //var_dump($exploded_isi_token);die();
            if (count($exploded_isi_token) >= 3) {
                if ($exploded_isi_token[0] == date('Ymd')) {
                    $_token = trim($exploded_isi_token[2]);

                    //Access IN
                    $response_fr = $this->crawling_face_recognition_in($request, $_token, $ip_server, $report_setting);

                    //echo json_encode($response_fr);die();
                    if ($response_fr['status'] == 1) {
//                        dd($response_fr);
                        $list_attendance = $response_fr['data']['pageData'];
                        /**
                         * Ada 2 device absensi :
                         * 1. clock IN
                         * 2. clock OUT
                         * 
                         * harus disimpan FLAG IN/OUT
                         */
                        if (count($list_attendance) > 0) {
                            foreach ($list_attendance as $dt_att) {
                                if (empty($dt_att['personId'])) {
                                    continue;
                                }
                                if (empty($dt_att['firstName'])) {
                                    continue;
                                }
                                /**
                                 * 2023/06/06
                                 * ada case ID worker lebih dari 30 char
                                 * 
                                 * bikin error
                                 * 
                                 * harus dibuang
                                 */
                                if (strlen($dt_att['firstName']) > 30) {
                                    continue;
                                }

                                $att['REMARK'] = "";
                                unset($dt_att['id']);
                                if (strtoupper($dt_att['deviceName']) == $report_setting->ip_clock_in) {
                                    $direction = "IN";
                                } else {
                                    $direction = "OUT";
                                }
//                                dd([$dt_att['deviceName'],$report_setting['ip_clock_in']]);
                                $obj_data = (object) $dt_att;
                                $this->insert_access($ops_unit, $obj_data, $direction, $now);
//                            $this->insert_access_in($obj_data, 'IN', $now);
                            }
                            $responses = array(
                                'status' => 'success',
                                'data' => [
                                    array(
                                        'code' => 200,
                                        'message' => 'OK - Access control - Data FR Terambil'
                                    )
                                ]
                            );
                        } else {

                            $responses = array(
                                'status' => 'success',
                                'data' => [
                                    array(
                                        'code' => 200,
                                        'message' => 'OK - Access control - Data FR Kosong'
                                    )
                                ]
                            );
                        }
                        $this->log_event($request, $responses, $now, 'crawling_passing_attendance <-> success');
                    } else {
                        $str_log = date('Y-m-d H:i:s') . ":[GET-Access Control][" . $response_fr['code'] . "][" . $response_fr['message'] . "]";
                        $this->log_event([], $response_fr, $now, 'crawling_passing_attendance <-> fail');
//                        Log::info($str_log);
                    }
                }
            }
        }
    }

    protected function crawling_face_recognition_in($request, $_token, $ip_server = null) {
        $date_start = $request->get('date_start');
        $date_end = $request->get('date_end');
        /**
         * Gunakan kalau date dalam format selain Y-m-d
          $date_start = \DateTimeImmutable::createFromFormat('Y-m-d', $request->date_start);
          if (!$date_start) {
          $return['status'] = 0;
          $return['data'] = [];
          $return['code'] = 503;
          $return['message'] = 'Datestart convertion fail.';
          return $return;
          }
          $date_end = \DateTimeImmutable::createFromFormat('Y-m-d', $request->date_end);
          if (!$date_end) {
          $return['status'] = 0;
          $return['data'] = [];
          $return['code'] = 503;
          $return['message'] = 'Dateend convertion fail.';
          return $return;
          }
          $timestamp_start = strtotime($date_start->format('Y-m-d 00:00:01'));
          $timestamp_end = strtotime($date_start->format('Y-m-d 23:59:59'));
         */
        //date_default_timezone_set('GMT');
        $zone = config('face.API_ZONE');
        /*         if ($zone == 'MY') {
          date_default_timezone_set('Asia/Kuala_Lumpur');
          } else {
          date_default_timezone_set('Asia/Jakarta');
          } */
        $date_start = \DateTime::createFromFormat('Y-m-d H:i A', "$date_start 01:01 am");
        $date_end = \DateTime::createFromFormat('Y-m-d H:i A', "$date_end 11:59 pm");
        $timestamp_start = $date_start->format('U');
        $timestamp_end = $date_end->format('U');
//dd([$timestamp_start,$timestamp_end]);
        $server = config('face.API_FACEAPI_DOMAIN');

        if (empty($ip_server)) {
            
        } else {
            $server = $ip_server;
        }
        $urlinit = "https://$server/obms/api/v1.1/acs/access/record/fetch/page";
        $ch = curl_init($urlinit);

        $body_posted = '{
                        "page": "1",
                        "pageSize": "1000",
                        "channelIds": [],
                        "personId": "",
                        "startTime": "' . $timestamp_start . '",
                        "endTime": "' . $timestamp_end . '"
                    }';
//        echo(json_encode($body_posted));die();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Subject-Token:' . $_token,
            'charset:UTF-8',
            'Content-Type:application/json'
                )
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_posted);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
//dd($result);
//        dd([$report_setting,$report]);
        if ($httpcode == 200) {
            $return['status'] = 1;
            $parse_result = json_decode($result, 1);
//        dd([$parse_result]);
            if ($parse_result['code'] == 1000) {
                $return['data'] = $parse_result['data'];
            } else {
                $return['status'] = 0;
                $return['data'] = [];
                $return['code'] = $parse_result['code'];
                $return['message'] = $parse_result['desc'];
//                $params = array(
//                    'start_check' => "$date_start 00:00:01",
//                    'end_check' => "$date_end 23:59:59",
//                );
//                $responses = array(
//                    'status' => 'error',
//                    'data' => [
//                        array(
//                            'code' => $parse_result['code'],
//                            'message' => $parse_result['desc']
//                        )
//                    ]
//                );
//                $this->log_event($params, $responses);
            }
        } else {
            $return['status'] = 0;
            $return['data'] = [];
            $return['code'] = $httpcode;
            $return['message'] = 'Connection error';
        }
        return $return;
    }

    protected function resend_to_cpi($request, $report_setting) {
        $ops_unit = $report_setting->unit_name;

        $data = DB::table('fa_accesscontrol')
                ->select('fa_accesscontrol_id', 'devicecode', 'devicename', 'channelid', 'channelname', 'alarmtypeid', 'personid', 'firstname', 'lastname', 'alarmtime', 'accesstype', 'unit_name')
//                ->where(function ($query) use ($strdate, $enddate) {
//                    $query->where('alarmtime', '>=', $strdate);
//                    $query->where('alarmtime', '<=', $enddate);
//                })
                ->where('sent_cpi', '=', 'F')
                ->offset(0)
                ->orderBy('alarmtime', 'asc')
                ->limit(200)
                ->get();
        $arr_data = $data->toArray();
        if (!$data || (count($arr_data) < 1)) {
            $responses = array(
                'status' => 'success',
                'data' => [
                    array(
                        'code' => 200,
                        'message' => 'There is no new data to transfer.'
                    )
                ]
            );
            $this->log_event([], $responses, '', 'passing_to_cpi_button');
        } else {
            //dd($arr_data);

            $sent_data = [];
            $updated_ids = [];

            $list_prfnr = [];
            foreach ($arr_data as $dt_attendance) {
                $updated_ids[] = $dt_attendance->fa_accesscontrol_id;
//                $att['MANDT'] = '';
                $att['RECORD_ID'] = $dt_attendance->fa_accesscontrol_id;
                $att['PRFNR'] = $ops_unit;
                $att['EMPNR'] = $dt_attendance->firstname;
                $att['SOURCE'] = "D";
//                            }
                $att_time = explode(" ", $dt_attendance->alarmtime);
//                $att['SDATE'] = str_replace("-","",$att_time[0]);
                $att['SDATE'] = $att_time[0];
//                $att['STIME'] = str_replace(":","",$att_time[1]);
                $att['STIME'] = $att_time[1];

                if ($dt_attendance->accesstype == "OUT") {
                    $att['TYPE'] = "O";
                } else {
                    $att['TYPE'] = "I";
                }
                $att['ERNAM'] = "";
                $att['ERDAT'] = "";
                $att['ERZET'] = "";
                $att['REMARK'] = "";
//                $att['AENAM'] = "";
//                $att['AEDAT'] = "";
//                $att['AEZET'] = "";
//                $att['APNAM'] = "";
//                $att['APDAT'] = "";
//                $att['APZET'] = "";
//                $att['DELETED'] = "";

                $sent_data[$att['PRFNR']][] = $att;
            }

//            dd($sent_data);
            //sent to cpi
            $prfnr_list = array_keys($sent_data);
            $res = [];
            $error_count = [];
            $delivered_ids = [];
            if (count($prfnr_list) > 1) {
                for ($i = 0; $i < count($prfnr_list) - 1; $i++) {
                    $dtsent1 = $sent_data[$prfnr_list[$i]];
                    $dtsent = $dtsent1;
                    foreach ($dtsent as $ksent => $oksent) {
                        unset($dtsent[$ksent]['RECORD_ID']);
                    }
                    $response0 = send_time_attendance_to_cpi($dtsent, $prfnr_list[$i], false);
                    if (empty($response0['feedback']['ERROR'])) {
                        $res[] = $response0;
                        $delivered_ids[] = $dtsent1[0]['RECORD_ID'];
                    } else {
                        $error_count[] = $response0['feedback']['ERROR'];
                        $res[] = $response0;
                        /**
                         * Handdle error
                         */
                    }
//                    echo "$i <br/>";
                }
                $dtsent1 = $sent_data[$prfnr_list[count($prfnr_list) - 1]];
                $dtsent = $dtsent1;
                foreach ($dtsent as $ksent => $oksent) {
                    unset($dtsent[$ksent]['RECORD_ID']);
                }
                $response1 = send_time_attendance_to_cpi($dtsent, $prfnr_list[count($prfnr_list) - 1], true);
                if (empty($response1['feedback']['ERROR'])) {
                    $res[] = $response1;
                    $delivered_ids[] = $dtsent1[0]['RECORD_ID'];
                } else {
                    $error_count[] = $response1['feedback']['ERROR'];
                    $res[] = $response1;
                    /**
                     * Handdle error
                     */
                }
                //  dd($response1['feedback']);                
            } else {
                $dtsent1 = $sent_data[$prfnr_list[0]];
//                dd($dtsent1);
                $dtsent = $dtsent1;
                foreach ($dtsent as $ksent => $oksent) {
                    unset($dtsent[$ksent]['RECORD_ID']);
                }
                $response2 = send_time_attendance_to_cpi($dtsent, $prfnr_list[0], true);
                if (empty($response2['feedback']['ERROR'])) {
                    foreach ($dtsent1 as $oksent) {
                        $delivered_ids[] = $oksent['RECORD_ID'];
                    }
                    $res[] = $response2;
                } else {
                    $error_count[] = $response2['feedback']['ERROR'];
                    $res[] = $response2;
                    /**
                     * Handdle error
                     */
                }
                //   dd($response2['feedback']);                
            }


            $responses = array(
                'status' => 'success',
                'data' => [
                    array(
                        'code' => 200,
                        'message' => 'OK - Data transferred',
                        'original' => $res
                    )
                ]
            );
            $affected = DB::table('fa_accesscontrol')
                    ->whereIn('fa_accesscontrol_id', $updated_ids)
                    ->update(['sent_cpi' => 'Y', 'remark' => '']);
            $this->log_event($sent_data, $responses, '', 'passing_to_cpi_oto');
            if (count($error_count) > 0) {
                $responses['status'] = 'fail';
                foreach ($error_count as $err) {
                    foreach ($err as $derr) {
                        $affected = DB::table('fa_accesscontrol')
                                ->where('firstname', $derr['EMPNR'])
                                ->where('alarmtime', "$derr[SDATE] $derr[STIME]")
                                ->update(['sent_cpi' => 'F', 'remark' => $derr['REMARK']]);
                    }
                }
                $this->log_event($sent_data, $responses, '', 'resend_to_cpi_button');
            }
        }
        return $responses;
    }

    protected function passing_to_cpi($request, $report_setting) {
        /**
         * Digunakan schema baru
         * 1. cari data IN untuk tanggal sebelum range
         * 2. cari all data untuk tanggal dalam range
         * 3. cari data OUT untuk tanggal setelah range
         */
        $ops_unit = $report_setting->unit_name;

        $startdate_ori = $sdate = $request->get('date_start');
        $enddate_ori = $enddate = $request->get('date_end');
        $enddate = "$enddate_ori 23:59:59";

        $sdate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -7 hour'));
        $edate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -3 second'));

        //Edate = date + 1 day 
        $date_after = date('Y-m-d', strtotime($enddate_ori . ' +1 day'));
        $sdate_after = date('Y-m-d H:i:s', strtotime($date_after . ' -3 second'));
        $edate_after = "$date_after 09:00:00";

//dd([$sdate_before,$edate_before,$sdate_after,$edate_after]);
        $arr_data = DB::table('fa_accesscontrol')
                        ->select('alarmtime', 'fa_accesscontrol.accesstype', 'fa_accesscontrol.personid', 'fa_accesscontrol.firstname', 'fa_person.orgcode', 'fa_person.orgname')
                        ->join('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($sdate_before, $edate_before, $sdate_after, $edate_after) {
                            $query->orWhere(function ($query3) use ($sdate_before, $edate_before) {
                                $query3->where('alarmtime', '>=', $sdate_before);
                                $query3->where('alarmtime', '<=', $edate_before);
//                                $query3->where('fa_accesscontrol.accesstype', '=', 'IN');
                            });
                            $query->orWhere(function ($query5) use ($edate_before, $sdate_after) {
                                $query5->where('alarmtime', '>=', $edate_before);
                                $query5->where('alarmtime', '<=', $sdate_after);
                            });
                            $query->orWhere(function ($query4) use ($sdate_after, $edate_after) {
                                $query4->where('alarmtime', '>=', $sdate_after);
                                $query4->where('alarmtime', '<=', $edate_after);
//                                $query4->where('fa_accesscontrol.accesstype', '=', 'OUT');
                            });
                        })
                        ->where('sent_cpi', '=', 'N')
//                        ->where('fa_accesscontrol.personid', '=', 'AHMADDEMMA')
                        ->orderBy('personid', 'asc')
                        ->orderBy('alarmtime', 'asc')
                        ->orderBy('accesstype', 'asc')->get();
        if (!$arr_data || count($arr_data) < 1) {
            $responses = array(
                'status' => 'success',
                'data' => [
                    array(
                        'code' => 200,
                        'message' => 'There is no new data to transfer.'
                    )
                ]
            );
            return $responses;
        }
//        dd(($arr_data));
        $swipetime = [];
        $new_data = [];
        $format = 'Y-m-d H:i:s';

        foreach ($arr_data as $dt_access) {
            if (empty($new_data[$dt_access->personid])) {
                $new_data[$dt_access->personid] = new \stdClass();
                $new_data[$dt_access->personid]->orgname = $dt_access->orgname;
                $new_data[$dt_access->personid]->orgcode = $dt_access->orgcode;
                $new_data[$dt_access->personid]->nama_personnel = $dt_access->personid;
                $new_data[$dt_access->personid]->worker_id = $dt_access->firstname;
            }
            if ($dt_access->accesstype == "OUT") {
                $type = "O";
            } else {
                $type = "I";
            }
            $date_swipetime = $dt_access->alarmtime . $type;
            $swipetime[$dt_access->personid][] = $date_swipetime;
        }

//        dd($new_data);
//        dd($swipetime);

        foreach ($swipetime as $personid => $direct) {
            /**
             * Data dengan selisih kurang dari 1 menit, dianggap duplikat
             */
            $dir_in = $direct;
            $intval_dir_in = 0;
            $time_dir_in = "";
            $tgl_trx = "";
            $type_trx = "";
            $new_dir = [];
            foreach ($dir_in as $k => $v) {
                $type = substr($v, -1);
                $v_dir = trim(substr($v, 0, -1));

                $intval_v = preg_replace('/[^0-9]/', '', $v_dir);
//                    print_r($intval_v);
//                    echo "$v";
//                    echo "\n";
                if ($k < 1 && $type == "O") {
                    continue;
                }
                if ($k < 1 && $type == "I") {
                    $tgl_v1 = substr($v, 0, 10);
                    $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v1);
                    $tgl_v = (String) $tgl_create->format('d/m/Y');
                    $tgl_trx = $tgl_v;
                    $new_dir[$tgl_v][] = $v;
                    $intval_dir_in = intval($intval_v) + 60;
                    $type_trx = "I";
                    continue;
                }

                if ($type_trx == $type) {
                    $tgl_v1 = substr($v, 0, 10);
                    $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v1);
                    $tgl_v = (String) $tgl_create->format('d/m/Y');
                    if ($type == 'O') {
                        //OUT
//                            if (intval($intval_v) >= $intval_dir_in) {
                        if (empty($new_dir[$tgl_trx])) {
                            $new_dir[$tgl_trx][] = $v;
                        } else {
                            $idx = count($new_dir[$tgl_trx]) - 1;
                            $new_dir[$tgl_trx][] = $v;
                        }
                        $intval_dir_in = intval($intval_v) + 60;
//                            }
                        $tgl_trx = $tgl_v;
                    } elseif ($type == 'I') {
                        //IN
//                        if (intval($intval_v) >= $intval_dir_in) {
                        if (empty($new_dir[$tgl_v])) {
                            $new_dir[$tgl_v][] = $v;
                        } else {
                            $idx = count($new_dir[$tgl_v]) - 1;
                            $new_dir[$tgl_v][] = $v;
                        }
                        $intval_dir_in = intval($intval_v) + 60;
//                        } else {
//                            
//                        }
                        $tgl_trx = $tgl_v;
                    }
                } else {
                    $tgl_v1 = substr($v, 0, 10);
                    $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v1);
                    $tgl_v = (String) $tgl_create->format('d/m/Y');
                    if ($type == 'O') {
                        //OUT

                        if (empty($new_dir[$tgl_trx])) {
                            $new_dir[$tgl_trx][] = $v;
                        } else {
                            $idx = count($new_dir[$tgl_trx]) - 1;
                            $new_dir[$tgl_trx][] = $v;
                        }

                        $intval_dir_in = intval($intval_v) + 60;
                        $tgl_trx = $tgl_v;
                    } elseif ($type == 'I') {
                        //IN
                        $new_dir[$tgl_v][] = $v;
                        $tgl_trx = $tgl_v;
                        $time_dir_in = $v;
                        $intval_dir_in = intval($intval_v) + 60;
                    }
                    $type_trx = $type;
                }
            }
            $swipetime[$personid] = $new_dir;
        }

//dd(($swipetime));

        $list_workdays = $this->list_of_working_days($startdate_ori, $enddate_ori);
        $workdays_keys = array_keys($list_workdays);
        foreach ($swipetime as $personid => $direct) {

            $attd = [];
            foreach ($direct as $tgl => $att) {
                if (in_array($tgl, $workdays_keys)) {
                    $attd[$tgl] = $att;
                }
            }
            if (empty($attd)) {
                unset($new_data[$personid]);
            } else {
                $new_data[$personid]->trx = $attd;
            }
        }


//        dd($new_data);
        if (!$new_data || (count($new_data) < 1)) {
            $responses = array(
                'status' => 'success',
                'data' => [
                    array(
                        'code' => 200,
                        'message' => 'There is no new data to transfer.'
                    )
                ]
            );
            $this->log_event([], $responses, '', 'passing_to_cpi_button');
        } else {
            //dd($arr_data);

            $sent_data = [];
            $updated_ids = [];

            $list_prfnr = [];
            foreach ($new_data as $dt_attendance) {
//                $att['MANDT'] = '';
                $attd = $dt_attendance->trx;
                $worker_id = $dt_attendance->worker_id;
                $exp_nik = explode('/', $worker_id);
                $nik = end($exp_nik);
                $nik_padded = str_pad($nik, 6, "0", STR_PAD_LEFT);
                foreach ($attd as $tgl => $in_out) {
//                            }
                    foreach ($in_out as $detail) {
                        $att = [];
                        $att['PRFNR'] = $ops_unit;
                        $att['EMPNR'] = $worker_id;
                        $att['SOURCE'] = "D";
                        $type = substr($detail, -1);
                        $timestamp = substr($detail, 0, -1);
                        $timestamp_without_second = substr($detail, 2, -1);
                        $timestamp_without_second_cleaned = str_replace(['-', ':', ' '], '', $timestamp_without_second); //10 chars
                        $record_id = $timestamp_without_second_cleaned . $nik_padded; //18 chars
                        $att['RECORD_ID'] = $record_id;
                        $att_time = explode(" ", $timestamp);

                        $att['SDATE'] = $att_time[0];
                        $att['STIME'] = $att_time[1];

                        $att['TYPE'] = "$type";
                        $att['ERNAM'] = "";
                        $att['ERDAT'] = "";
                        $att['ERZET'] = "";
                        $att['REMARK'] = "";
//                $att['AENAM'] = "";
//                $att['AEDAT'] = "";
//                $att['AEZET'] = "";
//                $att['APNAM'] = "";
//                $att['APDAT'] = "";
//                $att['APZET'] = "";
//                $att['DELETED'] = "";

                        $sent_data[$att['PRFNR']][] = $att;
                    }
                }
            }

//            dd($sent_data);
            //sent to cpi
            $prfnr_list = array_keys($sent_data);
            $res = [];
            $error_count = [];
            $delivered_ids = [];

            $dtsent1 = $sent_data[$prfnr_list[0]];
            $dtsent = $dtsent1;
            foreach ($dtsent as $ksent => $oksent) {
                unset($dtsent[$ksent]['RECORD_ID']);
            }
            $response2 = send_time_attendance_to_cpi($dtsent, $prfnr_list[0], true);
//                dd($response2);
            if (empty($response2['feedback']['ERROR'])) {
                foreach ($dtsent1 as $oksent) {
                    $delivered_ids[] = $oksent['RECORD_ID'];
                }
                $res[] = $response2;
            } else {
                $error_count[] = $response2['feedback']['ERROR'];
                $res[] = $response2;
                /**
                 * Handdle error
                 */
            }

//            dd($error_count);

            $responses = array(
                'status' => 'success',
                'data' => [
                    array(
                        'code' => 200,
                        'message' => 'OK - Data transferred',
                        'original' => $res
                    )
                ]
            );
            foreach ($delivered_ids as $dt_ids) {
                $nik_padded = substr($dt_ids, -6);
                $nik = trim($nik_padded, "0");
                $time_trx = substr($dt_ids, 0, 12);
//                echo "$dt_ids -> $nik_padded > $nik  >> $time_trx \n";
                DB::table('fa_accesscontrol')
                        ->whereRaw("TO_CHAR(alarmtime,'YYMMDDHH24MISS') ='$time_trx'")
                        ->whereRaw("firstname LIKE '%$nik'")
                        ->update(['sent_cpi' => 'Y']);
            }
//            dd($delivered_ids);
            $this->log_event($sent_data, $responses, '', 'passing_to_cpi_oto');
            if (count($error_count) > 0) {
                $responses['status'] = 'fail';
                foreach ($error_count as $err) {
                    foreach ($err as $derr) {
                        $affected = DB::table('fa_accesscontrol')
                                ->where('firstname', $derr['EMPNR'])
                                ->where('alarmtime', "$derr[SDATE] $derr[STIME]")
                                ->update(['sent_cpi' => 'F', 'remark' => $derr['REMARK']]);
                    }
                }
                $this->log_event($sent_data, $responses, '', 'passing_to_cpi_button');
            }
        }
        return $responses;
    }

    public function keep_alive($report_setting) {
//        $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
        $ip_server = $report_setting->ip_server_fr;
        $ops_unit = $report_setting->unit_name;
//dd($report_setting);
        //Storage::disk('local')->put('_token.txt', 'CHECK');
//        $now = date('Y-m-d H:i:s');
//        $this->log_event([], $ip_server, $now, 'keep_alive <-> start');
//        die();
        if ((Storage::disk('local')->exists('_token.txt'))) {
            $isi_token = Storage::disk('local')->get('_token.txt');

            if ($isi_token) {
                $exploded_isi_token = explode("|", $isi_token);
                //var_dump($exploded_isi_token);die();
                if (count($exploded_isi_token) >= 3) {
                    if ($exploded_isi_token[0] == date('Ymd')) {
                        $datetimestamp = strtotime('+25 minutes', strtotime("$exploded_isi_token[0] $exploded_isi_token[1]"));
                        $is_run_auth = Storage::disk('local')->get('_run_cron.txt');
                        if ($is_run_auth == 'Y') {
                            if ($datetimestamp < strtotime('now')) {
                                $this->do_auth($ip_server);
                            } else {
                                $this->do_heartbeat($ip_server, $exploded_isi_token);
                            }
                        }
                    } else {
                        //first auth
                        $this->do_auth($ip_server);
                    }
                } else {
                    //re run auth
                    $this->do_auth($ip_server);
                }
            } else {
                //first auth
                $this->do_auth($ip_server);
            }
        } else {
            $this->do_auth($ip_server);
        }
    }

    function list_of_working_days($from1, $to1) {
//        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
//        $holidayDays = explode(",", config('face.HOLYDAYS')); # variable and fixed holidays

        $from = new \DateTime($from1);
        $to = new \DateTime($to1);
        $to->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $periods = new \DatePeriod($from, $interval, $to);

        $days = 0;
        $dates = [];
        foreach ($periods as $period) {
//            if (!in_array($period->format('N'), $workingDays))
//                continue;
//            if (in_array($period->format('Y-m-d'), $holidayDays))
//                continue;
//            if (in_array($period->format('*-m-d'), $holidayDays))
//                continue;
            $days++;
            $dates[$period->format('d/m/Y')] = '';
        }
        return $dates;
    }

    protected function test_conn_with_auth($ip_server = null) {
        $data_post_auth = '{
                "userName": "system",
                "ipAddress": "",
                "clientType": "WINPC_V2"}';

        /** $server AMBIL DARI SETTINGAN di table setting* */
        $ch_token = curl_init("https://$ip_server/brms/api/v1.0/accounts/authorize");

        curl_setopt($ch_token, CURLOPT_POSTFIELDS, $data_post_auth);
        curl_setopt($ch_token, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_token, CURLOPT_CUSTOMREQUEST, 'POST');
        $result_token = curl_exec($ch_token);
//        var_dump($result_token);exit;
//        $httpcode_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
        curl_close($ch_token);
        $decoded_res_token = json_decode($result_token, 1);
        if (!$decoded_res_token) {
            echo json_encode(
                    array(
                        'status' => 'fail',
                        'message' => 'connection fail'
                    )
            );
        } else {
            if (empty($decoded_res_token['randomKey'])) {

                echo json_encode(
                        array(
                            'status' => 'fail',
                            'message' => 'connection fail. Data not available'
                        )
                );
            }
            echo json_encode(
                    array(
                        'status' => 'success',
                        'message' => 'connection succeed'
                    )
            );
        }
    }

    protected function do_auth($ip_server = null) {
        $data_post_auth = '{
                "userName": "system",
                "ipAddress": "",
                "clientType": "WINPC_V2"}';
        $server = config('face.API_FACEAPI_DOMAIN');
        if (empty($ip_server)) {
            
        } else {
            $server = $ip_server;
        }
        /** $server AMBIL DARI SETTINGAN di table setting* */
        $ch_token = curl_init("https://" . $server . "/brms/api/v1.0/accounts/authorize");

        curl_setopt($ch_token, CURLOPT_POSTFIELDS, $data_post_auth);
        curl_setopt($ch_token, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch_token, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_token, CURLOPT_CUSTOMREQUEST, 'POST');
        $result_token = curl_exec($ch_token);
        //       var_dump($result_token);exit;
//        $httpcode_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
        curl_close($ch_token);
        $decoded_res_token = json_decode($result_token, 1);
//        dd($decoded_res_token);
        $_realm = empty($decoded_res_token["realm"]) ? 'DSS' : $decoded_res_token["realm"];
        $_rndkey = $decoded_res_token["randomKey"];
        $_pubkey = $decoded_res_token["publickey"];
        $_enctyp = $decoded_res_token["encryptType"];
//dd($decoded_res_token);
        $userName = config('face.API_DSS_USER');
        $password = config('face.API_DSS_PWD');

        $md5_1 = md5($password);
        $md5_2 = md5("$userName$md5_1");
        $md5_3 = md5($md5_2);
        $prm_md5_4 = "$userName:$_realm:$md5_3";
        $md5_4 = md5($prm_md5_4);

        $signature = md5("$md5_4:$_rndkey");

        /**
         * B. Second authentication
         */
        $data_post2 = array(
            "mac" => "",
            "signature" => "$signature",
            "userName" => "system",
            "randomKey" => "$_rndkey",
            "publicKey" => "$_pubkey",
            "encryptType" => "MD5",
            "ipAddress" => "",
            "clientType" => "WINPC_V2",
            "userType" => "0"
        );
        $encoded_post2 = json_encode($data_post2);
        $ch_token2 = curl_init("https://" . $server . "/brms/api/v1.0/accounts/authorize");
//        $ch_token2 = curl_init("https://" . $server . "/admin/API/v1.0/accounts/authorize");

        curl_setopt($ch_token2, CURLOPT_POSTFIELDS, $encoded_post2);
        curl_setopt($ch_token2, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch_token2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_token2, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch_token2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_token2, CURLOPT_CUSTOMREQUEST, 'POST');
        $result_token2 = curl_exec($ch_token2);

        curl_close($ch_token2);
        $decoded_res_token2 = json_decode($result_token2, 1);
//dd($decoded_res_token2);
        /**
         * C. populate attendance
         * C.1 create Heartbeat every 22 seconds
         */
        $_token = $decoded_res_token2["token"];
        $zone = config('face.API_ZONE');
        $_token = date('Ymd') . "|" . date('H:i:s') . "|$_token";
        $now = date('Y-m-d H:i:s');
        $this->log_event([], $_token, $now, 'do-auth');
        Storage::disk('local')->put('_token.txt', $_token);
        Storage::disk('local')->put('_run_cron.txt', "Y");
    }

    protected function do_heartbeat($ip_server = null, $exploded_isi_token) {
        $_token = trim($exploded_isi_token[2]);
        $server = config('face.API_FACEAPI_DOMAIN');
        if (empty($ip_server)) {
            
        } else {
            $server = $ip_server;
        }
        $ch = curl_init("https://" . $server . "/brms/api/v1.0/accounts/keepalive");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type:application/json;charset=UTF-8',
            'X-Subject-Token:' . $_token
                )
        );
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $result = curl_exec($ch);
        //var_dump($result);die();
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //dd($httpcode);
        curl_close($ch);
        $zone = config('face.API_ZONE');
        $exploded_isi_token[3] = strtotime('now');
        $imploded_isi = implode("|", $exploded_isi_token);
        Storage::disk('local')->put('_token.txt', $imploded_isi);
        $now = date('Y-m-d H:i:s');
//        $this->log_event([], 'do_heartbeat', $now, 'do_heartbeat');        
    }

    protected function log_event($params, $responses, $saveNow = '', $url = '') {
        $newLog = new RequestLog();

        if (empty($saveNow)) {
            $now = date('Y-m-d H:i:s');
        } else {
            $now = $saveNow;
        }

        $newLog->transaction_type = 'ATTENDANCE';
        $newLog->url = $url;
        $newLog->params = json_encode($params);
        $newLog->response_status = 'OK';
        $newLog->response_message = json_encode($responses);
        $newLog->created_at = $now;
        $newLog->save();
    }

    protected function insert_access($ops_unit, $att, $direction, $now) {
        if (AccessControl::where(
                        [
                            ['personid', '=', $att->personId],
                            ['channelname', '=', $att->channelName],
                            ['alarmtime', '=', date('Y-m-d H:i:s', $att->alarmTime)],
                        ])->count() > 0) {
            // user found
        } else {
            $newAccess = new AccessControl();
            foreach ($att as $idx => $vals) {
                $newAccess->{strtolower($idx)} = $vals;
            }
            $newAccess->alarmtime = date('Y-m-d H:i:s', $att->alarmTime);
//            if (strtoupper($att->channelName) == 'DOOR2') {
//                $direction = "OUT";
//            } else {
//                $direction = "IN";
//            }
            unset($newAccess->alarmtypename);
            unset($newAccess->captureimageurl);
            $newAccess->accesstype = $direction;
            $newAccess->unit_name = $ops_unit;
            $newAccess->created_at = $now;
            $newAccess->save();
        }
    }
}
