<?php

namespace App\Traits;

trait Sandbox
{
    protected $connection;

    public function __construct(){

        $this->connection = 'sandbox';
        if(request()->header('x-services-secret-key') == env('services_secret_key')){
            if(request()->header('x-sandbox-mode') == 'disabled'){
                $this->connection  = 'live';
            }
        }

        $this->connection;
    }

}
