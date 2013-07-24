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
	
	protected $model;
	protected $beforeResponseCallable; //usato per memorizzare una funzione da eseguire nell'hook beforeResponse

	public function __construct()
	{
		$this->controllerName = Str::lower(preg_replace('/Controller$/', '', get_class($this)));
		$this->modelName = Str::studly(Str::singular($this->controllerName));
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
	* ricerca parole separate da spazio tra i campi $searchable del model
	*/
	protected function buildSearchAny($query,$queryString)
    {
		$words = explode(' ',$queryString);
		foreach($words as $word)
		{
			if($word!='')
			{
				$query->where(function($query) use($word)
				{
					$model  = new $this->modelName;
					$fields = $model->getSsearchable() ?: array();
					foreach($fields as $field){
						$query->orWhere($field,'like',"%$word%");
					}
				});
			}
		}

		return $query;
	}


	/**
	* ordina i risultati della query in base a parametri in formato json "{"titolo":1,"tecnica":-1}"
	* @param Illuminate\Database\Eloquent\Builder $query
	* @param string $sortOptions una stringa formato json con i parametri per l'ordinamento
	*/
	protected function buildSortBy($query, $sortOptions)
	{
		if(!is_array($sortOptions)) $sortOptions = json_decode($sortOptions);

		foreach($sortOptions as $column=>$direction)
		{
			$direction = ($direction==-1) ? 'desc' : 'asc';
			$query->orderBy($column, $direction);
		}
		return $query;
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

		$model = new $this->modelName;

		//use this hook to alter the parameters
		$beforeQuery = Event::fire('before.query', array(&$parameters));
		$query = $model
					->searchAny($parameters['q'])
					->sortBy($parameters['s']);

		//use this hook to alter the query or the model
		$beforeResults = Event::fire('before.results', array(&$query,&$model));

		// pagination
		if(isset($parameters['page']))
		{
			$perPage = $parameters['pp'] ?: $model->getPerPage();
			$paginator = $query->paginate($perPage);

			//preserve the url query in the paginator
			$paginator->appends(array_except($parameters,'page'));
		}

		$results = isset($paginator) ? $paginator->getCollection() : $query->get();
		$total = isset($paginator) ? $paginator->getTotal() : $results->count();

		//set the data for the view
		$data = array(
			'total' => $total,
			$this->controllerName => $results,
		);

		if(Request::ajax())
		{
			$data['error'] = false;
			$data[$this->controllerName] = $results->toArray();

			//use this hook to alter the data of the view
			$beforeResponse = Event::fire('before.response', array(&$data));
			return \Response::json($data,
				200
			);
		}else {
			//use this hook to alter the data of the view
			if($paginator) $data['paginator'] = $paginator;
			$beforeResponse = Event::fire('before.response', array(&$data));
			$this->layout->content = View::make("{$this->controllerName}.index", $data);
		}

	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$event = Event::fire('before.response', array());

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

		$event = Event::fire('before.response', array($this->model, $message));

		if(Request::ajax())
		{	
			return \Response::json([
				'error' => false,
				'message' =>$message,
				'results' =>$this->model->toArray()],
				201
			);
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
		//se ho impostato una custom query nel controller la eseguo, altrimenti eseguo all
		$this->model = (isset($this->query)) ? $this->query->find($id) : call_user_func_array( "$this->modelName::find", array($id) );

		if(is_null($this->model)) return $this->modelNotFoundError();

		$event = Event::fire('before.response', array($this->model));

		if(Request::ajax())
		{
			return \Response::json([
				'error' => false,
				'results' =>$this->model->toArray()],
				200
			);
		}
		$this->layout->content = View::make("{$this->controllerName}.show", [
			Str::singular($this->controllerName) => $this->model
		]);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$this->model = (isset($this->query)) ? $this->query->find($id) : call_user_func_array( "$this->modelName::find", array($id) );

		if(is_null($this->model)) return $this->modelNotFoundError();

		$event = Event::fire('before.response', array());

		$this->layout->content = View::make("{$this->controllerName}.edit", [
			Str::singular($this->controllerName) => $this->model
		]);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$this->model = call_user_func_array( array($this->modelName,"find"), array($id) );

		// if(is_null($this->model)) return $this->modelNotFoundError();
		if(is_null($this->model)) return $this->store();

		$input = Input::all();
		$this->model->fill($input);

		//controlla se ci sono problemi durante il salvataggio
		if(! $this->model->save() ) return $this->savingError($this->model);

		$event = Event::fire('before.response', array());

		$message = 'elemento aggiornato';
		if(Request::ajax())
		{
			return \Response::json([
				'error' => false,
				'message' =>$message,
				'results' =>$this->model->toArray()],
				200
			);
		}
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
		$this->model = call_user_func_array( "$this->modelName::find", array($id) );

		if(is_null($this->model)) return $this->modelNotFoundError();

		$this->model->delete();

		$event = Event::fire('before.response', array());

		$message = 'elemento eliminato';
		if(Request::ajax())
			return \Response::json([
			'error' => false,
			'message' =>$message,
			'results' =>$this->model->toArray()],
			200
		);
		return Redirect::route("{$this->controllerName}.index")->with('success', $message);
	}

	/**
	* generates an error response when the model is not found or redirects home with an error
	*
	* @param String $message the message to print
	* @return Response
	*/
	protected function modelNotFoundError($message = 'elemento non trovato')
	{
		if(Request::ajax())
			return \Response::json([
			'error' => true,
			'message' =>$message],
			404
		);
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
		if(Request::ajax())
			return \Response::json([
			'error' => true,
			'message' => 'saving error',
			'errors' => $model->errors->toArray()],
			400
		);
		return Redirect::back()->withInput()->withErrors($model->errors);
	}

	private function getRelationModelClass($relation = false)
	{
		return Str::studly(Str::singular($relation));
	}

	/**
	* sincronizza elementi che hanno una relazione many to many (belongsToMany)
	*
	* @param string $relation the relation method name as defined in the model (IE: authors)
	* @param array $data the data to use to set the relations
	**/
	protected function syncModels($relation = false, $data = array())
	{
		$attached = $this->model->$relation()->get(); //models già associati
		$attachableModelsSync = array(); //contiene i models da sincronizzare

		foreach($data as $attachableModelInput)
		{
			$attachableModelID = isset($attachableModelInput['id']) ? $attachableModelInput['id'] : false;

			$relatedInstance = $this->model->$relation()->getRelated(); //instance of the related model

			if( $attachableModel = $relatedInstance::find($attachableModelID) )
			{
				if($attached->contains($attachableModelID)) $this->model->$relation()->detach($attachableModelID); //dissocio per assicurarmi di aggiornare i dati pivot

				$pivotData = $attachableModelInput['pivot'] ?: array();
				$attachableModelsSync[$attachableModelID] = $pivotData;
			}
		}
		$this->model->$relation()->sync($attachableModelsSync);
	}

	/**
	* attach models with a hasMany or hasOne relation.
	* it also detaches the models not sent with $data
	*
	* @param string $relation the relation method name as defined in the model (IE: authors)
	* @param array $data the data to use to set the relations
	**/
	protected function attachModels($relation = false, $data = array())
	{
		$attached = $this->model->$relation()->get(); //models già associati
		$attachables = array(); //models che verranno associati

		foreach($data as $attachableData)
		{

			$attachableID = isset($attachableData['id']) ? $attachableData['id'] : false;
			$relatedInstance = $this->model->$relation()->getRelated(); //instance of the related model
			if($attachable = $relatedInstance::find($attachableID))	$attachables[] = $attachable;
		}
		$this->model->$relation()->saveMany($attachables);

		//detach related model not sent
		$attached->each(function($item) use($relation, $attachables){
			if( ! in_array($item, $attachables) ) $this->detachModel($relation, $item);
		});
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
		if($attachable = $relatedInstance::find($attachableID))	return $this->model->$relation()->save($attachable);
	}

	/**
	* detach a model in a hasMany or hasOne relation
	* @param string $relation the relation method name as defined in the model (IE: authors)
	*/
	protected function detachModel($relation = false, $related)
	{
		$foreignKey = $this->model->$relation()->getPlainForeignKey();
		$related->setAttribute($foreignKey, 0);
		return $related->save();
	}

}