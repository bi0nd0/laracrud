<?php namespace Whitegolem\LaraCrud\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Validator;

class Crud extends Model{

	public $errors;

	protected static $rules = array();

	protected $searchable = array();

	public static function boot()
	{
		parent::boot();

		static::saving(function($model)
		{
			$input = $model->toArray();

			return $model->validate($input);
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
	* applica i parametri di ricerca, ordinamento, paginazione alla query
	*/
	public function scopeApplyParameters($query, $parameters)
	{
		extract($parameters);

		//ricerca fulltext
		if(isset($q)) $query = $this->searchAny($query, $q);

		//ordinamento
		if(isset($s)) $query = $this->sortBy($query, $s);


		return $query;
	}

	/**
	* ricerca parole separate da spazio tra i campi $searchable del model
	*/
	protected function searchAny($query,$queryString)
    {
		$words = explode(' ',$queryString);
		foreach($words as $word)
		{
			if($word!='')
			{
				$query->where(function($query) use($word)
				{
					$fields = $this->searchable ?: array();
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
	protected function sortBy($query,$sortOptions)
	{
		if(!is_array($sortOptions)) $sortOptions = json_decode($sortOptions);

		foreach($sortOptions as $column=>$direction)
		{
			$direction = ($direction==-1) ? 'desc' : 'asc';
			$query->orderBy($column, $direction);
		}
		return $query;
	}
}