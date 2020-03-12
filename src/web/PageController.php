<?php declare(strict_types=1);

namespace mii\web;

use Mii;
use mii\core\ACL;

class PageController extends Controller
{
    /**
     * @var \mii\core\ACL Access Control List object
     */
    public ?ACL $acl = null;

    /**
     * @var Block
     */
    public $index_block;

    public string $index_block_name = 'index';

    /**
     * @var Block
     */
    public $layout = false;

    public string $layout_block_name = 'layout';

    public array $layout_depends = [];

    /**
     * @var string Site title
     */
    public string $title = '';

    /**
     * @var string Site description
     */
    public string $description = '';

    /**
     * @var array OpenGraph parameters
     */
    public array $og = [];

    /**
     * @var array Links (rel="prev|next")
     */
    public array $links = [];

    public bool $render_layout = true;

    protected function access_rules() {
    }

    protected function before() {

        if (!Mii::$app->request->is_ajax() && $this->render_layout) {
            $this->setup_layout();
        }

        return true;
    }


    protected function after($content = null): Response {
        if ($this->render_layout AND $this->response->format === Response::FORMAT_HTML) {

            $this->setup_index();

            if (!$this->layout) {
                $this->setup_layout();
            }
            $this->index_block->set('layout',
                $this->layout
                    ->set('content', $content)
                    ->render(true)
            );

            $this->response->content($this->index_block->render(true));

        } else {

            if (\is_array($content) AND $this->request->is_ajax()) {
                $this->response->format = Response::FORMAT_JSON;
            }
            $this->response->content($content);
        }

        return $this->response;
    }

    protected function setup_index($block_name = false): void {
        $name = ($block_name) ? $block_name : $this->index_block_name;

        $this->index_block = \block($name)
            ->bind('title', $this->title)
            ->bind('description', $this->description)
            ->bind('og', $this->og)
            ->bind('links', $this->links);
    }


    protected function setup_layout($block_name = null, $depends = null): void {
        if ($block_name === null)
            $block_name = $this->layout_block_name;

        if ($depends === null)
            $depends = $this->layout_depends;

        $this->layout = \block($block_name)
            ->depends($depends);
    }

    protected function on_access_denied() {
        throw new ForbiddenHttpException('User has no rights to access ' . $this->request->uri());
    }

    public function execute(string $action, $params) {

        $this->access_rules();

        if ($this->acl !== null) {
            $roles = Mii::$app->auth->get_user() ? Mii::$app->auth->get_user()->get_roles() : '*';

            if (empty($roles))
                $roles = '*';

            if (!$this->acl->check($roles, $action)) {
                return $this->on_access_denied();
            }
        }

        $method = new \ReflectionMethod($this, $action);

        if (!$method->isPublic())
            throw new BadRequestHttpException("Cannot access not public method");

        $this->before();

        $this->after($this->execute_action($method, $action, $params));
    }

}