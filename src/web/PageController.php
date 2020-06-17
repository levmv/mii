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

    protected function access_rules()
    {
    }

    protected function before()
    {
        if ($this->render_layout && !Mii::$app->request->isAjax()) {
            $this->setupLayout();
        }

        return true;
    }


    protected function after($content = null): Response
    {
        if ($this->render_layout && $this->response->format === Response::FORMAT_HTML) {
            $this->setupIndex();

            if (!$this->layout) {
                $this->setupLayout();
            }
            $this->index_block->set(
                'layout',
                $this->layout
                    ->set('content', $content)
                    ->render(true)
            );

            $this->response->content($this->index_block->render(true));
        } else {
            if (\is_array($content) && $this->request->isAjax()) {
                $this->response->format = Response::FORMAT_JSON;
            }
            $this->response->content($content);
        }

        return $this->response;
    }

    protected function setupIndex($block_name = false): void
    {
        $name = ($block_name) ?: $this->index_block_name;

        $this->index_block = \block($name)
            ->bind('title', $this->title)
            ->bind('description', $this->description)
            ->bind('og', $this->og)
            ->bind('links', $this->links);
    }


    protected function setupLayout($block_name = null, $depends = null): void
    {
        if ($block_name === null) {
            $block_name = $this->layout_block_name;
        }

        if ($depends === null) {
            $depends = $this->layout_depends;
        }

        $this->layout = \block($block_name)
            ->depends($depends);
    }

    protected function onAccessDenied(): void
    {
        throw new ForbiddenHttpException('User has no rights to access ' . $this->request->uri());
    }

    public function execute(string $action, $params): void
    {
        $this->access_rules();

        if ($this->acl !== null) {
            $roles = Mii::$app->auth->getUser() ? Mii::$app->auth->get_user()->getRoles() : '*';

            if (empty($roles)) {
                $roles = '*';
            }

            if (!$this->acl->check($roles, $action)) {
                $this->onAccessDenied();
                return;
            }
        }

        parent::execute($action, $params);
    }
}
