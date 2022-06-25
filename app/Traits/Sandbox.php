<?php

namespace App\Traits;

trait Sandbox
{
    public $connection;

    public function __construct(){

        $this->connection = 'sandbox';
        if(request()->header('x-services-secret-key') == config('app.SERVICES_SECRET_KEY')){
            if(request()->header('x-sandbox-mode') === 'disabled'){
                return $this->connection  = 'live';
            }
        }

        return $this->connection;
    }

}
