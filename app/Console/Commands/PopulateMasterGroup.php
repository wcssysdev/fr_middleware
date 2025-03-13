<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\WorkerGroup;
use App\Models\RequestLog;

class PopulateMasterGroup extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:receive_group_dept';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get group department from DSS Dahua';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
   protected function log_event($params, $responses, $saveNow = '', $url = '', $type = 'MASTER-GROUP') {
        $newLog = new RequestLog();

        if (empty($saveNow)) {
            $now = date('Y-m-d H:i:s');
        } else {
            $now = $saveNow;
        }

        $newLog->transaction_type = $type;
        $newLog->url = $url;
        $newLog->params = json_encode($params);
        $newLog->response_status = 'OK';
        $newLog->response_message = json_encode($responses);
        $newLog->created_at = $now;
        $newLog->save();
    }
    
    public function handle() {
        $return = 0;
        
//        $this->log_event([],['ok' => 'Master-Group']);
        $data_person = $this->get_data_worker_group_from_dss(NULL);
        if (($data_person['status']) > 0) {
            $return = $this->do_save_group($data_person['data']);
        }
        return $return;
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
        $return['message'] = 'Success get data Group.';
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
                if ($httpcode == 200) {
                    $return['status'] = 1;
                    /**
                     * SAMPLE DATA, MUST DELETED AFTER PUBLISH
                     */

                    $parse_result = json_decode($result, 1);
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
}
