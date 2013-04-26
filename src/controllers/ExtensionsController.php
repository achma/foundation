<?php namespace Orchestra\Foundation;

use Input,
	Event,
	Redirect,
	View,
	Illuminate\Support\Fluent,
	Orchestra\App,
	Orchestra\Extension,
	Orchestra\Messages,
	Orchestra\Site,
	Orchestra\Services\Html\ExtensionPresenter;

class ExtensionsController extends AdminController {

	/**
	 * Construct Extensions Controller, only authenticated user should be
	 * able to access this controller.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();

		$this->beforeFilter('orchestra.auth');
		$this->beforeFilter('orchestra.manage');
	}

	/**
	 * List all available extensions.
	 * 
	 * GET (:orchestra)/extensions
	 *
	 * @access public
	 * @return Response
	 */
	public function getIndex()
	{
		$extensions = Extension::detect();

		Site::set('title', trans("orchestra/foundation::title.extensions.list"));

		return View::make('orchestra/foundation::extensions.index', compact('extensions'));
	}

	/**
	 * Activate an extension.
	 *
	 * GET (:orchestra)/extensions/activate/(:name)
	 *
	 * @access public
	 * @param  string   $name   name of the extension
	 * @return Response
	 */
	public function getActivate($name)
	{
		$name = str_replace('.', '/', $name);

		if (Extension::started($name)) return App::abort(404);

		Extension::activate($name);
		Messages::add('success', trans('orchestra/foundation::response.extensions.activate', compact('name')));

		return Redirect::to(handles('orchestra/foundation::extensions'));
	}

	/**
	 * Deactivate an extension.
	 *
	 * GET (:orchestra)/extensions/deactivate/(:name)
	 *
	 * @access public
	 * @param  string   $name   name of the extension
	 * @return Response
	 */
	public function getDeactivate($name)
	{
		$name = str_replace('.', '/', $name);

		if ( ! Extension::started($name) and ! Extension::active($name)) return App::abort(404);
		
		Extension::deactivate($name);
		Messages::add('success', trans('orchestra/foundation::response.extensions.deactivate', compact('name')));

		return Redirect::to(handles('orchestra/foundation::extensions'));
	}

	/**
	 * Configure an extension.
	 *
	 * GET (:orchestra)/extensions/configure/(:name)
	 *
	 * @access public
	 * @param  string   $name name of the extension
	 * @return Response
	 */
	public function getConfigure($name)
	{
		$name = str_replace('.', '/', $name);

		if ( ! Extension::started($name)) return App::abort(404);

		// Load configuration from memory.
		$memory        = App::memory();
		$config        = $memory->get("extensions.active.{$name}.config", array());
		$eloquent      = new Fluent((array) $memory->get("extension_{$name}", $config));
		$extensionName = $memory->get("extensions.available.{$name}.name", $name);

		// Add basic form, allow extension to add custom configuration field
		// to this form using events.
		$form = ExtensionPresenter::form($eloquent, $name);

		Event::fire("orchestra.form: extension.{$name}", array($eloquent, $form));
		Site::set('title', $extensionName);
		Site::set('description', trans("orchestra/foundation::title.extensions.configure"));

		return View::make('orchestra/foundation::extensions.configure', compact('eloquent', 'form'));
	}

	/**
	 * Update extension configuration.
	 *
	 * POST (:orchestra)/extensions/configure/(:name)
	 *
	 * @access public
	 * @param  string   $name   name of the extension
	 * @return Response
	 */
	public function postConfigure($name)
	{
		$name = str_replace('.', '/', $name);

		if ( ! Extension::started($name)) return App::abort(404);

		$input  = Input::all();
		$memory = App::memory();
		$config = (array) $memory->get("extension.active.{$name}.config", array());
		$input  = array_merge($config, $input);

		Event::fire("orchestra.saving: extension.{$name}", array($input));

		$memory->put("extensions.active.{$name}.config", $input);
		$memory->put("extension_{$name}", $input);
		
		Event::fire("orchestra.saved: extension.{$name}", array($input));

		Messages::add('success', trans("orchestra/foundation::response.extensions.configure", compact('name')));

		return Redirect::to(handles('orchestra/foundation::extensions'));
	}

	/**
	 * Update an extension, run migration and bundle publish command.
	 *
	 * GET (:orchestra)/extensions/update/(:name)
	 *
	 * @access public
	 * @param  string   $name   name of the extension
	 * @return Response
	 */
	public function getUpdate($name)
	{
		if ( ! Extension::started($name)) return App::abort(404);

		Extension::publish($name);
		Messages::add('success', trans('orchestra/foundation::response.extensions.update', compact('name')));

		return Redirect::to(handles('orchestra/foundation::extensions'));
	}
}