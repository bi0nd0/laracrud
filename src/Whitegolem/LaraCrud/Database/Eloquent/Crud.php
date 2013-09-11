<?php namespace Whitegolem\LaraCrud\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;

class Crud extends Model{

	public $errors;

	protected static $rules = array();

	protected static $searchable; //it's set by default to the content of $fillable (see scopeSearchAny)



	public static function boot()
	{
		parent::boot();

		static::saving(function($model)
		{
			$input = $model->toArray();

			return $model->validate($input);
		});

		static::saved(function($model)
		{
			$model->updateRelations();
		});

		static::deleted(function($model)
		{
			$model->destroyRelations();
		});
	}

	public function validate($attributes = null)
	{
		$attributes = $attributes ?: $this->toArray();

		$validation = Validator::make($attributes, static::$rules);

		if($validation->passes()) return true;

		$this->errors = $validation->messages();

		return false;

	}

	/**
	 * Get the fields available for full search.
	 *
	 * @return array
	 */
	public function getSearchable()
	{
		return isset(static::$searchable) ? static::$searchable : $this->getFillable();
	}

	/**
	* --- RELAZIONI
	*/

	/*
	* this method is called on 'saved' event.
	* can be overridden to handle relations
	*/
	public function updateRelations() {
		$attributes = Input::all();
		$keys = array_keys($attributes);

		foreach($keys as $key)
		{
			if ( method_exists($this, $key) && is_a($this->$key(),'Illuminate\Database\Eloquent\Relations\Relation') ) {
				$methodClass =  get_class($this->$key());

				switch($methodClass)
				{
					case 'Illuminate\Database\Eloquent\Relations\BelongsToMany':
						$data = $attributes[$key];
						$this->setBelongsToMany($key,$data);
						break;
					case 'Illuminate\Database\Eloquent\Relations\HasOneOrMany':
					case 'Illuminate\Database\Eloquent\Relations\HasOne':
					case 'Illuminate\Database\Eloquent\Relations\HasMany':
						$data = $attributes[$key];
						$this->setHasOneOrMany($key,$data);
						break;
				}
			}
		}

	}

	/*
	* this method is called on 'deleted' event.
	* can be overridden to handle relations
	*/
	public function destroyRelations() {}

	/**
	* sincronizza elementi che hanno una relazione many to many (belongsToMany)
	*
	* @param string $relation the relation method name as defined in the model (IE: authors)
	* @param array $data the data to use to set the relations
	**/
	protected function setBelongsToMany($relationCallable = false, $data = array())
	{
		if(!is_array($data)) return;

		$relatedCollection = $this->$relationCallable()->get(); //Collection of attached models
		$relatedIds = $relatedCollection->modelKeys(); //array of attached models ids
		$newIds = array(); //lists the attached models ids


		foreach($data as $attachableModelInput)
		{
			$attachableModelID = isset($attachableModelInput['id']) ? $attachableModelInput['id'] : false;

			$relatedInstance = $this->$relationCallable()->getRelated(); //instance of the related model

			if( $attachableModel = $relatedInstance::find($attachableModelID) )
			{
				if($relatedCollection->contains($attachableModelID))
					$this->$relationCallable()->detach($attachableModelID); //detach to update pivot data
				
				$pivotData = (isset($attachableModelInput['pivot'])) ? $attachableModelInput['pivot'] : array();
				$this->$relationCallable()->attach($attachableModelID, $pivotData);

				$newIds[] = $attachableModelID; //add the associated model id to the list
			}
		}
		//remove the previously attached models 
		$detachIds = array_diff($relatedIds, $newIds); //array of model ids to detach
		if (count($detachIds) > 0) $this->$relationCallable()->detach($detachIds);
	}

	/**
	* attach models with a hasMany or hasOne relation.
	* it also detaches the models not sent with $data
	*
	* @param string $relation the relation method name as defined in the model (IE: authors)
	* @param array $data the data to use to set the relations
	**/
	protected function setHasOneOrMany($relation = false, $data = array())
	{
		$attached = $this->$relation()->get(); //models già associati
		$attachables = array(); //models che verranno associati

		foreach($data as $attachableData)
		{

			$attachableID = isset($attachableData['id']) ? $attachableData['id'] : false;
			$relatedInstance = $this->$relation()->getRelated(); //instance of the related model
			if($attachable = $relatedInstance::find($attachableID))	$attachables[] = $attachable;
		}
		$this->$relation()->saveMany($attachables);

		//detach related model not sent
		$attached->each(function($item) use($relation, $attachables){
			if( ! in_array($item, $attachables) ) $this->detachModel($relation, $item);
		});
	}

	/**
	* detach a model in a hasMany or hasOne relation
	* @param string $relation the relation method name as defined in the model (IE: authors)
	*/
	protected function detachModel($relation = false, $related)
	{
		$foreignKey = $this->$relation()->getPlainForeignKey();
		$related->setAttribute($foreignKey, 0);
		return $related->save();
	}

	/**
	* --- SCOPES
	*/

	/**
	* eager loads nested resources
	*
	* jQuery simple example:
	*	$.ajax({
	*		'url': 'http://bardi.dev/characters',
	*		'type': 'GET',
	*		'data': {
	*			'with': 'documents'
	*		}
	*	})
	*
	* jQuery array example:
	*	$.ajax({
	*		'url': 'http://bardi.dev/characters',
	*		'type': 'GET',
	*		'data': {
	*			'with': ['places',documents']
	*		}
	*	})
	*
	* URL simple example: http://bardi.dev/characters?with=documents
	*
	* URL array example: http://bardi.dev/characters?with[]=places&with[]=documents
	*
	*/
	public function scopeEagerLoad($query, $relations=array())
    {
		if($relations) $query->with($relations);

		return $query;
	}

	/**
	* ricerca parole separate da spazio tra i campi $searchable del model
	*/
	public function scopeFilterWhere($query,$filters=array())
    {
    	if(!is_array($filters)) return $query;

    	//manage single filter. transform it in array of arrays
    	if( isset($filters[0]) && !is_array($filters[0]) ) $filters = array($filters);

    	foreach($filters as $filter)
    	{
    		$query = call_user_func_array(array($query,'where'), $filter);

    	}

		return $query;
	}

	/**
	* ricerca parole separate da spazio tra i campi $searchable del model
	*/
	public function scopeSearchAny($query,$queryString='')
    {
		$words = explode(' ',$queryString);
		// set the default searchable fields to the fillable array
		$fields = $this->getSearchable();
		foreach($words as $word)
		{
			if($word!='')
			{
				$query->where(function($query) use($word, $fields)
				{
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
	public function scopeSortBy($query,$sortOptions='{}')
	{
		if(is_null($sortOptions)) return $query;

		if(!is_array($sortOptions)) $sortOptions = json_decode($sortOptions);


		foreach($sortOptions as $column=>$direction)
		{
			$direction = ($direction==-1) ? 'desc' : 'asc';
			$query->orderBy($column, $direction);
		}
		return $query;
	}
}