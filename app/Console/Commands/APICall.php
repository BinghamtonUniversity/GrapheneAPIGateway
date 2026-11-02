<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Models\APIInstance;
use App\Models\APIVersion;
use App\Models\Scheduler;
use Illuminate\Console\Command;
use App\Libraries\ExecAPI;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class APICall extends Command
{
    protected $config = [];
    protected $signature = 'api:call {verb} {domain} {slug} {route} {args?*}';
    protected $description = 'Calls and executes specified API';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $verb = strtoupper($this->argument('verb'));
        if (!in_array($verb, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'ALL'])) {
            $this->error('Invalid Verb! Must be: GET, POST, PUT, PATCH, DELETE');
            return Command::FAILURE;
        }

        $environment = Environment::where('domain', $this->argument('domain'))->first();
        if (is_null($environment)) {
            $this->error('Domain "'.$this->argument('domain').'" Not Found!');
            return Command::FAILURE;
        }

        $api_instance = APIInstance::where('slug', $this->argument('slug'))
            ->where('environment_id', $environment->id)
            ->with('api')->first();    

        if (is_null($api_instance)) {
            $this->error('API Slug "'.$this->argument('slug').'" Not Found!');
            return Command::FAILURE;
        }

        $route = $this->argument('route');
        if (!str_starts_with($route, '/')) {
            $this->error('Routes must start with a "/" --> Invalid: "'.$route.'"');
            return Command::FAILURE;
        }

        $args = [];
        foreach($this->argument('args') as $arg) {
            $args_array = explode('=', $arg, 2);
            if (isset($args_array[1])) {
                $args[$args_array[0]] = $args_array[1];
            } else {
                $this->info('Skipping Argument: "'.$arg.'" --> Missing "=" and value');
            }
        }

        $exec_api = new ExecAPI();
        $_SERVER['REQUEST_METHOD'] = $verb;
        $_SERVER['REQUEST_URI'] = '/'.$api_instance->slug.$route;
        $_GET = $args;

        $result = $exec_api->eval_code($api_instance);

        if ($result instanceof SymfonyResponse) {
            var_dump(json_decode($result->getContent(), true));
        } else {
            var_dump($result);
        }

        return Command::SUCCESS;
    }
}