<?php

if(!function_exists('Resource')) {
    function Resource($uri, $controller = null){
        global $app;
        if(is_array($uri)){
            foreach($uri as $key => $val){
                $controller = $val;
                // using prefix url with services version
                $app->router->group(['prefix' => $key], function ($router) use ($controller) {
                    $router->get('/', ['uses' => $controller . '@index']);
                    $router->get('/info', ['uses' => $controller . '@info']);
                    $router->get('/search', ['uses' => $controller . '@search']);
                    $router->get('/read/{uuid}', ['uses' => $controller . '@show']);
                    $router->get('/details', ['uses' => $controller . '@details']);
                    $router->post('/create', ['uses' => $controller . '@store']);
                    $router->put('/update/{uuid}', ['uses' => $controller . '@update']);
                    $router->post('/update/{uuid}', ['uses' => $controller . '@update']);
                    $router->delete('/delete/{uuid}', ['uses' => $controller . '@delete']);

                    // authentication
                    $router->post('/auth', ['uses' => $controller . '@authentication']);

                    // file upload access
                    $router->get('/upload/{name}', ['uses' => $controller . '@upload']);

                    // services consume
                    $router->put('/ServicesConsume', ['uses' => $controller . '@ServicesConsume']);

                    $router->group(['prefix' => 'trash'], function () use ($controller, $router) {
                        $router->get('/', ['uses' => $controller . '@trash']);
                        $router->put('/restore/{uuid}', ['uses' => $controller . '@trashRestore']);
                        $router->delete('/delete/{uuid}', ['uses' => $controller . '@trashDelete']);
                    });
                });
            }
        }
        else
        {
            // services url using prefix with services uri
            $app->router->group(['prefix' => $uri], function ($router) use ($controller) {
                $router->get('/', ['uses' => $controller . '@index']);
                $router->get('/search', ['uses' => $controller . '@search']);
                $router->get('/read/{uuid}', ['uses' => $controller . '@show']);
                $router->post('/create', ['uses' => $controller . '@store']);
                $router->put('/update/{uuid}', ['uses' => $controller . '@update']);
                $router->delete('/delete/{uuid}', ['uses' => $controller . '@delete']);

                // trash url
                $router->group(['prefix' => 'trash'], function () use ($controller, $router) {
                    $router->get('/', ['uses' => $controller . '@trash']);
                    $router->put('/restore/{uuid}', ['uses' => $controller . '@trashRestore']);
                    $router->delete('/delete/{uuid}', ['uses' => $controller . '@trashDelete']);
                });
            });
        }
    }
}
