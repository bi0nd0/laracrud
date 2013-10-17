<?php namespace Whitegolem\LaraCrud\Routing\Controllers;

use Illuminate\Routing\Controllers\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class NestedController extends Controller {

	protected $modelName;

	protected $viewsBasePath;

	private $resultsKey;

	private $resultsKeySingular;

	protected $paginate = false; //true to enable pagination by default

	public function __construct()
	{

	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index($id, )
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create($id, )
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store($id, )
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