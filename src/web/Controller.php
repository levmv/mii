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


    public $action_params;


    /**
     * @var \mii\core\ACL Access Controll List object
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

    /**
     * @var string Site title
     */
    public $title = '';

    /**
     * @var array OpenGraph parameters
     */
    public $og = [];

    /**
     * @var string
     */
    public $content = '';

    public $render_layout = true;

    public $breadcrumbs = '';

    public $_main_menu = '';

    public $user;




    /**
     * Creates a new controller instance. Each controller must be constructed
     * with the request object that created it.
     *
     * @param   Request $request Request that created the controller
     * @param   Response $response The request's response
     * @return  void
     */
    public function __construct(Request $request, Response $response)
    {
        // Assign the request to the controller
        $this->request = $request;

        // Assign a response to the controller
        $this->response = $response;

        $this->acl = new ACL();
    }

    protected function access_rules() {}

    public function index() {}


    protected function before() {
        if (!$this->request->is_ajax()) {
            $this->setup_layout();
        }

        return true;
    }


    protected function after($content = null) {

        if($this->render_layout AND $this->response->format != Response::FORMAT_JSON AND !$this->request->is_ajax()) {

            $this->setup_index();

            if (!$this->layout) {
                $this->setup_layout();
            }
            $this->index_block->set('layout', $this->layout->render(true));

            $this->response->content($this->index_block->render(true));

        } else {
            $content = empty($this->content) ? $content : $this->content;
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
            ->bind('og', $this->og);
    }


    public function setup_layout($block_name = 'layout', $depends = [])
    {
        $this->layout = block($block_name)
            ->depends($depends)
            ->bind('content', $this->content);
    }


    public function execute($params = [])
    {
        $method = new \ReflectionMethod($this, $this->request->action());

        $args = $this->process_action_params($method, $this->request->params());

        $this->access_rules();

        $this->user = Mii::$app->user = Mii::$app->auth()->get_user();

        $roles = Mii::$app->user ? Mii::$app->user->get_roles() : '*';

        if(! $this->acl->check($roles, $this->request->controller(), $this->request->action())) {
            throw new HttpException(404, 'Page :page does not exist', [':page' => $this->request->uri()]);
        }

        $this->before();

        $content = call_user_func_array([$this, $this->request->action()], $args);

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
            throw new HttpException(500, 'Missing required parameters: :param}', [
                ':params' => implode(', ', $missing),
            ]);
        }
        $this->action_params = $action_params;

        return $args;
    }


}