## Installation

To get started, you should add the `anhnguyen02/code-generator` Composer dependency to your project:
```
composer require anhnguyen02/code-generator --dev
```
Currently only laravel 10.x is supported

## Usage

#### Create CURD api
```
php artisan crud:api music MusicController --api --requests --middleware="['api']"
```
When you run the above command, you will either receive a confirmation or be prompted for assistance with the next steps. Alternatively, you can also use a combination of the command options below to bypass the need for support.


#### Creat controller:
```
php artisan crud:controller PostController --model=Post
```
###### Options:
```md
['api':  'Exclude the create and edit methods from the controller']
['type': 'Manually specify the controller stub file to use']
['force': 'Create the class even if the controller already exists']
['invokable': 'Generate a single method, invokable controller class']
['model': 'Generate a resource controller for the given model']
['parent': 'Generate a nested resource controller class']
['resource': 'Generate a resource controller class']
['requests': 'Generate FormRequest classes for store and update']
['singleton': 'Generate a singleton resource controller class']
['creatable': 'Indicate that a singleton resource should be creatable']
```

#### Create model:
```
php artisan crud:model Post --fillable="['title', 'body']"
```
###### Options:
```md
['all': 'Generate a migration classes for the model'],
['migration': 'Create a new migration file for the model'],
['soft-deletes': 'Enable soft deletes for a model'],
['has-uuids': 'Use a UUID key instead of an auto-incrementing integer key'],
['fillable': 'The names of the fillable columns'],
['pk': 'The name of the primary key'],
['relationships': 'The relationships for the model'],
['force': 'Overwrite model file content'],
# If you want to create migration
['schema': 'The name of the schema'],
['indexes': 'The fields to add an index to'],
['foreign-keys': 'Foreign keys'],
```

#### Create migration:
```
php artisan crud:migration posts --schema="title#string; body#text"
```
###### Options:
```md
['schema': 'The name of the schema'],
['indexes': 'The fields to add an index to'],
['foreign-keys': 'Foreign keys'],
['pk': 'The name of the primary key'],
['soft-deletes': 'Include soft deletes fields'],
```
**Migration Field Types:**
- string
- char
- varchar
- date
- datetime
- time
- timestamp
- text
- mediumtext
- longtext
- json
- jsonb
- binary
- integer
- bigint
- mediumint
- tinyint
- smallint
- boolean
- decimal
- double
- float
- enum
