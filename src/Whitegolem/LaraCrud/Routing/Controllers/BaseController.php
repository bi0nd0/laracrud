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

	protected $viewPath;

	protected $paginate = true; //true to enable pagination by default

	public function __construct()
	{

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

	protected function getViewPath()
	{
		if (isset($this->viewPath)) return $this->viewPath;

		$classNameParts = explode('\\', get_class($this));
		array_pop($classNameParts); //leave only the namespace
		array_push($classNameParts,Str::plural($this->getModelName()));
		$basePath = implode('.',$classNameParts);
		return Str::lower($basePath);
	}

	 /**
   	 * Get a segment from the Controller
   	 *
   	 * @param  int 		$index
   	 * @param  string	$default
     * @return string
   	 */
   	private function controllerSegment($index=0, $default = null)
   	{
		$classBaseName = class_basename($this);
		$controllername = preg_replace('/Controller$/', '', $classBaseName); //remove the string 'Controller' from the class name
		$segments = preg_split('/(?=[A-Z])/', $controllername, -1, PREG_SPLIT_NO_EMPTY); //split on capital letters using positive lookahead (?=)
		$segment = isset($segments[$index]) ? $segments[$index] : $default;

		return $segment;
   	}

   	/**
   	 * @return string the key used to display json results, typically a plural noun
   	 */
	protected function getResourceKey()
	{
		return Str::lower( $this->controllerSegment(0) );
	}

	/**
	 * get the class name of the model
	 */
	protected function getModelName()
	{
		if (isset($this->modelName)) return $this->modelName;

		$controllerName = $this->controllerSegment(0);
		return Str::singular($controllerName);
	}

	/**
	 * get the class name of the related model
	 */
	protected function getRelatedName()
	{
		if (isset($this->relatedName)) return $this->relatedName;

		$controllerName = $this->controllerSegment(1);
		return Str::singular($controllerName);
	}

	/**
	 * get an instance of the model associated with the controller
	 */
	protected function getModel($input=array())
	{
		$modelClass = $this->getModelName();
		$model = new $modelClass($input);

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
		$base = $base ?: $this->getViewPath();

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