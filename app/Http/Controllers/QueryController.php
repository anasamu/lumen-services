<?php

namespace App\Http\Controllers;

use finfo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
    Berikut ini beberapa parameter request input value yang dapat digunakan secara default
    anda dapat menggunakan request ini pada function index dan function search atau function delete.

    - order_by         => digunakan untuk order data yang akan ditampilkan pastikan request menurut column yang ada pada table. secara default order_by menurut column created_at
    - order_type       => digunakan untuk sorting data. parameter yang dimasukan hanya "asc" atau "desc" selain itu akan digunakan value yang diguanakan "desc" secara default
    - limit            => digunakan untuk membatasi jumlah data yang akan ditampilkan. parameter yang dikirim harus bertipe integer. jika limit yang dikirmkan 0 maka akan menampilkan semua data yang ada pada table.
    - query            => digunakan untuk mencari data.
    - cursor           => digunakan untuk key pagination data
    - period_by        => digunakan untuk menampilkan data berdasarkan periode tertentu. parameter yang dikirim berupa column pada table yang menggunakan tipedata date
    - period_start     => digunakan untuk menampilkan data berdasarkan periode tertentu. parameter yang dikirim berupa format date "mm/dd/yyyy". untuk menggunakan fitur ini pastikan request "period_by" sudah diisi
    - period_end       => digunakan untuk menampilkan data berdasarkan periode tertentu. parameter yang dikirim berupa format date "mm/dd/yyyy". untuk menggunakan fitur ini pastikan request "period_by" sudah diisi
    - count_all        => digunakan untuk menampilkan jumlah data yang ada pada table.
    - count_data       => digunakan untuk menampilkan jumlah data min, max, avg pada table. pastikan request untuk "count_data_by_all" sudah diisi.
    - count_data_by    => digunakan untuk menampilkan jumlah data berdasarkan column yang hanya berisi value integer atau decimal.
    - group_by         => digunakan untuk menampilkan data berdasarkan group. pastikan parameter yang dikirim ada pada column table.
    - forced_delete    => digunakan untuk menghapus paksa dan melewati soft delete pada function delete. value parameter ini harus diisi "yes"
*/

Trait QueryController
{
    public function __construct($model){
        $this->model = $model;
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
            $offset = (request()->input('limit') !== null && is_integer(request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT'));

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
                $response = $this->model->orderBy($order, $order_type)->cursorPaginate($offset);
            }

            // cek jika response berhasil
            if($response){
                // menampilkan data yang direquest
                return $this->res($response, Response::HTTP_OK, trans("apps.msg_results"));
            }

            // menampilkan respon "not found" jika data tidak ditemukan
            return $this->res(null, 404, trans("apps.msg_null_results"));
        }
        catch (QueryException $e) {
            // menampilkan kesalahan yang tidak diketahui!
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
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
            $result = $this->model->where(function($q){

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

            })->orderBy($order, $order_type); // sorting pencarian sesuai order yang dipilih

            // cek jika hasil pencarian hanya ingin menampilkan total dari hasil pencarian
            if(request()->input('count_all') == true){
                // count all rows
                $response = [
                    'count_all' => $result->count(),
                ];

                // mengirimkan hasil pencarian kedalam bentuk json
                if($response){
                    return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
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
                    return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            else
            {
                // cek jika hasil pencarian ingin ditampilkan semua data
                $offset = (request()->input('limit') !== null && is_integer(request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT'));
                if($offset == 0)
                {
                    // menampilkan semua data
                    $response = $result->get();
                }
                else
                {
                    // menampilkan data berdasarkan per page
                    $response = $result->cursorPaginate($offset);
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
                return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
            }

            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function authentication(){
        if($this->auth_request['auth_request'] !== true){
            abort(404);
        }

        $request = request()->all();
        if(!array_key_exists($this->auth_request['auth_key_name'], $request) AND !array_key_exists($this->auth_request['auth_key_hash'], $request)){
            abort(404);
        }

        try {
            $result = $this->model->where(function($q){
                // cek auth key name
                $q->where($this->auth_request['auth_key_name'], request()->input($this->auth_request['auth_key_name']));
            })->firstOrFail()->toArray();

            $request_auth = request()->input($this->auth_request['auth_key_hash']);
            $response_hash = $result[$this->auth_request['auth_key_hash']];
            if(password_verify($request_auth,$response_hash))
            {
                $response = [
                    'authentication' => true,
                    'data' => $result
                ];

                unset($response['data'][$this->auth_request['auth_key_hash']]);
                return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_auth_success'));
            }

            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_auth_failed'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function upload($name = null){

        if($name == null){
            abort(404);
        }

        if($this->upload_request['upload_request'] != true){
            abort(404);
        }

        $file_name = $this->upload_request['upload_dir'].'/'.$name;
        $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
        if($storage->exists($file_name)){
            if($storage->exists($file_name)){
                $url = config('app.AWS_URL') . $file_name;
                if(config('app.FILESYSTEM_DISK','local') == "local"){
                    $url = storage_path('app/'. $file_name);
                }

                $getfile    = file_get_contents($url);
                $file_info  = new finfo(FILEINFO_MIME_TYPE);
                $mime_type  = $file_info->buffer($getfile);
                return response($getfile, 200)->header('Content-type', $mime_type);
            }
        }

        abort(404);
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

            // cek jika upload request diaktifkan
            if($this->upload_request['upload_request'] == true){
                // cek jika request menggunakan multipart
                if(request()->hasFile($this->upload_request['upload_name'])){
                    $files = request()->file($this->upload_request['upload_name']);
                    $random_name = $files->hashName();

                    // cek ekstensi yang diupload
                    if(!in_array($files->getClientOriginalExtension(),$this->upload_request['extension'])){
                        abort(415,trans('apps.msg_upload_ext_not_support'));
                    }

                    // convert byte to kb
                    $size = number_format($files->getSize() / 1024, 0);
                    // jika max upload 0 maka unlimited size upload
                    if($this->upload_request['max_upload'] > 0){
                        if($size > $this->upload_request['max_upload']){
                            abort(413,trans('apps.msg_upload_large'));
                        }
                    }

                    // tambahkan name upload pada request
                    $request[$this->upload_request['upload_name']] = $random_name;
                    // tambahkan metadata pada request
                    $request['file_original_name']  = $files->getClientOriginalName();
                    $request['file_extension']      = $files->getClientOriginalExtension();
                    $request['file_mime_type']      = $files->getMimeType();
                    $request['file_size']           = $files->getSize();

                    // proses upload file
                    $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                    $request['url_s3'] = null;
                    if(config('app.FILESYSTEM_DISK','local') == 's3'){
                        $request['url_s3'] = config('app.AWS_URL') . '/' . $random_name;
                    }

                    $request['sub_directory'] = null;
                    $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $random_name;
                    $storage->put($upload_files, file_get_contents($files), 'public');
                }
            }

            $results = $this->model->create($request);
            return $this->res($results, Response::HTTP_CREATED, trans('apps.msg_store_data'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function show($uuid){
        try {
            $results = $this->model->findOrFail($uuid);
            if($results){
                $response = $this->services_dependency($results);
                return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_results_show_data'));
            }
            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
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

            // upload options
            // cek jika request ada perubahan pada file upload
            // cek jika upload request diaktifkan
            if($this->upload_request['upload_request'] == true){
                if(array_key_exists($this->upload_request['upload_name'], $request)){
                    // cek jika upload request diaktifkan
                    if($this->upload_request['upload_request'] == true){
                        // cek jika request menggunakan multipart
                        if(request()->hasFile($this->upload_request['upload_name'])){
                            $temp = $results->toArray();
                            $name = $temp[$this->upload_request['upload_name']];
                            $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $name;
                            $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                            if($storage->exists($upload_files)){
                                $storage->delete($upload_files);
                            }

                            $files = request()->file($this->upload_request['upload_name']);
                            $random_name = $files->hashName();
                            // cek ekstensi yang diupload
                            if(!in_array($files->getClientOriginalExtension(),$this->upload_request['extension'])){
                                abort(415,trans('apps.msg_upload_ext_not_support'));
                            }

                            // convert byte to kb
                            $size = number_format($files->getSize() / 1024, 0);
                            // jika max upload 0 maka unlimited size upload
                            if($this->upload_request['max_upload'] > 0){
                                if($size > $this->upload_request['max_upload']){
                                    abort(413,trans('apps.msg_upload_large'));
                                }
                            }

                            // tambahkan name upload pada request
                            $request[$this->upload_request['upload_name']] = $random_name;
                            // tambahkan metadata pada request
                            $request['file_original_name']  = $files->getClientOriginalName();
                            $request['file_extension']      = $files->getClientOriginalExtension();
                            $request['file_mime_type']      = $files->getMimeType();
                            $request['file_size']           = $files->getSize();

                            // proses upload
                            $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                            $request['url_s3'] = null;
                            if(config('app.FILESYSTEM_DISK','local') == 's3'){
                                $request['url_s3'] = config('app.AWS_URL') . '/' . $random_name;
                            }

                            $request['sub_directory'] = null;
                            $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $random_name;

                            $storage->put($upload_files, file_get_contents($files), 'public');
                        }
                    }

                    // convert results to array
                    $temp = $results->toArray();
                    $upload_files = null; // deklarasi ulang variable
                    // proses hapus file sebelumnya
                    $name = $temp[$this->upload_request['upload_name']];
                    $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $name;
                    $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                    if($storage->exists($upload_files)){
                        $storage->delete($upload_files);
                    }
                }
            }

            if($results->update($request)){
                return $this->res($results, Response::HTTP_CREATED, trans('apps.msg_update_data'));
            }
            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_update_data_failed'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function delete($uuid){
        try {
            $results = $this->model->findOrFail($uuid);
            if($results->delete()){
                if(request()->input('forced_delete') == "yes"){
                    // proses hapus file sebelumnya
                    $temp = $results->toArray();
                    $name = $temp[$this->upload_request['upload_name']];
                    $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $name;
                    $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                    if($storage->exists($upload_files)){
                        $storage->delete($upload_files);
                        $results->forceDelete();
                        return $this->res($results, Response::HTTP_ACCEPTED, trans('apps.msg_delete_data_success'));
                    }
                }

                return $this->res($results, Response::HTTP_OK, trans('apps.msg_move_to_trash_success'));
            }

            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_move_to_trash_failed'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    // Trash function for soft deleted
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
                    return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
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
                    return $this->res($response, Response::HTTP_FOUND, trans('apps.msg_search_results'));
                }

                return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
            }
            else
            {
                # check search result if need get all data or with pagination
                $offset = (request()->input('limit') !== null && is_integer(request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT'));
                if($offset == 0)
                {
                    $response = $result->get();
                }
                else
                {
                    $response = $result->cursorPaginate($offset);
                }

                // check if using group_by request
                if(request()->input('group_by') !== null){
                    if(in_array(request()->input('group_by'), $columns)){
                        $response = $result->get()->groupBy(request()->input('group_by'));
                    }
                }
            }

            if($response){
                return $this->res($response, Response::HTTP_OK, trans('apps.msg_results'));
            }
            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_null_results'));

        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function trashRestore($uuid){
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            if($results->trashed())
            {
                $results->restore();
                return $this->res($results, Response::HTTP_CREATED, trans('apps.msg_restore_trash_success'));
            }
            return $this->res($results, Response::HTTP_NOT_FOUND, trans('apps.msg_restore_trash_failed'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    public function trashDelete($uuid){
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            $temp = $results;
            if($results->trashed())
            {
                if($this->upload_request['upload_request'] == true){
                    // proses hapus file sebelumnya
                    $temp = $results->toArray();
                    $name = $temp[$this->upload_request['upload_name']];
                    $upload_files = '/' . $this->upload_request['upload_dir'] . '/' . $name;
                    $storage = Storage::disk(config('app.FILESYSTEM_DISK','local'));
                    if($storage->exists($upload_files)){
                        $storage->delete($upload_files);
                        $results->forceDelete();
                        return $this->res($results, Response::HTTP_ACCEPTED, trans('apps.msg_delete_data_success'));
                    }
                }

                return $this->res($results, Response::HTTP_ACCEPTED, trans('apps.msg_delete_data_success'));
            }
            return $this->res($results, Response::HTTP_NOT_FOUND, trans('apps.msg_delete_data_failed'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }

    protected function services_dependency($response){
        $res = $response->toArray();
        // cek jika pencarian menggunakan uuid_dependecy
        $services_json = null;
        if(file_exists(base_path('services.json'))){
            $services_files = file_get_contents(base_path('services.json'));
            $services_json = json_decode($services_files,true);

            // if using show response data
            if($services_json !== null){
                foreach($this->uuid_dependency as $item_uuid => $val){
                    // setup services dependency
                    $uuid                       = $res[$item_uuid];
                    $services_name_dependency   = explode('@',$val)[0];
                    $services_scope_dependency  = explode('@',$val)[1];

                    // connect to other services
                    if(array_key_exists($services_name_dependency,$services_json)){
                        $services            = $services_json[$services_name_dependency];
                        $services_host       = rtrim($services['host'], '/\\');
                        $services_secret_key = $services['secret_key'];
                        $services_url        = $services_host . '/' . $services_scope_dependency . '/' . 'ServicesConsume/' . $uuid;
                        $services_response   = $this->get_services($services_url, $services_secret_key);

                        if($services_response !== null){
                            if($item_uuid == 'created_by' OR $item_uuid == 'updated_by'){
                                $res[$item_uuid] = $services_response;
                            }
                            else{
                                $add_services_scope_list = $services_scope_dependency;
                                $res[$add_services_scope_list] = $services_response;
                            }
                        }
                    }
                }
            }
        }

        // return back response data with modify results response
        return $res;
    }

    protected function get_services($url, $secret_key){
        try {
            $mode   = request()->header('x-sandbox-mode');
            $res = Http::withHeaders([
                'x-services-secret-key' => $secret_key,
                'x-sandbox-mode' => $mode
            ])->put($url);
            $body = json_decode($res->body());
            return $body->results;

        } catch (\Throwable $th) {
            return null;
        }
    }

    public function ServicesConsume($uuid){
        try {
            $results = $this->model->select($this->services_consume)->findOrFail($uuid);
            if($results){
                return $this->res($results, Response::HTTP_FOUND, trans('apps.msg_results_show_data'));
            }
            return $this->res(null, Response::HTTP_NOT_FOUND, trans('apps.msg_search_not_found'));
        }
        catch (QueryException $e) {
            return $this->res(null,Response::HTTP_BAD_REQUEST, $e->errorInfo[2]);
        }
    }
}