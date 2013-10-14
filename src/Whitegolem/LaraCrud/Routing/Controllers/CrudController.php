<?php namespace Whitegolem\LaraCrud\Routing\Controllers;

use Illuminate\Routing\Controllers\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class CrudController extends Controller {

	protected $modelName;

	protected $viewsBasePath;

	private $resultsKey;

	private $resultsKeySingular;

	protected $paginate = false; //true to enable pagination by default

	public function __construct()
	{
		$this->modelName = $this->modelName ?: Str::studly(Str::singular(static::controllerName()));
		$this->viewsBasePath = $this->viewsBasePath ?: $this->setViewsBasePath();
		$this->resultsKey = static::controllerName();
		$this->resultsKeySingular = Str::singular($this->resultsKey);
	}

	private function setViewsBasePath()
	{
		$classNameParts = explode('\\', get_called_class());
		array_pop($classNameParts); //leave only the namespace
		array_push($classNameParts,static::controllerName());
		$basePath = implode('.',$classNameParts);
		return Str::lower($basePath);
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

	private static function controllerName()
	{
		$fullClassName = Str::lower(preg_replace('/Controller$/', '', get_called_class()));

		$nameParts = explode('\\', $fullClassName); //handle namespaces
		$className = array_pop($nameParts);
		return $className;
	}

	/**
	 * get an instance of the model associated with the controller
	 *
	 */
	private function modelInstance($input=array())
	{
		$modelName = $this->modelName;
		$model = new $modelName($input);

		return $model;
	}

	/**
	 * builds the path of the view starting from a base (usually the controller name IE the plural form of the model)
	 *
	 * @param $array one or more views to concatenate
	 * @return string the path of the view
	 */
	private function buildViewPath($views=array(), $base=null)
	{

		if(is_null($base))
			$base = $this->viewsBasePath ?: static::controllerName();
		if(!is_array($views)) $views = array($views);
		array_unshift($views, $base);
		$path = implode('.', $views);
		return $path;
	}

	/**
	* apply the request params to the query
	*
	* @param mixed $object Model/Relation a model query or a relation to be filtered
	* @return array a modified query and eventually a Paginator
	*/
	protected function applyParams($object = null)
	{

		if(!$object) $object = $this->modelInstance();

		//search, sort
		$query = $object
					->eagerLoad(Input::get('with'))
					->filterWhere(Input::get('where'))
					->searchAny(Input::get('q'))
					->sortBy(Input::get('sort'));

		//use this hook to alter the parameters
		$paginator = null;

		if( (Input::get('page') || $this->paginate) )
		{
			$beforePagination = Event::fire('before.pagination', array(&$query));

			//check if $object is a model or a relation
			$model = method_exists($object, 'getRelated') ? $object->getRelated() : $object;

			$perPage = Input::get('pp') ?: $model->getPerPage();
			$paginator = $query->paginate($perPage);

			//preserve the url query in the paginator
			$paginator->appends(Input::except('page'));
		}

		return array($query,$paginator);
	}

	/**
	 * checks wheter it's an ajax request or not
	 * in case of a jsonp request sets $callback to the name of the callback function
	 */
	protected function isAjaxRequest()
	{
		$callback = Input::get('callback', false);

		return (Request::ajax() || $callback);
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

		if($callback = Input::get('callback')) $response = $response->setCallback($callback);

		return $response;
	}

	/**
	 * get an array of data to use in the response
	 * @param Query $query the query to use to retrieve the data
	 * @param Paginator $paginator
	 * @param array $additionalData an associative array of data to merge with the data array
	 */
	protected function getData($query,$paginator=null, $additionalData = array())
	{
		$results = isset($paginator) ? $paginator->getCollection() : $query->get();

		$data = array();
		$data[$this->resultsKey] = ($this->isAjaxRequest()) ? $results->toArray() : $results;
		$data['total'] = isset($paginator) ? $paginator->getTotal() : $data->{$this->resultsKey}->count();
		if($paginator) $data['paginator'] = $paginator;

		if(is_array($additionalData)) $data = array_merge($data, $additionalData);

		return $data;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * Use the event hooks in the inheriting controller to alter the variables passed by reference
	 *
	 * For example to add default pagination in a controller:
	 * Event::listen('before.query', function(&$parameters){
	 *		if(!in_array('page', $parameters)) $parameters['page'] = 1;
	 *	});
	 *
	 *
	 * @return Response
	 */
	public function index()
	{
		$model = $this->modelInstance();

		//use this hook to alter the parameters
		$beforeQuery = Event::fire('before.query', array(&$parameters));

		list($query,$paginator) = $this->applyParams($model);

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$data = $this->getData($query,$paginator);
		if($this->isAjaxRequest())
		{
			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,200);
		}

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		
		$viewPath = $this->buildViewPath("index");
		$this->layout->content = View::make($viewPath, $data);
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$beforeResponse = Event::fire('before.response', array());
		$this->buildViewPath("create");

		$viewPath = $this->buildViewPath("create");
		$this->layout->content = View::make($viewPath);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$input = Input::all();
		
		$model = $this->modelInstance($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $model->save() ) return $this->savingError($model);

		$message = 'nuovo elemento creato';

		if($this->isAjaxRequest())
		{
			$data = array();
			$data[$this->resultsKeySingular] = $model->toArray();
			$data['message'] = $message;

			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,201);
		}

		$viewPath = $this->buildViewPath("edit");
		return Redirect::route($viewPath, array($model->id))->with('success', $message);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$query = $this->modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$model = $query->find($id);

		if(is_null($model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $model
		);

		if($this->isAjaxRequest())
		{
			$data[$this->resultsKeySingular] = $model->toArray();

			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}

		$beforeResponse = Event::fire('before.response', array(&$data));

		$viewPath = $this->buildViewPath("show");
		$this->layout->content = View::make($viewPath, $data);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$query = $this->modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$model = $query->find($id);

		if(is_null($model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $model
		);

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));

		$viewPath = $this->buildViewPath("edit");
		$this->layout->content = View::make($viewPath, $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$query = $this->modelInstance()->query();

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$model = $query->find($id);

		//se l'elemento non è presente creane uno nuovo
		if(is_null($model)) return $this->store();

		$input = Input::all();
		$model->fill($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $model->save() ) return $this->savingError($model);

		$message = 'elemento aggiornato';

		if($this->isAjaxRequest())
		{
			$data = array();
			$data[$this->resultsKeySingular] = $model->toArray();
			$data['message'] = $message;

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}

		$viewPath = $this->buildViewPath("edit");
		return Redirect::route($viewPath, array($id))->with('success', $message);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$model = $this->getModel($id);

		$model->delete();

		$event = Event::fire('before.response', array());

		$message = 'elemento eliminato';

		if($this->isAjaxRequest())
		{
			$data = array();
			$data[$this->resultsKeySingular] = $model->toArray();
			$data['message'] = $message;

			$beforeResponse = Event::fire('before.response', array(&$data));
			
			return $this->jsonResponse($data,200);
		}

		$viewPath = $this->buildViewPath("index");
		return Redirect::route($viewPath)->with('success', $message);
	}

	protected function getModel($id = null)
	{
		if($model = $this->modelInstance()->find($id)) return $model;

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
	 *
	 * ---- RELATIONS ----
	 *
	 */

	private function getRelatedMethod()
	{
		$requestSegments = Request::segments();

		if(count($requestSegments)>=3) return array_pop($requestSegments);

		return null;
	}
	private function getRelatedModel()
	{
		$method = $this->getRelatedMethod();
		$model = $this->modelInstance();
		$relation = $model->$method();
		return $relation->getRelated();
	}

	/**
	 * Display a listing of the related resource.
	 *
	 * @return Response
	 * @param  int  $id
	 */
	public function relatedIndex($id, $viewPath=null)
	{
		$method = $this->getRelatedMethod();

		$model = $this->getModel($id);

		//set the view to use in the response
		if(!$viewPath)
		{
			$viewPath = $this->buildViewPath("index",$method); 
			if($method == Str::singular($method)) $viewPath = $this->buildViewPath("show",$method);
		}

		$relation = call_user_func(array($model, $method));
		list($query, $paginator) = $this->applyParams($relation);

		// get the related items
		$related = isset($paginator) ? $paginator->getCollection() : $query->get();

		$data = array();
		$data[$method] = $related;
		$data['total'] = ($paginator) ? $paginator->getTotal() : $related->count();
		if($paginator) $data['paginator'] = $paginator;

		if($this->isAjaxRequest())
		{
			$data[$method] = $related->toArray();

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));

			return $this->jsonResponse($data,200);
		}

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		$this->layout->content = View::make($viewPath, $data);
	}

	/**
	 * Display the specified related resource.
	 *
	 * @param  int  $id
	 * @param  int  $relatedId
	 * @return Response
	 */
	public function relatedShow($id,$relatedId)
	{}

	/**
	 * set belongsTo
	 */
	public function relatedAssociate($id,$relatedId)
	{
		$method = $this->getRelatedMethod();
		$relatedModel = $this->getRelatedModel();


		if( $related = $relatedModel->find($relatedId) )
		{
			$model = $this->getModel($id);


			$model->$method()->associate($related);

			if(! $model->save() ) return $this->savingError($model);
			
			$message = 'relazione salvata';

			if($this->isAjaxRequest())
			{
				$data = array();
				$data[$method] = $related->toArray();
				$data['message'] = $message;
				
				return $this->jsonResponse($data,200);
			}

			$viewPath = $this->buildViewPath("index");
			return Redirect::route($viewPath)->with('success', $message);
		}
		return $this->modelNotFoundError();
	}

	/**
	 * set belongsToMany
	 */
	public function relatedAttach($id,$relatedId)
	{
		$method = $this->getRelatedMethod();
		$relatedModel = $this->getRelatedModel();



		if( $related = $relatedModel->find($relatedId) )
		{
			$model = $this->getModel($id);
			$pivotData = Input::get('pivot', array());
			if(!is_array($pivotData)) $pivotData = (array)$pivotData;

			$model->$method()->detach($relatedId); //detach first to avoid duplicates
			$model->$method()->attach($relatedId, $pivotData);
			
			$message = 'relazione salvata';

			if($this->isAjaxRequest())
			{
				$data = array();
				$data[$method] = $related->toArray();
				$data['message'] = $message;
				
				return $this->jsonResponse($data,200);
			}

			$viewPath = $this->buildViewPath("index");
			return Redirect::route($viewPath)->with('success', $message);
		}
		return $this->modelNotFoundError();
	}
	
	/**
	 * updates pivot data in belongsToMany
	 */
	public function relatedUpdate($id,$relatedId)
	{}

	/**
	 * unset belongsToMany
	 */
	public function relatedDetach($id,$relatedId)
	{
		$method = $this->getRelatedMethod();

		$model = $this->getModel($id);

		if( $related = $model->$method()->get()->find($relatedId) )
		{
			$model->$method()->detach($relatedId);
			
			$message = 'relazione eliminata';

			if($this->isAjaxRequest())
			{
				$data = array();
				$data[$method] = $related->toArray();
				$data['message'] = $message;
				
				return $this->jsonResponse($data,200);
			}

			$viewPath = $this->buildViewPath("index");
			return Redirect::route($viewPath)->with('success', $message);
		}
		return $this->modelNotFoundError();
	}

}