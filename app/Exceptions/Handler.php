<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Traits\Sandbox;

use Throwable;
class Handler extends ExceptionHandler
{
    use sandbox;

    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    public function render($request, Throwable $e)
    {
        $rendered = parent::render($request, $e);
        return response()->json([
            'status' => false,
            'messages' => (config('app.APP_DEBUG')) ? $e->getMessage() : trans('apps.msg_error'),
            'services' => config('app.SERVICES_NAME'),
            'mode' => $this->connection,
            'results' => null
        ], $rendered->getStatusCode());
    }
}
