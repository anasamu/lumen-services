<?php

namespace App\Http\Controllers;

use App\Console\Commands\SandboxClear;
use App\Console\Commands\SandboxCopy;
use App\Services\ServicesResponse;
use Illuminate\Support\Facades\Queue;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use ServicesResponse;
    public $order_type = ['asc','desc'];
    public $search_column = [];
    public $uuid_dependency = [];
    public $date_columns = [];
    public $number_columns = [];
    public $services_consume = [];
    public $auth_request = [
        'auth_request' => false,
    ];

    public function index(){
        $results = [
            'SERVICES_NAME' => config('app.SERVICES_NAME'),
            'SERVICES_VERSION' => config('app.SERVICES_VERSION'),
            'SERVICES_DESC' => config('app.SERVICES_DESC')
        ];

        return $this->response($results, 200, 'Services Information');
    }

    //Copy Live Database To Sandbox database
    public function liveToSandbox(){
        Queue::push(new SandboxCopy, 'Copy Database');
        return $this->response(null,200, trans('apps.msg_copy_live_to_sandbox'));
    }

    // clear all data in sandbox database
    public function clearSandbox(){
        Queue::push(new SandboxClear, 'Clear Database');
        return $this->response(null,200, trans('apps.msg_clear_sandbox'));
    }

}
