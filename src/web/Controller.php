<?php

namespace mii\web;

use Mii;
use mii\core\ACL;

class Controller extends \mii\core\Controller
{

    /**
     * @var  Request  Request that created the controller
     */
    public $request;

    /**
     * @var  Response The response that will be returned from controller
     */
    public $response;

    public $app;

    public $action_params;

    /**
     * @var \mii\core\ACL Access Control List object
     */
    public $acl;

    /**
     * @var Block
     */
    public $index_block;

    public $index_block_name = 'index';

    /**
     * @var Block
     */
    public $head = false;

    /**
     * @var Block
     */
    public $layout = false;

    public $layout_block_name = 'layout';

    public $layout_depends = [];

    /**
     * @var string Site title
     */
    public $title = '';

    /**
     * @var string Site description
     */
    public $description = '';

    /**
     * @var array OpenGraph parameters
     */
    public $og = [];

    /**
     * @var array Links (rel="prev|next")
     */
    public $links = [];

    /**
     * @var string
     */
    public $content = '';

    public $render_layout = true;

    public $breadcrumbs = '';

    public $user;

    public $csrf_validation = true;


    /**
     * Creates a new controller instance. Each controller must be constructed
     * with the request object that created it.
     *
     * @param   Request $request Request that created the controller
     * @param   Response $response The request's response
     * @param   App $app
     */
    public function __construct(Request $request, Response $response)
    {
        // Assign the request to the controller
        $this->request = $request;

        // Assign a response to the controller
        $this->response = $response;

        $this->acl = new ACL;
    }

    protected function access_rules()
    {
    }

    public function index()
    {
    }


    protected function before()
    {
        if (!$this->request->is_ajax()) {
            $this->setup_layout();
        }

        return true;
    }


    protected function after($content = null)
    {
        if ($this->render_layout AND $this->response->format === Response::FORMAT_HTML AND !$this->request->is_ajax()) {

            $this->setup_index();

            if (!$this->layout) {
                $this->setup_layout();
            }
            $this->index_block->set('layout', $this->layout->render(true));

            $this->response->content($this->index_block->render(true));

        } else {
            if($content === null)
                $content = $this->content;

            if (is_array($content) AND $this->request->is_ajax()) {
                $this->response->format = Response::FORMAT_JSON;
            }
            $this->response->content($content);
        }

        return $this->response;
    }

    public function setup_index($block_name = false)
    {
        $name = ($block_name) ? $block_name : $this->index_block_name;

        $this->index_block = block($name)
            ->bind('title', $this->title)
            ->bind('description', $this->description)
            ->bind('og', $this->og)
            ->bind('links', $this->links);
    }


    public function setup_layout($block_name = null, $depends = null)
    {
        if($block_name === null)
            $block_name = $this->layout_block_name;

        if($depends === null)
            $depends = $this->layout_depends;

        $this->layout = block($block_name)
            ->depends($depends)
            ->bind('content', $this->content);
    }


    public function execute($params = [])
    {
        $method = new \ReflectionMethod($this, $this->request->action);

        $args = $this->process_action_params($method, $this->request->params);

        $this->access_rules();

        $this->user = Mii::$app->user = Mii::$app->auth->get_user();

        $roles = Mii::$app->user ? Mii::$app->user->get_roles() : '*';

        if (!$this->acl->check($roles, $this->request->controller, $this->request->action)) {
            throw new ForbiddenHttpException('User has no rights to access :page', [':page' => $this->request->uri()]);
        }

        if ($this->csrf_validation && !$this->request->validate_csrf_token()) {
            throw new BadRequestHttpException('Token mistmatch error');
        }

        $this->before();

        $content = call_user_func_array([$this, $this->request->action], $args);

        return $this->after($content);
    }

    protected function process_action_params(\ReflectionMethod $method, $params)
    {
        $args = [];
        $missing = [];
        $action_params = [];
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                if ($param->isArray()) {
                    $args[] = $action_params[$name] = is_array($params[$name]) ? $params[$name] : [$params[$name]];
                } elseif (!is_array($params[$name])) {
                    $args[] = $action_params[$name] = $params[$name];
                } else {
                    throw new HttpException(500, 'Invalid data received for parameter ":param".', [
                        ':param' => $name,
                    ]);
                }
                unset($params[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $action_params[$name] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            throw new HttpException(500, 'Missing required parameters: ":params"', [
                ':params' => implode(', ', $missing),
            ]);
        }
        $this->action_params = $action_params;

        return $args;
    }


}