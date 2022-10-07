<?php

namespace App\Traits;

use App\Traits\Sandbox;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait ServicesResponse {

    use Sandbox;

    public function response($body, $code = 200, $msg = null){
        if($code >= 200 AND $code <= 302)
        {
            if($body){
                $data = (object) $body;
                if(isset($data->data)){
                    $first_page_url = null;
                    if(isset($data->first_page_url)){
                        $first_page_url = parse_url($data->first_page_url);
                        parse_str($first_page_url['query'], $first_page_url);
                        $first_page_url = (int) $first_page_url['page'];
                    }

                    $next_page_url = null;
                    if(isset($data->next_page_url)){
                        $next_page_url = parse_url($data->next_page_url);
                        parse_str($next_page_url['query'], $next_page_url);
                        $next_page_url = (int) $next_page_url['page'];
                    }

                    $prev_page_url = null;
                    if(isset($data->prev_page_url)){
                        $prev_page_url = parse_url($data->prev_page_url);
                        parse_str($prev_page_url['query'], $prev_page_url);
                        $prev_page_url = (int) $prev_page_url['page'];
                    }

                    $total = null;
                    if(isset($data->total)){
                        $total = $data->total;
                    }

                    $current_page = null;
                    if(isset($data->current_page)){
                        $current_page = $data->current_page;
                    }

                    $last_page = null;
                    if(isset($data->last_page)){
                        $last_page = $data->last_page;
                    }

                    $response = (object) [
                        'status' => TRUE,
                        'messages' => $msg,
                        'mode' => $this->connection,
                        'services' => config('app.SERVICES_NAME'),
                        'results' => [
                            "data" => $data->data,
                            "per_page" => $data->per_page,
                            "current_page" => $current_page,
                            "first_page" => $first_page_url,
                            "last_page" => $last_page,
                            "prev_page" => $prev_page_url,
                            "next_page" => $next_page_url,
                            "total" => $total
                        ]
                    ];
                }
                else
                {
                    $response = (object) [
                        'status' => TRUE,
                        'messages' => $msg,
                        'mode' => $this->connection,
                        'services' => config('app.SERVICES_NAME'),
                        'results' => $body
                    ];
                }
            }
            else
            {
                $response = (object) [
                    'status' => TRUE,
                    'messages' => $msg,
                    'mode' => $this->connection,
                    'services' => config('app.SERVICES_NAME'),
                    'results' => null
                ];
            }

            return response()->json($response, $code);
        }
        else
        {
            return $this->error_response($msg);
        }
    }

    public function error_response($messages, $code = 400, $error = null){
        return response()->json((object) [
            'status' => FALSE,
            'messages' => $messages,
            'mode' => $this->connection,
            'services' => config('app.SERVICES_NAME'),
            'results' => $error
        ], $code);
    }

    public function handleApiException($e){

        $messages = 'Terjadi Kesalahan! Silahkan coba lagi.';
        $trace = null;
        if(config('app.APP_DEBUG')){
            $messages = $e->getMessage();
            $trace = $e->getTrace();
        }

        if ($e instanceof HttpExceptionInterface) {
            return $this->error_response($messages, $e->getStatusCode(), $trace);
        }

        if($e instanceof PDOException){
            return $this->error_response($messages, Response::HTTP_INTERNAL_SERVER_ERROR, $trace);
        }

        if($e instanceof QueryException){
            return $this->error_response($messages, Response::HTTP_SERVICE_UNAVAILABLE, $trace);
        }

        if($e instanceof HttpResponseException){
            return $this->error_response($messages, Response::HTTP_INTERNAL_SERVER_ERROR, $trace);
        }

        if($e instanceof AuthenticationException){
            return $this->error_response('Unauthorized', 401, $trace);
        }

        if($e instanceof DecryptException){
            return $this->error_response($messages, Response::HTTP_BAD_REQUEST, $trace);
        }

        return $this->error_response($messages, 500, $trace);
    }
}
