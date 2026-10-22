<?php

namespace App\Console\Commands;

use App\Models\Scheduler;
use App\Models\APIInstance;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Libraries\ExecAPI;

class ScheduleExecCommand extends Command
{
    protected $config = [];
    protected $signature = 'schedule:exec {id}';
    protected $description = 'Runs Specified Schedule By ID';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $schedule_id = $this->argument('id');
        $task = Scheduler::where('id', $schedule_id)->first();

        if (is_null($task)) {
            $this->error('Scheduled Task Not Found!');
            return Command::FAILURE;
        }

        if ($task->enabled !== true) {
            $this->info('Scheduled Task "'.$task->name.'" is not Enabled... skipping');
            return Command::SUCCESS;
        }

        $current_minute = Carbon::now()->format("Y-m-d H:i:00");
        $task->last_exec_cron = $current_minute;
        $task->last_exec_start = Carbon::now()->format("Y-m-d H:i:s");
        $task->save();

        $exec_api = new ExecAPI();
        $api_instance = APIInstance::where('id', $task->api_instance_id)->with('api')->first();    

        if (is_null($api_instance)) {
            $this->error('API Instance Not Found!');
            return Command::FAILURE;
        }

        $_SERVER['REQUEST_METHOD'] = strtoupper($task->verb ?? 'GET');
        $_SERVER['REQUEST_URI'] = '/'.$api_instance->slug.$task->route;

        $args = [];
        if (is_iterable($task->args)) {
            foreach($task->args as $arg) {
                if (is_object($arg) && isset($arg->name)) {
                    $args[$arg->name] = $arg->value ?? null;
                } elseif (is_array($arg) && isset($arg['name'])) {
                    $args[$arg['name']] = $arg['value'] ?? null;
                }
            }
        }

        $_GET = $args;
        $result = $exec_api->eval_code($api_instance);

        $task->last_response = $result;
        $task->last_exec_stop = Carbon::now()->format("Y-m-d H:i:s");
        $task->save();

        return Command::SUCCESS;
    }
}