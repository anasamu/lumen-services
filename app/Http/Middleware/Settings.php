<?php

namespace App\Http\Middleware;

use App\Traits\Sandbox;
use App\Traits\ServicesResponse;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Settings
{
    use Sandbox, ServicesResponse;

    public function handle($request, Closure $next)
    {

        config()->set('database.default', $this->connection);

        // set default language with id
        app('translator')->setLocale('id');
        if(request()->header('x-lang') !== null){
            // set custom language
            app('translator')->setLocale(request()->header('x-lang'));
        }

        if(config('app.SERVICES_SECRET_KEY') !== null){
            // check if debug mode true then return progress without services secret key
            if(config("app.APP_DEBUG")){
                // return $next($request);
            }

            // check if secret key from header same with secret key in env variable
            if($request->header('x-services-secret-key') == config('app.SERVICES_SECRET_KEY')){
                return $next($request);
            }
        }

        return $this->error_response('Invalid Services Secret Key', 401);
    }
}
