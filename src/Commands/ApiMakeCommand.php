<?php

namespace Anhnguyen02\CodeGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use function Laravel\Prompts\confirm;

class ApiMakeCommand extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:api {name : route group prefix.}
                                     {controller : route group prefix.}
                                     {--middleware=} {--model=} {--api=} {--requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CRUD route group';

    /**
     * Create a new controller creator command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $prefix = Str::slug($this->argument('name'));
        $stub = $this->files->get($this->getStub());

        $middleware = $this->option('middleware');
        $controller = $this->argument('controller');
        $controllerPath = app_path('\Http\Controllers\\' . $controller . '.php');

        if (!$this->alreadyExists($controllerPath)
            && confirm("A $controller does not exist. The route requires a controller. Do you want to generate it?", default: true))
        {
            if ($this->option('model')) {
                $this->call('crud:controller', [
                    'name' => $controller,
                    '--api' => true,
                    '--model' => $this->option('model'),
                    '--requests' => true
                ]);
            }
            else if (confirm('Do you want to use the model in the controller?', true))
            {
                $modelName = $this->ask('What is model name?');
                if ($modelName) {
                    $this->call('crud:controller', [
                        'name' => $controller,
                        '--api' => true,
                        '--model' => $modelName,
                        '--requests' => true
                    ]);
                }
            } else {
                $this->call('crud:controller', [
                    'name' => $controller,
                    '--api' => true
                ]);
            }

            return $this->replacePrefix($stub, $prefix)
                        ->replaceController($stub, $controller)
                        ->replaceMiddleware($stub, $middleware)
                        ->writeRoute($stub, $controller);
        }
    }

    /**
     * Determine if the file already exists.
     *
     * @param  string $file
     * @return bool
     */
    protected function alreadyExists($file)
    {
        return $this->files->exists($file);
    }

    /**
     * @param $controller
     * @return $this
     */
    protected function addUseController($controller)
    {
        $path = base_path('routes/api.php');
        $content = $this->files->get($path);
        $namespace = 'App\Http\Controllers\\'.$controller;
        $content = str_replace("<?php\n\n", "<?php\n\nuse $namespace;\n", $content);

        return $content;
    }

    /**
     * Write the route to file routes/api.php.
     *
     * @param $stub
     * @param $controller
     * @return void
     */
    protected function writeRoute($stub, $controller)
    {
        $path = base_path('routes/api.php');
        $this->files->append($path, "\n" . $stub, $separator = PHP_EOL);

        $content = $this->addUseController($controller);
        $this->files->put($path, $content);

        $this->components->info(sprintf('Route [%s] created successfully.', $path));
    }

    /**
     * Replace the middleware part for the given stub.
     *
     * @param $stub
     * @param $middleware
     * @return $this
     */
    protected function replaceMiddleware(&$stub, $middleware)
    {
        if ($middleware) {
            $middleware = "->middleware($middleware)";
        }
        $stub = str_replace('{{middleware}}', $middleware, $stub);
        return $this;
    }

    /**
     * Replace the controler part for the given stub.
     *
     * @param $stub
     * @param $controller
     * @return $this
     */
    protected function replaceController(&$stub, $controller)
    {
        $stub = str_replace('{{controller}}', $controller, $stub);
        return $this;
    }

    /**
     * Replace the prefix part for the given stub.
     *
     * @param $stub
     * @param $prefix
     * @return $this
     */
    protected function replacePrefix(&$stub, $prefix)
    {
        $stub = str_replace('{{prefix}}', $prefix, $stub);
        return $this;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/route.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }
}
