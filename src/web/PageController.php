<?php declare(strict_types=1);

namespace mii\web;

use Mii;

class PageController extends Controller
{
    public ?Block $layout = null;

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

    public bool $autoRender = true;


    protected function before(): void
    {
        if ($this->autoRender && $this->response->format === Response::FORMAT_HTML) {
            $this->setupLayout();
        }
    }


    protected function after($content = null): void
    {
        if ($this->autoRender && $this->response->format === Response::FORMAT_HTML) {

            if (!$this->layout) {
                $this->setupLayout();
            }

            if(!isset($this->og['title']) && $this->title) {
                $this->og['title'] = $this->title;
            }

            if(!isset($this->og['description']) && $this->description) {
                $this->og['description'] = $this->description;
            }

            $this->layout->set('content', $content);

            $this->response->content($this->renderIndex());
        } else {
            if (\is_array($content)) {
                $this->response->format = Response::FORMAT_JSON;
            }
            $this->response->content($content);
        }
    }

    protected function renderIndex(string $blockName = 'index'): string
    {
        return renderBlock($blockName, [
            'title' => $this->title,
            'description' => $this->description,
            'og' => $this->og,
            'links' => $this->links,
            'layout' => $this->layout->render(true)
        ]);
    }

    protected function setupLayout(string $block_name = 'layout', array $depends = []): void
    {
        $this->layout = \block($block_name)
            ->depends($depends);
    }
}
