<?php namespace Whitegolem\LaraCrud\Routing\Controllers;

use Illuminate\Routing\Controllers\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class BaseController extends Controller {

	protected $modelName;

	protected $viewsBasePath;

	protected $paginate = false; //true to enable pagination by default

	public function __construct()
	{
		$this->modelName = $this->modelName ?: Str::studly(Str::singular(static::controllerName()));
		$this->viewsBasePath = $this->viewsBasePath ?: $this->setViewsBasePath();
	}

	protected function setViewsBasePath()
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

	protected static function controllerName()
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
	protected function getModel($name=null, $input=array())
	{
		$modelName = $name ?: $this->modelName;
		$model = new $modelName($input);

		return $model;
	}

	/**
	 * builds the path of the view starting from a base (usually the controller name IE the plural form of the model)
	 *
	 * @param $array one or more views to concatenate
	 * @return string the path of the view
	 */
	protected function buildViewPath($views=array(), $base=null)
	{

		if(is_null($base))
			$base = $this->viewsBasePath ?: static::controllerName();
		if(!is_array($views)) $views = array($views);
		array_unshift($views, $base);
		$path = implode('.', $views);
		return $path;
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


}