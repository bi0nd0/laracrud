<?php namespace Whitegolem\LaraCrud\Routing\Controllers;

use Illuminate\Routing\Controllers\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class NestedController extends BaseController {

	protected $nestedModelName;

	public function __construct()
	{

	}

	/**
	 * get the names of the parent or nested resource
	 */
	private function getModelsName($nested=false)
	{
		$classBaseName = class_basename($this);
		$segments = explode('_',snake_case($classBaseName)); //the last segment should be the string 'controller'
		$modelName = $segments[0];
		$nestedModelName = $segments[1];

		$name = ($nested) ? $nestedModelName : $modelName;

		return Str::singular($name);
	}

	/**
	 * get the name of the parent resource model
	 */
	protected function getModelName()
	{
		if(isset($this->modelName)) return $this->modelname;

		return $this->getModelsName();
	}

	/**
	 * get the name of the nested resource model
	 */
	protected function getNestedModelName()
	{
		if(isset($this->nestedModelName)) return $this->nestedModelname;

		return $this->getModelsName(true);
	}

	protected function getNestedModel($input=array())
	{
		$modelClass = Str::studly($this->getModelName(true));
		$nestedModel = new $modelClass($input);

		return $nestedModel;
	}



	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($id)
	{
		$model = $this->getModel();
		$nestedModel = $this->getNestedModel();

		$query = $nestedModel->newQuery();

		//use this hook to alter the query
		$beforeQuery = Event::fire('before.query', array(&$query));

		$query = $this->getResults($query);
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create($id)
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($id)
	{
		//
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id, $nestedId)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id, $nestedId)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id, $nestedId)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id, $nestedId)
	{
		//
	}

	

}

/**
 * GET /admin/users/{users}/offices                | admin.users.offices.index         | Admin\UsersOfficesController@index
 * GET /admin/users/{users}/offices/create         | admin.users.offices.create        | Admin\UsersOfficesController@create
 * POST /admin/users/{users}/offices               | admin.users.offices.store         | Admin\UsersOfficesController@store
 * GET /admin/users/{users}/offices/{offices}      | admin.users.offices.show          | Admin\UsersOfficesController@show
 * GET /admin/users/{users}/offices/{offices}/edit | admin.users.offices.edit          | Admin\UsersOfficesController@edit
 * PUT /admin/users/{users}/offices/{offices}      | admin.users.offices.update        | Admin\UsersOfficesController@update
 * PATCH /admin/users/{users}/offices/{offices}    |                                   | Admin\UsersOfficesController@update
 * DELETE /admin/users/{users}/offices/{offices}   | admin.users.offices.destroy       | Admin\UsersOfficesController@destroy
 */