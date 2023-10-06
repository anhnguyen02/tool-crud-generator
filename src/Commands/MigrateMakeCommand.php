<?php

namespace Anhnguyen02\CodeGenerator\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'crud:migration')]
class MigrateMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * Exp: php artisan crud:migration Category --soft-deletes --schema="title#string; body#text; name#enum#options={\"Yes\": \"Yes\",\"No\": \"No\"}"
     * @var string
     */
    protected $name = 'crud:migration';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'crud:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Migration';

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['schema', 's', InputOption::VALUE_REQUIRED, 'The name of the schema'],
            ['indexes', 'i', InputOption::VALUE_NONE, 'The fields to add an index to'],
            ['foreign-keys', 'fk', InputOption::VALUE_NONE, 'Foreign keys'],
            ['pk', '', InputOption::VALUE_REQUIRED, 'The name of the primary key'],
            ['soft-deletes', 'f', InputOption::VALUE_NONE, 'Include soft deletes fields'],
        ];
    }

    /**
     *  Migration column types collection.
     *
     * @var array
     */
    protected $typeLookup = [
        'char' => 'char',
        'date' => 'date',
        'datetime' => 'dateTime',
        'time' => 'time',
        'timestamp' => 'timestamp',
        'text' => 'text',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'binary' => 'binary',
        'number' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigInteger',
        'mediumint' => 'mediumInteger',
        'tinyint' => 'tinyInteger',
        'smallint' => 'smallInteger',
        'boolean' => 'boolean',
        'decimal' => 'decimal',
        'double' => 'double',
        'float' => 'float',
        'enum' => 'enum',
    ];

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/migration.stub');
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
     * Get the destination class path.
     *
     * @param  string  $tableName
     *
     * @return string
     */
    protected function getPath($tableName)
    {
        $name = str_replace($this->laravel->getNamespace(), '', $tableName);
        $datePrefix = date('Y_m_d_His');

        return database_path('migrations/') . $datePrefix . '_create_' . $tableName . '_table.php';
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() {
        $tableName = Str::snake(trim($this->input->getArgument('name')));

        $stub = $this->files->get($this->getStub());
        $path = $this->getPath($tableName);
        $this->files->ensureDirectoryExists(dirname($path));

        $fieldsToIndex = trim($this->option('indexes')) != ''
            ? explode(',', $this->option('indexes'))
            : [];
        $foreignKeys = trim($this->option('foreign-keys')) != ''
            ? explode(',', $this->option('foreign-keys'))
            : [];

        $schema = rtrim($this->option('schema'), ';');
        $fields = explode(';', $schema);

        $data = array();

        if ($schema) {
            $x = 0;
            foreach ($fields as $field) {
                $fieldArray = explode('#', $field);
                $data[$x]['name'] = trim($fieldArray[0]);
                $data[$x]['type'] = trim($fieldArray[1]);
                if (($data[$x]['type'] === 'select'
                        || $data[$x]['type'] === 'enum')
                    && isset($fieldArray[2])
                ) {
                    $options = trim($fieldArray[2]);
                    $data[$x]['options'] = str_replace('options=', '', $options);
                }

                $data[$x]['modifier'] = '';

                $modifierLookup = [
                    'comment',
                    'default',
                    'first',
                    'nullable',
                    'unsigned',
                ];

                if (isset($fieldArray[2]) && in_array(trim($fieldArray[2]), $modifierLookup)) {
                    $data[$x]['modifier'] = "->" . trim($fieldArray[2]) . "()";
                }

                $x++;
            }
        }

        $tabIndent = '    ';
        $schemaFields = '';
        foreach ($data as $item) {
            if (isset($this->typeLookup[$item['type']])) {
                $type = $this->typeLookup[$item['type']];

                if ($type === 'select' || $type === 'enum') {
                    $enumOptions = array_keys(json_decode($item['options'], true));
                    $enumOptionsStr = implode(",", array_map(function ($string) {
                        return '"' . $string . '"';
                    }, $enumOptions));
                    $schemaFields .= "\$table->" . $type . "('" . $item['name'] . "', [" . $enumOptionsStr . "])";
                } else {
                    $schemaFields .= "\$table->" . $type . "('" . $item['name'] . "')";
                }
            } else {
                $schemaFields .= "\$table->string('" . $item['name'] . "')";
            }

            // Append column modifier
            $schemaFields .= $item['modifier'];
            $schemaFields .= ";\n" . $tabIndent . $tabIndent . $tabIndent;
        }

        // add indexes and unique indexes as necessary
        foreach ($fieldsToIndex as $fldData) {
            $line = trim($fldData);

            // is a unique index specified after the #?
            // if no hash present, we append one to make life easier
            if (strpos($line, '#') === false) {
                $line .= '#';
            }

            // parts[0] = field name (or names if pipe separated)
            // parts[1] = unique specified
            $parts = explode('#', $line);
            if (strpos($parts[0], '|') !== 0) {
                $fieldNames = "['" . implode("', '", explode('|', $parts[0])) . "']"; // wrap single quotes around each element
            } else {
                $fieldNames = trim($parts[0]);
            }

            if (count($parts) > 1 && $parts[1] == 'unique') {
                $schemaFields .= "\$table->unique(" . trim($fieldNames) . ")";
            } else {
                $schemaFields .= "\$table->index(" . trim($fieldNames) . ")";
            }

            $schemaFields .= ";\n" . $tabIndent . $tabIndent . $tabIndent;
        }

        // foreign keys
        foreach ($foreignKeys as $fk) {
            $line = trim($fk);

            $parts = explode('#', $line);

            // if we don't have three parts, then the foreign key isn't defined properly
            // --foreign-keys="foreign_entity_id#id#foreign_entity#onDelete#onUpdate"
            if (count($parts) == 3) {
                $schemaFields .= "\$table->foreign('" . trim($parts[0]) . "')"
                    . "->references('" . trim($parts[1]) . "')->on('" . trim($parts[2]) . "')";
            } elseif (count($parts) == 4) {
                $schemaFields .= "\$table->foreign('" . trim($parts[0]) . "')"
                    . "->references('" . trim($parts[1]) . "')->on('" . trim($parts[2]) . "')"
                    . "->onDelete('" . trim($parts[3]) . "')" . "->onUpdate('" . trim($parts[3]) . "')";
            } elseif (count($parts) == 5) {
                $schemaFields .= "\$table->foreign('" . trim($parts[0]) . "')"
                    . "->references('" . trim($parts[1]) . "')->on('" . trim($parts[2]) . "')"
                    . "->onDelete('" . trim($parts[3]) . "')" . "->onUpdate('" . trim($parts[4]) . "')";
            } else {
                continue;
            }

            $schemaFields .= ";\n" . $tabIndent . $tabIndent . $tabIndent;
        }

        $primaryKey = $this->option('pk');
        $softDeletes = $this->option('soft-deletes');

        $softDeletesSnippets = '';
        if ($softDeletes == 'yes') {
            $softDeletesSnippets = $tabIndent . $tabIndent . $tabIndent . "\$table->softDeletes();\n";
        }

        $schemaFields = rtrim($schemaFields);
        if ($schemaFields) {
            $schemaFields = "\n" . $tabIndent . $tabIndent . $tabIndent . $schemaFields;
        }

        $schemaUp =
            "Schema::create('" . $tableName . "', function (Blueprint \$table) {
            \$table->increments('" . $primaryKey . "');$schemaFields
            \$table->timestamps();\n" .
            $softDeletesSnippets .
            $tabIndent . $tabIndent ."});";

        $schemaDown = "Schema::drop('" . $tableName . "');";

        return $this->replaceSchemaUp($stub, $schemaUp)
            ->replaceSchemaDown($stub, $schemaDown)
            ->writeMigration($stub, $path);
    }

    /**
     * Write the migration file to disk.
     * @param $stub
     * @return void
     */
    protected function writeMigration($stub, $path)
    {
        $this->files->put($path, $stub);

        $this->components->info(sprintf('Migration [%s] created successfully.', $path));
    }

    /**
     * Replace the schema_up for the given stub.
     *
     * @param  string  $stub
     * @param  string  $schemaUp
     *
     * @return $this
     */
    protected function replaceSchemaUp(&$stub, $schemaUp)
    {
        $stub = str_replace('{{schema_up}}', $schemaUp, $stub);

        return $this;
    }

    /**
     * Replace the schema_down for the given stub.
     *
     * @param  string  $stub
     * @param  string  $schemaDown
     *
     * @return $this
     */
    protected function replaceSchemaDown(&$stub, $schemaDown)
    {
        $stub = str_replace('{{schema_down}}', $schemaDown, $stub);

        return $this;
    }
}
