<?php

namespace App\Http\Controllers;

use App\Services\ServicesResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

Trait QueryController
{
    use ServicesResponse;

    public function __construct($model){
        $this->model = $model;
    }

    public function info(){
        try {
            $columns = Schema::getColumnListing($this->model->getTable());
            $results = [
                'columns' => $columns
            ];
            return $this->response($results,200, 'Info Scope');
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function index(){

        // cek jika tidak ada masalah saat pemanggilan function index
        try {

            // buat order default menggunakan column "created_at"
            $order      = 'created_at';
            // cek jika request order_type tersedia.
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';

            // cek jika request order_by
            if(request()->input('order_by') !== null){
                $columns    = Schema::getColumnListing($this->model->getTable());
                // cek column yang tersedia pada table
                // cek jika order_by ada pada column tabel
                if(in_array(request()->input('order_by'), $columns)){
                    // ganti order default sesuai dengan request
                    $order = request()->input('order_by');
                }
            }

            // buat variabel untuk batas data yang akan ditampilkan
            $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');

            // cek jika order type yang dikirim "asc" atau "desc"
            if(!in_array($order_type,$this->order_type)){
                // jika tidak maka ubah order type menjadi "desc"
                $order_type = 'desc';
            }

            // cek variable limit. jika request limit 0 maka akan menampilkan semua data
            if($offset == 0){
                // tampilkan semua data dan urutkan sesuai dengan request order
                // jika semua data ditampilkan maka tidak akan menampilka dependency data dari services lain
                $response = $this->model->orderBy($order, $order_type)->get();
            }
            else
            {
                // tampilkan data berdasarkan limit dan urutkan sesuai dengan request order
                $response = $this->model->orderBy($order, $order_type)->Paginate($offset);
                if(request()->input('_relatedColumns')){
                    if(request()->input('_relatedColumns')){
                        $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                    }
                }
            }

            // cek jika response berhasil
            if($response){
                // menampilkan data yang direquest
                return $this->response($response, Response::HTTP_OK, trans("apps.msg_results"));
            }

            // menampilkan respon "not found" jika data tidak ditemukan
            return $this->response(null, 404, trans("apps.msg_null_results"));
        }
        catch (\Throwable $e) {
            // menampilkan kesalahan yang tidak diketahui!
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function search(){
        try {

            $order      = 'created_at';
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';

            // cek jika order type yang dikirim "asc" atau "desc"
            // jika tidak maka ubah order type menjadi "desc"
            if(!in_array($order_type,$this->order_type)){
                $order_type = 'desc';
            }

            // cek jika value pada parameter order_by ada dalam column table
            $columns = Schema::getColumnListing($this->model->getTable());
            if(request()->input('order_by') !== null){
                if(in_array(request()->input('order_by'), $columns)){
                    $order = request()->input('order_by');
                }
            }

            // fitur pencarian pada table
            $result = $this->model->where(function($q) use($columns){

                if(request()->input("query") !== null){
                    // digunakan untuk mencari kata kunci sesuai parameter query yang dimasukkan.
                    foreach($this->search_column as $items){
                        $q->orWhere($items, 'LIKE', '%' . request()->input("query") . '%');
                    }
                }
                else
                {
                    foreach($columns as $items){
                        if(array_key_exists($items, request()->all())){
                            $q->where($items, request()->input($items));
                        }
                    }
                }

                // custom advanced search
                if(request()->input('_customSearch') !== null){
                    $customSearch = request()->input('_customSearch');
                    if(is_array($customSearch)){
                        foreach($customSearch as $items => $val1){
                            if(in_array($items, $columns)){
                                if(is_array($val1)){
                                    foreach($val1 as $arr => $val2){
                                        if(is_array($val2)){

                                            if($arr == 'whereClause'){
                                                foreach($val2 as $i => $v){
                                                    $q->where($items, $i, $v);
                                                }
                                            }

                                            if($arr == 'whereIn'){
                                                $q->whereIn($items, $val2);
                                            }
                                            elseif($arr == 'whereNotIn'){
                                                $q->whereNotIn($items, $val2);
                                            }

                                            if($arr == 'orWhereIn'){
                                                $q->orWhereIn($items, $val2);
                                            }
                                            elseif($arr == 'orWhereNotIn'){
                                                $q->orWhereNotIn($items, $val2);
                                            }

                                            if($arr == 'whereBetween'){
                                                $q->whereBetween($items, $val2);
                                            }
                                            elseif($arr == 'whereNotBetween'){
                                                $q->whereNotBetween($items, $val2);
                                            }

                                            if($arr == 'orWhereBetween'){
                                                $q->orWhereBetween($items, $val2);
                                            }
                                            elseif($arr == 'orWhereNotBetween'){
                                                $q->orWhereNotBetween($items, $val2);
                                            }
                                        }
                                        else
                                        {
                                            if(in_array($items, $this->date_columns)){
                                                if($arr == 'whereDate'){
                                                    $q->whereDate($items, $val2);
                                                }
                                                elseif($arr == 'whereMonth'){
                                                    $q->whereMonth($items, $val2);
                                                }
                                                elseif($arr == 'whereDay'){
                                                    $q->whereDay($items, $val2);
                                                }
                                                elseif($arr == 'whereYear'){
                                                    $q->whereYear($items, $val2);
                                                }
                                            }
                                        }
                                    }
                                }
                                else
                                {
                                    $q->where($items, $val1);
                                }
                            }
                        }
                    }
                }

                // cek parameter jika menggunakan period_by dan ubah parameter date menjadi 'Y-m-d'
                $period_by = request()->input('period_by');
                // cek parameter period_by ada dalam date_column pada tabel
                if(in_array($period_by, $this->date_columns))
                {
                    $period_start   = date('Y-m-d', strtotime(request()->input('period_start')));
                    $period_end     = date('Y-m-d');
                    if(request()->input('period_end') !== null){
                        $period_end = date('Y-m-d', strtotime(request()->input('period_end')));
                    }

                    // mencari sesuai dengan period yang dipilih
                    if($period_by !== null && $period_start !== null && $period_end !== null){
                        $q->whereBetween($period_by,[$period_start, $period_end]);
                    }
                }

            })->orderBy($order, $order_type); // sorting pencarian sesuai order yang dipilih

            // cek jika hasil pencarian hanya ingin menampilkan total dari hasil pencarian
            if(request()->input('count_all') == true){
                // count all rows
                $response = [
                    'count_all' => $result->count(),
                ];

                // mengirimkan hasil pencarian kedalam bentuk json
                if($response){
                    return $this->response($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            elseif(request()->input('count_data') == true && request()->input('count_data_by') !== null){
                // cek jika hasil pencarian hanya ingin menampilkan data statistik min, max, avg
                if(in_array(request()->input('count_data_by'), $this->number_columns)){
                    $response = [
                        'count_min' => $result->min(request()->input('count_data_by')),
                        'count_max' => $result->max(request()->input('count_data_by')),
                        'count_avg' => number_format($result->avg(request()->input('count_data_by')), 2),
                    ];
                }

                // mengirimkan hasil pencarian kedalam bentuk json
                if($response){
                    return $this->response($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            else
            {
                // cek jika hasil pencarian ingin ditampilkan semua data
                $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');
                if($offset == 0)
                {
                    // menampilkan semua data
                    $response = $result->get();
                }
                else
                {
                    // menampilkan data berdasarkan per page
                    $response = $result->Paginate($offset);
                    if(request()->input('_relatedColumns')){
                        if(request()->input('_relatedColumns')){
                            $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                        }
                    }
                }

                // cek jika hasil pencarian akan ditampilkan menurut group
                if(request()->input('group_by') !== null){
                    if(in_array(request()->input('group_by'), $columns)){
                        $response = $result->get()->groupBy(request()->input('group_by'));
                    }
                }
            }

            // mengirimkan hasil pencarian kedalam bentuk json
            if($response){
                return $this->response($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
            }

            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(){
        try {
            $columns    = Schema::getColumnListing($this->model->getTable());
            $request    = request()->all();

            foreach($request as $key => $val){
                # remove request key if not column in table
                if(!in_array($key,$columns)){
                    unset($request[$key]);
                }

                // if in array using password request
                if($this->auth_request['auth_request']){
                    if($key == $this->auth_request['auth_key_hash']){
                        $request[$key] = password_hash($val, PASSWORD_DEFAULT);
                    }
                }
            }

            $results = $this->model->create($request);
            return $this->response($results, Response::HTTP_CREATED, trans('apps.msg_store_data'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function show($uuid){
        try {
            $results = $this->model->findOrFail($uuid);
            if($results){
                if(request()->input('_relatedColumns')){
                    $results = $this->services_dependency($results, request()->input('_relatedColumns'));
                }

                return $this->response($results, Response::HTTP_FOUND, trans('apps.msg_results_show_data'));
            }
            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function details(){
        try {
            $results = false;
            $request = request()->input('details');
            if(is_array($request)){
                if(isset($request['columns_key'])){
                    if(isset($request['columns_value'])){
                        $columns = Schema::getColumnListing($this->model->getTable());
                        if(in_array($request['columns_key'], $columns)){
                            $results = $this->model->where($request['columns_key'], '=', $request['columns_value'])->first();
                        }
                    }
                }
            }

            if($results){
                if(request()->input('_relatedColumns')){
                    $results = $this->services_dependency($results, request()->input('_relatedColumns'));
                }

                return $this->response($results, Response::HTTP_FOUND, trans('apps.msg_results_show_data'));
            }

            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function update($uuid){
        try {
            $results = $this->model->findOrFail($uuid);
            $columns = Schema::getColumnListing($this->model->getTable());
            $request = request()->all();

            foreach($request as $key => $val){
                # remove request key if not column in table
                if(!in_array($key,$columns)){
                    unset($request[$key]);
                }

                // if in array auth_request using password request
                if($this->auth_request['auth_request']){
                    if($key == $this->auth_request['auth_key_hash']){
                        $request[$key] = password_hash($val, PASSWORD_DEFAULT);
                    }
                }
            }

            if($results->update($request)){
                return $this->response($results, Response::HTTP_CREATED, trans('apps.msg_update_data'));
            }
            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_update_data_failed'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function delete($uuid){
        try {
            $results = $this->model->findOrFail($uuid);
            if($results->delete()){
                if(request()->input('forced_delete') == "yes"){
                    $results->forceDelete();
                    return $this->response($results, Response::HTTP_ACCEPTED, trans('apps.msg_delete_data_success'));
                }

                return $this->response($results, Response::HTTP_OK, trans('apps.msg_move_to_trash_success'));
            }

            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_move_to_trash_failed'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function trash()
    {
        try {
            $columns    = Schema::getColumnListing($this->model->getTable());
            $order      = 'created_at';
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';

            // cek jika order type yang dikirim "asc" atau "desc"
            // jika tidak maka ubah order type menjadi "desc"
            if(!in_array($order_type,$this->order_type)){
                $order_type = 'desc';
            }

            if(request()->input('order_by') !== null){
                if(in_array(request()->input('order_by'), $columns)){
                    $order = request()->input('order_by');
                }
            }

            # Search Options
            $result = $this->model->onlyTrashed()->where(function($q){

                // digunakan untuk mencari kata kunci sesuai parameter query yang dimasukkan.
                if(request()->input("query") !== null){
                    foreach($this->search_column as $items){
                        $q->orWhere($items, 'LIKE', '%' . request()->input("query") . '%');
                    }
                }
                else
                {
                    // cek jika parameter input menggunakan column name
                    $columns = Schema::getColumnListing($this->model->getTable());
                    foreach($columns as $items){
                        if(array_key_exists($items, request()->all())){
                            if(request()->input('query_statement'))
                            {
                                $q->where($items, request()->input('query_statement') ,request()->input($items));
                            }
                            else
                            {
                                $q->where($items, request()->input($items));
                            }
                        }
                    }
                }

                // cek parameter jika menggunakan period_by dan ubah parameter date menjadi 'Y-m-d'
                $period_by = request()->input('period_by');
                // cek parameter period_by ada dalam date_column pada tabel
                if(in_array($period_by, $this->date_columns))
                {
                    $period_start   = date('Y-m-d', strtotime(request()->input('period_start')));
                    $period_end     = date('Y-m-d');
                    if(request()->input('period_end') !== null){
                        $period_end = date('Y-m-d', strtotime(request()->input('period_end')));
                    }

                    // mencari sesuai dengan period yang dipilih
                    if($period_by !== null && $period_start !== null && $period_end !== null){
                        $q->whereBetween($period_by,[$period_start, $period_end]);
                    }
                }
            })->orderBy($order, $order_type); # Order search by request

            // cek jika hasil pencarian hanya ingin menampilkan total dari hasil pencarian
            if(request()->input('count_all') == true){
                // count all rows
                $response = [
                    'count_all' => $result->count(),
                ];

                // mengirimkan hasil pencarian kedalam bentuk json
                if($response){
                    return $this->response($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            elseif(request()->input('count_data') == true && request()->input('count_data_by') !== null){
                // cek jika hasil pencarian hanya ingin menampilkan data statistik min, max, avg
                if(in_array(request()->input('count_data_by'), $this->number_columns)){
                    $response = [
                        'count_min' => $result->min(request()->input('count_data_by')),
                        'count_max' => $result->max(request()->input('count_data_by')),
                        'count_avg' => number_format($result->avg(request()->input('count_data_by')), 2),
                    ];
                }

                // mengirimkan hasil pencarian kedalam bentuk json
                if($response){
                    return $this->response($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            else
            {
                # check search result if need get all data or with pagination
                $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');
                if($offset == 0)
                {
                    $response = $result->get();
                }
                else
                {
                    $response = $result->Paginate($offset);
                }

                // check if using group_by request
                if(request()->input('group_by') !== null){
                    if(in_array(request()->input('group_by'), $columns)){
                        $response = $result->get()->groupBy(request()->input('group_by'));
                    }
                }
            }

            if($response){
                return $this->response($response, Response::HTTP_OK, trans('apps.msg_results'));
            }
            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_null_results'));

        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function trashRestore($uuid){
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            if($results->trashed())
            {
                $results->restore();
                return $this->response($results, Response::HTTP_CREATED, trans('apps.msg_restore_trash_success'));
            }
            return $this->response($results, Response::HTTP_NOT_FOUND, trans('apps.msg_restore_trash_failed'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    public function trashDelete($uuid){
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            if($results->trashed())
            {
                $results->forceDelete();
                return $this->response($results, Response::HTTP_ACCEPTED, trans('apps.msg_delete_data_success'));
            }

            return $this->response($results, Response::HTTP_NOT_FOUND, trans('apps.msg_delete_data_failed'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }

    protected function services_dependency($response, $related){
        $res = $response->toArray();
        // cek jika pencarian menggunakan uuid_dependecy
        $services_json = null;
        if(file_exists(base_path('services.json'))){
            $services_files = @file_get_contents(base_path('services.json'));
            $services_json = json_decode($services_files,true);

            // if using show response data
            if($services_json !== null){
                if(!isset($res['data'])){
                    foreach($related as $r_srv){
                        foreach($r_srv as $srvDetails => $srvColumns){
                            // setup services dependency
                            $services_name_dependency   = explode('@',$srvDetails)[0];
                            $services_scope_dependency  = explode('@',$srvDetails)[1];
                            for ($i=0; $i <= count($srvColumns) ; $i++) {

                                if(!isset($srvColumns[$i]['foreign_key'])){
                                    return $res;
                                }

                                if(!isset($srvColumns[$i]['primary_key'])){
                                    return $res;
                                }

                                // connect to other services
                                if(array_key_exists($services_name_dependency,$services_json)){
                                    $services            = $services_json[$services_name_dependency];
                                    $services_host       = rtrim($services['host'], '/\\');
                                    $services_secret_key = $services['secret_key'];
                                    $services_url        = $services_host . '/' . $services_scope_dependency . '/' . 'ServicesConsume/';
                                    $foreign_key         = $srvColumns[$i]['foreign_key'];
                                    $foreign_value       = $res[$foreign_key];
                                    $requestServices     = [
                                        'primary_key' => $srvColumns[$i]['primary_key'],
                                        'value' => $foreign_value
                                    ];

                                    $services_response   = $this->get_services($services_url, $services_secret_key, $requestServices);
                                    if($services_response !== null){
                                        if(isset($srvColumns[$i]['alias'])){
                                            unset($res[$foreign_key]);
                                            $res[$srvColumns[$i]['alias']] = $services_response;
                                        }
                                        else
                                        {
                                            $res[$foreign_key] = $services_response;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    return $res;
                }

                $nums = 0;
                foreach($res['data'] as $items){
                    foreach($related as $r_srv){
                        foreach($r_srv as $srvDetails => $srvColumns){
                            // setup services dependency
                            $services_name_dependency   = explode('@',$srvDetails)[0];
                            $services_scope_dependency  = explode('@',$srvDetails)[1];
                            for ($i=0; $i <= count($srvColumns) ; $i++) {

                                if(!isset($srvColumns[$i]['foreign_key'])){
                                    return $res;
                                }

                                if(!isset($srvColumns[$i]['primary_key'])){
                                    return $res;
                                }

                                // connect to other services
                                if(array_key_exists($services_name_dependency,$services_json)){
                                    $services            = $services_json[$services_name_dependency];
                                    $services_host       = rtrim($services['host'], '/\\');
                                    $services_secret_key = $services['secret_key'];
                                    $services_url        = $services_host . '/' . $services_scope_dependency . '/' . 'ServicesConsume/';
                                    $foreign_key         = $srvColumns[$i]['foreign_key'];
                                    $foreign_value       = $items[$foreign_key];
                                    $requestServices     = [
                                        'primary_key' => $srvColumns[$i]['primary_key'],
                                        'value' => $foreign_value
                                    ];

                                    $services_response   = $this->get_services($services_url, $services_secret_key, $requestServices);
                                    if($services_response !== null){
                                        if(isset($srvColumns[$i]['alias'])){
                                            unset($res['data'][$nums][$foreign_key]);
                                            $res['data'][$nums][$srvColumns[$i]['alias']] = $services_response;
                                        }
                                        else
                                        {
                                            $res['data'][$nums][$foreign_key] = $services_response;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $nums++;
                }

                return $res;
            }
        }

        // return back response data with modify results response
        return $res;
    }

    protected function get_services($url, $secret_key, $request = null){
        try {
            $mode   = request()->header('x-sandbox-mode');
            $res = Http::withHeaders([
                'x-services-secret-key' => $secret_key,
                'x-sandbox-mode' => $mode
            ])->put($url, $request);
            $body = json_decode($res->body());
            return $body->results;

        } catch (\Throwable $th) {
            return null;
        }
    }

    public function ServicesConsume(){
        try {
            $results = false;
            $columns = Schema::getColumnListing($this->model->getTable());
            if(in_array(request()->input('primary_key'), $columns)){
                $results = $this->model->select(request()->input('primary_key'))->select($this->services_consume)->where(function($q){
                    $q->where(request()->input('primary_key'),'=',request()->input('value'));
                })->first();
            }

            if($results){
                return $this->response($results, Response::HTTP_FOUND, trans('apps.msg_results_show_data'));
            }
            return $this->response(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (\Throwable $e) {
            return $this->error_response($e->getMessage(),Response::HTTP_BAD_REQUEST);
        }
    }
}
