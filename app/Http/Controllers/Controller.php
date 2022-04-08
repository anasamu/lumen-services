<?php

namespace App\Http\Controllers;

use App\Console\Commands\SandboxClear;
use App\Console\Commands\SandboxCopy;
use App\Traits\Sandbox;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use Sandbox;

    public $search_column = [];
    public $uuid_dependency = [];
    public $date_columns = [];
    public $number_columns = [];
    public $services_consume = [];
    public $auth_request = [
        'auth_request' => false,
    ];

    public $upload_request = [
        'upload_request' => false,
    ];

    public $order_type = ['asc','desc'];

    public function res($results = array(), $code = 200, $msg = null){
        if($code == 200 OR $code == 302 OR $code == 201 OR $code == 202)
        {
            return response()->json([
                'status' => TRUE,
                'messages' => $msg,
                'mode' => $this->connection,
                'services' => config('app.SERVICES_NAME'),
                'results' => $results
            ], $code);
        }
        else
        {
            return response()->json([
                'status' => FALSE,
                'messages' => $msg,
                'mode' => $this->connection,
                'services' => config('app.SERVICES_NAME'),
                'results' => $results
            ], $code);
        }
    }

    public function index(){
        $results = [
            'SERVICES_NAME' => config('app.SERVICES_NAME'),
            'SERVICES_VERSION' => config('app.SERVICES_VERSION'),
            'SERVICES_DESC' => config('app.SERVICES_DESC')
        ];

        return $this->res($results, 200, 'Services Information');
    }

    //Copy Live Database To Sandbox database
    public function liveToSandbox(){
        Queue::push(new SandboxCopy, 'Copy Database');
        return $this->res(null,200, trans('apps.msg_copy_live_to_sandbox'));
    }

    // clear all data in sandbox database
    public function clearSandbox(){
        Queue::push(new SandboxClear, 'Clear Database');
        return $this->res(null,200, trans('apps.msg_clear_sandbox'));
    }

}
