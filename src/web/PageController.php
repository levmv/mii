<?php

namespace mii\web;

use Mii;
use mii\core\ACL;

class PageController extends Controller
{
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

    public $csrf_validation = true;


    protected function access_rules() {
    }


    protected function before() {

        if (!Mii::$app->request->is_ajax()) {
            $this->setup_layout();
        }

        return true;
    }


    protected function after($content = null): Response {
        if ($this->render_layout AND $this->response->format === Response::FORMAT_HTML AND !Mii::$app->request->is_ajax()) {

            if ($content !== null)
                $this->content = $content;

            $this->setup_index();

            if (!$this->layout) {
                $this->setup_layout();
            }
            $this->index_block->set('layout', $this->layout->render(true));

            Mii::$app->response->content($this->index_block->render(true));

        } else {
            if ($content === null)
                $content = $this->content;

            if (is_array($content) AND $this->request->is_ajax()) {
                Mii::$app->response->format = Response::FORMAT_JSON;
            }
            Mii::$app->response->content($content);
        }

        return $this->response;
    }

    public function setup_index($block_name = false): void {
        $name = ($block_name) ? $block_name : $this->index_block_name;

        $this->index_block = block($name)
            ->bind('title', $this->title)
            ->bind('description', $this->description)
            ->bind('og', $this->og)
            ->bind('links', $this->links);
    }


    public function setup_layout($block_name = null, $depends = null): void {
        if ($block_name === null)
            $block_name = $this->layout_block_name;

        if ($depends === null)
            $depends = $this->layout_depends;

        $this->layout = block($block_name)
            ->depends($depends)
            ->bind('content', $this->content);
    }

    protected function on_access_denied() {
        throw new ForbiddenHttpException('User has no rights to access :page', [':page' => $this->request->uri()]);
    }

    public function execute(string $action, $params) {

        $this->access_rules();

        if($this->acl !== null) {
            $roles = Mii::$app->auth->get_user() ? Mii::$app->auth->get_user()->get_roles() : '*';

            if (empty($roles))
                $roles = '*';

            if (!$this->acl->check($roles, $action)) {
                return $this->on_access_denied();
            }
        }

        if ($this->csrf_validation && !Mii::$app->request->validate_csrf_token()) {
            throw new BadRequestHttpException('Token mismatch error');
        }

        $this->before();

        $this->after($this->execute_action($action, $params));
    }

}