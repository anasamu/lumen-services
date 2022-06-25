<?php
namespace App\Http\Controllers;

use App\Models\Example as Model;
use App\Http\Controllers\QueryController;
class ExampleController extends Controller
{
    use QueryController;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Model();

        $this->auth_request = [
            'auth_request' => true, // true or false to active this feature
            'auth_key_name' => 'name', // from column name in table example
            'auth_key_hash' =>'password' // from column password in table example
        ];

        $this->upload_request = [
            'upload_request' => true,
            'upload_dir' => 'images', // direktori yang dituju untuk upload
            'upload_name' => 'foto', // column pada table untuk menyimpan info upload
            'extension' => ['jpg','mp4','docx','pdf'], // ekstensi yang bisa upload
            'max_upload' => '5024' // maksimal ukuran file yang akan diupload
        ];

        $this->services_consume = [
            'uuid',
            'name'
        ];

        $this->search_column = [
            'name',
            'price',
            'qty',
            'description'
        ];

        $this->uuid_dependency = [
            'uuid_sync' => 'services-sync@sync',
            'created_by' => 'services-user@user',
            'updated_by' => 'services-user@user',
        ];

        $this->date_columns = [
            'created_at',
            'updated_at',
        ];

        $this->number_columns = [
            'price',
            'qty'
        ];
    }
}
