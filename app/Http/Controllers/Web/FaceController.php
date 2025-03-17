<?php

namespace App\Http\Controllers\Web;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AccessControl;
use App\Models\WorkerGroup;
use DataTables;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FaceController extends BaseController
{

    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests;

    public function report(Request $request)
    {
        $dataview['group'] = WorkerGroup::latest()->get();
        if ($request->ajax()) {
            $data = AccessControl::latest()->get();
            return Datatables::of($data)
                ->make(true);
        }

        return view('report/index', $dataview);
    }

    public function report_montly(Request $request)
    {
        $dataview['group'] = WorkerGroup::latest()->get();

        $sdate = $request->get('startdate');
        if (empty($sdate)) {
            $sdate = date('Y-m-d');
        }
        $enddate = $request->get('enddate');
        if (empty($enddate)) {
            $enddate = date('Y-m-d');
        }
        $group = trim($request->get('group'));

        $dataview['sdate'] = $sdate;
        $dataview['edate'] = $enddate;
        $dataview['datatrx'] = [];
        return view('report/report', $dataview);
    }

    public function report_formatted(Request $request)
    {
        $dataview['group'] = WorkerGroup::latest()->get();
        if ($request->ajax()) {
            $data = AccessControl::latest()->get();
            return Datatables::of($data)
                ->make(true);
        }
        //        return view('report/index_pretty_des22');
        return view('report/index_pretty', $dataview);
    }

    public function getData_att(Request $request)
    {
        if ($request->ajax()) {
            $data = Attendance::latest()->get();
            return Datatables::of($data)
                ->make(true);
        }
        //        var_dump($request);
    }

    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $data = AccessControl::latest()->get();
            return Datatables::of($data)
                ->make(true);
        }
        //        var_dump($request);
    }
    public function getEmployees(Request $request)
    {
        $group = $request->get('group');

        $employees = DB::table('fa_person')
            ->select('personid', 'firstname')
            ->when($group !== 'all', function ($query) use ($group) {
                return $query->where('orgcode', $group);
            })
            ->orderBy('firstname', 'asc')
            ->get();

        return response()->json($employees);
    }

    public function getDataFormatted(Request $request)
    {
        if ($request->ajax()) {
            $zone = env('API_ZONE', 'MY');
            if ($zone == 'MY') {
                date_default_timezone_set('Asia/Kuala_Lumpur');
            } else {
                date_default_timezone_set('Asia/Jakarta');
            }
            /**
             * Setting day
             */
            $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
            $setting_sdate = explode(" ", $report_setting->startdate);
            $setting_edate = explode(" ", $report_setting->enddate);

            /**
             * kalau enddate antara 00:01 - 11:59 pagi,
             * brarti in / out di range tsb, masih masuk ke hari sebelumnya
             * 
             * 2023-10
             * 
             * added setting start end time for night work
             * 
             *         'starttime_nightwork',
             *         'endtime_nightwork',
             */
            $nightwork_starttime = $report_setting->starttime_nightwork;
            $nightwork_endtime = $report_setting->endtime_nightwork;

            if ($setting_sdate[0] !== $setting_edate[0]) {
                //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
            }

            //            dd($report_setting->startdate);
            //            $strdate = $request->get('startdate');
            $enddate = $request->get('enddate');
            $sdate = $request->get('startdate');
            $group = trim($request->get('group'));
            $employee = $request->get('employee');
            if (!empty($sdate)) {
                $strdate = "$sdate $setting_sdate[1]";
            } else {
                $strdate = "$enddate $setting_sdate[1]";
            }
            //            var_dump([(date('His')),intval(str_replace(":", "", $nightwork_endtime)), intval(str_replace(":", "", substr($setting_edate[1],0,5)))]);
            //            echo "<br/>";


            /**
             * IF end time of setting normal work > end time of setting night work
             */
            if (intval(str_replace(":", "", substr($setting_edate[1], 0, 5))) > intval(str_replace(":", "", $nightwork_endtime))) {
                $endtimework = $setting_edate[1];
            } else {
                $endtimework = $nightwork_endtime;
            }

            $enddate_01 = "$enddate 00:00:01";
            if (intval(date('His')) >= intval(str_replace(":", "", $endtimework))) {
                //                var_dump(date('His'));
                //                echo "<br/>";
                $enddate1 = new \DateTime($enddate);
                $enddate1->modify('+1 day');
                $enddate_01 = "{$enddate1->format('Y-m-d')} 00:00:01";
                $enddate = "{$enddate1->format('Y-m-d')} $endtimework";
                //                $strdate = "$enddate 00:00:01";
                //                $enddate = "$enddate 23:59:59";
                //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
            } else {
                if ($setting_sdate[0] !== $setting_edate[0]) {
                    $startdate1 = new \DateTime($enddate);
                    $enddate = $startdate1->format("Y-m-d") . " $endtimework";
                    $enddate_01 = "{$startdate1->format('Y-m-d')} 00:00:01";
                    $startdate1->modify('-1 day');
                    $strdate = $startdate1->format("Y-m-d") . " $setting_sdate[1]";
                } else {
                    $enddate = "$enddate $endtimework";
                }
            }
            //            var_dump([$setting_sdate, $setting_edate]);
            //            echo "<br/>";
            //            var_dump([$setting_sdate[0], $setting_edate[0]]);
            //            echo "<br/>";
            //            var_dump([$strdate, $enddate]);
            //            echo "<br/>";

            $search_val_all = $request->get('searchbox');
            $w_personid = '1=1';
            //            var_dump($search_val_all);
            if (!empty($search_val_all)) {
                $date_search = \DateTime::createFromFormat('Y-m-d', $search_val_all);
                if ($date_search) {
                    $strdate1 = $date_search->format('Y-m-d');
                    $strdate = "$strdate1 $setting_sdate[1]";
                    $enddate1 = date('Y-m-d', strtotime($strdate1 . ' +1 day'));
                    $enddate_01 = "$enddate1 00:00:01";
                    $enddate = "$enddate1 $setting_edate[1]";
                    //                    var_dump([$strdate, $enddate]);
                    $searchwhere = "%$search_val_all%";
                    $data = DB::table('fa_accesscontrol')
                        ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                            $query->where(function ($query2) use ($strdate, $enddate_01) {
                                $query2->where('alarmtime', '>=', $strdate);
                                $query2->where('alarmtime', '<', $enddate_01);
                            });
                            $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                                $query2->where('alarmtime', '>=', $enddate_01);
                                $query2->where('alarmtime', '<=', $enddate);
                                $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                            });
                        })->where(function ($query2) use ($group) {
                            if (empty($group) || $group == 'ALL') {
                            } else {
                                $query2->orWhere('fa_person.orgcode', '=', $group);
                            }
                        })
                        ->where(function ($query2) use ($employee) {
                            if (empty($employee) || $employee == 'ALL') {
                            } else {
                                $query2->orWhere('fa_person.personid', '=', $employee);
                            }
                        })
                        ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                        ->get();
                } else {
                    $w_personid = "((fa_accesscontrol.personid ilike '%" . $search_val_all . "%')";
                    $w_personid .= " or (fa_accesscontrol.firstname ilike '%" . $search_val_all . "%'))";
                    //                    dd([$w_personid, $search_val_all]);
                    //                    var_dump([$strdate, $enddate,$enddate_01]);                    
                    $searchwhere = "%$search_val_all%";
                    $data = DB::table('fa_accesscontrol')
                        ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                            $query->where(function ($query2) use ($strdate, $enddate_01) {
                                $query2->where('alarmtime', '>=', $strdate);
                                $query2->where('alarmtime', '<', $enddate_01);
                            });
                            $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                                $query2->where('alarmtime', '>=', $enddate_01);
                                $query2->where('alarmtime', '<=', $enddate);
                                $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                            });
                        })
                        ->where(function ($query1) use ($searchwhere) {
                            $query1->orWhere('fa_accesscontrol.personid', 'ilike', $searchwhere);
                            $query1->orWhere('fa_person.orgname', 'ilike', $searchwhere);
                            $query1->orWhere('fa_accesscontrol.firstname', 'ilike', $searchwhere);
                        })
                        ->where(function ($query2) use ($group) {
                            if (empty($group) || $group == 'ALL') {
                            } else {
                                $query2->orWhere('fa_person.orgcode', '=', $group);
                            }
                        })
                        ->where(function ($query2) use ($employee) {
                            if (empty($employee) || $employee == 'ALL') {
                            } else {
                                $query2->orWhere('fa_person.personid', '=', $employee);
                            }
                        })
                        ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                        ->get();
                }
            } else {
                //                dd([$strdate,$enddate]);
                $data = DB::table('fa_accesscontrol')
                    ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                        $query->where(function ($query2) use ($strdate, $enddate_01) {
                            $query2->where('alarmtime', '>=', $strdate);
                            $query2->where('alarmtime', '<', $enddate_01);
                        });
                        $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                            $query2->where('alarmtime', '>=', $enddate_01);
                            $query2->where('alarmtime', '<=', $enddate);
                            $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                        });
                    })
                    ->where(function ($query2) use ($group) {
                        if (empty($group) || $group == 'ALL') {
                        } else {
                            $query2->orWhere('fa_person.orgcode', '=', $group);
                        }
                    })
                    ->where(function ($query2) use ($employee) {
                        if (empty($employee) || $employee == 'ALL') {
                        } else {
                            $query2->orWhere('fa_person.personid', '=', $employee);
                        }
                    })
                    ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                    //                    ->orWhere('personid','ilike',"%".$search_val_all."%")
                    //                    ->whereRaw($w_personid)
                    ->get();
            }
            //            $data->dd();
            //            dd([$strdate, $enddate, $w_personid]);

            $arr_data = $data->toArray();
            //            echo json_encode($arr_data);die();
            if (!$arr_data || count($arr_data) < 1) {
                return Datatables::of($data)
                    ->make(true);
            }

            $swipetime = [];
            $new_data = [];
            $format = 'Y-m-d H:i:s';
            foreach ($arr_data as $dt_access) {
                if (empty($new_data[$dt_access->personid])) {
                    $new_data[$dt_access->personid] = $dt_access;
                    //                    $new_data[$dt_access->personid]->nama_personnel = $dt_access->personid;
                    //                    $new_data[$dt_access->personid]->worker_id = $dt_access->firstname;
                    $nm = wordwrap($dt_access->personid, 8, "\n", true);
                    $new_data[$dt_access->personid]->nama_personnel = $nm;
                    $fn = wordwrap($dt_access->firstname, 8, "\n", true);
                    $new_data[$dt_access->personid]->worker_id = $fn;
                    //                    $new_data[$dt_access->personid]->nama_personnel = '-';
                    for ($loopInOt = 0; $loopInOt < 6; $loopInOt++) {
                        $new_data[$dt_access->personid]->{"time_in_$loopInOt"} = '';
                        $new_data[$dt_access->personid]->{"time_ot_$loopInOt"} = '';
                    }
                }
                $swipetime[$dt_access->personid][$dt_access->accesstype][] = $dt_access->alarmtime;
            }
            //            echo json_encode($swipetime);die();
            /** sorting first IN and/or Last OUT * */
            $default_element_time = ['', '', '', '', '', ''];
            foreach ($swipetime as $personid => $direct) {
                //Per direction IN/OUT
                $swipetime[$personid]['IN'] = $default_element_time;
                $swipetime[$personid]['OUT'] = $default_element_time;
                $swipetime[$personid]['first_in'] = '0';
                $swipetime[$personid]['last_out'] = '0';
                $new_data[$personid]->work_date = '-';
                $new_data[$personid]->ot = '-';
                $new_data[$personid]->first_in = '0';
                $new_data[$personid]->last_out = '0';

                //                var_dump($direct);echo PHP_EOL;
                /**
                 * Sorting
                 * IN ascending timestamp
                 * OUT descending timestamp
                 * 
                 * Becarefull for night work
                 * 
                 * OUT timestamp must greater than first IN timestamp
                 */
                if (empty($direct['IN'])) {
                    $swipetime[$personid]['IN'] = [];
                    $swipetime[$personid]['OUT'] = [];
                } else {
                    $dir_in = array_values($direct['IN']);
                    sort($dir_in);
                    /**
                     * Data dengan selisih kurang dari 1 menit, dianggap duplikat
                     */
                    $intval_dir_in = preg_replace('/[^0-9]/', '', $dir_in[0]) + 60;
                    foreach ($dir_in as $k => $v) {
                        if ($k < 1) {
                            continue;
                        }

                        $intval_v = preg_replace('/[^0-9]/', '', $v);
                        if (intval($intval_v) > $intval_dir_in) {
                            $intval_dir_in = intval($intval_v) + 60;
                        } else {
                            array_splice($dir_in, $k, 1);
                        }
                    }

                    $direct['IN'] = $dir_in;
                    $listtime_in = (array_slice($dir_in, 0, 6)) + $default_element_time;
                    $swipetime[$personid]['IN'] = $listtime_in;
                    $first_in = $dir_in[0];

                    if (empty($direct['OUT'])) {
                        $swipetime[$personid]['OUT'] = [];
                    } else {
                        $intval_in = intval(preg_replace('/[^0-9]/', '', $first_in)) + 60;
                        //                        $dir_out = array_filter($direct['OUT'], function ($v) use ($intval_in) {
                        //                            $intval_v = preg_replace('/[^0-9]/', '', $v);
                        //                            return intval($intval_v) > $intval_in;
                        //                        });
                        $dir_out = $direct['OUT'];
                        sort($dir_out);
                        foreach ($dir_out as $k => $v) {
                            $intval_v = preg_replace('/[^0-9]/', '', $v);
                            if (intval($intval_v) > $intval_in) {
                                $intval_in = intval($intval_v) + 60;
                                //echo "$intval_in <br/>";
                            } else {
                                //                                echo "$k <br/>";
                                $dir_out[$k] = '';
                                //                                array_splice($dir_out, $k, 1);
                            }
                        }

                        rsort($dir_out);
                        //                        echo json_encode($dir_out);
                        //                        die();
                        $direct['OUT'] = $dir_out;
                        $swipetime[$personid]['OUT'] = (array_slice($dir_out, 0, 6)) + $default_element_time;
                    }
                }
                //die();
                //                foreach ($direct as $dir => $listtime) {
                //                    if ($dir == 'IN') {
                //                        sort($listtime);
                //                    } else {
                //                        rsort($listtime);
                //                    }
                //                    $listtime = array_slice($listtime, 0, 6);
                //                    $listtime = $listtime + $default_element_time;
                //                    $swipetime[$personid][$dir] = $listtime;
                //                }

                $inning_sorted_up = array_filter($swipetime[$personid]['IN']);
                $outing_sorted_down = array_filter($swipetime[$personid]['OUT']);
                sort($inning_sorted_up);
                rsort($outing_sorted_down);
                if (!empty($inning_sorted_up[0])) {
                    $swipetime[$personid]['first_in'] = $inning_sorted_up[0];
                    $new_data[$personid]->first_in = $inning_sorted_up[0];
                    $date_in = \DateTimeImmutable::createFromFormat($format, $inning_sorted_up[0]);
                    if ($date_in) {
                        $new_data[$personid]->work_date = $date_in->format('Y-m-d');
                    } else {
                        $new_data[$personid]->work_date = '';
                    }
                    if ($date_in && !empty($outing_sorted_down[0])) {
                        $swipetime[$personid]['last_out'] = $outing_sorted_down[0];
                        $new_data[$personid]->last_out = $outing_sorted_down[0];
                        $date_ot = \DateTimeImmutable::createFromFormat($format, $outing_sorted_down[0]);
                        if ($date_ot) {
                            $interval = $date_in->diff($date_ot);
                            $new_data[$personid]->duration = $interval->format('%dD%hH%iM');
                        } else {
                            $new_data[$personid]->duration = '0';
                        }
                    } else {
                        $new_data[$personid]->duration = '0';
                    }
                } else {
                    $new_data[$personid]->duration = '0';
                }
                //var_dump($personid); echo PHP_EOL;
                //dd($swipetime);
                //Time IN/OUT sorting for 6 columns
                $time_in_out = [];
                foreach ($swipetime[$personid]['IN'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "I";
                }
                foreach ($swipetime[$personid]['OUT'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "O";
                }
                //                if($personid == 'SAPRI'){
                //                    var_dump($time_in_out);
                //                }
                sort($time_in_out);
                //                if($personid == 'SAPRI'){
                //                    var_dump($time_in_out);
                //                }
                $loopIn = 0;
                $loopOt = 0;

                $total_rest = 0;
                $start_sum_total_rest = 0;
                $start_sum_out = "";
                $days_rests = 0;
                $hours_rests = 0;
                $minutes_rests = 0;
                foreach ($time_in_out as $timing) {
                    $kode = substr($timing, -1);
                    $dttime = str_replace($kode, '', $timing);
                    if (empty($dttime)) {
                        continue;
                    }
                    if ($kode == 'O') {
                        //                        var_dump($dttime);echo PHP_EOL;
                        //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                        $new_data[$personid]->{"time_ot_$loopOt"} = $dttime;
                        $loopOt++;
                        $start_sum_total_rest = 1;
                        $start_sum_out = $dttime;
                    }
                    if ($kode == 'I') {
                        if ($start_sum_total_rest == 1) {
                            $start_sum_out1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $start_sum_out);
                            $start_sum_in1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $dttime);
                            if ($start_sum_out1 && $start_sum_in1) {
                                //                                var_dump([$start_sum_out1,$start_sum_in1]);
                                //                                dd([$start_sum_out1,$start_sum_in1]);
                                $interval = date_diff($start_sum_out1, $start_sum_in1);
                                //                            $interval = ((DateTimeImmutable)$start_sum_out1)->diff($start_sum_in1);
                                $days_rests = $days_rests + ($interval)->format("%d");
                                $hours_rests = $hours_rests + ($interval)->format("%h");
                                $minutes_rests = $minutes_rests + ($interval)->format("%i");
                            }
                            $start_sum_total_rest = 0;
                            $start_sum_out = "";
                        }
                        //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                        $dttime_wwrap = wordwrap($dttime, 10, "\n", true);
                        $new_data[$personid]->{"time_in_$loopIn"} = $dttime_wwrap;
                        $loopOt = $loopIn;
                        $loopIn++;
                    }
                }
                //Rest Total
                if ($minutes_rests >= 60) {
                    $sisa_jam = round(($minutes_rests / 60));
                    if ($sisa_jam < 1) {
                        $sisa_jam = 0;
                    }
                    $sisa_jam = $hours_rests + $sisa_jam;
                    $minutes_rests = $minutes_rests % 60;
                } else {
                    $sisa_jam = $hours_rests;
                }
                //                $new_data[$personid]->no_urut = count($swipetime) + 1;
                //                $new_data[$personid]->total_rest = "$hours_rests >> $minutes_rests".$sisa_jam . "h" . ($minutes_rests % 60) . "m";
                $new_data[$personid]->total_rest = $days_rests . "d" . $sisa_jam . "h" . ($minutes_rests) . "m";
            }
            //echo json_encode($new_data, 1);die();
            //            dd([$new_data]);

            /**
             * 1. First IN and LAST OUT
             * 2. 
             */
            $total_duration = 0;
            //            foreach ($swipetime as $personid => $direct) {
            //
            //            }
            /**
             * ingat format laporan ada TIME IN - TIME OUT
             */
            $result = array_values($new_data);
            $no = 1;
            foreach ($result as $keyr => $rslt) {
                $result[$keyr]->no_urut = $no;
                $no++;
            }
            //            dd($new_data);
            //            dd($result);
            $dttable = Datatables::of($result)->make(true);
            return $dttable;
        }
        //        var_dump($request);
    }

    public function export(Request $request)
    {
        $zone = env('API_ZONE', 'MY');
        if ($zone == 'MY') {
            date_default_timezone_set('Asia/Kuala_Lumpur');
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        /**
         * Setting day
         */
        $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
        $setting_sdate = explode(" ", $report_setting->startdate);
        $setting_edate = explode(" ", $report_setting->enddate);

        /**
         * kalau enddate antara 00:01 - 11:59 pagi,
         * brarti in / out di range tsb, masih masuk ke hari sebelumnya
         * 
         * 2023-10
         * 
         * added setting start end time for night work
         * 
         *         'starttime_nightwork',
         *         'endtime_nightwork',
         */
        $nightwork_starttime = $report_setting->starttime_nightwork;
        $nightwork_endtime = $report_setting->endtime_nightwork;

        if ($setting_sdate[0] !== $setting_edate[0]) {
            //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
        }

        $enddate = $request->get('enddate');
        $sdate = $request->get('startdate');
        $group = trim($request->get('group'));
        if (!empty($sdate)) {
            $strdate = "$sdate $setting_sdate[1]";
        } else {
            $strdate = "$enddate $setting_sdate[1]";
        }

        /**
         * IF end time of setting normal work > end time of setting night work
         */
        if (intval(str_replace(":", "", substr($setting_edate[1], 0, 5))) > intval(str_replace(":", "", $nightwork_endtime))) {
            $endtimework = $setting_edate[1];
        } else {
            $endtimework = $nightwork_endtime;
        }

        $enddate_01 = "$enddate 00:00:01";
        if (intval(date('His')) >= intval(str_replace(":", "", $endtimework))) {
            $enddate1 = new \DateTime($enddate);
            $enddate1->modify('+1 day');
            $enddate_01 = "{$enddate1->format('Y-m-d')} 00:00:01";
            $enddate = "{$enddate1->format('Y-m-d')} $endtimework";
            //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
        } else {
            if ($setting_sdate[0] !== $setting_edate[0]) {
                $startdate1 = new \DateTime($enddate);
                $enddate = $startdate1->format("Y-m-d") . " $endtimework";
                $enddate_01 = "{$startdate1->format('Y-m-d')} 00:00:01";
                $startdate1->modify('-1 day');
                $strdate = $startdate1->format("Y-m-d") . " $setting_sdate[1]";
            } else {
                $enddate = "$enddate $endtimework";
            }
        }

        $search_val_all = $request->get('searchbox');
        $w_personid = '1=1';
        if (!empty($search_val_all)) {
            $date_search = \DateTime::createFromFormat('Y-m-d', $search_val_all);
            if ($date_search) {
                $strdate1 = $date_search->format('Y-m-d');
                $strdate = "$strdate1 $setting_sdate[1]";
                $enddate1 = date('Y-m-d', strtotime($strdate1 . ' +1 day'));
                $enddate_01 = "$enddate1 00:00:01";
                $enddate = "$enddate1 $setting_edate[1]";
                $searchwhere = "%$search_val_all%";
                $data = DB::table('fa_accesscontrol')
                    ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                        $query->where(function ($query2) use ($strdate, $enddate_01) {
                            $query2->where('alarmtime', '>=', $strdate);
                            $query2->where('alarmtime', '<', $enddate_01);
                        });
                        $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                            $query2->where('alarmtime', '>=', $enddate_01);
                            $query2->where('alarmtime', '<=', $enddate);
                            $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                        });
                    })
                    ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                    ->get();
            } else {
                $w_personid = "((fa_accesscontrol.personid ilike '%" . $search_val_all . "%')";
                $w_personid .= " or (fa_accesscontrol.firstname ilike '%" . $search_val_all . "%'))";
                $searchwhere = "%$search_val_all%";
                $data = DB::table('fa_accesscontrol')
                    ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                        $query->where(function ($query2) use ($strdate, $enddate_01) {
                            $query2->where('alarmtime', '>=', $strdate);
                            $query2->where('alarmtime', '<', $enddate_01);
                        });
                        $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                            $query2->where('alarmtime', '>=', $enddate_01);
                            $query2->where('alarmtime', '<=', $enddate);
                            $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                        });
                    })
                    ->where(function ($query1) use ($searchwhere) {
                        $query1->orWhere('fa_accesscontrol.personid', 'ilike', $searchwhere);
                        $query1->orWhere('fa_person.orgname', 'ilike', $searchwhere);
                        $query1->orWhere('fa_accesscontrol.firstname', 'ilike', $searchwhere);
                    })
                    ->where(function ($query2) use ($group) {
                        if (empty($group) || $group == 'ALL') {
                        } else {
                            $query2->orWhere('fa_person.orgcode', '=', $group);
                        }
                    })
                    ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                    ->get();
            }
        } else {
            $data = DB::table('fa_accesscontrol')
                ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                ->where(function ($query) use ($strdate, $enddate, $enddate_01) {
                    $query->where(function ($query2) use ($strdate, $enddate_01) {
                        $query2->where('alarmtime', '>=', $strdate);
                        $query2->where('alarmtime', '<', $enddate_01);
                    });
                    $query->orWhere(function ($query2) use ($enddate_01, $enddate) {
                        $query2->where('alarmtime', '>=', $enddate_01);
                        $query2->where('alarmtime', '<=', $enddate);
                        $query2->where('fa_accesscontrol.accesstype', 'OUT', $enddate);
                    });
                })
                ->where(function ($query2) use ($group) {
                    if (empty($group) || $group == 'ALL') {
                    } else {
                        $query2->orWhere('fa_person.orgcode', '=', $group);
                    }
                })
                ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                //                    ->orWhere('personid','ilike',"%".$search_val_all."%")
                //                    ->whereRaw($w_personid)
                ->get();
        }
        //            $data->dd();
        //            dd([$strdate, $enddate, $w_personid]);

        $arr_data = $data->toArray();
        if (!$arr_data || count($arr_data) < 1) {
            //
        }

        $swipetime = [];
        $new_data = [];
        $format = 'Y-m-d H:i:s';
        foreach ($arr_data as $dt_access) {
            if (empty($new_data[$dt_access->personid])) {
                $new_data[$dt_access->personid] = $dt_access;
                $new_data[$dt_access->personid]->nama_personnel = $dt_access->personid;
                $new_data[$dt_access->personid]->worker_id = $dt_access->firstname;
                for ($loopInOt = 0; $loopInOt < 6; $loopInOt++) {
                    $new_data[$dt_access->personid]->{"time_in_$loopInOt"} = '';
                    $new_data[$dt_access->personid]->{"time_ot_$loopInOt"} = '';
                }
            }
            $swipetime[$dt_access->personid][$dt_access->accesstype][] = $dt_access->alarmtime;
        }
        /** sorting first IN and/or Last OUT * */
        $default_element_time = ['', '', '', '', '', ''];
        foreach ($swipetime as $personid => $direct) {
            //Per direction IN/OUT
            $swipetime[$personid]['IN'] = $default_element_time;
            $swipetime[$personid]['OUT'] = $default_element_time;
            $swipetime[$personid]['first_in'] = '0';
            $swipetime[$personid]['last_out'] = '0';
            $new_data[$personid]->work_date = '-';
            $new_data[$personid]->ot = '-';
            $new_data[$personid]->first_in = '0';
            $new_data[$personid]->last_out = '0';

            //                var_dump($direct);echo PHP_EOL;
            /**
             * Sorting
             * IN ascending timestamp
             * OUT descending timestamp
             * 
             * Becarefull for night work
             * 
             * OUT timestamp must greater than first IN timestamp
             */
            if (empty($direct['IN'])) {
                $swipetime[$personid]['IN'] = [];
                $swipetime[$personid]['OUT'] = [];
            } else {
                $dir_in = array_values($direct['IN']);
                sort($dir_in);
                /**
                 * Data dengan selisih kurang dari 1 menit, dianggap duplikat
                 */
                $intval_dir_in = preg_replace('/[^0-9]/', '', $dir_in[0]) + 60;
                foreach ($dir_in as $k => $v) {
                    if ($k < 1) {
                        continue;
                    }

                    $intval_v = preg_replace('/[^0-9]/', '', $v);
                    if (intval($intval_v) > $intval_dir_in) {
                        $intval_dir_in = intval($intval_v) + 60;
                    } else {
                        array_splice($dir_in, $k, 1);
                    }
                }

                $direct['IN'] = $dir_in;
                $listtime_in = (array_slice($dir_in, 0, 6)) + $default_element_time;
                $swipetime[$personid]['IN'] = $listtime_in;
                $first_in = $dir_in[0];

                if (empty($direct['OUT'])) {
                    $swipetime[$personid]['OUT'] = [];
                } else {
                    $intval_in = intval(preg_replace('/[^0-9]/', '', $first_in)) + 60;
                    $dir_out = $direct['OUT'];
                    sort($dir_out);
                    foreach ($dir_out as $k => $v) {
                        $intval_v = preg_replace('/[^0-9]/', '', $v);
                        if (intval($intval_v) > $intval_in) {
                            $intval_in = intval($intval_v) + 60;
                        } else {
                            $dir_out[$k] = '';
                        }
                    }

                    rsort($dir_out);
                    $direct['OUT'] = $dir_out;
                    $swipetime[$personid]['OUT'] = (array_slice($dir_out, 0, 6)) + $default_element_time;
                }
            }

            $inning_sorted_up = array_filter($swipetime[$personid]['IN']);
            $outing_sorted_down = array_filter($swipetime[$personid]['OUT']);
            sort($inning_sorted_up);
            rsort($outing_sorted_down);
            if (!empty($inning_sorted_up[0])) {
                $swipetime[$personid]['first_in'] = $inning_sorted_up[0];
                $new_data[$personid]->first_in = $inning_sorted_up[0];
                $date_in = \DateTimeImmutable::createFromFormat($format, $inning_sorted_up[0]);
                if ($date_in) {
                    $new_data[$personid]->work_date = $date_in->format('Y-m-d');
                } else {
                    $new_data[$personid]->work_date = '';
                }
                if ($date_in && !empty($outing_sorted_down[0])) {
                    $swipetime[$personid]['last_out'] = $outing_sorted_down[0];
                    $new_data[$personid]->last_out = $outing_sorted_down[0];
                    $date_ot = \DateTimeImmutable::createFromFormat($format, $outing_sorted_down[0]);
                    if ($date_ot) {
                        $interval = $date_in->diff($date_ot);
                        $new_data[$personid]->duration = $interval->format('%dD%hH%iM');
                    } else {
                        $new_data[$personid]->duration = '0';
                    }
                } else {
                    $new_data[$personid]->duration = '0';
                }
            } else {
                $new_data[$personid]->duration = '0';
            }
            //var_dump($personid); echo PHP_EOL;
            //dd($swipetime);
            //Time IN/OUT sorting for 6 columns
            $time_in_out = [];
            foreach ($swipetime[$personid]['IN'] as $dt_ins) {
                $time_in_out[] = "$dt_ins" . "I";
            }
            foreach ($swipetime[$personid]['OUT'] as $dt_ins) {
                $time_in_out[] = "$dt_ins" . "O";
            }

            sort($time_in_out);

            $loopIn = 0;
            $loopOt = 0;

            $total_rest = 0;
            $start_sum_total_rest = 0;
            $start_sum_out = "";
            $days_rests = 0;
            $hours_rests = 0;
            $minutes_rests = 0;
            foreach ($time_in_out as $timing) {
                $kode = substr($timing, -1);
                $dttime = str_replace($kode, '', $timing);
                if (empty($dttime)) {
                    continue;
                }
                if ($kode == 'O') {
                    //                        var_dump($dttime);echo PHP_EOL;
                    //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                    $new_data[$personid]->{"time_ot_$loopOt"} = $dttime;
                    $loopOt++;
                    $start_sum_total_rest = 1;
                    $start_sum_out = $dttime;
                }
                if ($kode == 'I') {
                    if ($start_sum_total_rest == 1) {
                        $start_sum_out1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $start_sum_out);
                        $start_sum_in1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $dttime);
                        if ($start_sum_out1 && $start_sum_in1) {
                            $interval = date_diff($start_sum_out1, $start_sum_in1);
                            //                            $interval = ((DateTimeImmutable)$start_sum_out1)->diff($start_sum_in1);
                            $days_rests = $days_rests + ($interval)->format("%d");
                            $hours_rests = $hours_rests + ($interval)->format("%h");
                            $minutes_rests = $minutes_rests + ($interval)->format("%i");
                        }
                        $start_sum_total_rest = 0;
                        $start_sum_out = "";
                    }
                    //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                    $new_data[$personid]->{"time_in_$loopIn"} = $dttime;
                    $loopOt = $loopIn;
                    $loopIn++;
                }
            }
            //Rest Total
            if ($minutes_rests >= 60) {
                $sisa_jam = round(($minutes_rests / 60));
                if ($sisa_jam < 1) {
                    $sisa_jam = 0;
                }
                $sisa_jam = $hours_rests + $sisa_jam;
                $minutes_rests = $minutes_rests % 60;
            } else {
                $sisa_jam = $hours_rests;
            }
            //                $new_data[$personid]->no_urut = count($swipetime) + 1;
            //                $new_data[$personid]->total_rest = "$hours_rests >> $minutes_rests".$sisa_jam . "h" . ($minutes_rests % 60) . "m";
            $new_data[$personid]->total_rest = $days_rests . "d" . $sisa_jam . "h" . ($minutes_rests) . "m";
        }
        //echo json_encode($new_data, 1);die();
        //            dd([$new_data]);

        /**
         * 1. First IN and LAST OUT
         * 2. 
         */
        $total_duration = 0;
        //            foreach ($swipetime as $personid => $direct) {
        //
        //            }
        /**
         * ingat format laporan ada TIME IN - TIME OUT
         */
        $result_report = array_values($new_data);

        //            dd($new_data);
        //        dd($result);
        //        $dttable = Datatables::of($result)->make(true);
        $filename = "Time_attendance_" . date('YmdHis_') . uniqid() . '.xlsx';
        $file_name_url = "storage/app/public/$filename";
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', "No.");
        $sheet->setCellValue('B1', "Group");
        $sheet->setCellValue('C1', "Emp. Code");
        $sheet->setCellValue('D1', "Emp. Name");
        $sheet->setCellValue('E1', "Work Date");
        $sheet->setCellValue('F1', "Time");
        $sheet->setCellValue('H1', "Time");
        $sheet->setCellValue('J1', "Time");
        $sheet->setCellValue('L1', "Time");
        $sheet->setCellValue('N1', "Time");
        $sheet->setCellValue('P1', "Time");
        $sheet->setCellValue('R1', "First IN");
        $sheet->setCellValue('S1', "Last OUT");
        $sheet->setCellValue('T1', "Total Dur.");
        $sheet->setCellValue('U1', "Total Rest");
        $sheet->setCellValue('V1', "OT Hours");

        $sheet->setCellValue('F2', " IN");
        $sheet->setCellValue('G2', "OUT");
        $sheet->setCellValue('H2', " IN");
        $sheet->setCellValue('I2', "OUT");
        $sheet->setCellValue('J2', " IN");
        $sheet->setCellValue('K2', "OUT");
        $sheet->setCellValue('L2', " IN");
        $sheet->setCellValue('M2', "OUT");
        $sheet->setCellValue('N2', " IN");
        $sheet->setCellValue('O2', "OUT");
        $sheet->setCellValue('P2', " IN");
        $sheet->setCellValue('Q2', "OUT");

        $counter = 3;
        $id = 1;
        for ($i = 0; $i < count($result_report); $i++) {
            $row = (array) $result_report[$i];
            $sheet->setCellValue('A' . ($counter), $id);
            $sheet->setCellValue('B' . ($counter), $row['orgname']);
            $sheet->setCellValue('C' . ($counter), $row['worker_id']);
            $sheet->setCellValue('D' . ($counter), $row['nama_personnel']);
            $sheet->setCellValue('E' . ($counter), $row['work_date']);
            $sheet->setCellValue('F' . ($counter), $row['time_in_0']);
            $sheet->setCellValue('G' . ($counter), $row['time_ot_0']);
            $sheet->setCellValue('H' . ($counter), $row['time_in_1']);
            $sheet->setCellValue('I' . ($counter), $row['time_ot_1']);
            $sheet->setCellValue('J' . ($counter), $row['time_in_2']);
            $sheet->setCellValue('K' . ($counter), $row['time_ot_2']);
            $sheet->setCellValue('L' . ($counter), $row['time_in_3']);
            $sheet->setCellValue('M' . ($counter), $row['time_ot_3']);
            $sheet->setCellValue('N' . ($counter), $row['time_in_4']);
            $sheet->setCellValue('O' . ($counter), $row['time_ot_4']);
            $sheet->setCellValue('P' . ($counter), $row['time_in_5']);
            $sheet->setCellValue('Q' . ($counter), $row['time_ot_5']);
            $sheet->setCellValue('R' . ($counter), $row['first_in']);
            $sheet->setCellValue('S' . ($counter), $row['last_out']);
            $sheet->setCellValue('T' . ($counter), $row['duration']);
            $sheet->setCellValue('U' . ($counter), $row['total_rest']);
            $sheet->setCellValue('V' . ($counter), $row['ot']);

            $counter++;
            $id++;
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($file_name_url);
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return response()->download($file_name_url, $filename, $headers)->deleteFileAfterSend(true);
    }

    public function export_monthly(Request $request)
    {
        $zone = env('API_ZONE', 'ID');
        if ($zone == 'MY') {
            date_default_timezone_set('Asia/Kuala_Lumpur');
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
        /**
         * Setting day
         */
        $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
        $setting_sdate = explode(" ", $report_setting->startdate);
        $time_sdate = substr($setting_sdate[1], 0, 5);
        $time_sdate_cast = intval(preg_replace('/[^0-9]/', '', $time_sdate));
        $setting_edate = explode(" ", $report_setting->enddate);

        /**
         * kalau enddate antara 00:01 - 11:59 pagi,
         * brarti in / out di range tsb, masih masuk ke hari sebelumnya
         * 
         * 2023-10
         * 
         * added setting start end time for night work
         * 
         *         'starttime_nightwork',
         *         'endtime_nightwork',
         */
        $nightwork_starttime = $report_setting->starttime_nightwork;
        $nightwork_endtime = $report_setting->endtime_nightwork;

        if ($setting_sdate[0] !== $setting_edate[0]) {
            //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
        }

        //            dd($report_setting->startdate);
        //            $strdate = $request->get('startdate');
        $startdate_ori = $sdate = $request->get('startdate');
        $enddate_ori = $enddate = $request->get('enddate');
        $group = trim($request->get('group'));
        if (!empty($sdate)) {
            $strdate = "$sdate $setting_sdate[1]";
        } else {
            $strdate = "$enddate $setting_sdate[1]";
        }
        //            var_dump([intval(date('His')), intval(str_replace(":", "", $setting_edate[1]))]);
        //            echo "<br/>";
        /**
         * IF end time of setting normal work > end time of setting night work
         */
        if (intval(str_replace(":", "", substr($setting_edate[1], 0, 5))) > intval(str_replace(":", "", $nightwork_endtime))) {
            $endtimework = $setting_edate[1];
        } else {
            $endtimework = $nightwork_endtime;
        }
        if (intval(date('His')) >= intval(str_replace(":", "", $endtimework))) {
            //                var_dump(date('His'));
            //                echo "<br/>";
            $enddate1 = new \DateTime($enddate);
            $enddate1->modify('+1 day');
            $enddate = "{$enddate1->format('Y-m-d')} $endtimework";
            //                $strdate = "$enddate 00:00:01";
            //                $enddate = "$enddate 23:59:59";
            //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
        } else {
            if ($setting_sdate[0] !== $setting_edate[0]) {
                $startdate1 = new \DateTime($enddate);
                $enddate = $startdate1->format("Y-m-d") . " $endtimework";
                $startdate1->modify('-1 day');
                $strdate = $startdate1->format("Y-m-d") . " $setting_sdate[1]";
            } else {
                $enddate = "$enddate $endtimework";
            }
        }
        //            var_dump([$setting_sdate, $setting_edate]);
        //            echo "<br/>";
        //            var_dump([$setting_sdate[0], $setting_edate[0]]);
        //            echo "<br/>";

        $search_val_all = $request->get('searchbox');
        $w_personid = '1=1';
        //            var_dump($search_val_all);
        if (!empty($search_val_all)) {
            $date_search = \DateTime::createFromFormat('Y-m-d', $search_val_all);
            if ($date_search) {
                $strdate1 = $date_search->format('Y-m-d');
                $strdate = "$strdate1 $setting_sdate[1]";
                $enddate1 = date('Y-m-d', strtotime($strdate1 . ' +1 day'));
                $enddate = "$enddate1 $setting_edate[1]";
                //                    var_dump([$strdate, $enddate]);
                $searchwhere = "%$search_val_all%";
                $data = DB::table('fa_accesscontrol')
                    ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate) {
                        $query->where('alarmtime', '>=', $strdate);
                        $query->where('alarmtime', '<=', $enddate);
                    })
                    ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                    ->get();
            } else {
                $w_personid = "((fa_accesscontrol.personid ilike '%" . $search_val_all . "%')";
                $w_personid .= " or (fa_accesscontrol.firstname ilike '%" . $search_val_all . "%'))";
                //                    dd([$w_personid, $search_val_all]);
                $searchwhere = "%$search_val_all%";
                $data = DB::table('fa_accesscontrol')
                    ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate) {
                        $query->where('alarmtime', '>=', $strdate);
                        $query->where('alarmtime', '<=', $enddate);
                    })
                    ->where(function ($query1) use ($searchwhere) {
                        $query1->orWhere('fa_accesscontrol.personid', 'ilike', $searchwhere);
                        $query1->orWhere('fa_person.orgname', 'ilike', $searchwhere);
                        $query1->orWhere('fa_accesscontrol.firstname', 'ilike', $searchwhere);
                    })
                    ->where(function ($query2) use ($group) {
                        if (empty($group) || $group == 'ALL') {
                        } else {
                            $query2->orWhere('fa_person.orgcode', '=', $group);
                        }
                    })
                    ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                    ->get();
            }
        } else {
            //                dd([$strdate,$enddate]);
            $data = DB::table('fa_accesscontrol')
                ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                ->where(function ($query) use ($strdate, $enddate) {
                    $query->where('alarmtime', '>=', $strdate);
                    $query->where('alarmtime', '<=', $enddate);
                })
                ->where(function ($query2) use ($group) {
                    if (empty($group) || $group == 'ALL') {
                    } else {
                        $query2->orWhere('fa_person.orgcode', '=', $group);
                    }
                })
                ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                //                    ->orWhere('personid','ilike',"%".$search_val_all."%")
                //                    ->whereRaw($w_personid)
                ->get();
        }
        //            $data->dd();
        //            dd([$strdate, $enddate, $w_personid]);

        $arr_data = $data->toArray();
        $list_workdays = $this->list_of_working_days($startdate_ori, $enddate_ori);
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
        //            var_dump($swipetime);die();
        foreach ($swipetime as $personid => $direct) {
            /**
             * Data dengan selisih kurang dari 1 menit, dianggap duplikat
             */
            $dir_in = $direct;
            $intval_dir_in = 0;
            $time_dir_in = "";
            $new_dir = [];
            foreach ($dir_in as $k => $v) {
                $type = substr($v, -1);
                $v_dir = substr($v, 0, -1);

                $intval_v = preg_replace('/[^0-9]/', '', $v_dir);
                if ($k < 1 && $type == "I") {
                    $intval_dir_in = intval($intval_v) + 60;
                    $tgl_v = substr($v, 0, 10);
                    $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v);
                    $tgl_v = (string) $tgl_create->format('d/m/Y');
                    $new_dir[$tgl_v][] = $v;
                    $time_dir_in = $v;
                    continue;
                }
                if ($k < 1 && $type == "O") {
                    continue;
                }

                if (intval($intval_v) > $intval_dir_in) {
                    $tgl_v = substr($v, 0, 10);
                    $time_v = substr($v, 11, 5);
                    $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v);
                    $tgl_v = (string) $tgl_create->format('d/m/Y');
                    $tgl_v_exp = explode("/", $tgl_v);
                    if (empty($new_dir[$tgl_v]) && $type == 'O') {
                        $time_v_cast = intval(preg_replace('/[^0-9]/', '', $time_v));
                        if ($time_v_cast > $time_sdate_cast) {
                        }
                    } else {
                        $new_dir[$tgl_v][] = $v;
                        $time_dir_in = $v;
                    }
                    $intval_dir_in = intval($intval_v) + 60;
                }
            }
            $swipetime[$personid] = $new_dir;
        }

        foreach ($swipetime as $personid => $direct) {
            $loopIn = 0;
            $loopOt = 0;
            $swipetime[$personid] = $direct + $list_workdays;
            $start_sum_out = "";
            $total_days_rests = $total_hours_rests = $total_minutes_rests = 0;
            foreach ($direct as $tgl => $time_in_out) {
                //                var_dump($tgl);echo "<br/>";
                //                var_dump($time_in_out);echo "<br/>";
                $days_rests = $hours_rests = $minutes_rests = 0;
                foreach ($time_in_out as $timing) {
                    $kode = substr($timing, -1);
                    $dttime = str_replace($kode, '', $timing);
                    if (empty($dttime)) {
                        continue;
                    }
                    if ($kode == 'I') {
                        $start_sum_total_rest = 1;
                        $start_sum_out = $dttime;
                    }
                    if ($kode == 'O') {
                        if ($start_sum_total_rest == 1) {
                            $start_sum_out1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $start_sum_out);
                            $start_sum_in1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $dttime);
                            if ($start_sum_out1 && $start_sum_in1) {
                                $interval = date_diff($start_sum_in1, $start_sum_out1);
                                $days_rests = $days_rests + ($interval)->format("%d");
                                $hours_rests = $hours_rests + ($interval)->format("%h");
                                $minutes_rests = $minutes_rests + ($interval)->format("%i");
                            }
                            $start_sum_total_rest = 0;
                            $start_sum_out = "";
                        }
                        //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                    }
                }

                $hours_rests = $hours_rests + (24 * $days_rests);
                if ($minutes_rests >= 60) {
                    $sisa_jam = round(($minutes_rests / 60));
                    $hours_rests = $hours_rests + $sisa_jam;
                    $minutes_rests = $minutes_rests % 60;
                }

                //                    echo "$tgl =".$hours_rests . "h" . ($minutes_rests) . "m"."\n";
                if ($hours_rests + $minutes_rests > 0) {
                    $swipetime[$personid][(string) $tgl] = $hours_rests . "h " . ($minutes_rests) . "m";
                } else {
                    $swipetime[$personid][(string) $tgl] = '';
                }
            }
            $newdata = (array) $new_data[$personid];
            //                var_dump($new_data);echo "<br/>";
            //                var_dump($swipetime[$personid]);echo "<br/>";
            ksort($swipetime[$personid]);
            //                var_dump($swipetime[$personid]);echo "<br/>";
            $new_data[$personid] = (object) array_merge($newdata, $swipetime[$personid]);
        }
        //            echo json_encode($swipetime, 1);
        //            die();
        //            dd([$new_data]);

        /**
         * ingat format laporan ada TIME IN - TIME OUT
         */
        $result_report = array_values($new_data);

        //        $dttable = Datatables::of($result)->make(true);
        $filename = "Report_monthly_" . date('YmdHis_') . uniqid() . '.xlsx';
        $file_name_url = "storage/app/public/$filename";
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', "No.");
        $sheet->getColumnDimension('B')->setAutoSize(false);
        $sheet->getColumnDimension('B')->setWidth(9);
        $sheet->getColumnDimension('C')->setAutoSize(false);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setAutoSize(false);
        $sheet->getColumnDimension('D')->setWidth(29);
        $sheet->setCellValue('B1', "Group");
        $sheet->setCellValue('C1', "Emp. Code");
        $sheet->setCellValue('D1', "Emp. Name");

        $header_date = array_keys($list_workdays);
        $start_char = "E";
        foreach ($header_date as $date_column) {
            $sheet->getColumnDimension($start_char)->setAutoSize(false);
            $sheet->getColumnDimension($start_char)->setWidth(13);
            $sheet->setCellValue($start_char . "1", $date_column);
            $start_char++;
        }
        $counter = 3;
        $id = 1;
        for ($i = 0; $i < count($result_report); $i++) {
            $row = (array) $result_report[$i];
            $sheet->setCellValue('A' . ($counter), $id);
            $sheet->setCellValue('B' . ($counter), $row['orgname']);
            $sheet->setCellValue('C' . ($counter), $row['worker_id']);
            $sheet->setCellValue('D' . ($counter), $row['nama_personnel']);
            $start_char = "E";
            foreach ($header_date as $date_column) {
                if (empty($row[$date_column])) {
                    $valval = "";
                } else {
                    $valval = $row[$date_column];
                }
                $sheet->setCellValue($start_char . ($counter), $valval);
                $start_char++;
            }

            $counter++;
            $id++;
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($file_name_url);
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return response()->download($file_name_url, $filename, $headers)->deleteFileAfterSend(true);
        //        var_dump($request);
    }

    public function getDataMonthly(Request $request)
    {
        if ($request->ajax()) {
            $zone = env('API_ZONE', 'ID');
            if ($zone == 'MY') {
                date_default_timezone_set('Asia/Kuala_Lumpur');
            } else {
                date_default_timezone_set('Asia/Jakarta');
            }
            /**
             * Setting day
             */
            $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
            $setting_sdate = explode(" ", $report_setting->startdate);
            $time_sdate = substr($setting_sdate[1], 0, 5);
            $time_sdate_cast = intval(preg_replace('/[^0-9]/', '', $time_sdate));
            $setting_edate = explode(" ", $report_setting->enddate);

            /**
             * kalau enddate antara 00:01 - 11:59 pagi,
             * brarti in / out di range tsb, masih masuk ke hari sebelumnya
             * 
             * 2023-10
             * 
             * added setting start end time for night work
             * 
             *         'starttime_nightwork',
             *         'endtime_nightwork',
             */
            $nightwork_starttime = $report_setting->starttime_nightwork;
            $nightwork_endtime = $report_setting->endtime_nightwork;

            if ($setting_sdate[0] !== $setting_edate[0]) {
                //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
            }

            //            dd($report_setting->startdate);
            //            $strdate = $request->get('startdate');
            $startdate_ori = $sdate = $request->get('startdate');
            $enddate_ori = $enddate = $request->get('enddate');
            $group = trim($request->get('group'));
            if (!empty($sdate)) {
                $strdate = "$sdate $setting_sdate[1]";
            } else {
                $strdate = "$enddate $setting_sdate[1]";
            }
            //            var_dump([intval(date('His')), intval(str_replace(":", "", $setting_edate[1]))]);
            //            echo "<br/>";
            /**
             * IF end time of setting normal work > end time of setting night work
             */
            if (intval(str_replace(":", "", substr($setting_edate[1], 0, 5))) > intval(str_replace(":", "", $nightwork_endtime))) {
                $endtimework = $setting_edate[1];
            } else {
                $endtimework = $nightwork_endtime;
            }
            if (intval(date('His')) >= intval(str_replace(":", "", $endtimework))) {
                //                var_dump(date('His'));
                //                echo "<br/>";
                $enddate1 = new \DateTime($enddate);
                $enddate1->modify('+1 day');
                $enddate = "{$enddate1->format('Y-m-d')} $endtimework";
                //                $strdate = "$enddate 00:00:01";
                //                $enddate = "$enddate 23:59:59";
                //kalau tanggalnya beda, brarti ada jam day worknya kelewat hari berjalan
            } else {
                if ($setting_sdate[0] !== $setting_edate[0]) {
                    $startdate1 = new \DateTime($enddate);
                    $enddate = $startdate1->format("Y-m-d") . " $endtimework";
                    $startdate1->modify('-1 day');
                    $strdate = $startdate1->format("Y-m-d") . " $setting_sdate[1]";
                } else {
                    $enddate = "$enddate $endtimework";
                }
            }
            //            var_dump([$setting_sdate, $setting_edate]);
            //            echo "<br/>";
            //            var_dump([$setting_sdate[0], $setting_edate[0]]);
            //            echo "<br/>";

            $search_val_all = $request->get('searchbox');
            $w_personid = '1=1';
            //            var_dump($search_val_all);
            if (!empty($search_val_all)) {
                $date_search = \DateTime::createFromFormat('Y-m-d', $search_val_all);
                if ($date_search) {
                    $strdate1 = $date_search->format('Y-m-d');
                    $strdate = "$strdate1 $setting_sdate[1]";
                    $enddate1 = date('Y-m-d', strtotime($strdate1 . ' +1 day'));
                    $enddate = "$enddate1 $setting_edate[1]";
                    //                    var_dump([$strdate, $enddate]);
                    $searchwhere = "%$search_val_all%";
                    $data = DB::table('fa_accesscontrol')
                        ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($strdate, $enddate) {
                            $query->where('alarmtime', '>=', $strdate);
                            $query->where('alarmtime', '<=', $enddate);
                        })
                        ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                        ->orderBy('alarmtime', 'asc')
                        ->get();
                } else {
                    $w_personid = "((fa_accesscontrol.personid ilike '%" . $search_val_all . "%')";
                    $w_personid .= " or (fa_accesscontrol.firstname ilike '%" . $search_val_all . "%'))";
                    //                    dd([$w_personid, $search_val_all]);
                    $searchwhere = "%$search_val_all%";
                    $data = DB::table('fa_accesscontrol')
                        ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($strdate, $enddate) {
                            $query->where('alarmtime', '>=', $strdate);
                            $query->where('alarmtime', '<=', $enddate);
                        })
                        ->where(function ($query1) use ($searchwhere) {
                            $query1->orWhere('fa_accesscontrol.personid', 'ilike', $searchwhere);
                            $query1->orWhere('fa_person.orgname', 'ilike', $searchwhere);
                            $query1->orWhere('fa_accesscontrol.firstname', 'ilike', $searchwhere);
                        })
                        ->where(function ($query2) use ($group) {
                            if (empty($group) || $group == 'ALL') {
                            } else {
                                $query2->orWhere('fa_person.orgcode', '=', $group);
                            }
                        })
                        ->select('fa_accesscontrol.*', 'fa_person.orgcode', 'fa_person.orgname')
                        ->orderBy('alarmtime', 'asc')
                        ->get();
                }
            } else {
                //                dd([$strdate,$enddate, $group]);
                $data = DB::table('fa_accesscontrol')
                    ->select('alarmtime', 'fa_accesscontrol.accesstype', 'fa_accesscontrol.personid', 'fa_accesscontrol.firstname', 'fa_person.orgcode', 'fa_person.orgname')
                    ->join('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                    ->where(function ($query) use ($strdate, $enddate) {
                        $query->where('alarmtime', '>=', $strdate);
                        $query->where('alarmtime', '<=', $enddate);
                    })
                    ->where(function ($query2) use ($group) {
                        if (empty($group) || $group == 'ALL') {
                        } else {
                            $query2->orWhere('fa_person.orgcode', '=', $group);
                        }
                    })->orderBy('alarmtime', 'asc')->get();
                //                        ->get();
                //                    ->orWhere('personid','ilike',"%".$search_val_all."%")
                //                    ->whereRaw($w_personid)
            }
            //            $data->dd();
            //            dd([$strdate, $enddate, $w_personid]);
            //            $arr_data = $data->toArray();
            $arr_data = $data;
            //            array_filter($arr_data);
            //            dd($arr_data);
            //            echo json_encode($arr_data);die();
            if (!$arr_data || count($arr_data) < 1) {
                return Datatables::of($data)
                    ->make(true);
            }
            $list_workdays = $this->list_of_working_days($startdate_ori, $enddate_ori);
            $swipetime = [];
            $new_data = [];
            $format = 'Y-m-d H:i:s';
            foreach ($arr_data as $dt_access) {
                if (empty($new_data[$dt_access->personid])) {
                    $new_data[$dt_access->personid] = new \stdClass();
                    $new_data[$dt_access->personid]->orgname = $dt_access->orgname;
                    $new_data[$dt_access->personid]->orgcode = $dt_access->orgcode;
                    $wrapped_id = wordwrap($dt_access->personid, 5, " ", true);
                    $new_data[$dt_access->personid]->nama_personnel = $wrapped_id;
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
            //            var_dump($swipetime);die();
            foreach ($swipetime as $personid => $direct) {
                /**
                 * Data dengan selisih kurang dari 1 menit, dianggap duplikat
                 */
                $dir_in = $direct;
                $intval_dir_in = 0;
                $time_dir_in = "";
                $new_dir = [];
                foreach ($dir_in as $k => $v) {
                    $type = substr($v, -1);
                    $v_dir = substr($v, 0, -1);

                    $intval_v = preg_replace('/[^0-9]/', '', $v_dir);
                    if ($k < 1 && $type == "I") {
                        $intval_dir_in = intval($intval_v) + 60;
                        $tgl_v = substr($v, 0, 10);
                        $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v);
                        $tgl_v = (string) $tgl_create->format('d/m/Y');
                        $new_dir[$tgl_v][] = $v;
                        $time_dir_in = $v;
                        continue;
                    }
                    if ($k < 1 && $type == "O") {
                        continue;
                    }

                    if (intval($intval_v) > $intval_dir_in) {
                        $tgl_v = substr($v, 0, 10);
                        $time_v = substr($v, 11, 5);
                        $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v);
                        $tgl_v = (string) $tgl_create->format('d/m/Y');
                        $tgl_v_exp = explode("/", $tgl_v);
                        if (empty($new_dir[$tgl_v]) && $type == 'O') {
                            $time_v_cast = intval(preg_replace('/[^0-9]/', '', $time_v));
                            if ($time_v_cast > $time_sdate_cast) {
                            }

                            //                            $tglx = intval($tgl_v_exp[0]) - 1;
                            //                            $tgl_v_exp[0] = str_pad($tglx, 2, "0", STR_PAD_LEFT);
                            //                            $tglbefore = implode("/", $tgl_v_exp);
                            //                            if (!empty($new_dir[$tglbefore])) {
                            ////                                $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tglbefore);
                            ////                                $tglbefore = (String) $tgl_create->format('d/m/Y');
                            //                                $new_dir[$tglbefore][] = $v;
                            //                            }
                        } else {
                            //                            $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v);
                            //                            $tgl_v = (String)$tgl_create->format('d/m/Y');
                            $new_dir[$tgl_v][] = $v;
                            $time_dir_in = $v;
                        }
                        $intval_dir_in = intval($intval_v) + 60;
                    }
                }
                $swipetime[$personid] = $new_dir;
            }

            //            echo json_encode($swipetime);
            //            die();

            /** sorting first IN and/or Last OUT * */
            //            $default_element_time = array_fill(0, count($list_workdays), '');
            foreach ($swipetime as $personid => $direct) {
                $loopIn = 0;
                $loopOt = 0;
                $swipetime[$personid] = $direct + $list_workdays;
                $start_sum_out = "";
                $total_days_rests = $total_hours_rests = $total_minutes_rests = 0;
                foreach ($direct as $tgl => $time_in_out) {
                    //                var_dump($tgl);echo "<br/>";
                    //                var_dump($time_in_out);echo "<br/>";
                    $days_rests = $hours_rests = $minutes_rests = 0;
                    foreach ($time_in_out as $timing) {
                        $kode = substr($timing, -1);
                        $dttime = str_replace($kode, '', $timing);
                        if (empty($dttime)) {
                            continue;
                        }
                        if ($kode == 'I') {
                            //                        var_dump($dttime);echo PHP_EOL;
                            //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                            $start_sum_total_rest = 1;
                            $start_sum_out = $dttime;
                        }
                        if ($kode == 'O') {
                            if ($start_sum_total_rest == 1) {
                                $start_sum_out1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $start_sum_out);
                                $start_sum_in1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $dttime);
                                if ($start_sum_out1 && $start_sum_in1) {
                                    //                                var_dump([$start_sum_out1,$start_sum_in1]);
                                    //                                dd([$start_sum_out1,$start_sum_in1]);
                                    $interval = date_diff($start_sum_in1, $start_sum_out1);
                                    //                            $interval = ((DateTimeImmutable)$start_sum_out1)->diff($start_sum_in1);
                                    $days_rests = $days_rests + ($interval)->format("%d");
                                    $hours_rests = $hours_rests + ($interval)->format("%h");
                                    $minutes_rests = $minutes_rests + ($interval)->format("%i");
                                }
                                $start_sum_total_rest = 0;
                                $start_sum_out = "";
                            }
                            //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                        }
                    }

                    $hours_rests = $hours_rests + (24 * $days_rests);
                    if ($minutes_rests >= 60) {
                        $sisa_jam = round(($minutes_rests / 60));
                        $hours_rests = $hours_rests + $sisa_jam;
                        $minutes_rests = $minutes_rests % 60;
                    }

                    //                    echo "$tgl =".$hours_rests . "h" . ($minutes_rests) . "m"."\n";
                    if ($hours_rests + $minutes_rests > 0) {
                        $swipetime[$personid][(string) $tgl] = $hours_rests . "h " . ($minutes_rests) . "m";
                    } else {
                        $swipetime[$personid][(string) $tgl] = '';
                    }
                }
                $newdata = (array) $new_data[$personid];
                //                var_dump($new_data);echo "<br/>";
                //                var_dump($swipetime[$personid]);echo "<br/>";
                ksort($swipetime[$personid]);
                //                var_dump($swipetime[$personid]);echo "<br/>";
                $new_data[$personid] = (object) array_merge($newdata, $swipetime[$personid]);
            }
            //            echo json_encode($swipetime, 1);
            //            die();
            //            dd([$new_data]);

            /**
             * ingat format laporan ada TIME IN - TIME OUT
             */
            $result = array_values($new_data);
            $no = 1;
            foreach ($result as $keyr => $rslt) {
                $result[$keyr]->no_urut = $no;
                $no++;
            }
            //            dd($new_data);
            //            dd($result);
            $dttable = Datatables::of($result)->make(true);
            return $dttable;
        }
        //        var_dump($request);
    }

    public function getDataFormatted_prev(Request $request)
    {
        if ($request->ajax()) {
            $strdate = $request->get('startdate');
            $strdate = "$strdate 00:00:01";
            $enddate = $request->get('enddate');
            $enddate = "$enddate 23:59:59";

            $data = DB::table('fa_accesscontrol')
                ->where('alarmtime', '>=', $strdate)
                ->where('alarmtime', '<=', $enddate)
                ->get();
            $arr_data = $data->toArray();
            //            dd($data);  
            if (!$arr_data || count($arr_data) < 1) {
                return Datatables::of($data)
                    ->make(true);
            }

            $swipetime = [];
            $new_data = [];
            $format = 'Y-m-d H:i:s';
            foreach ($arr_data as $dt_access) {
                if (empty($new_data[$dt_access->personid])) {
                    $new_data[$dt_access->personid] = $dt_access;
                    //                    $new_data[$dt_access->personid]->nama_personnel = '-';
                    $nm = wordwrap($dt_access->personid, 8, " ", true);
                    $new_data[$dt_access->personid]->nama_personnel = $nm;
                    $fn = wordwrap($dt_access->firstname, 11, "\n", true);
                    $new_data[$dt_access->personid]->worker_id = $fn;
                    for ($loopInOt = 0; $loopInOt < 6; $loopInOt++) {
                        $new_data[$dt_access->personid]->{"time_in_$loopInOt"} = '';
                        $new_data[$dt_access->personid]->{"time_ot_$loopInOt"} = '';
                    }
                }
                $swipetime[$dt_access->personid][$dt_access->accesstype][] = $dt_access->alarmtime;
            }
            //dd([$new_data,$swipetime]);
            /** sorting first IN and/or Last OUT * */
            $default_element_time = ['', '', '', '', '', ''];
            foreach ($swipetime as $personid => $direct) {
                //Per direction IN/OUT
                $swipetime[$personid]['IN'] = $default_element_time;
                $swipetime[$personid]['OUT'] = $default_element_time;
                $swipetime[$personid]['first_in'] = '0';
                $swipetime[$personid]['last_out'] = '0';
                $new_data[$personid]->first_in = '0';
                $new_data[$personid]->last_out = '0';

                //                var_dump($direct);echo PHP_EOL;
                foreach ($direct as $dir => $listtime) {
                    if ($dir == 'IN') {
                        sort($listtime);
                    } else {
                        rsort($listtime);
                    }
                    $listtime = array_slice($listtime, 0, 6);
                    $listtime = $listtime + $default_element_time;
                    $swipetime[$personid][$dir] = $listtime;
                }

                $inning_sorted_up = array_filter($swipetime[$personid]['IN']);
                $outing_sorted_down = array_filter($swipetime[$personid]['OUT']);
                sort($inning_sorted_up);
                rsort($outing_sorted_down);
                if (!empty($inning_sorted_up[0])) {
                    $swipetime[$personid]['first_in'] = $inning_sorted_up[0];
                    $new_data[$personid]->first_in = $inning_sorted_up[0];
                    if (!empty($outing_sorted_down[0])) {
                        $swipetime[$personid]['last_out'] = $outing_sorted_down[0];
                        $new_data[$personid]->last_out = $outing_sorted_down[0];
                        $date_in = \DateTimeImmutable::createFromFormat($format, $inning_sorted_up[0]);
                        $date_ot = \DateTimeImmutable::createFromFormat($format, $outing_sorted_down[0]);
                        $interval = $date_in->diff($date_ot);
                        if ($interval) {
                            $new_data[$personid]->duration = $interval->format('%hH%iM');
                        } else {
                            $new_data[$personid]->duration = '0';
                        }
                    } else {
                        $new_data[$personid]->duration = '0';
                    }
                } else {
                    $new_data[$personid]->duration = '0';
                }
                //var_dump($personid); echo PHP_EOL;
                //dd($swipetime);
                //Time IN/OUT sorting for 6 columns
                $time_in_out = [];
                foreach ($swipetime[$personid]['IN'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "I";
                }
                foreach ($swipetime[$personid]['OUT'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "O";
                }
                //                if($personid == 'SAPRI'){
                //                    var_dump($time_in_out);
                //                }
                sort($time_in_out);
                //                if($personid == 'SAPRI'){
                //                    var_dump($time_in_out);
                //                }
                $loopIn = 0;
                $loopOt = 0;

                $total_rest = 0;
                $start_sum_total_rest = 0;
                $start_sum_out = "";
                $hours_rests = 0;
                $minutes_rests = 0;
                foreach ($time_in_out as $timing) {
                    $kode = substr($timing, -1);
                    $dttime = str_replace($kode, '', $timing);
                    if (empty($dttime)) {
                        continue;
                    }
                    if ($kode == 'O') {
                        //                        var_dump($dttime);echo PHP_EOL;
                        //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                        $new_data[$personid]->{"time_ot_$loopOt"} = $dttime;
                        $loopOt++;
                        $start_sum_total_rest = 1;
                        $start_sum_out = $dttime;
                    }
                    if ($kode == 'I') {
                        if ($start_sum_total_rest == 1) {
                            $start_sum_out1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $start_sum_out);
                            $start_sum_in1 = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $dttime);
                            $interval  =  ($start_sum_out1)->diff($start_sum_in1);
                            $hours_rests = $hours_rests + ($interval)->format("%h");
                            $minutes_rests = $minutes_rests + ($interval)->format("%i");
                            $start_sum_total_rest = 0;
                            $start_sum_out = "";
                        }
                        //                        $datetime = \DateTimeImmutable::createFromFormat($format, $dttime);
                        $new_data[$personid]->{"time_in_$loopIn"} = $dttime;
                        $loopOt = $loopIn;
                        $loopIn++;
                    }
                }
                //Rest Total
                $new_data[$personid]->total_rest = ($hours_rests + ($minutes_rests / 60)) . "h" . ($minutes_rests % 60) . "m";
            }

            //            dd([$new_data]);

            /**
             * 1. First IN and LAST OUT
             * 2. 
             */
            $total_duration = 0;
            //            foreach ($swipetime as $personid => $direct) {
            //
            //            }
            /**
             * ingat format laporan ada TIME IN - TIME OUT
             */
            $result = array_values($new_data);
            return Datatables::of($result)
                ->make(true);
        }
        //        var_dump($request);
    }

    public function getDataFormatted_att(Request $request)
    {
        if ($request->ajax()) {
            $data = DB::table('fa_attendance')
                ->where('swipetime', '>=', $request->get('startdate'))
                ->where('swipetime', '<=', $request->get('enddate'))
                //                    ->where('personnelname', '=', '1PDP/IOI/0422/33013 MUHAMMAD SY')
                //                    ->where('personnelname', '=', '1PDP/IOI/0704/26019 AZHARI BIN')
                ->get();
            $arr_data = $data->toArray();
            //            dd($data);
            if (!$arr_data || count($arr_data) < 1) {
                return Datatables::of($data)
                    ->make(true);
            }

            $swipetime = [];
            $new_data = [];
            $format = 'Y-m-d H:i:s';
            foreach ($arr_data as $dt_attendance) {
                if (empty($new_data[$dt_attendance->personnelcode])) {
                    $new_data[$dt_attendance->personnelcode] = $dt_attendance;
                    for ($loopInOt = 0; $loopInOt < 6; $loopInOt++) {
                        $new_data[$dt_attendance->personnelcode]->{"time_in_$loopInOt"} = '';
                        $new_data[$dt_attendance->personnelcode]->{"time_ot_$loopInOt"} = '';
                    }
                }
                $swipetime[$dt_attendance->personnelcode][$dt_attendance->swipdirection][] = $dt_attendance->swipetime;
            }
            //dd([$new_data,$swipetime]);
            /** sorting first IN and/or Last OUT * */
            $default_element_time = ['', '', '', '', '', ''];
            foreach ($swipetime as $personnelcode => $direct) {
                //Per direction IN/OUT
                $swipetime[$personnelcode]['IN'] = $default_element_time;
                $swipetime[$personnelcode]['OUT'] = $default_element_time;
                foreach ($direct as $dir => $listtime) {
                    if ($dir == 'IN') {
                        sort($listtime);
                    } else {
                        rsort($listtime);
                    }
                    $listtime = array_slice($listtime, 0, 6);
                    $listtime = $listtime + $default_element_time;
                    $swipetime[$personnelcode][$dir] = $listtime;
                }

                $swipetime['first_in'] = '0';
                $swipetime['last_out'] = '0';
                $new_data[$personnelcode]->first_in = '0';
                $new_data[$personnelcode]->last_out = '0';
                $inning_sorted_up = array_filter($swipetime[$personnelcode]['IN']);
                $outing_sorted_down = array_filter($swipetime[$personnelcode]['OUT']);
                sort($inning_sorted_up);
                rsort($outing_sorted_down);
                if (!empty($inning_sorted_up[0]) && !empty($outing_sorted_down[0])) {
                    $swipetime['first_in'] = $inning_sorted_up[0];
                    $swipetime['last_out'] = $outing_sorted_down[0];
                    $new_data[$personnelcode]->first_in = $inning_sorted_up[0];
                    $new_data[$personnelcode]->last_out = $outing_sorted_down[0];
                    $date_in = \DateTimeImmutable::createFromFormat($format, $inning_sorted_up[0]);
                    $date_ot = \DateTimeImmutable::createFromFormat($format, $outing_sorted_down[0]);
                    $interval = $date_in->diff($date_ot);
                    if ($interval) {
                        $new_data[$personnelcode]->duration = $interval->format('%hH%iM');
                    } else {
                        $new_data[$personnelcode]->duration = '0';
                    }
                } else {
                    $new_data[$personnelcode]->duration = '0';
                }
                //Time IN/OUT sorting for 6 columns
                $time_in_out = [];
                foreach ($swipetime[$personnelcode]['IN'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "I";
                }
                foreach ($swipetime[$personnelcode]['OUT'] as $dt_ins) {
                    $time_in_out[] = "$dt_ins" . "O";
                }
                sort($time_in_out);
                $loopIn = 0;
                $loopOt = 0;
                foreach ($time_in_out as $timing) {
                    $kode = substr($timing, -1);
                    $dttime = str_replace($kode, '', $timing);
                    if ($kode == 'I') {
                        $datetime = \DateTimeImmutable::createFromFormat('%m%A', $dttime);
                        $new_data[$personnelcode]->{"time_in_$loopIn"} = $dttime;
                        //                        $new_data[$personnelcode]->{"time_ot_$loopOt"} = '';
                        $loopIn++;
                    }
                    if ($kode == 'O') {
                        //                        $new_data[$personnelcode]->{"time_in_$loopIn"} = '';
                        \DateTimeImmutable::createFromFormat($format2, $inning_sorted_up[0]);
                        $new_data[$personnelcode]->{"time_ot_$loopOt"} = $dttime;
                        $loopOt++;
                    }
                }
                //var_dump($time_in_out);
                //                $swipetime['duration'] = $interval->format('%hH%iM');
            }
            //            dd([$new_data]);

            /**
             * 1. First IN and LAST OUT
             * 2. 
             */
            $total_duration = 0;
            //            foreach ($swipetime as $personnelcode => $direct) {
            //
            //            }
            /**
             * ingat format laporan ada TIME IN - TIME OUT
             */
            $result = array_values($new_data);
            return Datatables::of($result)
                ->make(true);
        }
        //        var_dump($request);
    }

    function list_of_working_days($from, $to)
    {
        //        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        //        $holidayDays = explode(",", config('face.HOLYDAYS')); # variable and fixed holidays

        $from = new \DateTime($from);
        $to = new \DateTime($to);
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
}
