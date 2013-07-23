laracrud
========

crud controller helper for laravel 4

1 - open config/app.php and add the service provider for this package in the $providers array 
		
		'Whitegolem\LaraCrud\CrudServiceProvider'

2 - edit BaseController.php:

	<?php

	use Whitegolem\LaraCrud\Routing\Controllers\CrudController;

	class BaseController extends CrudController {
		...
		...
		...

3 - make a BaseModel and inherit from Crud.php:

	<?php

	use Whitegolem\LaraCrud\Database\Eloquent\Crud;

	class Base extends Crud {
		
	}