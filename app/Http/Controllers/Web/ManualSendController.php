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
use App\Models\WorkerGroup;
use DataTables;
use App\Models\Attendance;
use App\Models\RequestLog;
use App\Models\AccessControl;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ManualSendController extends BaseController {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        $dataview['group'] = WorkerGroup::latest()->get();
        if ($request->ajax()) {
            $data = AccessControl::latest()->get();
            return Datatables::of($data)
                            ->make(true);
        }

        return view('resend/report', $dataview);
    }

    public function transfersap(Request $request) {
        $setting = Setting::orderBy('fa_setting_id', 'desc')->first();
        $res = $this->resend_to_cpi($request, $setting);
//        $res = json_encode(
//                array(
//                    'status' => 'success',
//                    'data' => $res,
//                    'setting' => $setting,
//                    'message' => 'Process completed.'
//                )
//        );
        return redirect()->route('webresend')->with('success', 'Data Has Been sent to SAP successfully');
    }

    public function getDataMonthly(Request $request) {
        if ($request->ajax()) {
            $zone = env('API_ZONE', 'ID');
            if ($zone == 'MY') {
                date_default_timezone_set('Asia/Kuala_Lumpur');
            } else {
                date_default_timezone_set('Asia/Jakarta');
            }

            /**
             * Idenya:
             * 1. ambil IN di hari sebelumnya siapa tahu ada lemburan start hari sebelumnya (yang biasanya setelah jam kerja)
             * 2. ambil OUT di hari setelahnya
             * 3. ambil data yang ada di range 
             */
            $startdate_ori = $sdate = $request->get('startdate');
            $enddate_ori = $enddate = $request->get('enddate');
            $group = trim($request->get('group'));

            $strdate = "$startdate_ori 00:00:01";
            $enddate = "$enddate_ori 23:59:59";

            $sdate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -8 hour'));
            $edate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -3 second'));

            //Edate = date + 1 day 
            $date_after = date('Y-m-d', strtotime($enddate_ori . ' +1 day'));
            $sdate_after = date('Y-m-d H:i:s', strtotime($date_after . ' -3 second'));
            $edate_after = "$date_after 09:00:00";

            $search_val_all = $request->get('searchbox');
            $w_personid = '1=1';
//            var_dump([$sdate_before,$edate_before,$sdate_after,$edate_after]);die();
            //Get data in the range
            if (!empty($search_val_all)) {
                $w_personid = "((fa_accesscontrol.personid ilike '%" . $search_val_all . "%')";
                $w_personid .= " or (fa_accesscontrol.firstname ilike '%" . $search_val_all . "%'))";
                $searchwhere = "%$search_val_all%";
                $data = DB::table('fa_accesscontrol')
                        ->leftJoin('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($sdate_before, $edate_before, $sdate_after, $edate_after) {
                            $query->orWhere(function ($query3) use ($sdate_before, $edate_before) {
                                $query3->where('alarmtime', '>=', $sdate_before);
                                $query3->where('alarmtime', '<=', $edate_before);
                                $query3->where('fa_accesscontrol.accesstype', '=', 'IN');
                            });
                            $query->orWhere(function ($query5) use ($edate_before, $sdate_after) {
                                $query5->where('alarmtime', '>=', $edate_before);
                                $query5->where('alarmtime', '<=', $sdate_after);
                            });
                            $query->orWhere(function ($query4) use ($sdate_after, $edate_after) {
                                $query4->where('alarmtime', '>=', $sdate_after);
                                $query4->where('alarmtime', '<=', $edate_after);
                                $query4->where('fa_accesscontrol.accesstype', '=', 'OUT');
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
                        ->orderBy('alarmtime', 'asc')
                        ->get();
            } else {
                $data = DB::table('fa_accesscontrol')
                                ->select('alarmtime', 'fa_accesscontrol.accesstype', 'fa_accesscontrol.personid', 'fa_accesscontrol.firstname', 'fa_person.orgcode', 'fa_person.orgname')
                                ->join('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                                ->where(function ($query) use ($sdate_before, $edate_before, $sdate_after, $edate_after) {
                                    $query->orWhere(function ($query3) use ($sdate_before, $edate_before) {
                                        $query3->where('alarmtime', '>=', $sdate_before);
                                        $query3->where('alarmtime', '<=', $edate_before);
                                        $query3->where('fa_accesscontrol.accesstype', '=', 'IN');
                                    });
                                    $query->orWhere(function ($query5) use ($edate_before, $sdate_after) {
                                        $query5->where('alarmtime', '>=', $edate_before);
                                        $query5->where('alarmtime', '<=', $sdate_after);
                                    });
                                    $query->orWhere(function ($query4) use ($sdate_after, $edate_after) {
                                        $query4->where('alarmtime', '>=', $sdate_after);
                                        $query4->where('alarmtime', '<=', $edate_after);
                                        $query4->where('fa_accesscontrol.accesstype', '=', 'OUT');
                                    });
                                })
                                ->where(function ($query2) use ($group) {
                                    if (empty($group) || $group == 'ALL') {
                                        
                                    } else {
                                        $query2->orWhere('fa_person.orgcode', '=', $group);
                                    }
                                })->orderBy('alarmtime', 'asc')->get();
            }
//            dd([$strdate, $enddate, $w_personid]);
//            $arr_data = $data->toArray();
//            echo json_encode($arr_data);die();
            $arr_data = $data;
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
//                    $wrapped_id = wordwrap($dt_access->personid, 5, " ", true);
//                    $new_data[$dt_access->personid]->nama_personnel = $wrapped_id;
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

//            var_dump($swipetime);
//            die();
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
                    $v_dir = substr($v, 0, -1);
                    $jam_saja = substr($v, 10);

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
                        $tgl_trx = $tgl_v = (String) $tgl_create->format('d/m/Y');
                        $new_dir[$tgl_v][] = $jam_saja;
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
                                $new_dir[$tgl_trx][] = $jam_saja;
                            } else {
                                $idx = count($new_dir[$tgl_trx]) - 1;
                                $new_dir[$tgl_trx][$idx] = $jam_saja;
                            }
                            $intval_dir_in = intval($intval_v) + 60;
//                            }
                        } elseif ($type == 'I') {
                            //IN
                            if (intval($intval_v) >= $intval_dir_in) {
                                if (empty($new_dir[$tgl_v])) {
                                    $new_dir[$tgl_v][] = $jam_saja;
                                } else {
                                    $idx = count($new_dir[$tgl_v]) - 1;
                                    $new_dir[$tgl_v][] = $jam_saja;
                                }
                                $intval_dir_in = intval($intval_v) + 60;
                            } else {
                                
                            }
                            $tgl_trx = $tgl_v;
                        }
                    } else {
                        $tgl_v1 = substr($v, 0, 10);
                        $tgl_create = \DateTimeImmutable::createFromFormat("Y-m-d", $tgl_v1);
                        $tgl_v = (String) $tgl_create->format('d/m/Y');
                        if ($type == 'O') {
                            //OUT

                            if (empty($new_dir[$tgl_trx])) {
                                $new_dir[$tgl_trx][] = $jam_saja;
                            } else {
                                $idx = count($new_dir[$tgl_trx]) - 1;
                                $new_dir[$tgl_trx][] = $jam_saja;
                            }

                            $intval_dir_in = intval($intval_v) + 60;
                        } elseif ($type == 'I') {
                            //IN
                            $new_dir[$tgl_v][] = $jam_saja;
                            $tgl_trx = $tgl_v;
                            $time_dir_in = $v;
                            $intval_dir_in = intval($intval_v) + 60;
                        }
                        $type_trx = $type;
                    }
                }
                $swipetime[$personid] = $new_dir;
            }

//            echo json_encode($swipetime);
//            die();

            /** sorting first IN and/or Last OUT * */
//            $default_element_time = array_fill(0, count($list_workdays), '');
            foreach ($swipetime as $personid => $direct) {
//                print_r($direct);echo "\n";
                $all_tgl = [];
                foreach ($direct as $tgl => $rows) {
                    $j = 0;
                    $rowData = [];
                    foreach ($rows as $dt) {
//                        print_r($dt);echo "\n";
                        $type = substr($dt, -1);
                        $v_dir = substr($dt, 0, -1);
                        if ($type == 'I') {
                            $rowData[$j] = $v_dir;
                            $j++;
                        } elseif ($type == 'O') {
                            $rowData[($j - 1)] = $rowData[($j - 1)] . ' : ' . $v_dir;
                        }
                    }
                    if ($rowData) {
                        $all_tgl[$tgl] = $rowData;
                    }
//                    echo json_encode($rows);
//                    echo "\n";
                }
                $swipetime[$personid] = ($all_tgl) + $list_workdays;
                $newdata = (array) ($new_data[$personid]);
                $new_data[$personid] = (object) array_merge($newdata, $swipetime[$personid]);
            }
//            echo json_encode($swipetime, 1);
//            die();
//            dd($new_data);

            /**
             * ingat format laporan ada TIME IN - TIME OUT
             */
            $result = array_values($new_data);
            $no = 1;
            foreach ($result as $keyr => $rslt) {
                $result[$keyr]->no_urut = $rslt->nama_personnel;
                $no++;
            }
//            dd($new_data);
//            dd($result);
            $dttable = Datatables::of($result)->make(true);
            return $dttable;
        }
//        var_dump($request);
    }

    public function export_monthly(Request $request) {
        $zone = env('API_ZONE', 'ID');
        if ($zone == 'MY') {
            date_default_timezone_set('Asia/Kuala_Lumpur');
        } else {
            date_default_timezone_set('Asia/Jakarta');
        }
//        dd($request);
        /**
         * Setting day
         */
        $report_setting = DB::table('fa_setting')->latest('fa_setting_id')->first();
        $setting_sdate = explode(" ", $report_setting->startdate);
        $time_sdate = substr($setting_sdate[1], 0, 5);
        $time_sdate_cast = intval(preg_replace('/[^0-9]/', '', $time_sdate));
        $setting_edate = explode(" ", $report_setting->enddate);

        $startdate_ori = $sdate = $request->get('startdate');
        $enddate_ori = $enddate = $request->get('enddate');
        $group = trim($request->get('group'));

        $strdate = "$startdate_ori 00:00:01";
        $enddate = "$enddate_ori 23:59:59";

        $sdate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -8 hour'));
        $edate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -3 second'));

        //Edate = date + 1 day 
        $date_after = date('Y-m-d', strtotime($enddate_ori . ' +1 day'));
        $sdate_after = date('Y-m-d H:i:s', strtotime($date_after . ' -3 second'));
        $edate_after = "$date_after 09:00:00";

        $w_personid = '1=1';
//            dd([$sdate_before,$edate_before,$sdate_after,$edate_after]);
        //Get data in the range
        $arr_data = DB::table('fa_accesscontrol')
                        ->select('alarmtime', 'fa_accesscontrol.accesstype', 'fa_accesscontrol.personid', 'fa_accesscontrol.firstname', 'fa_person.orgcode', 'fa_person.orgname')
                        ->join('fa_person', 'fa_person.firstname', '=', 'fa_accesscontrol.firstname')
                        ->where(function ($query) use ($sdate_before, $edate_before, $sdate_after, $edate_after) {
                            $query->orWhere(function ($query3) use ($sdate_before, $edate_before) {
                                $query3->where('alarmtime', '>=', $sdate_before);
                                $query3->where('alarmtime', '<=', $edate_before);
                                $query3->where('fa_accesscontrol.accesstype', '=', 'IN');
                            });
                            $query->orWhere(function ($query5) use ($edate_before, $sdate_after) {
                                $query5->where('alarmtime', '>=', $edate_before);
                                $query5->where('alarmtime', '<=', $sdate_after);
                            });
                            $query->orWhere(function ($query4) use ($sdate_after, $edate_after) {
                                $query4->where('alarmtime', '>=', $sdate_after);
                                $query4->where('alarmtime', '<=', $edate_after);
                                $query4->where('fa_accesscontrol.accesstype', '=', 'OUT');
                            });
                        })
                        ->orderBy('alarmtime', 'asc')->get();

        if (!$arr_data || count($arr_data) < 1) {
            return false;
        }

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
                $v_dir = substr($v, 0, -1);

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
                    $tgl_trx = $tgl_v = (String) $tgl_create->format('d/m/Y');
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
                            $new_dir[$tgl_trx][$idx] = $v;
                        }
                        $intval_dir_in = intval($intval_v) + 60;
//                            }
                    } elseif ($type == 'I') {
                        //IN
                        if (intval($intval_v) >= $intval_dir_in) {
                            if (empty($new_dir[$tgl_v])) {
                                $new_dir[$tgl_v][] = $v;
                            } else {
                                $idx = count($new_dir[$tgl_v]) - 1;
                                $new_dir[$tgl_v][] = $v;
                            }
                            $intval_dir_in = intval($intval_v) + 60;
                        } else {
                            
                        }
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

        $list_workdays = $this->list_of_working_days($startdate_ori, $enddate_ori);
        $workdays_keys = array_keys($list_workdays);
            foreach ($swipetime as $personid => $direct) {
//                print_r($direct);echo "\n";
                $all_tgl = [];
                foreach ($direct as $tgl => $rows) {
                    $j = 0;
                    $rowData = [];
                    foreach ($rows as $dt) {
//                        print_r($dt);echo "\n";
                        $type = substr($dt, -1);
                        $v_dir = substr($dt, 0, -1);
                        if ($type == 'I') {
                            $rowData[$j] = $v_dir;
                            $j++;
                        } elseif ($type == 'O') {
                            $rowData[($j - 1)] = $rowData[($j - 1)] . ' : ' . $v_dir;
                        }
                    }
                    if ($rowData) {
                        $all_tgl[$tgl] = $rowData;
                    }
//                    echo json_encode($rows);
//                    echo "\n";
                }
                $swipetime[$personid] = ($all_tgl) + $list_workdays;
                $newdata = (array) ($new_data[$personid]);
                $new_data[$personid] = (object) array_merge($newdata, $swipetime[$personid]);
            }
            
//            dd($new_data);
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
//        dd($result_report);
        for ($i = 0; $i < count($result_report); $i++) {
            $row = (array) $result_report[$i];
            $sheet->setCellValue('A' . ($counter), $id);
            $sheet->setCellValue('B' . ($counter), $row['orgname']);
            $sheet->setCellValue('C' . ($counter), $row['worker_id']);
            $sheet->setCellValue('D' . ($counter), $row['nama_personnel']);
            $start_char = "E";

            $counter_ori = $counter;
            foreach ($header_date as $date_column) {
                    if (empty($row[$date_column])) {
                        $valval = "N/A";
                        $sheet->setCellValue($start_char . ($counter_ori), $valval);
                    } else {
                        $countertrx = $counter_ori;
                        foreach ($row[$date_column] as $att) {
//                        $valval = implode(",", $row[$date_column]);
                            $sheet->setCellValue($start_char . ($countertrx), $att);
                            $countertrx++;
                        }
                        if($countertrx > $counter){
                            $counter = $countertrx - 1;
                        }
                    }
                $start_char++;
            }
            $columnA = "A$counter_ori:A$counter";
            $columnB = "B$counter_ori:B$counter";
            $columnC = "C$counter_ori:C$counter";
            $columnD = "D$counter_ori:D$counter";
            $sheet->mergeCells($columnA);
            $sheet->mergeCells($columnB);
            $sheet->mergeCells($columnC);
            $sheet->mergeCells($columnD);


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

    protected function resend_to_cpi($request, $report_setting) {
        $ops_unit = $report_setting->unit_name;

//        $data = DB::table('fa_accesscontrol')
//                ->select('fa_accesscontrol_id', 'devicecode', 'devicename', 'channelid', 'channelname', 'alarmtypeid', 'personid', 'firstname', 'lastname', 'alarmtime', 'accesstype', 'unit_name')


        $startdate_ori = $sdate = $request->post('sdate');
        $enddate_ori = $enddate = $request->post('edate');
        $emps = $request->emp;
        if (!empty($emps)) {
            $emps = array_unique(array_filter($emps));
        } else {
            $responses = array(
                'status' => 'failed',
                'data' => [
                    array(
                        'code' => 400,
                        'message' => 'Select at lease 1 worker.'
                    )
                ]
            );
            return $responses;
        }
        $enddate = "$enddate_ori 23:59:59";

        $sdate_before = date('Y-m-d H:i:s', strtotime($startdate_ori . ' -8 hour'));
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
                                $query3->where('fa_accesscontrol.accesstype', '=', 'IN');
                            });
                            $query->orWhere(function ($query5) use ($edate_before, $sdate_after) {
                                $query5->where('alarmtime', '>=', $edate_before);
                                $query5->where('alarmtime', '<=', $sdate_after);
                            });
                            $query->orWhere(function ($query4) use ($sdate_after, $edate_after) {
                                $query4->where('alarmtime', '>=', $sdate_after);
                                $query4->where('alarmtime', '<=', $edate_after);
                                $query4->where('fa_accesscontrol.accesstype', '=', 'OUT');
                            });
                        })->whereIn('fa_accesscontrol.personid', $emps)
                        ->orderBy('alarmtime', 'asc')->get();

        if (!$arr_data || count($arr_data) < 1) {
            return false;
        }

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
                $v_dir = substr($v, 0, -1);

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
                    $tgl_trx = $tgl_v = (String) $tgl_create->format('d/m/Y');
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
                            $new_dir[$tgl_trx][$idx] = $v;
                        }
                        $intval_dir_in = intval($intval_v) + 60;
//                            }
                    } elseif ($type == 'I') {
                        //IN
                        if (intval($intval_v) >= $intval_dir_in) {
                            if (empty($new_dir[$tgl_v])) {
                                $new_dir[$tgl_v][] = $v;
                            } else {
                                $idx = count($new_dir[$tgl_v]) - 1;
                                $new_dir[$tgl_v][] = $v;
                            }
                            $intval_dir_in = intval($intval_v) + 60;
                        } else {
                            
                        }
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

        $list_workdays = $this->list_of_working_days($startdate_ori, $enddate_ori);
        $workdays_keys = array_keys($list_workdays);
        foreach ($swipetime as $personid => $direct) {

            $attd = [];
            foreach ($direct as $tgl => $att) {
                if (in_array($tgl, $workdays_keys)) {
                    $attd[$tgl] = $att;
                }
            }
            $new_data[$personid]->trx = $attd;
        }

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
            $this->log_event([], $responses, '', 'passing_to_cpi_from_menu_resend');
        } else {
            //dd($new_data);

            $sent_data = [];
            $updated_ids = [];

            $list_prfnr = [];
            foreach ($new_data as $dt_attendance) {
//                $att['MANDT'] = '';
                $attd = $dt_attendance->trx;
                foreach ($attd as $tgl => $in_out) {
//                            }
                    foreach ($in_out as $detail) {
                        $att = [];
                        $att['PRFNR'] = $ops_unit;
                        $att['EMPNR'] = $dt_attendance->worker_id;
                        $att['SOURCE'] = "D";
                        $type = substr($detail, -1);
                        $timestamp = substr($detail, 0, -1);
                        $record_id = str_replace(['-', ':', ' '], '', substr($timestamp, 2));
                        $att['RECORD_ID'] = $record_id;
                        $att_time = explode(" ", $timestamp);
//                $att['SDATE'] = str_replace("-","",$att_time[0]);
                        $att['SDATE'] = $att_time[0];
//                $att['STIME'] = str_replace(":","",$att_time[1]);
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
            if (empty($response2['feedback']['ERROR'])) {
                $res[] = $response2;
            } else {
                $error_count[] = $response2['feedback']['ERROR'];
                $res[] = $response2;
                /**
                 * Handdle error
                 */
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
//            $affected = DB::table('fa_accesscontrol')
//                    ->whereIn('fa_accesscontrol_id', $updated_ids)
//                    ->update(['sent_cpi' => 'Y', 'remark' => '']);
//            
//            $this->log_event($sent_data, $responses, '', 'passing_to_cpi_');
//            if (count($error_count) > 0) {
//                $responses['status'] = 'fail';
//                foreach ($error_count as $err) {
//                    foreach ($err as $derr) {
//                        $affected = DB::table('fa_accesscontrol')
//                                ->where('firstname', $derr['EMPNR'])
//                                ->where('alarmtime', "$derr[SDATE] $derr[STIME]")
//                                ->update(['sent_cpi' => 'F', 'remark' => $derr['REMARK']]);
//                    }
//                }
//                $this->log_event($sent_data, $responses, '', 'resend_to_cpi_button');
//            }
        }
        return $responses;
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

    function list_of_working_days($from, $to) {
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
