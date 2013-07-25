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

	public function __construct()
	{
		$this->controllerName = Str::lower(preg_replace('/Controller$/', '', get_class($this)));
		$this->modelName = Str::studly(Str::singular($this->controllerName));
		$this->resultsKey = $this->controllerName;
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
	 * @return Response
	 */
	public function index()
	{
		$parameters = array(
			'q' => Input::get('q'), //queryString
			's' => Input::get('s'), //sortoptions
			'page' => Input::get('page'), //pagination
			'pp' => Input::get('pp'), //perpage
		);

		if(!$parameters['page'] && $this->paginate) $parameters['page'] = 1;

		$this->model = new $this->modelName;

		//use this hook to alter the parameters
		$beforeQuery = Event::fire('before.query', array(&$parameters));
		$query = $this->model
					->searchAny($parameters['q'])
					->sortBy($parameters['s']);

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		// pagination
		if(isset($parameters['page']))
		{
			$perPage = $parameters['pp'] ?: $this->model->getPerPage();
			$paginator = $query->paginate($perPage);

			//preserve the url query in the paginator
			$paginator->appends(array_except($parameters,'page'));
		}

		$results = isset($paginator) ? $paginator->getCollection() : $query->get();
		$total = isset($paginator) ? $paginator->getTotal() : $results->count();

		//set the data for the view
		$data = array(
			'total' => $total,
			$this->resultsKey => $results,
		);

		if(Request::ajax())
		{
			$data['error'] = false;
			$data[$this->resultsKey] = $results->toArray();

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,200);
		}

		if(isset($paginator)) $data['paginator'] = $paginator;

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		$this->layout->content = View::make("{$this->controllerName}.index", $data);

	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$beforeResponse = Event::fire('before.response', array());

		$this->layout->content = View::make("{$this->controllerName}.create");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$input = Input::all();
		
		$this->model = new $this->modelName($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $this->model->save() ) return $this->savingError($this->model);

		$message = 'nuovo elemento creato';
		$data = array();

		if(Request::ajax())
		{	

			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;
			$data['error'] = false;

			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,201);
		}

		return Redirect::route("{$this->controllerName}.edit", array($this->model->id))->with('success', $message);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$query = call_user_func(array($this->modelName, 'query'));

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$this->model = $query->find($id);

		if(is_null($this->model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $this->model
		);

		if(Request::ajax())
		{
			$data['error'] = false;
			$data[$this->resultsKeySingular] = $this->model->toArray();

			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,200);
		}

		$beforeResponse = Event::fire('before.response', array(&$data));
		$this->layout->content = View::make("{$this->controllerName}.show", $data);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$query = call_user_func(array($this->modelName, 'query'));

		//use this hook to alter the query
		$beforeResults = Event::fire('before.results', array(&$query));

		$this->model = $query->find($id);

		if(is_null($this->model)) return $this->modelNotFoundError();

		$data = array(
			$this->resultsKeySingular => $this->model
		);

		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		$this->layout->content = View::make("{$this->controllerName}.edit", $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$query = call_user_func(array($this->modelName, 'query'));

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

		if(Request::ajax())
		{	

			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;
			$data['error'] = false;
		//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,200);
		}
		//use this hook to alter the data of the view
		$beforeResponse = Event::fire('before.response', array(&$data));
		return Redirect::route("{$this->controllerName}.edit", array($id))->with('success', $message);
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

		if(Request::ajax())
		{
			$data[$this->resultsKeySingular] = $this->model->toArray();
			$data['message'] = $message;
			$data['error'] = false;

			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,200);
		}
		return Redirect::route("{$this->controllerName}.index")->with('success', $message);
	}

	protected function getModel($id = null)
	{
		if(is_null($id)) return new $this->modelName;

		$model = call_user_func_array( array($this->modelName, 'find'), array($id) );

		if(is_null($model)) return $this->modelNotFoundError();

		return $model;

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

		if(Request::ajax())	return \Response::json($data,404);
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

		if(Request::ajax()) return \Response::json($data,400);
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
		$relatedInstance = $this->model->$relation()->getRelated(); //instance of the related model)
		if($attachable = $relatedInstance::find($attachableID))
			return $this->model->$relation()->save($attachable);
	}

}