<?php

namespace App\Http\Controllers;

use App\Traits\ServicesResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

trait QueryController
{
    use ServicesResponse;

    public function __construct($model)
    {
        $this->model = $model;
    }

    /*
        Get informasi columns pada services
    */
    public function info()
    {
        try {
            $columns = Schema::getColumnListing($this->model->getTable());
            $results = [
                'scopeAccess' => request()->segment(1),
                'columnsList' => $columns
            ];
            return $this->response($results, 200, 'Info Scope');
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Menampilkan data pada services
    */
    public function index()
    {

        // cek jika tidak ada masalah saat pemanggilan function index
        try {
            $order      = 'created_at';
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';
            if (!in_array($order_type, $this->order_type)) {
                $order_type = 'desc';
            }

            if (request()->input('order_by') !== null) {
                $columns    = Schema::getColumnListing($this->model->getTable());
                if (in_array(request()->input('order_by'), $columns)) {
                    $order = request()->input('order_by');
                }
            }

            $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');
            if ($offset == 0) {
                $response = $this->selectColumns()->orderBy($order, $order_type)->get();
            } else {
                $response = $this->selectColumns()->orderBy($order, $order_type)->Paginate($offset);
                if (request()->input('_relatedColumns')) {
                    $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                }
            }

            if ($response) {
                return $this->response($response, Response::HTTP_OK, trans("apps.msg_results"));
            }

            return $this->error_response(trans('apps.msg_null_results'), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            // menampilkan kesalahan yang tidak diketahui!
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Pencarian pada services
    */
    public function search()
    {
        try {

            $order      = 'created_at';
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';

            // cek jika order type yang dikirim "asc" atau "desc"
            // jika tidak maka ubah order type menjadi "desc"
            if (!in_array($order_type, $this->order_type)) {
                $order_type = 'desc';
            }

            // cek jika value pada parameter order_by ada dalam column table
            $columns = Schema::getColumnListing($this->model->getTable());
            if (request()->input('order_by') !== null) {
                if (in_array(request()->input('order_by'), $columns)) {
                    $order = request()->input('order_by');
                }
            }

            // fitur pencarian pada table
            $result = $this->selectColumns()->where(function ($q) {
                return $this->searchColumns($q);
            })->where(function ($q) use ($columns) {

                if (request()->input("query")) {
                    if (request()->input('_selectColumns')) {
                        $firstSearch = 0;
                        foreach (request()->input('_selectColumns') as $items) {
                            if (in_array($items, $columns)) {
                                if ($firstSearch == 0) {
                                    $q->where($items, 'like', '%' . request()->input('query') . '%');
                                } else {
                                    $q->orWhere($items, 'like', '%' . request()->input('query') . '%');
                                }

                                $firstSearch++;
                            }
                        }
                    } else {
                        if (isset($this->search_column)) {
                            foreach ($this->search_column as $i => $v) {
                                if ($i == 0) {
                                    $q->where($v, 'LIKE', '%' . request()->input('query') . '%');
                                } else {
                                    $q->orWhere($v, 'LIKE', '%' . request()->input('query') . '%');
                                }
                            }
                        } else {
                            foreach ($columns as $i => $v) {
                                if ($i == 0) {
                                    $q->where($v, 'LIKE', '%' . request()->input('query') . '%');
                                } else {
                                    $q->orWhere($v, 'LIKE', '%' . request()->input('query') . '%');
                                }
                            }
                        }
                    }
                }
            })->where(function ($q) use ($columns) {
                if (request()->input('_usersDataConfig')) {
                    if (is_array(request()->input('_usersDataConfig'))) {
                        foreach (request()->input('_usersDataConfig') as $items => $val) {
                            if (in_array($items, $columns)) {
                                $q->where($items, $val);
                            }
                        }
                    }
                }
            })->orderBy($order, $order_type);

            // cek jika hasil pencarian hanya ingin menampilkan total dari hasil pencarian
            if (request()->input('_countResults')) {
                return $this->countResults($result);
            } else {
                // cek jika hasil pencarian ingin ditampilkan semua data
                $response = $result->get();
                $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');
                if ($offset > 0) {
                    // menampilkan data berdasarkan per page
                    $response = $result->Paginate($offset);
                    if (request()->input('_relatedColumns')) {
                        if (request()->input('_relatedColumns')) {
                            $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                        }
                    }
                }

                // cek jika hasil pencarian akan ditampilkan menurut group
                if (request()->input('group_by') !== null) {
                    if (in_array(request()->input('group_by'), $columns)) {
                        $response = $result->get()->groupBy(request()->input('group_by'));
                        if (request()->input('_relatedColumns')) {
                            if (request()->input('_relatedColumns')) {
                                $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                            }
                        }
                    }
                }
            }

            // mengirimkan hasil pencarian kedalam bentuk json
            if ($response) {
                return $this->response($response, Response::HTTP_OK, trans('apps.msg_search_results'));
            }

            return $this->error_response(trans('apps.msg_search_not_found'), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        menyimpan data pada services
    */
    public function store()
    {
        try {
            $columns    = Schema::getColumnListing($this->model->getTable());
            $request    = request()->all();

            // validation
            if (isset($request['_rules'])) {
                $rules = $request['_rules'];
                $validator = Validator::make($request, $rules);
                if ($validator->fails()) {
                    return $this->error_response('Request tidak valid', Response::HTTP_BAD_REQUEST, $validator->errors());
                }
            }

            foreach ($request as $key => $val) {
                # remove request key if not column in table
                if (!in_array($key, $columns)) {
                    unset($request[$key]);
                }
            }

            $results = $this->model->create($request);
            if ($results) {
                return $this->response($results, Response::HTTP_CREATED, trans('apps.msg_store_data'));
            }
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Menampilkan data berdasarkan uuid
    */
    public function show($uuid)
    {
        try {
            $results = $this->selectColumns()->findOrFail($uuid);
            if ($results) {
                if (request()->input('_relatedColumns')) {
                    $results = $this->services_dependency($results, request()->input('_relatedColumns'));
                }
            }
            return $this->response($results, Response::HTTP_OK, trans('apps.msg_results_show_data'));
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Menampilkan data berdasarkan request
    */
    public function details()
    {
        try {
            $request = request()->input('_details');
            if (!request()->input('_details')) {
                return $this->error_response('request tidak valid!');
            }

            if (is_array($request)) {
                if (isset($request['columns_key'])) {
                    if (isset($request['columns_value'])) {
                        $columns = Schema::getColumnListing($this->model->getTable());
                        if (in_array($request['columns_key'], $columns)) {
                            $results = $this->selectColumns()->where($request['columns_key'], $request['columns_value'])->firstOrFail();
                            if ($results) {
                                if (request()->input('_relatedColumns')) {
                                    $results = $this->services_dependency($results, request()->input('_relatedColumns'));
                                }

                                return $this->response($results, Response::HTTP_OK, trans('apps.msg_results_show_data'));
                            }
                        }
                    } else {
                        return $this->error_response('columns_value tidak tersedia');
                    }
                } else {
                    return $this->error_response('columns_key tidak tersedia');
                }
            }

            return $this->error_response(trans('apps.msg_search_not_found'), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage());
        }
    }

    /*
        mengupdate data berdasarkan uuid
    */
    public function update($uuid)
    {
        try {
            $results = $this->model->findOrFail($uuid);
            $columns = Schema::getColumnListing($this->model->getTable());
            $request = request()->all();

            // validation
            if (isset($request['_rules'])) {
                $rules = $request['_rules'];
                foreach ($rules as $items => $val) {
                    $rules[$items] = str_replace('{uuid}', $results->uuid, $val);
                }

                $validator = Validator::make($request, $rules);
                if ($validator->fails()) {
                    return $this->error_response('Request tidak valid', Response::HTTP_BAD_REQUEST, $validator->errors());
                }
            }

            foreach ($request as $key => $val) {
                # remove request key if not column in table
                if (!in_array($key, $columns)) {
                    unset($request[$key]);
                }
            }

            if ($results->update($request)) {
                return $this->response($results, Response::HTTP_CREATED, trans('apps.msg_update_data'));
            }
            return $this->error_response(trans('apps.msg_update_data_failed'), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        menghapus data berdasarkan uuid
    */
    public function delete($uuid)
    {
        try {
            $results = $this->model->findOrFail($uuid);
            if ($results->delete()) {
                if (request()->input('forced_delete') == "yes") {
                    $results->forceDelete();
                }
            }

            return $this->response(null, Response::HTTP_OK, trans('apps.msg_delete_data_success'));
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Menampilkan semua data yang terhapus
    */
    public function trash()
    {
        try {
            $columns    = Schema::getColumnListing($this->model->getTable());
            $order      = 'created_at';
            $order_type = (request()->input('order_type') !== null) ? request()->input('order_type') : 'desc';

            // cek jika order type yang dikirim "asc" atau "desc"
            // jika tidak maka ubah order type menjadi "desc"
            if (!in_array($order_type, $this->order_type)) {
                $order_type = 'desc';
            }

            if (request()->input('order_by') !== null) {
                if (in_array(request()->input('order_by'), $columns)) {
                    $order = request()->input('order_by');
                }
            }

            # Search Options
            $result = $this->selectColumns()->onlyTrashed()->where(function ($q) {
                return $this->searchColumns($q);
            })->where(function ($q) use ($columns) {
                if (request()->input("query")) {
                    if (request()->input('_selectColumns')) {
                        $firstSearch = 0;
                        foreach (request()->input('_selectColumns') as $items) {
                            if (in_array($items, $columns)) {
                                if ($firstSearch == 0) {
                                    $q->where($items, 'like', '%' . request()->input('query') . '%');
                                } else {
                                    $q->orWhere($items, 'like', '%' . request()->input('query') . '%');
                                }

                                $firstSearch++;
                            }
                        }
                    } else {
                        foreach ($this->search_column as $i => $v) {
                            if ($i == 0) {
                                $q->where($v, 'LIKE', '%' . request()->input('query') . '%');
                            } else {
                                $q->orWhere($v, 'LIKE', '%' . request()->input('query') . '%');
                            }
                        }
                    }
                }
            })->where(function ($q) use ($columns) {
                if (request()->input('_usersDataConfig')) {
                    if (is_array(request()->input('_usersDataConfig'))) {
                        foreach (request()->input('_usersDataConfig') as $items => $val) {
                            if (in_array($items, $columns)) {
                                $q->where($items, $val);
                            }
                        }
                    }
                }
            })->orderBy($order, $order_type);

            if (request()->input('_countResults')) {
                return $this->countResults($result);
            } else {
                # check search result if need get all data or with pagination
                $offset = (request()->input('limit')) ? request()->input('limit') : config('app.APP_PAGINATION_LIMIT');
                if ($offset == 0) {
                    $response = $result->get();
                } else {
                    $response = $result->Paginate($offset);
                    if (request()->input('_relatedColumns')) {
                        $response = $this->services_dependency($response, request()->input('_relatedColumns'));
                    }
                }

                // check if using group_by request
                if (request()->input('group_by') !== null) {
                    if (in_array(request()->input('group_by'), $columns)) {
                        $response = $result->get()->groupBy(request()->input('group_by'));
                    }
                }
            }

            if ($response) {
                return $this->response($response, Response::HTTP_OK, trans('apps.msg_results'));
            }

            return $this->error_response(trans('apps.msg_null_results'), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Mengembalikan data yang dihapus berdasarkan uuid
    */
    public function trashRestore($uuid)
    {
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            if ($results->trashed()) {
                $results->restore();
            }

            return $this->response(null, Response::HTTP_OK, trans('apps.msg_restore_trash_success'));
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        Menghapus data secara permanent berdasarkan uuid
    */
    public function trashDelete($uuid)
    {
        try {
            $results = $this->model->withTrashed()->findOrFail($uuid);
            if ($results->trashed()) {
                $results->forceDelete();
            }
            return $this->response(null, Response::HTTP_OK, trans('apps.msg_delete_data_success'));
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }


    /*
        Services Consume Dependency out
    */
    protected function services_dependency($response, $related)
    {
        $res = $response->toArray();
        // cek jika pencarian menggunakan uuid_dependecy
        $services_json = null;
        if (file_exists(base_path('services.json'))) {
            $services_files = @file_get_contents(base_path('services.json'));
            $services_json = json_decode($services_files, true);
            // if using show response data
            if ($services_json !== null) {
                if (isset($res['data'])) {
                    $response = [];
                    foreach ($res['data'] as $index => $val) {
                        $response[$index] = $this->servicesRelated($val, $related);
                    }

                    $res['data'] = $response;
                    return $res;
                } else {
                    if (array_key_exists(0, $res)) {
                        if (count($res) > 0) {
                            foreach ($res as $index => $val) {
                                $res[$index] = $this->servicesRelated($val, $related);
                            }

                            return $res;
                        }

                        $res = $this->servicesRelated($res, $related);
                        return $res;
                    } else {
                        $response = [];
                        foreach ($res as $in => $yx) {
                            foreach ($yx as $items) {
                                $response[$in] = $this->servicesRelated($items, $related);
                            }
                        }
                        $res = $response;
                        return $res;
                    }
                }
            }
        }

        // return back response data with modify results response
        return $res;
    }

    /*
        Services Related process
    */
    protected function servicesRelated($res, $related)
    {
        $services_json = null;
        if (file_exists(base_path('services.json'))) {
            $services_files = @file_get_contents(base_path('services.json'));
            $services_json = json_decode($services_files, true);
            // if using show response data
            if ($services_json !== null) {
                foreach ($related as $srvDetails => $srvColumns) {
                    $services_name_dependency   = explode('@', $srvDetails)[0];
                    $services_scope_dependency  = explode('@', $srvDetails)[1];
                    foreach ($srvColumns as $_srvColumns) {
                        if (isset($_srvColumns['foreign_key'])) {
                            if (isset($_srvColumns['primary_key'])) {
                                // connect to other services
                                if (array_key_exists($services_name_dependency, $services_json)) {
                                    $services               = $services_json[$services_name_dependency];
                                    $services_host          = rtrim($services['host'], '/\\');
                                    $services_secret_key    = $services['secret_key'];
                                    $services_url           = $services_host . '/' . $services_scope_dependency . '/' . 'ServicesConsume/';
                                    $foreign_key            = $_srvColumns['foreign_key'];
                                    $foreign_value          = $res[$foreign_key];
                                    $_srvColumns['value']   = $foreign_value;

                                    if ($services_name_dependency === 'services-api') {
                                        $header = [
                                            'x-services-name' => $services_name_dependency,
                                            'x-scope-access' => $services_scope_dependency,
                                            'Authorization' => request()->header('Authorization'),
                                            'x-services-secret-key' => $services_secret_key,
                                            'x-sandbox-mode' => request()->header('x-sandbox-mode'),
                                            'x-api-key' => request()->header('x-api-key'),
                                        ];
                                    } else {
                                        $header = [
                                            'x-services-secret-key' => $services_secret_key,
                                            'x-sandbox-mode' => request()->header('x-sandbox-mode')
                                        ];
                                    }

                                    $services_response = $this->get_services($services_url, $_srvColumns, $header);

                                    if ($services_response !== null) {
                                        $res[$_srvColumns['alias']] = $services_response;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $res;
    }

    /*
        Services Get Data
    */
    protected function get_services($url, $request = null, $header = null)
    {
        try {
            $res = Http::withHeaders($header)->put($url, $request);
            $body = json_decode($res->body());
            return $body->results;
        } catch (\Throwable $th) {
            return null;
        }
    }

    /*
        Services Consume Data
    */
    public function ServicesConsume()
    {
        try {
            $results = false;
            $columns = Schema::getColumnListing($this->model->getTable());
            if (in_array(request()->input('primary_key'), $columns)) {
                $results = $this->selectColumns()->where(function ($q) {
                    $q->where(request()->input('primary_key'), request()->input('value'));
                })->get();
            }

            if ($results) {
                if (request()->input('_relatedColumns')) {
                    if (request()->input('_relatedColumns')) {
                        $results = $this->services_dependency($results, request()->input('_relatedColumns'));
                    }
                }
                return $this->responseData($results, Response::HTTP_OK, trans('apps.msg_results_show_data'));
            }
            return $this->error_response(trans('apps.msg_null_results'), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->error_response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /*
        select columns secara spesifik
    */
    protected function selectColumns()
    {
        $columns = Schema::getColumnListing($this->model->getTable());
        if (request()->input('_selectColumns')) {
            if (is_array(request()->input('_selectColumns'))) {
                $colData = [];
                foreach (request()->input('_selectColumns') as $i => $val) {
                    if (in_array($val, $columns)) {
                        array_push($colData, $val);
                    }
                }

                if (count($colData) > 0) {
                    return $this->model->select($colData);
                }
            } else {
                if (in_array(request()->input('_selectColumns'), $columns)) {
                    return $this->model->select(request()->input('_selectColumns'));
                }
            }
        }

        return $this->model->select($columns);
    }

    /*
        Pencarian data secara spesifik
    */
    protected function searchColumns($q)
    {
        $columns = Schema::getColumnListing($this->model->getTable());
        if (request()->input('_customSearch')) {
            $customSearch = request()->input('_customSearch');

            if (is_array($customSearch)) {

                foreach ($customSearch as $items => $val1) {
                    // cek jika columns tersedia
                    if (in_array($items, $columns)) {
                        // cek jika nilai dari columns bertipe array
                        if (is_array($val1)) {

                            foreach ($val1 as $arr => $val2) {
                                // cek jika value dari value pertama bertipe array
                                if (is_array($val2)) {
                                    if ($arr == 'whereClause') {
                                        foreach ($val2 as $i => $v) {
                                            $q->where($items, $i, $v);
                                        }
                                    }

                                    if ($arr == 'whereIn') {
                                        $q->whereIn($items, $val2);
                                    } elseif ($arr == 'whereNotIn') {
                                        $q->whereNotIn($items, $val2);
                                    }

                                    if ($arr == 'orWhereIn') {
                                        $q->orWhereIn($items, $val2);
                                    } elseif ($arr == 'orWhereNotIn') {
                                        $q->orWhereNotIn($items, $val2);
                                    }

                                    if ($arr == 'whereBetween') {
                                        $q->whereBetween($items, $val2);
                                    } elseif ($arr == 'whereNotBetween') {
                                        $q->whereNotBetween($items, $val2);
                                    }

                                    if ($arr == 'orWhereBetween') {
                                        $q->orWhereBetween($items, $val2);
                                    } elseif ($arr == 'orWhereNotBetween') {
                                        $q->orWhereNotBetween($items, $val2);
                                    }
                                } else {
                                    if (in_array($items, $this->date_columns)) {
                                        if ($arr == 'whereDate') {
                                            $q->whereDate($items, $val2);
                                        } elseif ($arr == 'whereMonth') {
                                            $q->whereMonth($items, $val2);
                                        } elseif ($arr == 'whereDay') {
                                            $q->whereDay($items, $val2);
                                        } elseif ($arr == 'whereYear') {
                                            $q->whereYear($items, $val2);
                                        }
                                    }
                                }
                            }
                        } else {
                            $q->where($items, $val1);
                        }
                    }
                }
            }
        }
        return $q;
    }

    /*
        Get response data with request count in columns
    */
    protected function countResults($result)
    {
        $countResults = request()->input('_countResults');
        if (is_array($countResults)) {

            if (isset($countResults['count_all'])) {

                if ($countResults['count_all']) {
                    return $this->response($result->count(), Response::HTTP_OK, trans('apps.msg_search_results'));
                }

                return $this->error_response('nilai dari count_all harus true');
            } elseif (isset($countResults['countData'])) {
                $countData = $countResults['countData'];
                if (is_array($countData)) {

                    if (!isset($countData['_selectColums'])) {
                        return $this->error_response('_selectColums tidak tersedia di countData');
                    }

                    if (!isset($countData['_responseResults'])) {
                        return $this->error_response('_responseResults tidak tersedia di countData');
                    }

                    $columns = Schema::getColumnListing($this->model->getTable());
                    $selectColumns = $countData['_selectColums'];
                    $responseResults = $countData['_responseResults'];

                    if (!in_array($selectColumns, $columns)) {
                        return $this->error_response('_selectColums tidak valid. columns yang dipilih tidak tersedia!');
                    }

                    $response = null;
                    if (isset($responseResults['min'])) {
                        $response['min'] = $result->min($selectColumns);
                    }

                    if (isset($responseResults['max'])) {
                        $response['max'] = $result->max($selectColumns);
                    }

                    if (isset($responseResults['average'])) {
                        $response['average'] = $result->avg($selectColumns);
                    }

                    if ($response) {
                        return $this->response($response, Response::HTTP_OK, trans('apps.msg_search_results'));
                    }

                    return $this->error_response('nilai dari _responseResults tidak valid!');
                }

                return $this->error_response('nilai countData harus bertipe array');
            }
        }

        return $this->error_response('request yang dikirimkan tidak valid!');
    }
}
