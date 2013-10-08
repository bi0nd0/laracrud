<?php namespace Whitegolem\LaraCrud\Routing\Controllers;

use Illuminate\Routing\Controllers\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class CrudController extends Controller {

	private $controllerName;

	private $modelName;

	private $resultsKey;

	private $resultsKeySingular;
	
	protected $model;

	protected $paginate = false; //true to enable pagination by default

	protected $callback = false; //callback function name for jsonp

	public function __construct()
	{
/*		$this->controllerName = Str::lower(preg_replace('/Controller$/', '', get_class($this)));
		$this->modelName = Str::studly(Str::singular($this->controllerName));*/
		$this->resultsKey = static::controllerName();
		$this->resultsKeySingular = Str::singular($this->resultsKey);
	}

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

	protected static function controllerName()
	{
		return Str::lower(preg_replace('/Controller$/', '', get_called_class()));
	}

	protected static function modelName()
	{
		return Str::studly(Str::singular(static::controllerName()));
	}

	protected static function modelInstance($input=array())
	{
		$modelName = Str::studly(Str::singular(static::controllerName()));
		$model = new $modelName($input);

		return $model;
	}

	private function applyParams($model = null)
	{

		if(!$model) $model = static::modelInstance();

		//search, sort
		$query = $model
					->eagerLoad(Input::get('with'))
					->filterWhere(Input::get('where'))
					->searchAny(Input::get('q'))
					->sortBy(Input::get('sort'));

		$paginator = false;

		if( (Input::get('page') || $this->paginate) )
		{
			$perPage = Input::get('pp') ?: $model->getPerPage();
			$paginator = $query->paginate($perPage);

			//preserve the url query in the paginator
			$paginator->appends(Input::except('page'));
		}
		return array($query,$paginator);
		dd($query->get());
	}
	/**
	* helper function for index
	* gets the result, the total and the paginator for the index action
	*
	* Use the event hooks in the inheriting controller to alter the variables passed by reference
	*
	* For example to add default pagination in a controller:
	* Event::listen('before.query', function(&$parameters){
	*		if(!in_array('page', $parameters)) $parameters['page'] = 1;
	*	});
	*
	* @return array $data array with the keys $this->resultsKey, total, paginator
	* 
	*/
	private function getIndexData()
	{
		$this->model = static::modelInstance();

		//use this hook to alter the parameters
		$beforeQuery = Event::fire('before.query', array(&$parameters));

		//search, sort
		$query = $this->model
					->eagerLoad(Input::get('with'))
					->filterWhere(Input::get('where'))
					->searchAny(Input::get('q'))
					->sortBy(Input::get('sort'));


		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		// pagination. do not paginate on ajax request
		if( (Input::get('page') || $this->paginate) && !$this->isAjaxRequest() )
		{
			$perPage = Input::get('pp') ?: $this->model->getPerPage();
			$paginator = $query->paginate($perPage);

			//preserve the url query in the paginator
			$paginator->appends(Input::except('page'));
		}

		$data = new \stdClass;
		$data->{$this->resultsKey} = isset($paginator) ? $paginator->getCollection() : $query->get();
		$data->total = isset($paginator) ? $paginator->getTotal() : $data->{$this->resultsKey}->count();
		$data->paginator = isset($paginator) ? $paginator : false;

		return (array) $data;
	}

	/**
	* checks wheter it's an ajax request or not
	* in case of a jsonp request sets $this->callback to the name of the callback function
	*/
	protected function isAjaxRequest()
	{
		$this->callback = Input::get('callback', false);

		return (Request::ajax() || $this->callback);
	}

	/**
	* prints a json response.
	* in case of a jsonp request wraps the json data with a callback function
	*
	* @param array $data the data to convert to json
	* @param int $status the status code to return in the response
	* @return json data
	*/
	protected function jsonResponse($data = array(), $status = 200)
	{
		$response = \Response::json($data,$status);

		if($this->callback) $response = $response->setCallback($this->callback);

		return $response;
	}


	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$data = $this->getIndexData();

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKey] = $data[$this->resultsKey]->toArray();
			unset($data['paginator']);

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,200);
		}

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		
		$routeName = static::controllerName().".index";
		$this->layout->content = View::make($routeName, $data);
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$beforeResponse = Event::fire('before.response', array());

		$routeName = static::controllerName().".create";
		$this->layout->content = View::make($routeName);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$input = Input::all();
		
		$this->model = static::modelInstance($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $this->model->save() ) return $this->savingError($this->model);

		$message = 'nuovo elemento creato';
		$data = array();

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;

			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,201);
		}

		$routeName = static::controllerName().".edit";
		return Redirect::route($routeName, array($this->model->id))->with('success', $message);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$query = static::modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$this->model = $query->find($id);

		if(is_null($this->model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $this->model
		);

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKeySingular] = $this->model->toArray();

			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}

		$beforeResponse = Event::fire('before.response', array(&$data));

		$routeName = static::controllerName().".show";
		$this->layout->content = View::make($routeName, $data);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$query = static::modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$this->model = $query->find($id);

		if(is_null($this->model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $this->model
		);

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));

		$routeName = static::controllerName().".edit";
		$this->layout->content = View::make($routeName, $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$query = static::modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$this->model = $query->find($id);

		//se l'elemento non è presente creane uno nuovo
		if(is_null($this->model)) return $this->store();

		$input = Input::all();
		$this->model->fill($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $this->model->save() ) return $this->savingError($this->model);

		$message = 'elemento aggiornato';
		$data = array();

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}
		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));

		$routeName = static::controllerName().".edit";
		return Redirect::route($routeName, array($id))->with('success', $message);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$this->model = $this->getModel($id);

		$this->model->delete();

		$event = Event::fire('before.response', array());

		$message = 'elemento eliminato';
		$data = array();

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;

			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}

		$routeName = static::controllerName().".index";
		return Redirect::route($routeName)->with('success', $message);
	}

	protected function getModel($id = null)
	{
		$model = static::modelInstance();

		if(is_null($id)) return $model;

		if($model = $model->find($id)) return $model;

		return $this->modelNotFoundError();

	}

	/**
	* generates an error response when the model is not found or redirects home with an error
	*
	* @param String $message the message to print
	* @return Response
	*/
	protected function modelNotFoundError($message = 'elemento non trovato')
	{
		$data = array(
			'error' => true,
			'message' =>$message
		);

		if($this->isAjaxRequest())
		{
			return $this->jsonResponse($data,404);
		}
		return Redirect::home()->with('error', $message);
	}

	/**
	* generates an error response when the model cannot be saved
	*
	* @param Model $model the model we tried to save
	* @return Response
	*/
	protected function savingError($model)
	{
		$data = array(
			'error' => true,
			'message' => 'saving error',
			'errors' => $model->errors->toArray()
		);

		if($this->isAjaxRequest())
		{
			return $this->jsonResponse($data,400);
		}
		return Redirect::back()->withInput()->withErrors($model->errors);
	}


	/**
	* attach a model with a hasMany or hasOne relation
	*
	* @param string $relation the relation method name as defined in the model (IE: authors)
	* @param array $data the data to use to set the relation
	* @return mixed the attached Model or false if cannot save the relation
	**/
	protected function attachModel($relation, $data)
	{
		$attachableID = isset($data['id']) ? $data['id'] : false;
		$methodInstance = $this->model->$relation()->getmethod(); //instance of the related model)
		if($attachable = $relatedInstance::find($attachableID))
			return $this->model->$relation()->save($attachable);
	}

	/**
	* handles relation calls
	* must be specified a route like this:
	*	Route::any('artworks/{id}/{related}', function($id,$related)
	*	{
	*    	...
	*	})->where(array('id' => '[0-9]+', 'related' => '[a-z]+'));
	*
	*/
	public function handleRelation($id,$relatedController,$relatedID = null)
	{

		$model = $this->getModel($id);

		$controller = new $relatedController;
		/*if(!is_a($model->$method(),'Illuminate\Database\Eloquent\Relations\Relation'))
			return $this->missingMethod(func_get_args());*/

		/*if(method_exists($model, $method))
		{
		}*/
			$spoofedMethods = array('DELETE', 'PATCH', 'PUT');
			$spoofedMethod = Input::get('_method');
			$requestMethod = Input::server('REQUEST_METHOD');

			//get the verb of the request. detect spoofed methods
			$verb = ($requestMethod=='POST' && in_array($spoofedMethod, $spoofedMethods)) ? $spoofedMethod : $requestMethod;

			$relationController = new RelationController($model, $method);

			switch($verb)
			{
				case 'GET':
					return $this->related($id, $method);
					break;
				case 'POST':
				case 'PUT':
					return $relationController->attach($id);
					break;
				case 'DELETE':
					return $relationController->detach($id);
					break;
				default:
					break;
			}
	}

	/**
	* lists the relations of a model
	*/
	public function related($id, $view=null)
	{
		$model = $this->getModel($id);

		$query = $this->applyParams($model);
		
		$requestSegments = Request::segments();
		$method = array_pop($requestSegments);

		// check if the method is a valid relation
		if ( !method_exists($model, $method) || !is_a($model->$method(),'Illuminate\Database\Eloquent\Relations\Relation') )
			$this->missingMethod($args = func_get_args());

		//set the view to use in the response
		if(!$view)
		{
			$view = "$method.index"; 
			if($method == Str::singular($method)) $view = "$method.show";
		}

		// get the related items
		$related = call_user_func(array($model, $method))->get();

		$data = new \stdClass;
		$data->total = $related->count();
		$data->$method = $related;

		$data = (array) $data;

		if($this->isAjaxRequest())
		{
			$data[$method] = $data[$method]->toArray();

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,200);
		}

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		$this->layout->content = View::make($view, $data);
		

		$this->modelNotFoundError();
	}

}

class RelationController extends Controller {

	protected $model;
	protected $method;

	public function __construct($model, $method)
	{
		$this->model = $model;
		$this->method = $method;
	}

	public function index(){
		$query = call_user_func(array($this->model,$this->method));
		$results = $query->get();

		$data = array(
			'results' => $results->toArray(),
			'total' => $results->count()
		);
		if(Request::ajax())
		{
			return \Response::json($data,200);
		}
		return $data;
	}

	public function attach($id){}
	public function detach($id){}

}