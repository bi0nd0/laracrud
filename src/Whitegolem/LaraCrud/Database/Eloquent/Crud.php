<?php namespace Whitegolem\LaraCrud\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Validator;

class Crud extends Model{

	public $errors;

	protected static $rules = array();

	protected static $searchable = array();

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
	 * Get the fields available for full search.
	 *
	 * @return array
	 */
	public function getSearchable()
	{
		return static::$searchable;
	}

	/**
	* ricerca parole separate da spazio tra i campi $searchable del model
	*/
	public function scopeSearchAny($query,$queryString='')
    {
		$words = explode(' ',$queryString);
		foreach($words as $word)
		{
			if($word!='')
			{
				$query->where(function($query) use($word)
				{
					$fields = static::$searchable ?: array();
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