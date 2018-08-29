<?php

namespace mii\email;

use mii\core\Component;
use mii\web\Block;

abstract class Mailer extends Component
{

    protected $to = [];
    protected $from;
    protected $reply_to;
    protected $subject;
    protected $attachments = [];

    protected $is_html = true;

    protected $assets_path = '';

    protected $from_mail;
    protected $from_name = '';

    public function init(array $config = []): void
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        // Legacy:
        if ($this->from_mail) {
            $this->from($this->from_mail, $this->from_name);
        }

    }

    public function attachment(string $attachment) {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function to(string $to, $name = '', $clear = false) {
        if($clear)
            $this->to = [];

        $this->to[] = [$to, $name];

        return $this;
    }

    public function from(string $from, $name = '') {
        $this->from = [$from, $name];

        return $this;
    }

    public function reply_to(string $to, $name = '') {

        $this->reply_to = [$to, $name];

        return $this;
    }

    public function subject(string $subject) {
        $this->subject = $subject;

        return $this;
    }

    public function html_mode($is_html = true) {
        $this->is_html = $is_html;
        return $this;
    }

    public function body($body) {
        if ($body instanceof Block) {
            $this->body = $body->render(true);
            $this->assets_path = \Mii::$app->blocks->assets_path_by_name($body->name());
            $this->is_html = true;
        } else {
            $this->body = $body;
        }
        return $this;
    }


    public function reset() {
        $this->from = '';
        $this->to = [];
        $this->reply_to = '';
        $this->subject = '';
        $this->body = '';
        $this->attachments = [];
        $this->assets_path = '';

    }

    public function send($to = null, $name = null, $subject = null, $body = null) {
        if($to !== null)
            $this->to($to, $name);

        if($subject != null)
            $this->subject($subject);

        if($body !== null)
            $this->body($body);
    }

}