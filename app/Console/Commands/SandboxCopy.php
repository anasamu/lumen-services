<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SandboxCopy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'sandbox:copy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy Database Live To Sandbox mode!';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->liveToSandbox();
        $this->info('Copy Database Live ke Sandbox mode');
    }

    //Copy Live Database To Sandbox database
    protected function liveToSandbox(){
        $this->clearSandbox();
        $tablesLive = DB::connection('live')->select('SHOW TABLES');
        foreach ($tablesLive as $table) {
            foreach ($table as $key => $value){
                // import sandbox database
                foreach(DB::connection('live')->table($value)->get() as $data){
                    if($value == 'migrations'){
                        continue;
                    }

                    if($value == 'failed_jobs'){
                        continue;
                    }

                    if($value == 'jobs'){
                        continue;
                    }

                    DB::connection('sandbox')->table($value)->insert((array) $data);
                }
            }
        }
    }

    // clear all data in sandbox database
    protected function clearSandbox(){
        DB::statement("SET foreign_key_checks=0");
        $tables = DB::connection('sandbox')->select('SHOW TABLES');
        foreach ($tables as $table) {
            foreach ($table as $key => $value){
                if($value == 'migrations'){
                    continue;
                }

                if($value == 'failed_jobs'){
                    continue;
                }

                if($value == 'jobs'){
                    continue;
                }
                DB::connection('sandbox')->table($value)->truncate();
            }
        }
        DB::statement("SET foreign_key_checks=1");
    }
}
