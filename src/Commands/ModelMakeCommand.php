<?php

namespace Anhnguyen02\CodeGenerator\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'crud:model')]
class ModelMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * Exp: php artisan crud:model Category --fillable="['title', 'body']" --has-uuids --soft-deletes --pk=id --relationships='comments#hasMany#App\Comment|id|comment_id;user#belongsTo#App\User|id|user_id'
     * @var string
     */
    protected $name = 'crud:model';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'crud:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ((! $this->hasOption('force') ||
                ! $this->option('force')) &&
            $this->alreadyExists($this->getNameInput())) {
            $this->components->error($this->type.' already exists.');

            return false;
        }

        $stub = $this->files->get($this->getStub());
        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);
        $softDeletes = $this->option('soft-deletes');

        if ($this->option('schema')) {
            $schema = rtrim($this->option('schema'), ';');
            $fields = explode(';', $schema);

            $fillableArray = [];
            foreach ($fields as $field) {
                $fieldArray = explode('#', $field);
                if ($fieldArray[0]) {
                    $fillableArray = [ ...$fillableArray, "'" . ltrim(rtrim($fieldArray[0])) . "'" ];
                }
            }
            $fillableString = implode(',', $fillableArray);
            $fillable = "[$fillableString]";
        } else {
            $fillable = $this->option('fillable');
        }

        $useHasUuids = $this->option('has-uuids');
        $primaryKey = $this->option('pk');
        $relationships = trim($this->option('relationships')) != '' ? explode(';', trim($this->option('relationships'))) : [];

        if ($this->option('all')) {
            $this->input->setOption('migration', true);
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        $stub = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);

        $ret = $this->replaceSoftDelete($stub, $softDeletes)
            ->replaceUseHasUuids($stub, $useHasUuids)
            ->replacePrimaryKey($stub, $primaryKey)
            ->replaceFillable($stub, $fillable);

        foreach ($relationships as $rel) {
            // relationshipname#relationshiptype#args_separated_by_pipes
            // e.g. employees#hasMany#App\Employee|id|dept_id
            // user is responsible for ensuring these relationships are valid
            $parts = explode('#', $rel);

            if (count($parts) != 3) {
                continue;
            }

            // blindly wrap each arg in single quotes
            $args = explode('|', trim($parts[2]));
            $argsString = '';
            foreach ($args as $k => $v) {
                if (trim($v) == '') {
                    continue;
                }

                $argsString .= "'" . trim($v) . "', ";
            }

            $argsString = substr($argsString, 0, -2); // remove last comma

            $ret->createRelationshipFunction($stub, trim($parts[0]), trim($parts[1]), $argsString);
        }

        $ret->replaceRelationshipPlaceholder($stub);

        return $this->writeModel($stub, $path);
    }

    /**
     * Write the model file to disk.
     * @param $stub
     * @return void
     */
    protected function writeModel($stub, $path)
    {
        $this->files->put($path, $stub);

        $this->components->info(sprintf('Model [%s] created successfully.', $path));
    }

    /**
     * Create the code for a model relationship
     *
     * @param string $stub
     * @param string $relationshipName  the name of the function, e.g. owners
     * @param string $relationshipType  the type of the relationship, hasOne, hasMany, belongsTo etc
     * @param array $relationshipArgs   args for the relationship function
     */
    protected function createRelationshipFunction(&$stub, $relationshipName, $relationshipType, $argsString)
    {
        $tabIndent = '    ';
        $code = "\n" . $tabIndent .  "public function " . $relationshipName . "()\n" . $tabIndent . "{\n" . $tabIndent . $tabIndent
            . "return \$this->" . $relationshipType . "(" . $argsString . ");"
            . "\n" . $tabIndent . "}";

        $str = '{{relationships}}';
        $stub = str_replace($str, $code . "\n" . $tabIndent . $str, $stub);

        return $this;
    }

    /**
     * remove the relationships placeholder when it's no longer needed
     *
     * @param $stub
     * @return $this
     */
    protected function replaceRelationshipPlaceholder(&$stub)
    {
        $stub = str_replace('{{relationships}}', '', $stub);
        return $this;
    }

    /**
     * Replace the (optional) hasUuids part for the given stub.
     *
     * @param $stub
     * @param $replaceUseHasUuids
     * @return $this
     */
    protected function replaceUseHasUuids(&$stub, $replaceUseHasUuids)
    {
        if ($replaceUseHasUuids) {
            $stub = str_replace('{{hasUuids}}', "\n    use HasUuids;", $stub);
            $stub = str_replace('{{useHasUuids}}', "use Illuminate\Database\Eloquent\Concerns\HasUuids;", $stub);
        } else {
            $stub = str_replace("{{hasUuids}}", '', $stub);
            $stub = str_replace("\n{{useHasUuids}}", '', $stub);
        }

        return $this;
    }

    /**
     * Replace the fillable for the given stub.
     *
     * @param  string  $stub
     * @param  string  $fillable
     * @return $this
     */
    protected function replaceFillable(&$stub, $fillable)
    {
        if ($fillable) {
            $fillable = "\n\n". <<<EOD
                /**
                * Attributes that should be mass-assignable.
                *
                * @var array
                */
                protected \$fillable = "$fillable";
            EOD;
        }
        $stub = str_replace('{{fillable}}', $fillable, $stub);

        return $this;
    }

    /**
     * Replace the primaryKey for the given stub.
     *
     * @param  string  $stub
     * @param  string  $fillable
     * @return $this
     */
    protected function replacePrimaryKey(&$stub, $primaryKey)
    {
        if ($primaryKey) {
            $primaryKey = "\n\n" . <<<EOD
                /**
                * The database primary key value.
                *
                * @var string
                */
                protected \$primaryKey = "$primaryKey";
            EOD;
        }
        $stub = str_replace('{{primaryKey}}', $primaryKey, $stub);

        return $this;
    }

    /**
     * Replace the (optional) soft deletes part for the given stub.
     *
     * @param  string  $stub
     * @param  string  $replaceSoftDelete
     * @return $this
     */
    protected function replaceSoftDelete(&$stub, $replaceSoftDelete)
    {
        if ($replaceSoftDelete) {
            $stub = str_replace('{{softDeletes}}', "\n    use SoftDeletes;", $stub);
            $stub = str_replace('{{useSoftDeletes}}', "use Illuminate\Database\Eloquent\SoftDeletes;", $stub);
        } else {
            $stub = str_replace("{{softDeletes}}", '', $stub);
            $stub = str_replace("\n{{useSoftDeletes}}", '', $stub);
        }

        return $this;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/model.stub');
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

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return is_dir(app_path('Models')) ? $rootNamespace.'\\Models' : $rootNamespace;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a migration classes for the model'],
            ['migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model'],
            ['soft-deletes', '', InputOption::VALUE_NONE, 'Enable soft deletes for a model'],
            ['has-uuids', '', InputOption::VALUE_NONE, 'Use a UUID key instead of an auto-incrementing integer key'],
            ['fillable', '', InputOption::VALUE_REQUIRED, 'The names of the fillable columns'],
            ['pk', '', InputOption::VALUE_REQUIRED, 'The name of the primary key'],
            ['relationships', 'r', InputOption::VALUE_REQUIRED, 'The relationships for the model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite model file content'],
            # Create migration
            ['schema', 's', InputOption::VALUE_REQUIRED, 'The name of the schema'],
            ['indexes', 'i', InputOption::VALUE_REQUIRED, 'The fields to add an index to'],
            ['foreign-keys', 'fk', InputOption::VALUE_REQUIRED, 'Foreign keys'],
        ];
    }

    /**
     * Create a migration file for the model.
     *
     * @return void
     */
    protected function createMigration()
    {
        $table = Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));

        $options = [
            'name' => $table
        ];

        if ($this->option('schema')) {
            $options['--schema'] = $this->option('schema');
        }

        if ($this->option('indexes')) {
            $options['--indexes'] = $this->option('indexes');
        }

        if ($this->option('foreign-keys')) {
            $options['--foreign-keys'] = $this->option('foreign-keys');
        }

        if ($this->option('pk')) {
            $options['--pk'] = $this->option('pk');
        }

        if ($this->option('soft-deletes')) {
            $options['--soft-deletes'] = $this->option('soft-deletes');
        }

        $this->call('crud:migration', $options);
    }
}
